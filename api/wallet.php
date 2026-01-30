<?php
/**
 * ADX Finance - API кошелька
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

// Получение рыночных цен для конвертации в USD (использует реальный API)
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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // Проверяем существование необходимых функций
    if (!function_exists('getAuthUser')) {
        throw new Exception('Function getAuthUser() is not defined. Check if auth.php is loaded correctly.', 500);
    }
    
    if (!function_exists('getDB')) {
        throw new Exception('Function getDB() is not defined. Check if database.php is loaded correctly.', 500);
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
                $db = getDB();
                $stmt = $db->prepare('
                    SELECT 
                        currency, 
                        SUM(available) as available, 
                        SUM(reserved) as reserved 
                    FROM balances 
                    WHERE user_id = ?
                    GROUP BY currency
                    ORDER BY 
                        CASE currency 
                            WHEN "USD" THEN 0 
                            WHEN "BTC" THEN 1 
                            WHEN "ETH" THEN 2 
                            ELSE 3 
                        END,
                        currency
                ');
                $stmt->execute([$user['id']]);
                $balances = $stmt->fetchAll();
                
                error_log("Wallet balances API: Query executed successfully, found " . count($balances) . " balance records");
                
                // Если балансов нет, создаем пустой массив
                if (!$balances) {
                    $balances = [];
                    error_log("Wallet balances API: No balances found for user_id={$user['id']}");
                } else {
                    // Логируем детали каждого баланса
                    foreach ($balances as $balance) {
                        error_log("Wallet balances API: Balance - currency={$balance['currency']}, available={$balance['available']}, reserved={$balance['reserved']}");
                    }
                }
                
                // Добавляем USD эквивалент
                $prices = getUsdPrices();
                $totalUsd = 0;
                
                foreach ($balances as &$balance) {
                    $price = $prices[$balance['currency']] ?? 0;
                    $balance['usd_value'] = (float)$balance['available'] * $price;
                    $totalUsd += $balance['usd_value'];
                    error_log("Wallet balances API: Calculated USD value for {$balance['currency']}: {$balance['usd_value']} (price={$price}, amount={$balance['available']})");
                }
                
                // Логируем итоговую информацию
                error_log("Wallet balances API: user_id={$user['id']}, balances_count=" . count($balances) . ", total_usd={$totalUsd}");
                
                // Очищаем буфер перед выводом JSON
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                echo json_encode([
                    'success' => true,
                    'balances' => $balances,
                    'total_usd' => $totalUsd
                ], JSON_UNESCAPED_UNICODE);
            } catch (PDOException $e) {
                error_log("Wallet balances API: Database error for user_id={$user['id']}: " . $e->getMessage());
                error_log("Wallet balances API: SQL State: " . $e->getCode());
                throw new Exception('Database error: ' . $e->getMessage(), 500, $e);
            } catch (Exception $e) {
                error_log("Wallet balances API: General error for user_id={$user['id']}: " . $e->getMessage());
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
            
            $db = getDB();
            $db->beginTransaction();
            
            try {
                // Проверяем/создаём баланс
                $stmt = $db->prepare('SELECT id, available FROM balances WHERE user_id = ? AND currency = ?');
                $stmt->execute([$user['id'], $currency]);
                $balance = $stmt->fetch();
                
                if ($balance) {
                    $stmt = $db->prepare('UPDATE balances SET available = available + ? WHERE id = ?');
                    $stmt->execute([$amount, $balance['id']]);
                } else {
                    $stmt = $db->prepare('INSERT INTO balances (user_id, currency, available) VALUES (?, ?, ?)');
                    $stmt->execute([$user['id'], $currency, $amount]);
                }
                
                // Создаём транзакцию
                $stmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, status, description, completed_at)
                    VALUES (?, "deposit", ?, ?, "completed", ?, NOW())
                ');
                $stmt->execute([
                    $user['id'],
                    $currency,
                    $amount,
                    "Пополнение {$amount} {$currency}"
                ]);
                
                $db->commit();
                
                // Синхронизация баланса с Supabase (в фоне, не блокирует основную логику)
                try {
                    require_once __DIR__ . '/sync.php';
                    if (function_exists('syncBalanceToSupabase')) {
                        syncBalanceToSupabase($user['id'], $currency);
                        error_log("Balance synced to Supabase: user_id={$user['id']}, currency=$currency (deposit)");
                    }
                } catch (Exception $e) {
                    error_log('Supabase balance sync error: ' . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Deposit successful'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
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
            
            $db = getDB();
            
            // Проверяем баланс
            $stmt = $db->prepare('SELECT available FROM balances WHERE user_id = ? AND currency = ?');
            $stmt->execute([$user['id'], $currency]);
            $balance = $stmt->fetch();
            
            if (!$balance || (float)$balance['available'] < $amount) {
                throw new Exception('Insufficient balance', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Списываем средства
                $stmt = $db->prepare('UPDATE balances SET available = available - ? WHERE user_id = ? AND currency = ?');
                $stmt->execute([$amount, $user['id'], $currency]);
                
                // Создаём транзакцию
                $stmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, status, description)
                    VALUES (?, "withdrawal", ?, ?, "pending", ?)
                ');
                $stmt->execute([
                    $user['id'],
                    $currency,
                    -$amount,
                    "Вывод {$amount} {$currency}" . ($address ? " на {$address}" : '')
                ]);
                
                $db->commit();
                
                // Синхронизация баланса с Supabase (в фоне, не блокирует основную логику)
                try {
                    require_once __DIR__ . '/sync.php';
                    if (function_exists('syncBalanceToSupabase')) {
                        syncBalanceToSupabase($user['id'], $currency);
                        error_log("Balance synced to Supabase: user_id={$user['id']}, currency=$currency (withdrawal)");
                    }
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
            
            if ($type && in_array($type, ['deposit', 'withdrawal', 'trade', 'fee'])) {
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
