<?php
/**
 * ADX Finance - Admin API для управления заявками на депозит
 * Требует заголовок X-Admin-API-Key для авторизации
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiKey = $_SERVER['HTTP_X_ADMIN_API_KEY'] ?? '';
$expectedKey = getenv('ADMIN_API_KEY') ?: (defined('ADMIN_API_KEY') ? ADMIN_API_KEY : '');

if (empty($expectedKey) || $apiKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    $db = getDB();
    
    switch ($action) {
        case 'list':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $status = $_GET['status'] ?? 'PENDING';
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            
            $stmt = $db->prepare('
                SELECT dr.*, u.email, u.first_name, u.last_name
                FROM deposit_requests dr
                JOIN users u ON u.id = dr.user_id
                WHERE dr.status = ?
                ORDER BY dr.created_at ASC
                LIMIT ?
            ');
            $stmt->execute([$status, $limit]);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'deposit_requests' => $requests
            ], JSON_NUMERIC_CHECK);
            break;
            
        case 'approve':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $requestId = (int)($data['id'] ?? $data['request_id'] ?? 0);
            
            if (!$requestId) {
                throw new Exception('Request ID required', 400);
            }
            
            $stmt = $db->prepare('SELECT * FROM deposit_requests WHERE id = ? AND status = ?');
            $stmt->execute([$requestId, 'PENDING']);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                throw new Exception('Deposit request not found or already processed', 404);
            }
            
            $db->beginTransaction();
            try {
                $userId = (int)$request['user_id'];
                $amount = (float)$request['amount'];
                
                // Обновляем balance_available
                $stmt = $db->prepare('UPDATE users SET balance_available = balance_available + ? WHERE id = ?');
                $stmt->execute([$amount, $userId]);
                
                // Создаём Transaction
                $stmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, status, description, completed_at)
                    VALUES (?, "DEPOSIT", "USD", ?, "completed", ?, NOW())
                ');
                $stmt->execute([
                    $userId,
                    $amount,
                    "Deposit approved: $amount USD"
                ]);
                
                // Обновляем deposit_request
                $stmt = $db->prepare('UPDATE deposit_requests SET status = ?, processed_at = NOW() WHERE id = ?');
                $stmt->execute(['APPROVED', $requestId]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Deposit approved',
                    'request_id' => $requestId
                ], JSON_NUMERIC_CHECK);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'reject':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $requestId = (int)($data['id'] ?? $data['request_id'] ?? 0);
            $notes = trim($data['notes'] ?? '');
            
            if (!$requestId) {
                throw new Exception('Request ID required', 400);
            }
            
            $stmt = $db->prepare('UPDATE deposit_requests SET status = ?, processed_at = NOW(), notes = ? WHERE id = ? AND status = ?');
            $stmt->execute(['REJECTED', $notes, $requestId, 'PENDING']);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Deposit request not found or already processed', 404);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Deposit rejected',
                'request_id' => $requestId
            ], JSON_NUMERIC_CHECK);
            break;
            
        default:
            throw new Exception('Unknown action', 400);
    }
    
} catch (Exception $e) {
    $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? (int)$e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
