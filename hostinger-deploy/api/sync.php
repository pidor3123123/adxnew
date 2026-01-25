<?php
/**
 * ADX Finance - API синхронизации с Supabase
 * Синхронизация данных между MySQL и Supabase для работы с админ панелью
 */

// Включаем буферизацию вывода для предотвращения попадания предупреждений в JSON
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'user':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? null;
            
            if (!$userId) {
                throw new Exception('user_id is required', 400);
            }
            
            syncUserToSupabase($userId);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'User synced successfully'
            ]);
            exit;
            break;
            
        case 'balance':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? null;
            $currency = $data['currency'] ?? null;
            
            if (!$userId) {
                throw new Exception('user_id is required', 400);
            }
            
            if ($currency) {
                syncBalanceToSupabase($userId, $currency);
            } else {
                syncAllBalancesToSupabase($userId);
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Balance synced successfully'
            ]);
            exit;
            break;
            
        case 'order':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = $data['order_id'] ?? null;
            
            if (!$orderId) {
                throw new Exception('order_id is required', 400);
            }
            
            syncOrderToSupabase($orderId);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Order synced successfully'
            ]);
            exit;
            break;
            
        case 'from_admin':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            // Валидация webhook секрета (добавьте в конфиг)
            $webhookSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
            $expectedSecret = getenv('WEBHOOK_SECRET') ?: 'your-webhook-secret';
            
            if ($webhookSecret !== $expectedSecret) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $type = $data['type'] ?? '';
            $payload = $data['payload'] ?? [];
            
            handleAdminWebhook($type, $payload);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);
            exit;
            break;
            
        default:
            throw new Exception('Unknown action', 400);
    }
    
} catch (Exception $e) {
    $code = $e->getCode();
    if (!is_numeric($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    $code = (int)$code;
    http_response_code($code);
    
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

/**
 * Синхронизация пользователя из MySQL в Supabase
 */
function syncUserToSupabase(int $mysqlUserId): void {
    try {
        $db = getDB();
        $supabase = getSupabaseClient();
        
        // Получаем пользователя из MySQL
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$mysqlUserId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception("User with ID $mysqlUserId not found", 404);
        }
        
        // Конвертируем MySQL ID в UUID
        $supabaseId = SupabaseClient::mysqlIdToUuid($mysqlUserId);
        
        // Проверяем, существует ли пользователь в Supabase
        $existingUser = $supabase->get('users', 'id', $supabaseId);
        
        // Подготавливаем данные для Supabase
        $userData = [
            'id' => $supabaseId,
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'country' => $user['country'] ?? 'US',
            'is_verified' => (bool)$user['is_verified'],
            'kyc_status' => 'pending',
            'kyc_verified' => false,
            'created_at' => $user['created_at'] ?? date('Y-m-d\TH:i:s.u\Z')
        ];
        
        // Upsert пользователя
        $supabase->upsert('users', $userData, 'id');
        
        // Синхронизируем user_security
        $securityData = [
            'user_id' => $supabaseId,
            'two_fa_enabled' => (bool)($user['two_factor_enabled'] ?? false),
            'failed_login_attempts' => 0,
            'account_locked_until' => $user['is_active'] ? null : date('Y-m-d\TH:i:s.u\Z', strtotime('+1 year'))
        ];
        
        $supabase->upsert('user_security', $securityData, 'user_id');
        
        // Сохраняем маппинг MySQL ID -> Supabase UUID в отдельной таблице (опционально)
        // Это можно использовать для обратного поиска
    } catch (Exception $e) {
        error_log("Supabase user sync error for user ID $mysqlUserId: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Синхронизация баланса пользователя
 */
function syncBalanceToSupabase(int $mysqlUserId, string $currency): void {
    try {
        $db = getDB();
        $supabase = getSupabaseClient();
        
        // Получаем баланс из MySQL
        $stmt = $db->prepare('SELECT * FROM balances WHERE user_id = ? AND currency = ?');
        $stmt->execute([$mysqlUserId, $currency]);
        $balance = $stmt->fetch();
        
        if (!$balance) {
            // Если баланса нет, создаем нулевой
            $stmt = $db->prepare('INSERT INTO balances (user_id, currency, available, reserved) VALUES (?, ?, 0, 0)');
            $stmt->execute([$mysqlUserId, $currency]);
            $balance = [
                'user_id' => $mysqlUserId,
                'currency' => $currency,
                'available' => 0,
                'reserved' => 0
            ];
        }
        
        $supabaseId = SupabaseClient::mysqlIdToUuid($mysqlUserId);
        
        // Проверяем существование баланса в Supabase
        $existingBalance = $supabase->get('user_balances', 'user_id', $supabaseId, 'id,currency');
        
        $balanceData = [
            'user_id' => $supabaseId,
            'currency' => $currency,
            'available_balance' => (float)$balance['available'],
            'locked_balance' => (float)$balance['reserved'],
            'updated_at' => date('Y-m-d\TH:i:s.u\Z')
        ];
        
        if ($existingBalance) {
            // Обновляем существующий баланс
            $supabase->update('user_balances', 'id', $existingBalance['id'], $balanceData);
        } else {
            // Создаем новый баланс
            $supabase->insert('user_balances', $balanceData);
        }
    } catch (Exception $e) {
        error_log("Supabase balance sync error for user ID $mysqlUserId, currency $currency: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Синхронизация всех балансов пользователя
 */
function syncAllBalancesToSupabase(int $mysqlUserId): void {
    $db = getDB();
    
    $stmt = $db->prepare('SELECT DISTINCT currency FROM balances WHERE user_id = ?');
    $stmt->execute([$mysqlUserId]);
    $currencies = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($currencies as $currency) {
        syncBalanceToSupabase($mysqlUserId, $currency);
    }
}

/**
 * Синхронизация ордера (опционально)
 */
function syncOrderToSupabase(int $orderId): void {
    // Если нужна синхронизация ордеров, реализуйте здесь
    // Пока оставляем пустым, так как это опционально
}

/**
 * Обработка webhook от админ панели
 */
function handleAdminWebhook(string $type, array $payload): void {
    $db = getDB();
    
    switch ($type) {
        case 'user_blocked':
            // Блокировка пользователя
            $supabaseId = $payload['user_id'] ?? null;
            $lockedUntil = $payload['locked_until'] ?? null;
            
            if (!$supabaseId) {
                throw new Exception('user_id is required', 400);
            }
            
            // Находим MySQL ID по email (так как обратная конвертация UUID сложна)
            // Лучше хранить маппинг, но для простоты используем email
            $email = $payload['email'] ?? null;
            if (!$email) {
                throw new Exception('email is required for user lookup', 400);
            }
            
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
            break;
            
        case 'balance_updated':
            // Обновление баланса из админки
            $supabaseId = $payload['user_id'] ?? null;
            $currency = $payload['currency'] ?? null;
            $available = $payload['available_balance'] ?? null;
            $locked = $payload['locked_balance'] ?? null;
            
            if (!$supabaseId || !$currency) {
                throw new Exception('user_id and currency are required', 400);
            }
            
            $email = $payload['email'] ?? null;
            if (!$email) {
                throw new Exception('email is required for user lookup', 400);
            }
            
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
            break;
            
        default:
            throw new Exception("Unknown webhook type: $type", 400);
    }
}
