<?php
/**
 * ADX Finance - API кошелька
 */

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
    $user = getAuthUser();
    
    switch ($action) {
        case 'balances':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
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
            
            // Добавляем USD эквивалент
            $prices = getUsdPrices();
            $totalUsd = 0;
            
            foreach ($balances as &$balance) {
                $price = $prices[$balance['currency']] ?? 0;
                $balance['usd_value'] = (float)$balance['available'] * $price;
                $totalUsd += $balance['usd_value'];
            }
            
            echo json_encode([
                'success' => true,
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
