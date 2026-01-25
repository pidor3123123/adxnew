<?php
/**
 * ADX Finance - API авторизации
 */

// Включаем буферизацию вывода для предотвращения попадания предупреждений в JSON
ob_start();

// Глобальная обработка ошибок для конвертации всех PHP ошибок в JSON
set_error_handler(function($severity, $message, $file, $line) {
    // Игнорируем ошибки, которые не являются критическими
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Очищаем буфер и устанавливаем заголовки
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $message . ' in ' . basename($file) . ':' . $line
    ]);
    exit;
});

// Обработка фатальных ошибок
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
        ]);
        exit;
    }
});

// Безопасная загрузка конфигурационных файлов
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка загрузки конфигурации: ' . $e->getMessage()
    ]);
    exit;
}

header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

// Безопасная загрузка дополнительных файлов
try {
    // Функции синхронизации (если доступны)
    if (file_exists(__DIR__ . '/sync.php')) {
        require_once __DIR__ . '/sync.php';
    }
    require_once __DIR__ . '/totp.php';
    require_once __DIR__ . '/../config/supabase.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка загрузки модулей: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Получение Authorization токена из разных источников
 * Apache может помещать заголовок в разные переменные
 */
function getAuthorizationToken(): string {
    $token = '';
    
    // Способ 1: Стандартный $_SERVER
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // Способ 2: После RewriteRule (REDIRECT_)
    elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // Способ 3: apache_request_headers()
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $token = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $token = $headers['authorization'];
        }
    }
    // Способ 4: getallheaders()
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $token = $headers['authorization'];
        }
    }
    
    // Удаляем префикс "Bearer "
    $token = str_replace('Bearer ', '', $token);
    
    return trim($token);
}

/**
 * Генерация токена сессии
 */
function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Хеширование пароля
 */
function hashPassword(string $password): string {
    // Используем BCRYPT для совместимости со всеми версиями PHP
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Проверка пароля
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Получение пользователя по токену
 */
function getUserByToken(string $token): ?array {
    $db = getDB();
    
    $stmt = $db->prepare('
        SELECT u.* FROM users u
        JOIN user_sessions s ON s.user_id = u.id
        WHERE s.token = ? AND s.expires_at > NOW() AND u.is_active = 1
    ');
    $stmt->execute([$token]);
    
    $user = $stmt->fetch();
    
    if ($user) {
        unset($user['password']);
    }
    
    return $user ?: null;
}

/**
 * Создание сессии
 */
function createSession(int $userId, bool $remember = false): string {
    try {
        $db = getDB();
        $token = generateToken();
        
        // Время жизни сессии: 30 дней если "запомнить", иначе 24 часа
        $expiresAt = date('Y-m-d H:i:s', strtotime($remember ? '+30 days' : '+24 hours'));
        
        $stmt = $db->prepare('
            INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $userId,
            $token,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);
        
        return $token;
    } catch (PDOException $e) {
        error_log('Error creating session: ' . $e->getMessage());
        throw new Exception('Ошибка создания сессии: ' . $e->getMessage(), 500, $e);
    } catch (Exception $e) {
        error_log('Error creating session: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Создание начального баланса для нового пользователя
 */
function createInitialBalances(int $userId): void {
    try {
        $db = getDB();
        
        $currencies = [
            ['USD', 0.00],
            ['BTC', 0],
            ['ETH', 0],
            ['BNB', 0],
            ['XRP', 0],
            ['SOL', 0]
        ];
        
        $stmt = $db->prepare('INSERT INTO balances (user_id, currency, available) VALUES (?, ?, ?)');
        
        foreach ($currencies as $currency) {
            try {
                $stmt->execute([$userId, $currency[0], $currency[1]]);
            } catch (PDOException $e) {
                // Логируем ошибку для конкретной валюты, но продолжаем
                error_log("Error creating balance for currency {$currency[0]}: " . $e->getMessage());
                // Если это не дубликат, пробрасываем дальше
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Error creating initial balances: ' . $e->getMessage());
        // Не пробрасываем исключение, чтобы не прервать регистрацию
        // Просто логируем ошибку
    } catch (Exception $e) {
        error_log('Error creating initial balances: ' . $e->getMessage());
        // Не пробрасываем исключение
    }
}

/**
 * Основная логика API
 * Выполняется только при прямом запросе к auth.php, а не при require_once в других файлах
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'auth.php') {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    try {
    switch ($action) {
        case 'register':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Invalid JSON data', 400);
            }
            
            $firstName = trim($data['firstName'] ?? '');
            $lastName = trim($data['lastName'] ?? '');
            $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $data['password'] ?? '';
            
            // Валидация
            if (!$firstName || !$lastName) {
                throw new Exception('Имя и фамилия обязательны', 400);
            }
            
            if (!$email) {
                throw new Exception('Некорректный email', 400);
            }
            
            if (strlen($password) < 8) {
                throw new Exception('Пароль должен быть минимум 8 символов', 400);
            }
            
            $db = getDB();
            
            // Проверка существования email
            try {
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    throw new Exception('Пользователь с таким email уже существует', 409);
                }
            } catch (PDOException $e) {
                error_log('Database error checking email: ' . $e->getMessage());
                throw new Exception('Ошибка проверки email. Проверьте настройку базы данных.', 500);
            }
            
            // Создание пользователя
            try {
                $stmt = $db->prepare('
                    INSERT INTO users (email, password, first_name, last_name)
                    VALUES (?, ?, ?, ?)
                ');
                
                $stmt->execute([
                    $email,
                    hashPassword($password),
                    $firstName,
                    $lastName
                ]);
                
                $userId = (int) $db->lastInsertId();
                
                if ($userId === 0) {
                    throw new Exception('Не удалось создать пользователя', 500);
                }
            } catch (PDOException $e) {
                error_log('Database error creating user: ' . $e->getMessage());
                throw new Exception('Ошибка создания пользователя. Проверьте настройку базы данных.', 500);
            }
            
            // Создание начальных балансов (функция уже обрабатывает ошибки внутри)
            createInitialBalances($userId);
            
            // Синхронизация с Supabase (в фоне, не блокируем ответ)
            // Используем отдельный процесс, чтобы не блокировать ответ
            try {
                $syncData = [
                    'user_id' => $userId
                ];
                
                // Используем curl для асинхронного запроса (не ждем ответа)
                $ch = curl_init('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/api/sync.php?action=user');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($syncData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true, // Возвращаем результат вместо вывода
                    CURLOPT_TIMEOUT => 1, // Таймаут 1 секунда, не ждем
                    CURLOPT_NOSIGNAL => 1,
                    CURLOPT_CONNECTTIMEOUT => 1, // Таймаут подключения 1 секунда
                ]);
                // Игнорируем результат, чтобы не блокировать ответ
                @curl_exec($ch);
                @curl_close($ch);
            } catch (Exception $e) {
                // Игнорируем ошибки синхронизации при регистрации
                error_log('Supabase sync error on registration: ' . $e->getMessage());
            } catch (Throwable $e) {
                // Перехватываем все ошибки, включая фатальные
                error_log('Supabase sync fatal error on registration: ' . $e->getMessage());
            }
            
            // Создание сессии (функция уже обрабатывает ошибки внутри)
            $token = createSession($userId, true);
            
            // Очищаем буфер перед выводом JSON
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'firstName' => $firstName,
                    'lastName' => $lastName
                ]
            ]);
            exit;
            
        case 'login':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Invalid JSON data', 400);
            }
            
            $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $data['password'] ?? '';
            $remember = (bool) ($data['remember'] ?? false);
            
            if (!$email || !$password) {
                throw new Exception('Email и пароль обязательны', 400);
            }
            
            $db = getDB();
            
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
            $stmt->execute([$email]);
            
            $user = $stmt->fetch();
            
            if (!$user || !verifyPassword($password, $user['password'])) {
                throw new Exception('Неверный email или пароль', 401);
            }
            
            // Проверяем, включена ли 2FA
            if ($user['two_factor_enabled']) {
                // Создаём временный токен для 2FA (действует 5 минут)
                $tfaToken = generateToken(32);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                
                $stmt = $db->prepare('
                    INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $user['id'],
                    'tfa_' . $tfaToken,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null,
                    $expiresAt
                ]);
                
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'requires_2fa' => true,
                    'tfa_token' => $tfaToken,
                    'remember' => $remember
                ]);
                exit;
            }
            
            // Создание сессии
            $token = createSession($user['id'], $remember);
            
            unset($user['password']);
            unset($user['two_factor_secret']);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => $user
            ]);
            exit;
            
        case 'logout':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $token = getAuthorizationToken();
            
            if ($token) {
                $db = getDB();
                $stmt = $db->prepare('DELETE FROM user_sessions WHERE token = ?');
                $stmt->execute([$token]);
            }
            
            ob_end_clean();
            echo json_encode(['success' => true]);
            exit;
            
        case 'me':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            
            $token = getAuthorizationToken();
            
            if (!$token) {
                throw new Exception('Token required', 401);
            }
            
            $user = getUserByToken($token);
            
            if (!$user) {
                throw new Exception('Invalid or expired token', 401);
            }
            
            // Получение балансов
            $db = getDB();
            $stmt = $db->prepare('SELECT currency, available, reserved FROM balances WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $balances = $stmt->fetchAll();
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'user' => $user,
                'balances' => $balances
            ]);
            exit;
            
        case 'check':
            $token = getAuthorizationToken();
            
            if (!$token) {
                ob_end_clean();
                echo json_encode(['authenticated' => false]);
                exit;
            }
            
            $user = getUserByToken($token);
            
            ob_end_clean();
            echo json_encode([
                'authenticated' => $user !== null,
                'user' => $user
            ]);
            exit;
            
        case 'login_2fa':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Invalid JSON data', 400);
            }
            
            $tfaToken = $data['tfa_token'] ?? '';
            $code = $data['code'] ?? '';
            $remember = (bool) ($data['remember'] ?? false);
            
            if (!$tfaToken || !$code) {
                throw new Exception('Токен и код обязательны', 400);
            }
            
            $db = getDB();
            
            // Находим временную сессию 2FA
            $stmt = $db->prepare('
                SELECT s.*, u.two_factor_secret, u.email
                FROM user_sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.token = ? AND s.expires_at > NOW()
            ');
            $stmt->execute(['tfa_' . $tfaToken]);
            $session = $stmt->fetch();
            
            if (!$session) {
                throw new Exception('Сессия истекла, войдите заново', 401);
            }
            
            // Проверяем TOTP код
            if (!TOTP::verifyCode($session['two_factor_secret'], $code)) {
                throw new Exception('Неверный код аутентификации', 401);
            }
            
            // Удаляем временную сессию
            $stmt = $db->prepare('DELETE FROM user_sessions WHERE token = ?');
            $stmt->execute(['tfa_' . $tfaToken]);
            
            // Создаём полноценную сессию
            $token = createSession($session['user_id'], $remember);
            
            // Получаем данные пользователя
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$session['user_id']]);
            $user = $stmt->fetch();
            
            unset($user['password']);
            unset($user['two_factor_secret']);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => $user
            ]);
            exit;
            
        case 'tfa_setup':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $token = getAuthorizationToken();
            
            if (!$token) {
                throw new Exception('Token required', 401);
            }
            
            $user = getUserByToken($token);
            
            if (!$user) {
                throw new Exception('Invalid or expired token', 401);
            }
            
            if ($user['two_factor_enabled']) {
                throw new Exception('2FA уже включена', 400);
            }
            
            // Генерируем данные для 2FA
            $setupData = TOTP::setupData($user['email']);
            
            // Сохраняем секрет в БД (но не активируем)
            $db = getDB();
            $stmt = $db->prepare('UPDATE users SET two_factor_secret = ? WHERE id = ?');
            $stmt->execute([$setupData['secret'], $user['id']]);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'secret' => $setupData['secret'],
                'qr_code_url' => $setupData['qr_code_url'],
                'otpauth_url' => $setupData['otpauth_url']
            ]);
            exit;
            
        case 'tfa_verify':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $token = getAuthorizationToken();
            
            if (!$token) {
                throw new Exception('Token required', 401);
            }
            
            $user = getUserByToken($token);
            
            if (!$user) {
                throw new Exception('Invalid or expired token', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $code = $data['code'] ?? '';
            
            if (!$code) {
                throw new Exception('Код обязателен', 400);
            }
            
            // Получаем секрет из БД
            $db = getDB();
            $stmt = $db->prepare('SELECT two_factor_secret FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            if (!$userData['two_factor_secret']) {
                throw new Exception('Сначала выполните настройку 2FA', 400);
            }
            
            // Проверяем код
            if (!TOTP::verifyCode($userData['two_factor_secret'], $code)) {
                throw new Exception('Неверный код. Проверьте время на устройстве.', 400);
            }
            
            // Активируем 2FA
            $stmt = $db->prepare('UPDATE users SET two_factor_enabled = 1 WHERE id = ?');
            $stmt->execute([$user['id']]);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Двухфакторная аутентификация включена'
            ]);
            exit;
            
        case 'tfa_disable':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            $token = getAuthorizationToken();
            
            if (!$token) {
                throw new Exception('Token required', 401);
            }
            
            $user = getUserByToken($token);
            
            if (!$user) {
                throw new Exception('Invalid or expired token', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $password = $data['password'] ?? '';
            
            if (!$password) {
                throw new Exception('Пароль обязателен', 400);
            }
            
            // Проверяем пароль
            $db = getDB();
            $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch();
            
            if (!verifyPassword($password, $userData['password'])) {
                throw new Exception('Неверный пароль', 401);
            }
            
            // Отключаем 2FA
            $stmt = $db->prepare('UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?');
            $stmt->execute([$user['id']]);
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Двухфакторная аутентификация отключена'
            ]);
            exit;
            
        case 'tfa_status':
            $token = getAuthorizationToken();
            
            if (!$token) {
                throw new Exception('Token required', 401);
            }
            
            $user = getUserByToken($token);
            
            if (!$user) {
                throw new Exception('Invalid or expired token', 401);
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'enabled' => (bool) $user['two_factor_enabled']
            ]);
            exit;
            
        default:
            throw new Exception('Unknown action', 400);
    }
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка базы данных. Запустите setup.php для настройки.'
    ]);
    exit;
} catch (Exception $e) {
    ob_end_clean();
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
    exit;
}
