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
        
        // Шаг 0: Проверяем существование пользователя по email в таблице users (приоритетная проверка)
        // Это гарантирует, что каждый email будет иметь только одну запись
        $existingUserByEmail = $supabase->get('users', 'email', $email);
        $authUserId = null;
        $needToCreateAuthUser = false;
        $needToUpdateMetadata = false;
        
        if ($existingUserByEmail) {
            // Пользователь с таким email уже существует - используем его UUID
            $authUserId = $existingUserByEmail['id'] ?? null;
            error_log("Found existing user by email $email with UUID: $authUserId");
            $needToUpdateMetadata = true;
        } else {
            // Пользователя с таким email нет - находим или создаем auth пользователя
            error_log("User with email $email not found in users table, checking auth.users...");
            
            // Шаг 1: Находим или создаем auth пользователя по email
            // ВАЖНО: Таблица users имеет внешний ключ на auth.users, поэтому нужно использовать UUID из auth.users
            $authUserId = $supabase->findAuthUserByEmail($email);
            
            if (!$authUserId) {
                // Auth пользователя нет - создаем нового
                $needToCreateAuthUser = true;
                error_log("Auth user not found for email $email, will create new one for MySQL user ID $mysqlUserId");
            } else {
                // Auth пользователь существует - проверяем, не используется ли этот UUID другим email в таблице users
                $existingUserById = $supabase->get('users', 'id', $authUserId);
                if ($existingUserById && ($existingUserById['email'] ?? '') !== $email) {
                    // КОНФЛИКТ: UUID уже используется другим email!
                    // Но мы уже проверили, что пользователя с таким email нет, поэтому создаем нового auth пользователя
                    error_log("WARNING: UUID $authUserId from findAuthUserByEmail is already used by email " . ($existingUserById['email'] ?? 'unknown') . ". Creating new auth user for $email");
                    $needToCreateAuthUser = true;
                    $authUserId = null; // Сброс, чтобы создать нового
                } else {
                    // UUID свободен или используется правильным email - продолжаем
                    error_log("Found existing auth user for email $email with UUID: $authUserId");
                    $needToUpdateMetadata = true; // Обновим метаданные при создании/обновлении записи в users
                }
            }
        }
        
        // Шаг 2: Создаем auth пользователя, если его нет
        if ($needToCreateAuthUser) {
            try {
                $tempPassword = bin2hex(random_bytes(16));
                $userMetadata = [
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                    'country' => $user['country'] ?? 'US',
                    'mysql_user_id' => $mysqlUserId // Сохраняем связь с MySQL
                ];
                
                $authUserId = $supabase->createAuthUser($email, $tempPassword, $userMetadata);
                error_log("Created auth user for MySQL user ID $mysqlUserId (email: $email) with UUID: $authUserId");
            } catch (Exception $e) {
                // Если пользователь уже существует - пытаемся найти его
                if (strpos($e->getMessage(), 'already') !== false || strpos($e->getMessage(), 'exists') !== false) {
                    $authUserId = $supabase->findAuthUserByEmail($email);
                    if (!$authUserId) {
                        throw new Exception("User exists but could not find UUID: " . $e->getMessage());
                    }
                    error_log("Found existing auth user after create error: $authUserId");
                    $needToUpdateMetadata = true;
                } else {
                    throw $e;
                }
            }
        }
        
        // Шаг 3: Создаем/обновляем запись в users с UUID из auth.users
        // ВАЖНО: Используем UUID из auth.users (не детерминированный), чтобы соблюсти foreign key constraint
        $existingUser = $supabase->get('users', 'id', $authUserId);
        
        // Если пользователь существует, проверяем, что email совпадает
        if ($existingUser) {
            $existingEmail = $existingUser['email'] ?? '';
            if ($existingEmail !== $email) {
                // КРИТИЧЕСКАЯ ОШИБКА: UUID используется другим пользователем!
                error_log("CRITICAL: UUID $authUserId is used by different user! Existing: $existingEmail, New: $email");
                throw new Exception("UUID conflict: UUID $authUserId is already used by user with email $existingEmail. Cannot sync user with email $email");
            }
            // Email совпадает - обновляем только изменяемые поля (не трогаем created_at и id)
            $updateData = [
                'email' => $email, // Обновляем email на случай, если он изменился
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'country' => $user['country'] ?? 'US',
                'is_verified' => (bool)$user['is_verified'],
                'kyc_status' => 'pending',
                'kyc_verified' => false
            ];
            try {
                $supabase->update('users', 'id', $authUserId, $updateData);
                error_log("Updated existing user record for MySQL user ID $mysqlUserId (UUID: $authUserId, email: $email)");
            } catch (Exception $e) {
                error_log("Error updating user in Supabase: " . $e->getMessage());
                throw $e;
            }
        } else {
            // Пользователь не существует - создаем новую запись с детерминированным UUID
            $userData = [
                'id' => $authUserId, // Используем детерминированный UUID на основе MySQL ID
                'email' => $email,
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'country' => $user['country'] ?? 'US',
                'is_verified' => (bool)$user['is_verified'],
                'kyc_status' => 'pending',
                'kyc_verified' => false,
                'created_at' => $user['created_at'] ?? date('Y-m-d\TH:i:s.u\Z')
            ];
            
            try {
                $supabase->insert('users', $userData);
                error_log("Created new user record for MySQL user ID $mysqlUserId (UUID: $authUserId, email: $email)");
            } catch (Exception $e) {
                error_log("Error creating user in Supabase: " . $e->getMessage());
                
                // Если это ошибка уникальности или foreign key constraint, проверяем, что произошло
                if (strpos($e->getMessage(), 'duplicate') !== false || 
                    strpos($e->getMessage(), 'unique') !== false ||
                    strpos($e->getMessage(), 'foreign key') !== false ||
                    strpos($e->getMessage(), 'users_id_fkey') !== false) {
                    
                    // Пользователь уже существует - проверяем email
                    $existingUser = $supabase->get('users', 'id', $authUserId);
                    if ($existingUser) {
                        $existingEmail = $existingUser['email'] ?? '';
                        if ($existingEmail === $email) {
                            // Это тот же пользователь - просто логируем и обновляем
                            error_log("User already exists with same email, updating instead for MySQL user ID $mysqlUserId");
                            $updateData = [
                                'first_name' => $user['first_name'] ?? '',
                                'last_name' => $user['last_name'] ?? '',
                                'country' => $user['country'] ?? 'US',
                                'is_verified' => (bool)$user['is_verified'],
                                'kyc_status' => 'pending',
                                'kyc_verified' => false
                            ];
                            $supabase->update('users', 'id', $authUserId, $updateData);
                        } else {
                            // UUID используется другим email - пытаемся создать нового auth пользователя
                            error_log("WARNING: UUID $authUserId is used by different email ($existingEmail). Attempting to create new auth user for $email");
                            
                            // Пытаемся создать нового auth пользователя для текущего email
                            try {
                                $tempPassword = bin2hex(random_bytes(16));
                                $userMetadata = [
                                    'first_name' => $user['first_name'] ?? '',
                                    'last_name' => $user['last_name'] ?? '',
                                    'country' => $user['country'] ?? 'US',
                                    'mysql_user_id' => $mysqlUserId
                                ];
                                
                                $newAuthUserId = $supabase->createAuthUser($email, $tempPassword, $userMetadata);
                                error_log("Created new auth user for MySQL user ID $mysqlUserId (email: $email) with UUID: $newAuthUserId");
                                
                                // Используем новый UUID для вставки
                                $userData['id'] = $newAuthUserId;
                                $supabase->insert('users', $userData);
                                error_log("Created new user record with new UUID for MySQL user ID $mysqlUserId (UUID: $newAuthUserId, email: $email)");
                                
                                // Обновляем authUserId для дальнейшего использования
                                $authUserId = $newAuthUserId;
                            } catch (Exception $createError) {
                                // Если не удалось создать нового auth пользователя, возможно он уже существует
                                error_log("Failed to create new auth user: " . $createError->getMessage());
                                
                                // Пытаемся найти существующего auth пользователя по email
                                $foundAuthUserId = $supabase->findAuthUserByEmail($email);
                                if ($foundAuthUserId && $foundAuthUserId !== $authUserId) {
                                    // Найден другой auth пользователь - используем его
                                    error_log("Found different auth user for $email with UUID: $foundAuthUserId");
                                    $userData['id'] = $foundAuthUserId;
                                    
                                    // Проверяем, не используется ли этот UUID другим email
                                    $checkUser = $supabase->get('users', 'id', $foundAuthUserId);
                                    if ($checkUser && ($checkUser['email'] ?? '') !== $email) {
                                        // Этот UUID тоже используется другим email - обновляем запись
                                        error_log("UUID $foundAuthUserId is used by different email, updating record...");
                                        $updateData = [
                                            'email' => $email,
                                            'first_name' => $user['first_name'] ?? '',
                                            'last_name' => $user['last_name'] ?? '',
                                            'country' => $user['country'] ?? 'US',
                                            'is_verified' => (bool)$user['is_verified'],
                                            'kyc_status' => 'pending',
                                            'kyc_verified' => false
                                        ];
                                        $supabase->update('users', 'id', $foundAuthUserId, $updateData);
                                        $authUserId = $foundAuthUserId;
                                    } else {
                                        // UUID свободен - вставляем
                                        try {
                                            $supabase->insert('users', $userData);
                                            error_log("Created new user record with found UUID for MySQL user ID $mysqlUserId (UUID: $foundAuthUserId, email: $email)");
                                            $authUserId = $foundAuthUserId;
                                        } catch (Exception $insertError) {
                                            // Если все еще ошибка, обновляем существующую запись
                                            error_log("Still cannot insert, updating existing record: " . $insertError->getMessage());
                                            $updateData = [
                                                'email' => $email,
                                                'first_name' => $user['first_name'] ?? '',
                                                'last_name' => $user['last_name'] ?? '',
                                                'country' => $user['country'] ?? 'US',
                                                'is_verified' => (bool)$user['is_verified'],
                                                'kyc_status' => 'pending',
                                                'kyc_verified' => false
                                            ];
                                            $supabase->update('users', 'id', $foundAuthUserId, $updateData);
                                            $authUserId = $foundAuthUserId;
                                        }
                                    }
                                } else {
                                    // Не удалось найти или создать auth пользователя - обновляем существующую запись с правильным email
                                    error_log("Cannot create or find auth user, updating existing record with correct email");
                                    $updateData = [
                                        'email' => $email,
                                        'first_name' => $user['first_name'] ?? '',
                                        'last_name' => $user['last_name'] ?? '',
                                        'country' => $user['country'] ?? 'US',
                                        'is_verified' => (bool)$user['is_verified'],
                                        'kyc_status' => 'pending',
                                        'kyc_verified' => false
                                    ];
                                    $supabase->update('users', 'id', $authUserId, $updateData);
                                }
                            }
                        }
                    } else {
                        // Запись не найдена, но ошибка уникальности - возможно проблема с foreign key
                        error_log("User record not found but duplicate error. This might be a foreign key constraint issue.");
                        throw $e;
                    }
                } else {
                    throw $e;
                }
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
        
        // Шаг 5: Создаем начальный кошелек в Supabase (если еще не существует)
        // Это гарантирует, что кошелек существует даже если пользователь еще не делал транзакций
        try {
            // Проверяем, существует ли уже кошелек
            $existingWallet = $supabase->get('wallets', 'user_id', $authUserId);
            
            if (!$existingWallet) {
                // Кошелек не существует - создаем его через apply_transaction с нулевым балансом
                // Это создаст запись в wallets через триггер
                $idempotencyKey = 'initial_wallet_' . $authUserId . '_USD_' . time();
                $result = $supabase->applyTransaction($authUserId, 0, 'admin_topup', 'USD', $idempotencyKey, [
                    'description' => 'Initial wallet creation',
                    'mysql_user_id' => $mysqlUserId
                ]);
                
                if (isset($result['success']) && $result['success']) {
                    error_log("Created initial wallet for MySQL user ID $mysqlUserId (UUID: $authUserId) via apply_transaction");
                } else {
                    error_log("Failed to create initial wallet for MySQL user ID $mysqlUserId (UUID: $authUserId). Result: " . json_encode($result));
                }
            } else {
                error_log("Wallet already exists for MySQL user ID $mysqlUserId (UUID: $authUserId)");
            }
        } catch (Exception $walletError) {
            // Не критично, если не удалось создать кошелек - он создастся при первой транзакции
            error_log("Warning: Could not create initial wallet for MySQL user ID $mysqlUserId (UUID: $authUserId): " . $walletError->getMessage());
        }
        
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
        
        // ВАЖНО: Получаем UUID из auth.users по email, а не используем детерминированный
        // Это нужно для соблюдения foreign key constraint
        $db = getDB();
        $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$mysqlUserId]);
        $mysqlUser = $stmt->fetch();
        
        if (!$mysqlUser) {
            throw new Exception("MySQL user with ID $mysqlUserId not found");
        }
        
        $email = $mysqlUser['email'];
        $supabaseId = $supabase->findAuthUserByEmail($email);
        
        if (!$supabaseId) {
            // Пользователь не существует в Supabase - синхронизируем его сначала
            error_log("Auth user not found for email $email, syncing user first...");
            syncUserToSupabase($mysqlUserId);
            // После синхронизации пользователя, повторно получаем UUID
            $supabaseId = $supabase->findAuthUserByEmail($email);
            if (!$supabaseId) {
                throw new Exception("Failed to get auth user UUID after sync for MySQL user ID $mysqlUserId");
            }
        }
        
        // ВАЖНО: Проверяем существование пользователя в Supabase перед созданием баланса
        $existingUser = $supabase->get('users', 'id', $supabaseId);
        if (!$existingUser) {
            // Пользователь не существует в таблице users - синхронизируем его
            error_log("User $supabaseId not found in users table, syncing user first...");
            syncUserToSupabase($mysqlUserId);
            // После синхронизации пользователя, повторно получаем UUID
            $supabaseId = $supabase->findAuthUserByEmail($email);
            if (!$supabaseId) {
                throw new Exception("Failed to get auth user UUID after sync for MySQL user ID $mysqlUserId");
            }
        }
        
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
            // Создаем новый баланс (теперь пользователь точно существует)
            $supabase->insert('user_balances', $balanceData);
        }
    } catch (Exception $e) {
        error_log("Supabase balance sync error for user ID $mysqlUserId, currency $currency: " . $e->getMessage());
        // Не пробрасываем исключение дальше, чтобы не блокировать основную логику
        // throw $e;
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
function syncBalanceFromSupabase(string $supabaseUserId, string $currency, float $availableBalance, float $lockedBalance, ?string $email = null): void {
    try {
        $db = getDB();
        $supabase = getSupabaseClient();
        
        // Используем переданный email или получаем из Supabase
        if (!$email) {
            $user = $supabase->get('users', 'id', $supabaseUserId, 'email');
            if (!$user || !isset($user['email'])) {
                throw new Exception("User not found in Supabase: $supabaseUserId");
            }
            $email = $user['email'];
        }
        
        error_log("syncBalanceFromSupabase: supabaseUserId=$supabaseUserId, email=$email, currency=$currency, available=$availableBalance, locked=$lockedBalance");
        
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
