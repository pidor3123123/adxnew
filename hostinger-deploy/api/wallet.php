<?php
/**
 * ADX Finance - API кошелька
 */

// Debug: Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure JSON response even on fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
        ]);
    }
});

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/auth.php';

// Получение рыночных цен для конвертации в USD
function getUsdPrices(): array {
    return [
        'USD' => 1.0,
        'BTC' => 43250.00,
        'ETH' => 2285.50,
        'BNB' => 312.40,
        'XRP' => 0.62,
        'SOL' => 98.75,
        'ADA' => 0.58,
        'DOGE' => 0.082,
        'DOT' => 7.85,
        'MATIC' => 0.92,
        'LTC' => 72.30,
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $user = getAuthUser();
    
    switch ($action) {
        case 'balances':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $db = getDB();
            // Получаем balance_available и balance_locked из users
            $stmt = $db->prepare('SELECT balance_available, balance_locked FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userRow = $stmt->fetch();
            $balanceAvailable = isset($userRow['balance_available']) ? (float)$userRow['balance_available'] : 0;
            $balanceLocked = isset($userRow['balance_locked']) ? (float)$userRow['balance_locked'] : 0;
            
            // Для совместимости - балансы по валютам (USD основной для торговли)
            $balances = [
                [
                    'currency' => 'USD',
                    'available' => $balanceAvailable,
                    'reserved' => $balanceLocked,
                    'usd_value' => $balanceAvailable
                ]
            ];
            $totalUsd = $balanceAvailable + $balanceLocked;
            
            echo json_encode([
                'success' => true,
                'balance_available' => $balanceAvailable,
                'balance_locked' => $balanceLocked,
                'balances' => $balances,
                'total_usd' => $totalUsd
            ]);
            break;
            
        case 'deposit':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $amount = (float)($data['amount'] ?? 0);
            $methodName = trim($data['method'] ?? 'Bank Transfer');
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount', 400);
            }
            
            $db = getDB();
            // Создаём заявку на депозит - баланс НЕ изменяем
            $stmt = $db->prepare('
                INSERT INTO deposit_requests (user_id, amount, method, status)
                VALUES (?, ?, ?, "PENDING")
            ');
            $stmt->execute([$user['id'], $amount, $methodName]);
            $requestId = (int) $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Deposit request created. Awaiting admin approval.',
                'request_id' => $requestId,
                'status' => 'PENDING'
            ]);
            break;
            
        case 'deposit_requests':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $db = getDB();
            $stmt = $db->prepare('
                SELECT id, amount, method, status, created_at, processed_at
                FROM deposit_requests
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 50
            ');
            $stmt->execute([$user['id']]);
            $requests = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'deposit_requests' => $requests
            ]);
            break;
            
        case 'withdraw':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $amount = (float)($data['amount'] ?? 0);
            $address = trim($data['address'] ?? '');
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount', 400);
            }
            
            $db = getDB();
            
            // Проверяем balance_available
            $stmt = $db->prepare('SELECT balance_available FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userRow = $stmt->fetch();
            $balanceAvailable = isset($userRow['balance_available']) ? (float)$userRow['balance_available'] : 0;
            
            if ($balanceAvailable < $amount) {
                throw new Exception('Insufficient balance', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Списываем balance_available
                $stmt = $db->prepare('UPDATE users SET balance_available = balance_available - ? WHERE id = ?');
                $stmt->execute([$amount, $user['id']]);
                
                // Создаём транзакцию
                $stmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, status, description)
                    VALUES (?, "withdrawal", "USD", ?, "pending", ?)
                ');
                $stmt->execute([
                    $user['id'],
                    -$amount,
                    "Вывод {$amount} USD" . ($address ? " на {$address}" : '')
                ]);
                
                $db->commit();
                
                // Синхронизация баланса с Supabase (в фоне)
                try {
                    $syncData = [
                        'user_id' => $user['id'],
                        'currency' => 'USD'
                    ];
                    
                    $ch = curl_init('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/api/sync.php?action=balance');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($syncData),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => false,
                        CURLOPT_TIMEOUT => 1,
                        CURLOPT_NOSIGNAL => 1,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                } catch (Exception $e) {
                    error_log('Supabase balance sync error: ' . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Withdrawal request submitted'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'transactions':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $type = $_GET['type'] ?? null;
            
            $db = getDB();
            
            $sql = 'SELECT * FROM transactions WHERE user_id = ?';
            $params = [$user['id']];
            
            if ($type && in_array($type, ['deposit', 'withdrawal', 'trade', 'DEPOSIT', 'TRADE_OPEN', 'TRADE_CLOSE', 'fee'])) {
                $sql .= ' AND type = ?';
                $params[] = $type;
            }
            
            $sql .= ' ORDER BY created_at DESC LIMIT ?';
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
            break;
            
        default:
            throw new Exception('Unknown action', 400);
    }
    
} catch (Exception $e) {
    $code = $e->getCode();
    // Валидация и приведение к int
    if (!is_numeric($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    $code = (int)$code;
    http_response_code($code);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
