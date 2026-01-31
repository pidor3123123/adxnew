<?php
/**
 * ADX Finance - API кошелька
 * Работает ТОЛЬКО с Supabase (Single Source of Truth)
 * Все операции с балансом выполняются через транзакции
 * 
 * Версия: 2.0.1
 * Дата обновления: 2026-01-30
 * Исправления: Улучшена обработка ошибок, все ответы в формате JSON
 */

// ВАЖНО: Включаем буферизацию вывода ПЕРВЫМ делом, до любых других операций
// Это должно быть ПЕРВОЙ строкой после открывающего тега PHP
if (!ob_get_level()) {
    @ob_start();
}

// Функция для безопасного вывода JSON ошибки
// Должна быть определена ДО установки обработчиков ошибок
// Упрощена для избежания рекурсии
function outputJsonError($message, $code = 500, $details = null) {
    // Отключаем обработчик ошибок, чтобы избежать рекурсии
    if (function_exists('restore_error_handler')) {
        restore_error_handler();
    }
    
    // Очищаем все уровни буфера
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Устанавливаем заголовки если еще не установлены
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($code);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    
    $response = [
        'success' => false,
        'error' => $message,
        'version' => '2.0.1' // Для диагностики версии файла
    ];
    
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Функция для безопасного вывода JSON успешного ответа
function outputJsonSuccess($data, $code = 200) {
    // Очищаем все уровни буфера
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Устанавливаем заголовки если еще не установлены
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($code);
        if (function_exists('setCorsHeaders')) {
            setCorsHeaders();
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    
    $response = array_merge(['success' => true], $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Глобальная обработка ошибок для конвертации всех PHP ошибок в JSON
// Упрощена для избежания рекурсии
set_error_handler(function($severity, $message, $file, $line) {
    // Игнорируем ошибки, которые не являются критическими
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Только для критических ошибок
    if (!in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        return false;
    }
    
    // Отключаем обработчик, чтобы избежать рекурсии
    restore_error_handler();
    
    // Логируем ошибку
    error_log("PHP Error in wallet.php [Severity: $severity]: $message in $file:$line");
    
    // Очищаем буфер
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Устанавливаем заголовки
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        header('Access-Control-Allow-Origin: *');
    }
    
    // Выводим JSON ошибку напрямую, без вызова функции
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $message . ' in ' . basename($file) . ':' . $line,
        'version' => '2.0.1',
        'details' => ['severity' => $severity, 'file' => basename($file), 'line' => $line]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}, E_ALL & ~E_NOTICE & ~E_WARNING);

// Обработка фатальных ошибок
// Упрощена для избежания рекурсии
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR])) {
        // Отключаем обработчик ошибок
        if (function_exists('restore_error_handler')) {
            restore_error_handler();
        }
        
        error_log("Fatal Error in wallet.php [Type: {$error['type']}]: {$error['message']} in {$error['file']}:{$error['line']}");
        
        // Очищаем буфер
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // Устанавливаем заголовки
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        
        // Выводим JSON ошибку напрямую
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'],
            'version' => '2.0.1',
            'details' => ['type' => $error['type'], 'file' => basename($error['file']), 'line' => $error['line']]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Загружаем необходимые файлы с проверкой существования и детальным логированием
// Пробуем несколько вариантов путей для совместимости с разными структурами серверов
function findConfigFile($filename) {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $currentDir = __DIR__;
    $parentDir = dirname($currentDir);
    
    // Собираем все возможные пути
    $possiblePaths = [
        // Относительно текущего файла (api/wallet.php)
        $currentDir . '/../config/' . $filename,
        $currentDir . '/../../config/' . $filename,
        
        // От корня документа
        $docRoot . '/config/' . $filename,
        $docRoot . '/../config/' . $filename,
        
        // Через dirname
        $parentDir . '/config/' . $filename,
        dirname($parentDir) . '/config/' . $filename,
        
        // Абсолютные пути (если DOCUMENT_ROOT установлен)
        realpath($docRoot . '/config/' . $filename) ?: null,
        realpath($parentDir . '/config/' . $filename) ?: null,
    ];
    
    // Убираем null значения
    $possiblePaths = array_filter($possiblePaths, function($path) {
        return $path !== null;
    });
    
    // Логируем все проверяемые пути
    error_log("Wallet.php: Searching for $filename in " . count($possiblePaths) . " possible paths");
    
    foreach ($possiblePaths as $path) {
        // Используем realpath для нормализации пути
        $realPath = realpath($path);
        
        if ($realPath && file_exists($realPath) && is_readable($realPath)) {
            error_log("Wallet.php: ✓ Found $filename at $realPath");
            return $realPath;
        } else {
            error_log("Wallet.php: ✗ Not found: $path");
        }
    }
    
    error_log("Wallet.php: ✗ File $filename not found in any of the searched paths");
    return null;
}

function findAuthFile() {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $currentDir = __DIR__;
    
    $possiblePaths = [
        // В той же папке, что и wallet.php
        $currentDir . '/auth.php',
        realpath($currentDir . '/auth.php') ?: null,
        
        // От корня документа
        $docRoot . '/api/auth.php',
        realpath($docRoot . '/api/auth.php') ?: null,
    ];
    
    // Убираем null значения
    $possiblePaths = array_filter($possiblePaths, function($path) {
        return $path !== null;
    });
    
    error_log("Wallet.php: Searching for auth.php in " . count($possiblePaths) . " possible paths");
    
    foreach ($possiblePaths as $path) {
        $realPath = realpath($path);
        
        if ($realPath && file_exists($realPath) && is_readable($realPath)) {
            error_log("Wallet.php: ✓ Found auth.php at $realPath");
            return $realPath;
        } else {
            error_log("Wallet.php: ✗ Not found: $path");
        }
    }
    
    error_log("Wallet.php: ✗ File auth.php not found in any of the searched paths");
    return null;
}

$requiredFiles = [
    'database' => findConfigFile('database.php'),
    'supabase' => findConfigFile('supabase.php'),
    'auth' => findAuthFile()
];

foreach ($requiredFiles as $name => $file) {
    // Проверяем существование файла
    if ($file === null || !file_exists($file)) {
        $errorMsg = "Required file not found: $name";
        error_log("Wallet.php ERROR: $errorMsg");
        error_log("Wallet.php: Searched paths for $name:");
        error_log("  - " . __DIR__ . '/../config/' . ($name === 'auth' ? 'auth.php' : "$name.php"));
        error_log("  - " . __DIR__ . '/../../config/' . ($name === 'auth' ? 'auth.php' : "$name.php"));
        error_log("  - " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '/config/' . ($name === 'auth' ? 'auth.php' : "$name.php"));
        error_log("  - Current __DIR__: " . __DIR__);
        error_log("  - DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'));
        
        // Выводим ошибку напрямую, без вызова функции
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        echo json_encode([
            'success' => false,
            'error' => "Ошибка загрузки: файл не найден - $name",
            'version' => '2.0.1',
            'details' => [
                'file' => $name,
                'searched_paths' => [
                    __DIR__ . '/../config/' . ($name === 'auth' ? 'auth.php' : "$name.php"),
                    __DIR__ . '/../../config/' . ($name === 'auth' ? 'auth.php' : "$name.php"),
                    ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . '/config/' . ($name === 'auth' ? 'auth.php' : "$name.php")
                ],
                'current_dir' => __DIR__,
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем, что файл читаемый
    if (!is_readable($file)) {
        $errorMsg = "Required file not readable: $name ($file)";
        error_log("Wallet.php ERROR: $errorMsg");
        
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        echo json_encode([
            'success' => false,
            'error' => "Ошибка загрузки: файл не читаемый - $name",
            'version' => '2.0.1',
            'details' => ['file' => $name, 'path' => $file]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Загружаем файл с обработкой ошибок
    try {
        error_log("Wallet.php: Loading file $name from $file");
        require_once $file;
        error_log("Wallet.php: Successfully loaded $name");
    } catch (ParseError $e) {
        $errorMsg = "Parse error in $name: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        error_log("Wallet.php PARSE ERROR: $errorMsg");
        
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        echo json_encode([
            'success' => false,
            'error' => "Ошибка синтаксиса в файле: $name",
            'version' => '2.0.1',
            'details' => ['file' => $name, 'message' => $e->getMessage(), 'line' => $e->getLine()]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        $errorMsg = "Error loading $name: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        error_log("Wallet.php LOAD ERROR: $errorMsg");
        error_log("Wallet.php Exception type: " . get_class($e));
        error_log("Wallet.php Exception trace: " . $e->getTraceAsString());
        
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(500);
            header('Access-Control-Allow-Origin: *');
        }
        echo json_encode([
            'success' => false,
            'error' => "Ошибка загрузки файла: $name - " . $e->getMessage(),
            'version' => '2.0.1',
            'details' => ['file' => $name, 'type' => get_class($e), 'line' => $e->getLine()]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Загружаем sync.php для функции syncUserToSupabase (опционально)
// Пробуем несколько путей
$syncPaths = [
    __DIR__ . '/sync.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/api/sync.php',
    realpath(__DIR__ . '/sync.php') ?: null,
];

$syncLoaded = false;
foreach ($syncPaths as $syncPath) {
    if ($syncPath && file_exists($syncPath) && is_readable($syncPath)) {
        try {
            require_once $syncPath;
            error_log("Wallet.php: ✓ Loaded sync.php from $syncPath");
            $syncLoaded = true;
            break;
        } catch (Throwable $e) {
            error_log("Wallet.php: Warning: Could not load sync.php from $syncPath: " . $e->getMessage());
        }
    }
}

if (!$syncLoaded) {
    error_log("Wallet.php: Info: sync.php not found or not loaded (not critical)");
}

// Устанавливаем заголовки после загрузки всех файлов
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    
    // Устанавливаем CORS заголовки если функция доступна
    if (function_exists('setCorsHeaders')) {
        setCorsHeaders();
    } else {
        // Fallback если функция не загружена
        header('Access-Control-Allow-Origin: *');
    }
    
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

/**
 * Установка заголовков для предотвращения кеширования
 * Используется для динамических данных (баланс, транзакции, профиль)
 */
function setNoCacheHeaders(): void {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    http_response_code(200);
    exit;
}

/**
 * Получение рыночных цен для конвертации в USD (использует реальный API)
 */
function getUsdPrices(): array {
    $prices = ['USD' => 1.0];
    
    // Для криптовалют используем CoinGecko API
    $cryptoMap = [
        'BTC' => 'bitcoin',
        'ETH' => 'ethereum',
        'BNB' => 'binancecoin',
        'XRP' => 'ripple',
        'SOL' => 'solana',
        'ADA' => 'cardano',
        'DOGE' => 'dogecoin',
        'DOT' => 'polkadot',
        'MATIC' => 'polygon-ecosystem-token',
        'LTC' => 'litecoin'
    ];
    
    try {
        $coinIds = array_values($cryptoMap);
        $url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . implode(',', $coinIds) . '&vs_currencies=usd';
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'header' => 'Accept: application/json'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data) {
                foreach ($cryptoMap as $symbol => $coinId) {
                    if (isset($data[$coinId]['usd'])) {
                        $prices[$symbol] = (float)$data[$coinId]['usd'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching crypto prices from CoinGecko: " . $e->getMessage());
    }
    
    // Fallback: моковые цены (если API недоступен)
    $fallbackPrices = [
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
    
    // Заполняем отсутствующие цены из fallback
    foreach ($fallbackPrices as $symbol => $price) {
        if (!isset($prices[$symbol])) {
            $prices[$symbol] = $price;
        }
    }
    
    return $prices;
}

/**
 * Получение Supabase UUID пользователя из MySQL user
 * @param array $mysqlUser MySQL user массив с полями id, email
 * @return string UUID пользователя в Supabase
 */
function getSupabaseUserId(array $mysqlUser): string {
    $supabase = getSupabaseClient();
    $email = $mysqlUser['email'] ?? null;
    $mysqlUserId = $mysqlUser['id'] ?? null;
    
    if (!$email) {
        error_log("getSupabaseUserId: User email is missing. MySQL user ID: " . ($mysqlUserId ?? 'N/A'));
        throw new Exception('User email is required to get Supabase UUID', 400);
    }
    
    error_log("getSupabaseUserId: Looking for user with email: $email, MySQL ID: " . ($mysqlUserId ?? 'N/A'));
    
    // Ищем пользователя в Supabase по email
    try {
        $supabaseUser = $supabase->get('users', 'email', $email);
        
        if ($supabaseUser && isset($supabaseUser['id'])) {
            error_log("getSupabaseUserId: Found user in Supabase users table. UUID: {$supabaseUser['id']}");
            return $supabaseUser['id'];
        }
    } catch (Exception $e) {
        error_log("getSupabaseUserId: Error searching in users table: " . $e->getMessage());
    }
    
    // Если пользователь не найден, пытаемся найти в auth.users
    try {
        $authUserId = $supabase->findAuthUserByEmail($email);
        
        if ($authUserId) {
            error_log("getSupabaseUserId: Found user in auth.users. UUID: $authUserId");
            return $authUserId;
        }
    } catch (Exception $e) {
        error_log("getSupabaseUserId: Error searching in auth.users: " . $e->getMessage());
    }
    
    // Если пользователь не найден, синхронизируем его
    // Это должно быть сделано при регистрации, но на случай если пропустили
    if (function_exists('syncUserToSupabase') && $mysqlUserId) {
        error_log("getSupabaseUserId: User not found, attempting sync. MySQL ID: $mysqlUserId, Email: $email");
        try {
            syncUserToSupabase($mysqlUserId);
            error_log("getSupabaseUserId: Sync completed, searching again for user");
            
            // Повторно ищем
            $supabaseUser = $supabase->get('users', 'email', $email);
            if ($supabaseUser && isset($supabaseUser['id'])) {
                error_log("getSupabaseUserId: User found after sync. UUID: {$supabaseUser['id']}");
                return $supabaseUser['id'];
            }
            
            // Пробуем еще раз через auth.users
            $authUserId = $supabase->findAuthUserByEmail($email);
            if ($authUserId) {
                error_log("getSupabaseUserId: User found in auth.users after sync. UUID: $authUserId");
                return $authUserId;
            }
            
            error_log("getSupabaseUserId: User still not found after sync. MySQL ID: $mysqlUserId, Email: $email");
        } catch (Exception $e) {
            error_log("getSupabaseUserId: Error syncing user to Supabase. MySQL ID: $mysqlUserId, Email: $email, Error: " . $e->getMessage());
            error_log("getSupabaseUserId: Sync exception trace: " . $e->getTraceAsString());
            throw new Exception("Failed to sync user to Supabase: " . $e->getMessage(), 500, $e);
        }
    } else {
        error_log("getSupabaseUserId: Cannot sync user - function not available or MySQL ID missing. MySQL ID: " . ($mysqlUserId ?? 'N/A'));
    }
    
    error_log("getSupabaseUserId: User not found in Supabase and sync failed or unavailable. MySQL ID: " . ($mysqlUserId ?? 'N/A') . ", Email: $email");
    throw new Exception("User not found in Supabase. Please contact support.", 404);
}

/**
 * Генерация idempotency key для защиты от double spend
 * @param string $type Тип транзакции
 * @param string $userId UUID пользователя
 * @param string|null $orderId ID ордера (если есть)
 * @return string Idempotency key
 */
function generateIdempotencyKey(string $type, string $userId, ?string $orderId = null): string {
    $parts = [$type, $userId];
    if ($orderId !== null) {
        $parts[] = $orderId;
    }
    $parts[] = time(); // Добавляем timestamp для уникальности
    return implode('_', $parts);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Проверяем существование необходимых функций
    if (!function_exists('getAuthUser')) {
        throw new Exception('Function getAuthUser() is not defined. Check if auth.php is loaded correctly.', 500);
    }
    
    if (!function_exists('getSupabaseClient')) {
        throw new Exception('Function getSupabaseClient() is not defined. Check if supabase.php is loaded correctly.', 500);
    }
    
    if (!function_exists('setCorsHeaders')) {
        throw new Exception('Function setCorsHeaders() is not defined. Check if database.php is loaded correctly.', 500);
    }
    
    $user = getAuthUser();
    
    // Логируем информацию о пользователе
    if ($user) {
        $userEmail = $user['email'] ?? 'N/A';
        error_log("Wallet API: Request from user_id={$user['id']}, email={$userEmail}, action={$action}");
    } else {
        error_log("Wallet API: Request from unauthenticated user, action={$action}");
    }
    
    switch ($action) {
        case 'balances':
            if (!$user) {
                error_log("Wallet balances API: Unauthorized request - no user found");
                outputJsonError('Unauthorized', 401);
                exit;
            }
            
            error_log("Wallet balances API: Starting balance fetch for user_id={$user['id']}");
            
            try {
                // Проверяем доступность getSupabaseClient перед вызовом
                if (!function_exists('getSupabaseClient')) {
                    error_log("Wallet balances API: getSupabaseClient function not found");
                    throw new Exception('Supabase client not available', 500);
                }
                
                $supabase = getSupabaseClient();
                
                if (!$supabase) {
                    error_log("Wallet balances API: getSupabaseClient returned null");
                    throw new Exception('Failed to initialize Supabase client', 500);
                }
                
                // Пытаемся получить Supabase UUID
                try {
                    $supabaseUserId = getSupabaseUserId($user);
                    error_log("Wallet balances API: Supabase user_id={$supabaseUserId}");
                } catch (Exception $e) {
                    error_log("Wallet balances API: User not found in Supabase, attempting sync. Error: " . $e->getMessage());
                    error_log("Wallet balances API: Exception type: " . get_class($e));
                    error_log("Wallet balances API: Exception code: " . $e->getCode());
                    error_log("Wallet balances API: Exception file: " . $e->getFile() . ":" . $e->getLine());
                    error_log("Wallet balances API: Exception trace: " . $e->getTraceAsString());
                    
                    // Пытаемся синхронизировать пользователя
                    if (function_exists('syncUserToSupabase') && isset($user['id'])) {
                        try {
                            error_log("Wallet balances API: Attempting to sync user ID {$user['id']} to Supabase");
                            syncUserToSupabase($user['id']);
                            $supabaseUserId = getSupabaseUserId($user);
                            error_log("Wallet balances API: User synced successfully, Supabase user_id={$supabaseUserId}");
                        } catch (Exception $syncError) {
                            error_log("Wallet balances API: Sync failed: " . $syncError->getMessage());
                            error_log("Wallet balances API: Sync exception type: " . get_class($syncError));
                            error_log("Wallet balances API: Sync exception code: " . $syncError->getCode());
                            error_log("Wallet balances API: Sync exception file: " . $syncError->getFile() . ":" . $syncError->getLine());
                            error_log("Wallet balances API: Sync exception trace: " . $syncError->getTraceAsString());
                            
                            // Возвращаем пустой баланс, если синхронизация не удалась
                            outputJsonSuccess([
                                'balances' => [],
                                'total_usd' => 0,
                                'warning' => 'User not synchronized with Supabase. Please contact support.'
                            ]);
                        }
                    } else {
                        // Если синхронизация недоступна, возвращаем пустой баланс
                        $userId = $user['id'] ?? 'N/A';
                        error_log("Wallet balances API: syncUserToSupabase function not available or user ID missing. User ID: " . $userId);
                        outputJsonSuccess([
                            'balances' => [],
                            'total_usd' => 0,
                            'warning' => 'User not synchronized with Supabase. Please contact support.'
                        ]);
                    }
                }
                
                // Получаем все балансы через RPC
                try {
                    $result = $supabase->getAllWalletBalances($supabaseUserId);
                    
                    error_log("Wallet balances API: RPC result type: " . gettype($result));
                    error_log("Wallet balances API: RPC result: " . json_encode($result));
                    
                    // Supabase может вернуть JSONB как массив с одним элементом
                    if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                        $result = $result[0];
                    }
                    
                    if (!isset($result['success']) || !$result['success']) {
                        error_log("Wallet balances API: RPC function returned unsuccessful result: " . json_encode($result));
                        // Возвращаем пустой баланс, если RPC функция не работает
                        if (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        echo json_encode([
                            'success' => true,
                            'balances' => [],
                            'total_usd' => 0,
                            'warning' => 'Wallet system not fully configured. Please contact support.'
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    $balances = $result['balances'] ?? [];
                    
                    // Если balances - это строка JSON, парсим её
                    if (is_string($balances)) {
                        $decoded = json_decode($balances, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $balances = $decoded;
                        } else {
                            error_log("Wallet balances API: Failed to decode balances JSON string: " . json_last_error_msg());
                            $balances = [];
                        }
                    }
                    
                    // Убеждаемся, что balances - это массив
                    if (!is_array($balances)) {
                        error_log("Wallet balances API: balances is not an array, type: " . gettype($balances) . ", value: " . json_encode($balances));
                        $balances = [];
                    }
                    
                    error_log("Wallet balances API: Found " . count($balances) . " balance records");
                    
                    // Преобразуем формат для совместимости с фронтендом
                    $formattedBalances = [];
                    $totalUsd = 0;
                    
                    // Получаем цены для конвертации в USD
                    $prices = getUsdPrices();
                    
                    foreach ($balances as $balance) {
                        $currency = $balance['currency'] ?? 'USD';
                        $balanceAmount = (float)($balance['balance'] ?? 0);
                        
                        $price = $prices[$currency] ?? 0;
                        $usdValue = $balanceAmount * $price;
                        $totalUsd += $usdValue;
                        
                        $formattedBalances[] = [
                            'currency' => $currency,
                            'available' => $balanceAmount,
                            'reserved' => 0, // В новой схеме нет reserved, все в balance
                            'usd_value' => $usdValue
                        ];
                        
                        error_log("Wallet balances API: Balance - currency={$currency}, balance={$balanceAmount}, usd_value={$usdValue}");
                    }
                    
                    // Сортируем балансы
                    usort($formattedBalances, function($a, $b) {
                        $order = ['USD' => 0, 'BTC' => 1, 'ETH' => 2];
                        $orderA = $order[$a['currency']] ?? 3;
                        $orderB = $order[$b['currency']] ?? 3;
                        return $orderA <=> $orderB ?: strcmp($a['currency'], $b['currency']);
                    });
                    
                    error_log("Wallet balances API: user_id={$user['id']}, balances_count=" . count($formattedBalances) . ", total_usd={$totalUsd}");
                    
                    // Очищаем буфер перед выводом JSON
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    
                    // Устанавливаем заголовки против кеширования
                    setNoCacheHeaders();
                    
                    echo json_encode([
                        'success' => true,
                        'balances' => $formattedBalances,
                        'total_usd' => $totalUsd
                    ], JSON_UNESCAPED_UNICODE);
                } catch (Exception $rpcError) {
                    error_log("Wallet balances API: RPC error: " . $rpcError->getMessage());
                    error_log("Wallet balances API: RPC error trace: " . $rpcError->getTraceAsString());
                    
                    // Если RPC функция не существует или не работает, возвращаем пустой баланс
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    
                    // Проверяем, является ли это ошибкой отсутствия функции
                    if (strpos($rpcError->getMessage(), 'function') !== false || 
                        strpos($rpcError->getMessage(), 'does not exist') !== false ||
                        strpos($rpcError->getMessage(), '404') !== false) {
                        echo json_encode([
                            'success' => true,
                            'balances' => [],
                            'total_usd' => 0,
                            'warning' => 'Wallet system not fully configured. Please execute SQL schema in Supabase.'
                        ], JSON_UNESCAPED_UNICODE);
                    } else {
                        // Другая ошибка - пробрасываем дальше
                        throw $rpcError;
                    }
                }
            } catch (Exception $e) {
                error_log("Wallet balances API: Error for user_id={$user['id']}: " . $e->getMessage());
                error_log("Wallet balances API: Exception type: " . get_class($e));
                error_log("Wallet balances API: Exception code: " . $e->getCode());
                error_log("Wallet balances API: Exception file: " . $e->getFile() . ":" . $e->getLine());
                error_log("Wallet balances API: Error trace: " . $e->getTraceAsString());
                throw $e;
            }
            break;
            
        case 'deposit':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $currency = strtoupper(trim($data['currency'] ?? ''));
            $amount = (float)($data['amount'] ?? 0);
            
            if (!$currency) {
                throw new Exception('Currency required', 400);
            }
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount', 400);
            }
            
            try {
                $supabase = getSupabaseClient();
                $supabaseUserId = getSupabaseUserId($user);
                
                // Генерируем idempotency key для защиты от double spend
                $idempotencyKey = generateIdempotencyKey('deposit', $supabaseUserId, null);
                
                // Применяем транзакцию через RPC
                $result = $supabase->applyTransaction(
                    $supabaseUserId,
                    $amount,
                    'deposit',
                    $currency,
                    $idempotencyKey,
                    [
                        'description' => "Пополнение {$amount} {$currency}",
                        'source' => 'user_deposit'
                    ]
                );
                
                if (!$result['success']) {
                    throw new Exception('Failed to process deposit', 500);
                }
                
                // Очищаем буфер перед выводом JSON
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Устанавливаем заголовки против кеширования
                setNoCacheHeaders();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Deposit processed successfully',
                    'transaction_id' => $result['transaction_id'],
                    'balance' => $result['balance'],
                    'duplicate' => $result['duplicate'] ?? false
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                error_log("Wallet deposit API: Error for user_id={$user['id']}: " . $e->getMessage());
                throw $e;
            }
            break;
            
        case 'withdraw':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $currency = strtoupper(trim($data['currency'] ?? ''));
            $amount = (float)($data['amount'] ?? 0);
            $address = trim($data['address'] ?? '');
            
            if (!$currency) {
                throw new Exception('Currency required', 400);
            }
            
            if ($amount <= 0) {
                throw new Exception('Invalid amount', 400);
            }
            
            try {
                $supabase = getSupabaseClient();
                $supabaseUserId = getSupabaseUserId($user);
                
                // Генерируем idempotency key
                $idempotencyKey = generateIdempotencyKey('withdrawal', $supabaseUserId, null);
                
                // Применяем транзакцию (отрицательная сумма для списания)
                $result = $supabase->applyTransaction(
                    $supabaseUserId,
                    -$amount, // Отрицательная сумма для списания
                    'withdrawal',
                    $currency,
                    $idempotencyKey,
                    [
                        'description' => "Вывод {$amount} {$currency}" . ($address ? " на {$address}" : ''),
                        'address' => $address,
                        'source' => 'user_withdrawal'
                    ]
                );
                
                if (!$result['success']) {
                    throw new Exception('Failed to process withdrawal', 500);
                }
                
                // Очищаем буфер перед выводом JSON
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Устанавливаем заголовки против кеширования
                setNoCacheHeaders();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Withdrawal processed successfully',
                    'transaction_id' => $result['transaction_id'],
                    'balance' => $result['balance'],
                    'duplicate' => $result['duplicate'] ?? false
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                error_log("Wallet withdraw API: Error for user_id={$user['id']}: " . $e->getMessage());
                throw $e;
            }
            break;
            
        case 'transactions':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $offset = (int)($_GET['offset'] ?? 0);
            $type = $_GET['type'] ?? null;
            $currency = $_GET['currency'] ?? null;
            
            try {
                $supabase = getSupabaseClient();
                
                // Пытаемся получить Supabase UUID
                try {
                    $supabaseUserId = getSupabaseUserId($user);
                } catch (Exception $e) {
                    error_log("Wallet transactions API: User not found in Supabase: " . $e->getMessage());
                    error_log("Wallet transactions API: Exception type: " . get_class($e));
                    error_log("Wallet transactions API: Exception code: " . $e->getCode());
                    error_log("Wallet transactions API: Exception file: " . $e->getFile() . ":" . $e->getLine());
                    error_log("Wallet transactions API: Exception trace: " . $e->getTraceAsString());
                    
                    // Пытаемся синхронизировать пользователя
                    if (function_exists('syncUserToSupabase') && isset($user['id'])) {
                        try {
                            error_log("Wallet transactions API: Attempting to sync user ID {$user['id']} to Supabase");
                            syncUserToSupabase($user['id']);
                            $supabaseUserId = getSupabaseUserId($user);
                            error_log("Wallet transactions API: User synced successfully, Supabase user_id={$supabaseUserId}");
                        } catch (Exception $syncError) {
                            error_log("Wallet transactions API: Sync failed: " . $syncError->getMessage());
                            error_log("Wallet transactions API: Sync exception trace: " . $syncError->getTraceAsString());
                            
                            // Возвращаем пустой список транзакций, если синхронизация не удалась
                            if (ob_get_level() > 0) {
                                ob_end_clean();
                            }
                            echo json_encode([
                                'success' => true,
                                'transactions' => [],
                                'total' => 0,
                                'warning' => 'User not synchronized with Supabase. Please contact support.'
                            ], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    } else {
                        // Если синхронизация недоступна, возвращаем пустой список
                        error_log("Wallet transactions API: syncUserToSupabase function not available or user ID missing");
                        if (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        echo json_encode([
                            'success' => true,
                            'transactions' => [],
                            'total' => 0,
                            'warning' => 'User not synchronized with Supabase. Please contact support.'
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                }
                
                // Получаем транзакции через RPC
                try {
                    $result = $supabase->getTransactions($supabaseUserId, $currency, $limit, $offset);
                    
                    error_log("Wallet transactions API: RPC result type: " . gettype($result));
                    error_log("Wallet transactions API: RPC result: " . json_encode($result));
                    
                    // Supabase может вернуть JSONB как массив с одним элементом
                    if (is_array($result) && isset($result[0]) && is_array($result[0])) {
                        $result = $result[0];
                    }
                    
                    if (!isset($result['success']) || !$result['success']) {
                        error_log("Wallet transactions API: RPC function returned unsuccessful result: " . json_encode($result));
                        // Возвращаем пустой список
                        if (ob_get_level() > 0) {
                            ob_end_clean();
                        }
                        echo json_encode([
                            'success' => true,
                            'transactions' => [],
                            'total' => 0
                        ], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    $transactions = $result['transactions'] ?? [];
                    
                    // Если transactions - это строка JSON, парсим её
                    if (is_string($transactions)) {
                        $decoded = json_decode($transactions, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $transactions = $decoded;
                        } else {
                            error_log("Wallet transactions API: Failed to decode transactions JSON string: " . json_last_error_msg());
                            $transactions = [];
                        }
                    }
                    
                    // Убеждаемся, что transactions - это массив
                    if (!is_array($transactions)) {
                        error_log("Wallet transactions API: transactions is not an array, type: " . gettype($transactions) . ", value: " . json_encode($transactions));
                        $transactions = [];
                    }
                    
                    // Фильтруем по типу, если указан
                    if ($type) {
                        $transactions = array_filter($transactions, function($t) use ($type) {
                            return ($t['type'] ?? '') === $type;
                        });
                        $transactions = array_values($transactions); // Переиндексируем
                    }
                    
                    // Преобразуем формат для совместимости
                    $formattedTransactions = [];
                    foreach ($transactions as $tx) {
                        $formattedTransactions[] = [
                            'id' => $tx['id'],
                            'type' => $tx['type'],
                            'currency' => $tx['currency'],
                            'amount' => (float)($tx['amount'] ?? 0),
                            'description' => $tx['metadata']['description'] ?? '',
                            'status' => 'completed', // В новой схеме все транзакции completed
                            'created_at' => $tx['created_at'],
                            'completed_at' => $tx['created_at']
                        ];
                    }
                    
                    // Очищаем буфер перед выводом JSON
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    
                    // Устанавливаем заголовки против кеширования
                    setNoCacheHeaders();
                    
                    echo json_encode([
                        'success' => true,
                        'transactions' => $formattedTransactions,
                        'total' => $result['total'] ?? count($formattedTransactions)
                    ], JSON_UNESCAPED_UNICODE);
                } catch (Exception $rpcError) {
                    error_log("Wallet transactions API: RPC error: " . $rpcError->getMessage());
                    error_log("Wallet transactions API: RPC error type: " . get_class($rpcError));
                    error_log("Wallet transactions API: RPC error code: " . $rpcError->getCode());
                    error_log("Wallet transactions API: RPC error file: " . $rpcError->getFile() . ":" . $rpcError->getLine());
                    error_log("Wallet transactions API: RPC error trace: " . $rpcError->getTraceAsString());
                    
                    // Возвращаем пустой список при ошибке RPC
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    
                    // Проверяем, является ли это ошибкой отсутствия функции
                    if (strpos($rpcError->getMessage(), 'function') !== false || 
                        strpos($rpcError->getMessage(), 'does not exist') !== false ||
                        strpos($rpcError->getMessage(), '404') !== false) {
                        echo json_encode([
                            'success' => true,
                            'transactions' => [],
                            'total' => 0,
                            'warning' => 'Wallet system not fully configured. Please execute SQL schema in Supabase.'
                        ], JSON_UNESCAPED_UNICODE);
                    } else {
                        echo json_encode([
                            'success' => true,
                            'transactions' => [],
                            'total' => 0
                        ], JSON_UNESCAPED_UNICODE);
                    }
                }
            } catch (Exception $e) {
                error_log("Wallet transactions API: Error for user_id={$user['id']}: " . $e->getMessage());
                error_log("Wallet transactions API: Exception type: " . get_class($e));
                error_log("Wallet transactions API: Exception code: " . $e->getCode());
                error_log("Wallet transactions API: Exception file: " . $e->getFile() . ":" . $e->getLine());
                error_log("Wallet transactions API: Exception trace: " . $e->getTraceAsString());
                throw $e;
            }
            break;
            
        default:
            throw new Exception('Unknown action', 400);
    }
    
} catch (PDOException $e) {
    // Очищаем буфер перед выводом ошибки
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Устанавливаем заголовки, если еще не установлены
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    $code = 500;
    http_response_code($code);
    
    // Логируем детали ошибки базы данных
    error_log("Wallet API PDOException: " . $e->getMessage());
    error_log("Wallet API PDOException SQL State: " . $e->getCode());
    error_log("Wallet API PDOException Trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred. Please try again later.',
        'debug' => (defined('DEBUG') && DEBUG) ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Очищаем буфер перед выводом ошибки
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Устанавливаем заголовки, если еще не установлены
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    $code = $e->getCode();
    // Валидация и приведение к int
    if (!is_numeric($code) || $code < 100 || $code > 599) {
        $code = 500;
    }
    $code = (int)$code;
    http_response_code($code);
    
    // Логируем детали ошибки
    error_log("Wallet API Exception: " . $e->getMessage());
    error_log("Wallet API Exception Code: " . $code);
    error_log("Wallet API Exception File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Wallet API Exception Trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => (defined('DEBUG') && DEBUG) ? [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    // Обработка всех остальных ошибок (Error, TypeError и т.д.)
    // Очищаем буфер перед выводом
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Устанавливаем заголовки
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
    
    // Логируем все детали ошибки
    error_log("Wallet API Throwable: " . $e->getMessage());
    error_log("Wallet API Throwable Type: " . get_class($e));
    error_log("Wallet API Throwable File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Wallet API Throwable Code: " . $e->getCode());
    error_log("Wallet API Throwable Trace: " . $e->getTraceAsString());
    
    // Всегда показываем детали ошибки для диагностики
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'version' => '2.0.1',
        'debug' => [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10) // Первые 10 строк trace
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
