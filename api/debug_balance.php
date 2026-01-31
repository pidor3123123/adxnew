<?php
/**
 * Диагностический endpoint для проверки баланса пользователя
 * Использование: /api/debug_balance.php?email=user@example.com
 */

// Включаем вывод ошибок для диагностики
error_reporting(E_ALL);
ini_set('display_errors', 0); // Не показываем ошибки пользователю, только логируем

// Начинаем буферизацию вывода
ob_start();

// Устанавливаем заголовки
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка OPTIONS запроса
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit;
}

// Функция для вывода JSON ошибки
function outputJsonError($message, $code = 500, $details = null) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code($code);
    
    $response = [
        'success' => false,
        'error' => $message
    ];
    
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Функция для вывода JSON успешного ответа
function outputJsonSuccess($data) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    http_response_code(200);
    
    $response = array_merge(['success' => true], $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Загружаем необходимые файлы
    $requiredFiles = [
        __DIR__ . '/../config/database.php',
        __DIR__ . '/../config/supabase.php',
        __DIR__ . '/auth.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            outputJsonError("Required file not found: " . basename($file), 500);
        }
        
        try {
            require_once $file;
        } catch (Throwable $e) {
            outputJsonError("Error loading file: " . basename($file) . " - " . $e->getMessage(), 500);
        }
    }
    
    // Проверяем наличие функций
    if (!function_exists('getSupabaseClient')) {
        outputJsonError('getSupabaseClient() function not available', 500);
    }
    
    if (!function_exists('getDB')) {
        outputJsonError('getDB() function not available', 500);
    }
    
    // Получаем email из параметров
    $email = $_GET['email'] ?? null;
    
    if (!$email) {
        outputJsonError('Email parameter is required. Usage: /api/debug_balance.php?email=user@example.com', 400);
    }
    
    $email = trim($email);
    
    // Инициализируем результат
    $result = [
        'email' => $email,
        'mysql_user' => null,
        'supabase_user' => null,
        'supabase_uuid' => null,
        'wallets' => [],
        'transactions' => [],
        'user_balances' => [],
        'mysql_balances' => [],
        'diagnostics' => []
    ];
    
    // 1. Ищем пользователя в MySQL
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT id, email, first_name, last_name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $mysqlUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mysqlUser) {
            $result['mysql_user'] = $mysqlUser;
            $result['diagnostics'][] = "✓ MySQL user found: ID={$mysqlUser['id']}";
            
            // Получаем балансы из MySQL
            $stmt = $db->prepare('SELECT currency, available, reserved FROM balances WHERE user_id = ?');
            $stmt->execute([$mysqlUser['id']]);
            $mysqlBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['mysql_balances'] = $mysqlBalances;
            $result['diagnostics'][] = "✓ MySQL balances found: " . count($mysqlBalances) . " currencies";
        } else {
            $result['diagnostics'][] = "✗ MySQL user not found";
        }
    } catch (Exception $e) {
        $result['diagnostics'][] = "✗ Error querying MySQL: " . $e->getMessage();
    }
    
    // 2. Ищем пользователя в Supabase
    try {
        $supabase = getSupabaseClient();
        
        // Ищем в таблице users
        $supabaseUser = $supabase->get('users', 'email', $email);
        
        if ($supabaseUser) {
            $result['supabase_user'] = $supabaseUser;
            $result['supabase_uuid'] = $supabaseUser['id'] ?? null;
            $result['diagnostics'][] = "✓ Supabase user found: UUID=" . ($result['supabase_uuid'] ?? 'N/A');
            
            if ($result['supabase_uuid']) {
                // 3. Получаем балансы из таблицы wallets
                try {
                    $walletsResult = $supabase->select('wallets', '*', ['user_id' => $result['supabase_uuid']]);
                    
                    if (is_array($walletsResult)) {
                        // Если результат - массив с одним элементом
                        if (count($walletsResult) === 1 && isset($walletsResult[0]) && is_array($walletsResult[0])) {
                            $result['wallets'] = [$walletsResult[0]];
                        } else {
                            $result['wallets'] = $walletsResult;
                        }
                    } else {
                        $result['wallets'] = [];
                    }
                    
                    $result['diagnostics'][] = "✓ Wallets found: " . count($result['wallets']) . " currencies";
                    
                    // Логируем каждый баланс
                    foreach ($result['wallets'] as $wallet) {
                        $currency = $wallet['currency'] ?? 'N/A';
                        $balance = $wallet['balance'] ?? 0;
                        $result['diagnostics'][] = "  - Wallet $currency: balance=$balance";
                    }
                } catch (Exception $e) {
                    $result['diagnostics'][] = "✗ Error querying wallets: " . $e->getMessage();
                }
                
                // 4. Получаем последние транзакции
                try {
                    // Используем RPC функцию get_transactions
                    if (method_exists($supabase, 'getTransactions')) {
                        $transactionsResult = $supabase->getTransactions($result['supabase_uuid'], null, 20, 0);
                        
                        if (is_array($transactionsResult)) {
                            // Обрабатываем различные форматы ответа
                            if (isset($transactionsResult[0]) && is_array($transactionsResult[0])) {
                                // Если это массив с одним элементом, который содержит транзакции
                                if (isset($transactionsResult[0]['transactions'])) {
                                    $result['transactions'] = $transactionsResult[0]['transactions'];
                                } else {
                                    $result['transactions'] = $transactionsResult[0];
                                }
                            } elseif (isset($transactionsResult['transactions'])) {
                                $result['transactions'] = $transactionsResult['transactions'];
                            } else {
                                $result['transactions'] = $transactionsResult;
                            }
                            
                            // Если это массив транзакций
                            if (isset($result['transactions']['transactions']) && is_array($result['transactions']['transactions'])) {
                                $result['transactions'] = $result['transactions']['transactions'];
                            }
                            
                            $txCount = is_array($result['transactions']) ? count($result['transactions']) : 0;
                            $result['diagnostics'][] = "✓ Transactions found (RPC): $txCount";
                            
                            // Логируем последние 5 транзакций
                            if (is_array($result['transactions']) && count($result['transactions']) > 0) {
                                $recentTransactions = array_slice($result['transactions'], 0, 5);
                                foreach ($recentTransactions as $tx) {
                                    $txType = $tx['type'] ?? 'N/A';
                                    $txAmount = $tx['amount'] ?? 0;
                                    $txCurrency = $tx['currency'] ?? 'N/A';
                                    $txDate = $tx['created_at'] ?? 'N/A';
                                    $result['diagnostics'][] = "  - Transaction: type=$txType, amount=$txAmount $txCurrency, date=$txDate";
                                }
                            }
                        } else {
                            $result['transactions'] = [];
                            $result['diagnostics'][] = "✗ No transactions found or invalid response format";
                        }
                    } else {
                        // Прямой запрос к таблице transactions
                        $transactionsResult = $supabase->select('transactions', '*', ['user_id' => $result['supabase_uuid']], 20, 0);
                        
                        if (is_array($transactionsResult)) {
                            $result['transactions'] = $transactionsResult;
                            $result['diagnostics'][] = "✓ Transactions found (direct query): " . count($result['transactions']);
                            
                            // Логируем последние 5 транзакций
                            if (count($result['transactions']) > 0) {
                                $recentTransactions = array_slice($result['transactions'], 0, 5);
                                foreach ($recentTransactions as $tx) {
                                    $txType = $tx['type'] ?? 'N/A';
                                    $txAmount = $tx['amount'] ?? 0;
                                    $txCurrency = $tx['currency'] ?? 'N/A';
                                    $txDate = $tx['created_at'] ?? 'N/A';
                                    $result['diagnostics'][] = "  - Transaction: type=$txType, amount=$txAmount $txCurrency, date=$txDate";
                                }
                            }
                        } else {
                            $result['transactions'] = [];
                            $result['diagnostics'][] = "✗ No transactions found";
                        }
                    }
                } catch (Exception $e) {
                    $result['diagnostics'][] = "✗ Error querying transactions: " . $e->getMessage();
                    $result['transactions_error'] = $e->getMessage();
                }
                
                // 5. Получаем балансы из user_balances (для совместимости)
                try {
                    $userBalancesResult = $supabase->select('user_balances', '*', ['user_id' => $result['supabase_uuid']]);
                    
                    if (is_array($userBalancesResult)) {
                        $result['user_balances'] = $userBalancesResult;
                        $result['diagnostics'][] = "✓ User balances found: " . count($result['user_balances']) . " currencies";
                        
                        foreach ($result['user_balances'] as $ub) {
                            $currency = $ub['currency'] ?? 'N/A';
                            $available = $ub['available_balance'] ?? 0;
                            $locked = $ub['locked_balance'] ?? 0;
                            $result['diagnostics'][] = "  - User balance $currency: available=$available, locked=$locked";
                        }
                    } else {
                        $result['user_balances'] = [];
                        $result['diagnostics'][] = "✗ No user_balances found";
                    }
                } catch (Exception $e) {
                    $result['diagnostics'][] = "✗ Error querying user_balances: " . $e->getMessage();
                }
                
                // 6. Тестируем RPC get_wallet_balance
                try {
                    if (method_exists($supabase, 'getWalletBalance')) {
                        $rpcResult = $supabase->getWalletBalance($result['supabase_uuid'], 'USD');
                        
                        $result['rpc_test'] = [
                            'function' => 'get_wallet_balance',
                            'result' => $rpcResult,
                            'success' => (is_array($rpcResult) && isset($rpcResult['success']) && $rpcResult['success'] === true) || 
                                        (is_array($rpcResult) && isset($rpcResult[0]['success']) && $rpcResult[0]['success'] === true)
                        ];
                        
                        $result['diagnostics'][] = "✓ RPC get_wallet_balance test: " . ($result['rpc_test']['success'] ? 'SUCCESS' : 'FAILED');
                    } else {
                        $result['diagnostics'][] = "✗ getWalletBalance method not available";
                    }
                } catch (Exception $e) {
                    $result['diagnostics'][] = "✗ Error testing RPC: " . $e->getMessage();
                }
            } else {
                $result['diagnostics'][] = "✗ Supabase UUID not found in user record";
            }
        } else {
            $result['diagnostics'][] = "✗ Supabase user not found";
            
            // Пытаемся найти в auth.users
            try {
                if (method_exists($supabase, 'findAuthUserByEmail')) {
                    $authUserId = $supabase->findAuthUserByEmail($email);
                    if ($authUserId) {
                        $result['supabase_uuid'] = $authUserId;
                        $result['diagnostics'][] = "✓ Found in auth.users: UUID=$authUserId";
                    } else {
                        $result['diagnostics'][] = "✗ User not found in auth.users either";
                    }
                }
            } catch (Exception $e) {
                $result['diagnostics'][] = "✗ Error searching in auth.users: " . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $result['diagnostics'][] = "✗ Error querying Supabase: " . $e->getMessage();
    }
    
    // Выводим результат
    outputJsonSuccess($result);
    
} catch (Exception $e) {
    outputJsonError('Unexpected error: ' . $e->getMessage(), 500, [
        'trace' => $e->getTraceAsString()
    ]);
} catch (Throwable $e) {
    outputJsonError('Fatal error: ' . $e->getMessage(), 500, [
        'trace' => $e->getTraceAsString()
    ]);
}
