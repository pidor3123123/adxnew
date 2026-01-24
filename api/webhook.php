<?php
/**
 * ADX Finance - Webhook endpoint для приема изменений из админ панели
 * 
 * Этот endpoint принимает изменения из админ панели и обновляет данные в MySQL
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// Разрешаем только POST запросы
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Валидация webhook секрета
$webhookSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
$expectedSecret = getenv('WEBHOOK_SECRET');

if (empty($expectedSecret)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Webhook secret not configured'
    ]);
    exit;
}

if (empty($webhookSecret) || $webhookSecret !== $expectedSecret) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Invalid webhook secret'
    ]);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data', 400);
    }
    
    $type = $data['type'] ?? '';
    $payload = $data['payload'] ?? [];
    
    if (empty($type)) {
        throw new Exception('Type is required', 400);
    }
    
    $db = getDB();
    
    switch ($type) {
        case 'user_blocked':
        case 'user_unblocked':
            handleUserBlock($db, $payload);
            break;
            
        case 'balance_updated':
            handleBalanceUpdate($db, $payload);
            break;
            
        case 'user_updated':
            handleUserUpdate($db, $payload);
            break;
            
        default:
            throw new Exception("Unknown webhook type: $type", 400);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Webhook processed successfully'
    ]);
    
} catch (Exception $e) {
    $code = $e->getCode();
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

/**
 * Обработка блокировки/разблокировки пользователя
 */
function handleUserBlock(PDO $db, array $payload): void {
    $email = $payload['email'] ?? null;
    $lockedUntil = $payload['locked_until'] ?? null;
    
    if (!$email) {
        throw new Exception('email is required', 400);
    }
    
    // Находим пользователя по email
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Обновляем is_active в MySQL
    $isActive = $lockedUntil === null ? 1 : 0;
    $stmt = $db->prepare('UPDATE users SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive, $user['id']]);
}

/**
 * Обработка обновления баланса
 */
function handleBalanceUpdate(PDO $db, array $payload): void {
    $email = $payload['email'] ?? null;
    $currency = $payload['currency'] ?? null;
    $available = $payload['available_balance'] ?? null;
    $locked = $payload['locked_balance'] ?? null;
    
    if (!$email || !$currency) {
        throw new Exception('email and currency are required', 400);
    }
    
    // Находим пользователя по email
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Обновляем баланс в MySQL
    $stmt = $db->prepare('
        INSERT INTO balances (user_id, currency, available, reserved)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            available = VALUES(available),
            reserved = VALUES(reserved)
    ');
    $stmt->execute([
        $user['id'],
        $currency,
        $available ?? 0,
        $locked ?? 0
    ]);
}

/**
 * Обработка обновления данных пользователя
 */
function handleUserUpdate(PDO $db, array $payload): void {
    $email = $payload['email'] ?? null;
    $updates = $payload['updates'] ?? [];
    
    if (!$email) {
        throw new Exception('email is required', 400);
    }
    
    if (empty($updates)) {
        throw new Exception('updates are required', 400);
    }
    
    // Находим пользователя по email
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found', 404);
    }
    
    // Разрешенные поля для обновления
    $allowedFields = ['first_name', 'last_name', 'country', 'is_verified', 'phone'];
    $updateFields = [];
    $updateValues = [];
    
    foreach ($updates as $field => $value) {
        if (in_array($field, $allowedFields)) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $value;
        }
    }
    
    if (empty($updateFields)) {
        throw new Exception('No valid fields to update', 400);
    }
    
    $updateValues[] = $user['id'];
    
    $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($updateValues);
}
