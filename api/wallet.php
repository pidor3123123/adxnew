<?php
/**
 * ADX Finance - API кошелька
 * Работает ТОЛЬКО с Supabase (Single Source of Truth)
 * Все операции с балансом выполняются через транзакции
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
    
    error_log("PHP Error in wallet.php: $message in $file:$line");
    
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
        
        error_log("Fatal Error in wallet.php: {$error['message']} in {$error['file']}:{$error['line']}");
        
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line']
        ]);
        exit;
    }
});

// Загружаем необходимые файлы
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/supabase.php';
    require_once __DIR__ . '/auth.php';
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    
    error_log("Error loading required files in wallet.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка загрузки конфигурации: ' . $e->getMessage()
    ]);
    exit;
}

// Устанавливаем заголовки после загрузки всех файлов
header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
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
    
    if (!$email) {
        throw new Exception('User email is required to get Supabase UUID', 400);
    }
    
    // Ищем пользователя в Supabase по email
    $supabaseUser = $supabase->get('users', 'email', $email);
    
    if ($supabaseUser && isset($supabaseUser['id'])) {
        return $supabaseUser['id'];
    }
    
    // Если пользователь не найден, пытаемся найти в auth.users
    $authUserId = $supabase->findAuthUserByEmail($email);
    
    if ($authUserId) {
        return $authUserId;
    }
    
    // Если пользователь не найден, синхронизируем его
    // Это должно быть сделано при регистрации, но на случай если пропустили
    if (function_exists('syncUserToSupabase') && isset($mysqlUser['id'])) {
        try {
            syncUserToSupabase($mysqlUser['id']);
            // Повторно ищем
            $supabaseUser = $supabase->get('users', 'email', $email);
            if ($supabaseUser && isset($supabaseUser['id'])) {
                return $supabaseUser['id'];
            }
        } catch (Exception $e) {
            error_log("Error syncing user to Supabase: " . $e->getMessage());
        }
    }
    
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
        error_log("Wallet API: Request from user_id={$user['id']}, email={$user['email'] ?? 'N/A'}, action={$action}");
    } else {
        error_log("Wallet API: Request from unauthenticated user, action={$action}");
    }
    
    switch ($action) {
        case 'balances':
            if (!$user) {
                error_log("Wallet balances API: Unauthorized request - no user found");
                throw new Exception('Unauthorized', 401);
            }
            
            error_log("Wallet balances API: Starting balance fetch for user_id={$user['id']}");
            
            try {
                $supabase = getSupabaseClient();
                $supabaseUserId = getSupabaseUserId($user);
                
                error_log("Wallet balances API: Supabase user_id={$supabaseUserId}");
                
                // Получаем все балансы через RPC
                $result = $supabase->getAllWalletBalances($supabaseUserId);
                
                if (!$result['success']) {
                    throw new Exception('Failed to fetch balances from Supabase', 500);
                }
                
                $balances = $result['balances'] ?? [];
                
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
                
                echo json_encode([
                    'success' => true,
                    'balances' => $formattedBalances,
                    'total_usd' => $totalUsd
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                error_log("Wallet balances API: Error for user_id={$user['id']}: " . $e->getMessage());
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
                $supabaseUserId = getSupabaseUserId($user);
                
                // Получаем транзакции через RPC
                $result = $supabase->getTransactions($supabaseUserId, $currency, $limit, $offset);
                
                if (!$result['success']) {
                    throw new Exception('Failed to fetch transactions from Supabase', 500);
                }
                
                $transactions = $result['transactions'] ?? [];
                
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
                
                echo json_encode([
                    'success' => true,
                    'transactions' => $formattedTransactions,
                    'total' => $result['total'] ?? count($formattedTransactions)
                ], JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                error_log("Wallet transactions API: Error for user_id={$user['id']}: " . $e->getMessage());
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
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    http_response_code(500);
    
    error_log("Wallet API Throwable: " . $e->getMessage());
    error_log("Wallet API Throwable Type: " . get_class($e));
    error_log("Wallet API Throwable File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Wallet API Throwable Trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred. Please try again later.',
        'debug' => (defined('DEBUG') && DEBUG) ? [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ] : null
    ], JSON_UNESCAPED_UNICODE);
}
