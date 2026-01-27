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

/**
 * Основная логика API
 * Выполняется только при прямом запросе к sync.php, а не при require_once в других файлах
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'sync.php') {
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
        
        $email = $user['email'];
        
        // Шаг 1: Проверяем, существует ли пользователь в auth.users
        $authUserId = $supabase->findAuthUserByEmail($email);
        
        // Шаг 2: Если пользователя нет в auth.users - создаем его
        if (!$authUserId) {
            // Генерируем случайный пароль (пользователь не сможет войти через Supabase Auth,
            // но это нормально, так как он использует основной сайт для входа)
            $tempPassword = bin2hex(random_bytes(16));
            
            // Подготавливаем метаданные для auth.users
            $userMetadata = [
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'country' => $user['country'] ?? 'US',
                'mysql_user_id' => $mysqlUserId // Сохраняем связь с MySQL
            ];
            
            try {
                $authUserId = $supabase->createAuthUser($email, $tempPassword, $userMetadata);
                error_log("Created auth user for MySQL user ID $mysqlUserId (email: $email) with UUID: $authUserId");
            } catch (Exception $e) {
                // Если ошибка "уже существует", пытаемся найти еще раз
                if (strpos($e->getMessage(), 'already') !== false) {
                    $authUserId = $supabase->findAuthUserByEmail($email);
                    if (!$authUserId) {
                        throw new Exception("User exists but could not find UUID: " . $e->getMessage());
                    }
                } else {
                    throw $e;
                }
            }
        } else {
            error_log("Found existing auth user for MySQL user ID $mysqlUserId (email: $email) with UUID: $authUserId");
        }
        
        // Шаг 3: Создаем/обновляем запись в users с UUID из auth.users
        $userData = [
            'id' => $authUserId, // Используем UUID из auth.users
            'email' => $email,
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'country' => $user['country'] ?? 'US',
            'is_verified' => (bool)$user['is_verified'],
            'kyc_status' => 'pending',
            'kyc_verified' => false,
            'created_at' => $user['created_at'] ?? date('Y-m-d\TH:i:s.u\Z')
        ];
        
        // Upsert пользователя (триггер может уже создать запись, но обновим данные)
        try {
            $supabase->upsert('users', $userData, 'id');
            error_log("Upserted user record for MySQL user ID $mysqlUserId (UUID: $authUserId)");
        } catch (Exception $e) {
            // Если ошибка внешнего ключа, возможно триггер еще не сработал
            // Попробуем просто обновить существующую запись
            $existingUser = $supabase->get('users', 'id', $authUserId);
            if ($existingUser) {
                $supabase->update('users', 'id', $authUserId, $userData);
                error_log("Updated user record for MySQL user ID $mysqlUserId (UUID: $authUserId)");
            } else {
                throw $e;
            }
        }
        
        // Шаг 4: Синхронизируем user_security
        $securityData = [
            'user_id' => $authUserId,
            'two_fa_enabled' => (bool)($user['two_factor_enabled'] ?? false),
            'failed_login_attempts' => 0,
            'account_locked_until' => $user['is_active'] ? null : date('Y-m-d\TH:i:s.u\Z', strtotime('+1 year'))
        ];
        
        $supabase->upsert('user_security', $securityData, 'user_id');
        error_log("Synced user_security for MySQL user ID $mysqlUserId (UUID: $authUserId)");
        
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
 * Синхронизация баланса из Supabase в MySQL
 * Используется когда баланс обновляется через админ панель
 */
function syncBalanceFromSupabase(string $supabaseUserId, string $currency, float $availableBalance, float $lockedBalance): void {
    try {
        $db = getDB();
        $supabase = getSupabaseClient();
        
        // Получаем email пользователя из Supabase
        $user = $supabase->get('users', 'id', $supabaseUserId, 'email');
        if (!$user || !isset($user['email'])) {
            throw new Exception("User not found in Supabase: $supabaseUserId");
        }
        
        $email = $user['email'];
        
        // Находим MySQL user_id по email
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $mysqlUser = $stmt->fetch();
        
        if (!$mysqlUser) {
            // Пользователь не найден в MySQL - возможно, он был создан только в админ панели
            // Попробуем синхронизировать пользователя сначала
            try {
                syncUserFromSupabase($supabaseUserId);
                // Повторно ищем пользователя
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $mysqlUser = $stmt->fetch();
                
                if (!$mysqlUser) {
                    throw new Exception("Failed to sync user from Supabase: $email");
                }
            } catch (Exception $e) {
                error_log("Failed to sync user before balance sync: " . $e->getMessage());
                throw new Exception("User not found in MySQL and sync failed: $email");
            }
        }
        
        $mysqlUserId = $mysqlUser['id'];
        
        // Получаем текущий баланс для логирования
        $stmt = $db->prepare('SELECT available, reserved FROM balances WHERE user_id = ? AND currency = ?');
        $stmt->execute([$mysqlUserId, $currency]);
        $oldBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldAvailable = $oldBalance ? (float)$oldBalance['available'] : 0;
        $oldReserved = $oldBalance ? (float)$oldBalance['reserved'] : 0;
        
        error_log("Syncing balance from Supabase to MySQL: user_id=$mysqlUserId (email=$email), currency=$currency");
        error_log("  Old balance: available=$oldAvailable, reserved=$oldReserved");
        error_log("  New balance: available=$availableBalance, reserved=$lockedBalance");
        
        // Обновляем или создаем баланс в MySQL
        $stmt = $db->prepare('
            INSERT INTO balances (user_id, currency, available, reserved)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                available = VALUES(available),
                reserved = VALUES(reserved),
                updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([
            $mysqlUserId,
            $currency,
            $availableBalance,
            $lockedBalance
        ]);
        
        $affectedRows = $stmt->rowCount();
        error_log("  SQL executed: affected_rows=$affectedRows");
        
        // Проверяем, что баланс реально обновился
        $stmt = $db->prepare('SELECT available, reserved FROM balances WHERE user_id = ? AND currency = ?');
        $stmt->execute([$mysqlUserId, $currency]);
        $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($newBalance) {
            $newAvailable = (float)$newBalance['available'];
            $newReserved = (float)$newBalance['reserved'];
            
            // Проверяем, что значения совпадают с ожидаемыми
            $availableMatch = abs($newAvailable - $availableBalance) < 0.01; // Допускаем небольшую погрешность для float
            $reservedMatch = abs($newReserved - $lockedBalance) < 0.01;
            
            if ($availableMatch && $reservedMatch) {
                error_log("  ✓ Balance successfully updated in MySQL: available=$newAvailable, reserved=$newReserved");
            } else {
                error_log("  ✗ WARNING: Balance mismatch! Expected: available=$availableBalance, reserved=$lockedBalance");
                error_log("    Actual: available=$newAvailable, reserved=$newReserved");
                throw new Exception("Balance update verification failed: values don't match");
            }
        } else {
            error_log("  ✗ ERROR: Balance not found in MySQL after update!");
            throw new Exception("Balance not found after update");
        }
        
        error_log("Synced balance from Supabase to MySQL: user_id=$mysqlUserId, currency=$currency, available=$availableBalance, locked=$lockedBalance");
        
    } catch (Exception $e) {
        error_log("Supabase balance sync error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Синхронизация пользователя из Supabase в MySQL
 * Используется когда пользователь создается или обновляется через админ панель
 */
function syncUserFromSupabase(string $supabaseUserId): void {
    try {
        $db = getDB();
        $supabase = getSupabaseClient();
        
        // Получаем данные пользователя из Supabase
        $supabaseUser = $supabase->get('users', 'id', $supabaseUserId);
        if (!$supabaseUser) {
            throw new Exception("User not found in Supabase: $supabaseUserId");
        }
        
        $email = $supabaseUser['email'] ?? null;
        if (!$email) {
            throw new Exception("User email not found in Supabase: $supabaseUserId");
        }
        
        // Проверяем, существует ли пользователь в MySQL
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $mysqlUser = $stmt->fetch();
        
        if ($mysqlUser) {
            // Пользователь существует - обновляем данные
            $mysqlUserId = $mysqlUser['id'];
            
            $stmt = $db->prepare('
                UPDATE users SET
                    first_name = ?,
                    last_name = ?,
                    country = ?,
                    is_verified = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ');
            $stmt->execute([
                $supabaseUser['first_name'] ?? '',
                $supabaseUser['last_name'] ?? '',
                $supabaseUser['country'] ?? 'US',
                $supabaseUser['is_verified'] ? 1 : 0,
                $mysqlUserId
            ]);
            
            error_log("Updated user from Supabase to MySQL: user_id=$mysqlUserId, email=$email");
        } else {
            // Пользователь не существует - создаем нового
            // Генерируем случайный пароль (пользователь не сможет войти через основной сайт,
            // но это нормально, так как он был создан в админ панели)
            $tempPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            
            $stmt = $db->prepare('
                INSERT INTO users (email, password, first_name, last_name, country, is_verified, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ');
            $stmt->execute([
                $email,
                $tempPassword,
                $supabaseUser['first_name'] ?? '',
                $supabaseUser['last_name'] ?? '',
                $supabaseUser['country'] ?? 'US',
                $supabaseUser['is_verified'] ? 1 : 0
            ]);
            
            $mysqlUserId = $db->lastInsertId();
            
            // Создаем начальные балансы для нового пользователя
            try {
                require_once __DIR__ . '/auth.php';
                if (function_exists('createInitialBalances')) {
                    createInitialBalances($mysqlUserId);
                }
            } catch (Exception $e) {
                error_log("Failed to create initial balances for synced user: " . $e->getMessage());
            }
            
            error_log("Created user from Supabase in MySQL: user_id=$mysqlUserId, email=$email");
        }
        
    } catch (Exception $e) {
        error_log("Supabase user sync error: " . $e->getMessage());
        throw $e;
    }
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
