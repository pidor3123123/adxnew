<?php
/**
 * ADX Finance - API торговли
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

/**
 * Установка заголовков для предотвращения кеширования
 */
function setNoCacheHeaders(): void {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/supabase.php';

// Получение пользователя из токена
function getAuthUser(): ?array {
    $token = getAuthorizationToken();
    if (!$token) return null;
    return getUserByToken($token);
}

// Получение баланса пользователя
function getBalance(int $userId, string $currency): float {
    $db = getDB();
    $stmt = $db->prepare('SELECT available FROM balances WHERE user_id = ? AND currency = ?');
    $stmt->execute([$userId, $currency]);
    $result = $stmt->fetch();
    return $result ? (float)$result['available'] : 0.0;
}

// Обновление баланса
function updateBalance(int $userId, string $currency, float $amount, bool $add = true, bool $skipSync = false): bool {
    $db = getDB();
    
    // Проверяем существование записи
    $stmt = $db->prepare('SELECT id, available, reserved FROM balances WHERE user_id = ? AND currency = ?');
    $stmt->execute([$userId, $currency]);
    $balance = $stmt->fetch();
    
    if ($balance) {
        $newAmount = $add ? (float)$balance['available'] + $amount : (float)$balance['available'] - $amount;
        if ($newAmount < 0) return false;
        
        $stmt = $db->prepare('UPDATE balances SET available = ? WHERE id = ?');
        $result = $stmt->execute([$newAmount, $balance['id']]);
        
        // Синхронизация с Supabase (в фоне, не блокирует основную логику)
        // Пропускаем синхронизацию, если $skipSync = true (для случаев, когда синхронизация будет выполнена позже)
        if ($result && !$skipSync) {
            try {
                require_once __DIR__ . '/sync.php';
                syncBalanceToSupabase($userId, $currency);
            } catch (Exception $e) {
                // Логируем ошибку, но не прерываем выполнение
                error_log("Supabase balance sync error for user $userId, currency $currency: " . $e->getMessage());
            }
        }
        
        return $result;
    } else {
        if (!$add || $amount < 0) return false;
        
        $stmt = $db->prepare('INSERT INTO balances (user_id, currency, available) VALUES (?, ?, ?)');
        $result = $stmt->execute([$userId, $currency, $amount]);
        
        // Синхронизация с Supabase (в фоне, не блокирует основную логику)
        // Пропускаем синхронизацию, если $skipSync = true (для случаев, когда синхронизация будет выполнена позже)
        if ($result && !$skipSync) {
            try {
                require_once __DIR__ . '/sync.php';
                syncBalanceToSupabase($userId, $currency);
            } catch (Exception $e) {
                // Логируем ошибку, но не прерываем выполнение
                error_log("Supabase balance sync error for user $userId, currency $currency: " . $e->getMessage());
            }
        }
        
        return $result;
    }
}

// Получение ID актива
function getAssetId(string $symbol): ?int {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM assets WHERE symbol = ?');
    $stmt->execute([strtoupper($symbol)]);
    $result = $stmt->fetch();
    return $result ? (int)$result['id'] : null;
}

// Получение рыночной цены из реального API
function getMarketPrice(string $symbol): float {
    $symbolUpper = strtoupper($symbol);
    
    // Для криптовалют используем CoinGecko API
    if (in_array($symbolUpper, ['BTC', 'ETH', 'BNB', 'XRP', 'SOL', 'ADA', 'DOGE', 'DOT', 'MATIC', 'LTC'])) {
        try {
            $coinIds = [
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
            
            $coinId = $coinIds[$symbolUpper] ?? null;
            if ($coinId) {
                $url = "https://api.coingecko.com/api/v3/simple/price?ids={$coinId}&vs_currencies=usd";
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'header' => 'Accept: application/json'
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if ($data && isset($data[$coinId]['usd'])) {
                        return (float)$data[$coinId]['usd'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching price for {$symbolUpper} from CoinGecko: " . $e->getMessage());
        }
    }
    
    // Fallback: моковые цены (используются только если API недоступен или для не-криптовалют)
    $prices = [
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
        // Акции
        'AAPL' => 178.52,
        'GOOGL' => 141.80,
        'MSFT' => 378.91,
        'AMZN' => 155.34,
        'TSLA' => 248.50,
        // Форекс (относительно USD)
        'EURUSD' => 1.0872,
        'GBPUSD' => 1.2698,
        // Индексы
        'SPX' => 4500.00,
        'NDX' => 15000.00,
        'DJI' => 35000.00,
        'FTSE' => 7500.00,
        'DAX' => 16000.00,
    ];
    
    // Для криптовалют не добавляем случайность (используем реальную цену)
    // Для других активов можно добавить небольшую случайность для симуляции
    $basePrice = $prices[$symbolUpper] ?? 100.00;
    
    // Для не-криптовалют добавляем небольшую случайность (симуляция)
    if (!in_array($symbolUpper, ['BTC', 'ETH', 'BNB', 'XRP', 'SOL', 'ADA', 'DOGE', 'DOT', 'MATIC', 'LTC'])) {
        return $basePrice * (1 + (rand(-100, 100) / 10000));
    }
    
    return $basePrice;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

error_log("Trading API called with action: " . $action . ", method: " . $method);

try {
    $user = getAuthUser();
    
    switch ($action) {
        case 'create':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Invalid JSON data', 400);
            }
            
            $symbol = strtoupper(trim($data['symbol'] ?? ''));
            $side = strtolower(trim($data['side'] ?? ''));
            $type = strtolower(trim($data['type'] ?? 'market'));
            $quantity = (float)($data['quantity'] ?? 0);
            $limitPrice = isset($data['price']) ? (float)$data['price'] : null;
            $currentPrice = isset($data['current_price']) && $data['current_price'] ? (float)$data['current_price'] : null;
            $takeProfit = isset($data['take_profit']) && $data['take_profit'] ? (float)$data['take_profit'] : null;
            $stopLoss = isset($data['stop_loss']) && $data['stop_loss'] ? (float)$data['stop_loss'] : null;
            $tradeDuration = isset($data['trade_duration']) && $data['trade_duration'] ? (int)$data['trade_duration'] : null; // Длительность в секундах для быстрой торговли
            
            // Валидация
            if (!$symbol) {
                throw new Exception('Symbol required', 400);
            }
            
            if (!in_array($side, ['buy', 'sell'])) {
                throw new Exception('Invalid side (buy/sell)', 400);
            }
            
            if (!in_array($type, ['market', 'limit'])) {
                throw new Exception('Invalid order type', 400);
            }
            
            if ($quantity <= 0) {
                throw new Exception('Invalid quantity', 400);
            }
            
            if ($type === 'limit' && (!$limitPrice || $limitPrice <= 0)) {
                throw new Exception('Limit price required for limit orders', 400);
            }
            
            $assetId = getAssetId($symbol);
            if (!$assetId) {
                throw new Exception('Unknown asset', 400);
            }
            
            // Получаем рыночную цену для расчетов
            $marketPrice = getMarketPrice($symbol);
            $price = $type === 'market' ? $marketPrice : $limitPrice;
            $total = $quantity * $price;
            $fee = $total * 0.001; // 0.1% комиссия
            
            // Валидация TP/SL
            // Используем цену от клиента для валидации, если она предоставлена
            // Иначе используем расчетную цену (рыночную или лимитную)
            $validationPrice = $currentPrice ?? $price;
            $tolerance = $validationPrice * 0.0001; // 0.01% толерантность для учета различий в ценах
            
            if ($takeProfit !== null || $stopLoss !== null) {
                if ($side === 'buy') {
                    // Для buy: TP должен быть выше цены входа, SL - ниже
                    if ($takeProfit !== null && $takeProfit <= ($validationPrice - $tolerance)) {
                        throw new Exception(sprintf('Take Profit должен быть выше цены покупки (текущая цена: %.2f)', $validationPrice), 400);
                    }
                    if ($stopLoss !== null && $stopLoss >= ($validationPrice + $tolerance)) {
                        throw new Exception(sprintf('Stop Loss должен быть ниже цены покупки (текущая цена: %.2f)', $validationPrice), 400);
                    }
                } else {
                    // Для sell (короткие позиции): TP должен быть ниже цены входа, SL - выше
                    if ($takeProfit !== null && $takeProfit >= ($validationPrice + $tolerance)) {
                        throw new Exception(sprintf('Take Profit должен быть ниже цены продажи (текущая цена: %.2f)', $validationPrice), 400);
                    }
                    if ($stopLoss !== null && $stopLoss <= ($validationPrice - $tolerance)) {
                        throw new Exception(sprintf('Stop Loss должен быть выше цены продажи (текущая цена: %.2f)', $validationPrice), 400);
                    }
                }
            }
            
            $db = getDB();
            $db->beginTransaction();
            
            try {
                if ($side === 'buy') {
                    // Проверяем баланс USD
                    $usdBalance = getBalance($user['id'], 'USD');
                    $requiredAmount = $total + $fee;
                    
                    if ($usdBalance < $requiredAmount) {
                        throw new Exception('Insufficient USD balance', 400);
                    }
                    
                    // Списываем USD
                    updateBalance($user['id'], 'USD', $requiredAmount, false);
                    
                    // Начисляем актив
                    updateBalance($user['id'], $symbol, $quantity, true);
                    
                } else { // sell
                    // Проверяем баланс актива
                    $assetBalance = getBalance($user['id'], $symbol);
                    
                    if ($assetBalance < $quantity) {
                        throw new Exception('Insufficient ' . $symbol . ' balance', 400);
                    }
                    
                    // Списываем актив
                    updateBalance($user['id'], $symbol, $quantity, false);
                    
                    // Начисляем USD (минус комиссия)
                    updateBalance($user['id'], 'USD', $total - $fee, true);
                }
                
                // Для быстрой торговли сохраняем duration в stop_price (временное решение)
                // В будущем нужно добавить отдельное поле trade_duration
                $stopPrice = null;
                if ($tradeDuration !== null && $tradeDuration > 0) {
                    $stopPrice = $tradeDuration; // Сохраняем duration в секундах в stop_price
                }
                
                // Создаём запись ордера
                $stmt = $db->prepare('
                    INSERT INTO orders (user_id, asset_id, type, side, status, quantity, filled_quantity, price, stop_price, average_price, total, fee, take_profit, stop_loss, filled_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                
                $stmt->execute([
                    $user['id'],
                    $assetId,
                    $type,
                    $side,
                    'filled', // Для market ордеров сразу filled
                    $quantity,
                    $quantity,
                    $price,
                    $stopPrice, // Используем для хранения duration для быстрой торговли
                    $price,
                    $total,
                    $fee,
                    $takeProfit,
                    $stopLoss
                ]);
                
                $orderId = (int) $db->lastInsertId();
                
                // Синхронизация балансов с Supabase (в фоне, не блокирует основную логику)
                // Выполняется после commit транзакции, чтобы ошибки синхронизации не влияли на сделку
                try {
                    $syncCurrencies = ['USD', $symbol];
                    foreach ($syncCurrencies as $syncCurrency) {
                        try {
                            require_once __DIR__ . '/sync.php';
                            syncBalanceToSupabase($user['id'], $syncCurrency);
                        } catch (Exception $syncError) {
                            // Логируем ошибку синхронизации, но не прерываем выполнение
                            error_log("Supabase balance sync error for user {$user['id']}, currency $syncCurrency: " . $syncError->getMessage());
                        }
                    }
                } catch (Exception $e) {
                    // Логируем общую ошибку синхронизации, но не прерываем выполнение
                    error_log('Supabase balance sync error (general): ' . $e->getMessage());
                }
                
                // Создаём запись транзакции
                $stmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, fee, status, order_id, description, completed_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                
                $stmt->execute([
                    $user['id'],
                    'trade',
                    $symbol,
                    $side === 'buy' ? $quantity : -$quantity,
                    $fee,
                    'completed',
                    $orderId,
                    ($side === 'buy' ? 'Покупка' : 'Продажа') . " {$quantity} {$symbol} по {$price}"
                ]);
                
                $db->commit();
                
                setNoCacheHeaders();
                echo json_encode([
                    'success' => true,
                    'order' => [
                        'id' => $orderId,
                        'symbol' => $symbol,
                        'side' => $side,
                        'type' => $type,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total' => $total,
                        'fee' => $fee,
                        'status' => 'filled',
                        'take_profit' => $takeProfit,
                        'stop_loss' => $stopLoss
                    ]
                ], JSON_NUMERIC_CHECK);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'open':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $db = getDB();
            $stmt = $db->prepare('
                SELECT o.*, a.symbol, a.name as asset_name
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.user_id = ? AND o.status IN ("pending", "open", "partially_filled")
                ORDER BY o.created_at DESC
            ');
            $stmt->execute([$user['id']]);
            $orders = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'orders' => $orders
            ], JSON_NUMERIC_CHECK);
            break;
            
        case 'history':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            
            $db = getDB();
            $stmt = $db->prepare('
                SELECT o.*, a.symbol, a.name as asset_name
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.user_id = ?
                ORDER BY o.created_at DESC
                LIMIT ?
            ');
            $stmt->execute([$user['id'], $limit]);
            $orders = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'orders' => $orders
            ], JSON_NUMERIC_CHECK);
            break;
            
        case 'cancel':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = (int)($data['orderId'] ?? 0);
            
            if (!$orderId) {
                throw new Exception('Order ID required', 400);
            }
            
            $db = getDB();
            
            // Получаем ордер
            $stmt = $db->prepare('
                SELECT o.*, a.symbol FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.id = ? AND o.user_id = ? AND o.status IN ("pending", "open")
            ');
            $stmt->execute([$orderId, $user['id']]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Order not found or cannot be cancelled', 404);
            }
            
            $db->beginTransaction();
            
            try {
                // Возвращаем зарезервированные средства
                $remainingQty = $order['quantity'] - $order['filled_quantity'];
                
                if ($order['side'] === 'buy') {
                    $returnAmount = $remainingQty * $order['price'];
                    updateBalance($user['id'], 'USD', $returnAmount, true);
                } else {
                    updateBalance($user['id'], $order['symbol'], $remainingQty, true);
                }
                
                // Обновляем статус ордера
                $stmt = $db->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
                $stmt->execute([$orderId]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order cancelled'
                ], JSON_NUMERIC_CHECK);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'check_tp_sl':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $db = getDB();
            $closedOrders = [];
            
            // Находим все заполненные ордера с TP/SL, которые еще активны
            // Активная позиция - это когда нет закрывающего противоположного ордера
            $stmt = $db->prepare('
                SELECT o.*, a.symbol
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.user_id = ? 
                AND o.status = "filled"
                AND (o.take_profit IS NOT NULL OR o.stop_loss IS NOT NULL)
                ORDER BY o.created_at DESC
            ');
            $stmt->execute([$user['id']]);
            $orders = $stmt->fetchAll();
            
            foreach ($orders as $order) {
                // Проверяем, закрыта ли позиция
                // Нужно проверить, есть ли противоположный ордер после этого
                $oppositeSide = $order['side'] === 'buy' ? 'sell' : 'buy';
                $checkStmt = $db->prepare('
                    SELECT SUM(filled_quantity) as closed_qty
                    FROM orders
                    WHERE user_id = ? 
                    AND asset_id = ?
                    AND side = ?
                    AND status = "filled"
                    AND created_at > ?
                ');
                $checkStmt->execute([$user['id'], $order['asset_id'], $oppositeSide, $order['created_at']]);
                $closed = $checkStmt->fetch();
                $closedQty = (float)($closed['closed_qty'] ?? 0);
                
                // Если позиция закрыта - пропускаем
                if ($closedQty >= $order['filled_quantity']) {
                    continue;
                }
                
                // Получаем текущую цену актива
                $currentPrice = getMarketPrice($order['symbol']);
                
                // Логирование для отладки
                error_log(sprintf(
                    "[TP/SL Check] Order ID: %d, Symbol: %s, Side: %s, Entry Price: %.2f, Current Price: %.2f, TP: %s, SL: %s",
                    $order['id'],
                    $order['symbol'],
                    $order['side'],
                    $order['price'],
                    $currentPrice,
                    $order['take_profit'] ? number_format($order['take_profit'], 2) : 'NULL',
                    $order['stop_loss'] ? number_format($order['stop_loss'], 2) : 'NULL'
                ));
                
                // Проверяем TP/SL
                $shouldClose = false;
                $closeReason = '';
                
                if ($order['side'] === 'buy') {
                    // Для buy: TP срабатывает если цена >= TP, SL если цена <= SL
                    if ($order['take_profit'] && $currentPrice >= $order['take_profit']) {
                        $shouldClose = true;
                        $closeReason = 'Take Profit';
                        error_log(sprintf("[TP/SL Check] Order %d: TP triggered (Current: %.2f >= TP: %.2f)", $order['id'], $currentPrice, $order['take_profit']));
                    } elseif ($order['stop_loss'] && $currentPrice <= $order['stop_loss']) {
                        $shouldClose = true;
                        $closeReason = 'Stop Loss';
                        error_log(sprintf("[TP/SL Check] Order %d: SL triggered (Current: %.2f <= SL: %.2f)", $order['id'], $currentPrice, $order['stop_loss']));
                    }
                } else {
                    // Для sell: TP срабатывает если цена <= TP, SL если цена >= SL
                    if ($order['take_profit'] && $currentPrice <= $order['take_profit']) {
                        $shouldClose = true;
                        $closeReason = 'Take Profit';
                        error_log(sprintf("[TP/SL Check] Order %d: TP triggered (Current: %.2f <= TP: %.2f)", $order['id'], $currentPrice, $order['take_profit']));
                    } elseif ($order['stop_loss'] && $currentPrice >= $order['stop_loss']) {
                        $shouldClose = true;
                        $closeReason = 'Stop Loss';
                        error_log(sprintf("[TP/SL Check] Order %d: SL triggered (Current: %.2f >= SL: %.2f)", $order['id'], $currentPrice, $order['stop_loss']));
                    }
                }
                
                if ($shouldClose) {
                    // Закрываем позицию - создаем противоположный ордер
                    $remainingQty = $order['filled_quantity'] - $closedQty;
                    $oppositeSide = $order['side'] === 'buy' ? 'sell' : 'buy';
                    
                    $db->beginTransaction();
                    
                    try {
                        if ($oppositeSide === 'sell') {
                            // Продаем актив
                            $assetBalance = getBalance($user['id'], $order['symbol']);
                            if ($assetBalance < $remainingQty) {
                                continue; // Недостаточно баланса
                            }
                            
                            $closeTotal = $remainingQty * $currentPrice;
                            $closeFee = $closeTotal * 0.001;
                            
                            // Списываем актив
                            updateBalance($user['id'], $order['symbol'], $remainingQty, false);
                            // Начисляем USD
                            updateBalance($user['id'], 'USD', $closeTotal - $closeFee, true);
                        } else {
                            // Покупаем актив
                            $closeTotal = $remainingQty * $currentPrice;
                            $closeFee = $closeTotal * 0.001;
                            $requiredAmount = $closeTotal + $closeFee;
                            
                            $usdBalance = getBalance($user['id'], 'USD');
                            if ($usdBalance < $requiredAmount) {
                                continue; // Недостаточно баланса
                            }
                            
                            // Списываем USD
                            updateBalance($user['id'], 'USD', $requiredAmount, false);
                            // Начисляем актив
                            updateBalance($user['id'], $order['symbol'], $remainingQty, true);
                        }
                        
                        // Создаем закрывающий ордер
                        $closeStmt = $db->prepare('
                            INSERT INTO orders (user_id, asset_id, type, side, status, quantity, filled_quantity, price, average_price, total, fee, filled_at)
                            VALUES (?, ?, "market", ?, "filled", ?, ?, ?, ?, ?, ?, NOW())
                        ');
                        $closeTotal = $remainingQty * $currentPrice;
                        $closeFee = $closeTotal * 0.001;
                        
                        $closeStmt->execute([
                            $user['id'],
                            $order['asset_id'],
                            $oppositeSide,
                            $remainingQty,
                            $remainingQty,
                            $currentPrice,
                            $currentPrice,
                            $closeTotal,
                            $closeFee
                        ]);
                        
                        $closeOrderId = (int) $db->lastInsertId();
                        
                        // Создаем транзакцию
                        $transStmt = $db->prepare('
                            INSERT INTO transactions (user_id, type, currency, amount, fee, status, order_id, description, completed_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ');
                        
                        $transStmt->execute([
                            $user['id'],
                            'trade',
                            $order['symbol'],
                            $oppositeSide === 'sell' ? -$remainingQty : $remainingQty,
                            $closeFee,
                            'completed',
                            $closeOrderId,
                            "Закрытие позиции ({$closeReason}): " . ($oppositeSide === 'sell' ? 'Продажа' : 'Покупка') . " {$remainingQty} {$order['symbol']} по {$currentPrice}"
                        ]);
                        
                        $db->commit();
                        
                        $closedOrders[] = [
                            'order_id' => $order['id'],
                            'close_order_id' => $closeOrderId,
                            'symbol' => $order['symbol'],
                            'quantity' => $remainingQty,
                            'price' => $currentPrice,
                            'reason' => $closeReason
                        ];
                        
                    } catch (Exception $e) {
                        $db->rollBack();
                        // Продолжаем проверку других ордеров
                        continue;
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'closed_orders' => $closedOrders
            ], JSON_NUMERIC_CHECK);
            break;
            
        case 'open_positions':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $db = getDB();
            // Получаем открытые позиции - это ордера со статусом filled, у которых есть остаток
            $stmt = $db->prepare('
                SELECT 
                    o.*, 
                    a.symbol, 
                    a.name as asset_name
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.user_id = ? 
                AND o.status = "filled"
                ORDER BY o.created_at DESC
            ');
            $stmt->execute([$user['id']]);
            $allOrders = $stmt->fetchAll();
            
            // Фильтруем открытые позиции (те, которые не закрыты полностью)
            $openPositions = [];
            foreach ($allOrders as $order) {
                $oppositeSide = $order['side'] === 'buy' ? 'sell' : 'buy';
                $checkStmt = $db->prepare('
                    SELECT SUM(filled_quantity) as closed_qty
                    FROM orders
                    WHERE user_id = ? 
                    AND asset_id = ?
                    AND side = ?
                    AND status = "filled"
                    AND created_at > ?
                ');
                $checkStmt->execute([$user['id'], $order['asset_id'], $oppositeSide, $order['created_at']]);
                $closed = $checkStmt->fetch();
                $closedQty = (float)($closed['closed_qty'] ?? 0);
                $remainingQty = (float)$order['filled_quantity'] - $closedQty;
                
                if ($remainingQty > 0) {
                    $currentPrice = getMarketPrice($order['symbol']);
                    // Используем average_price, если он установлен, иначе используем price
                    $entryPrice = (float)($order['average_price'] ?? $order['price'] ?? 0);
                    if ($entryPrice <= 0) {
                        $entryPrice = (float)$order['price'];
                    }
                    $order['average_price'] = $entryPrice; // Убеждаемся, что average_price установлен
                    $order['entry_price'] = $entryPrice; // Добавляем явную цену входа для фронтенда
                    $order['current_price'] = $currentPrice;
                    $order['remaining_quantity'] = $remainingQty;
                    
                    if ($order['side'] === 'buy') {
                        $order['unrealized_pnl'] = ($currentPrice - $entryPrice) * $remainingQty;
                        $order['unrealized_pnl_percent'] = $entryPrice > 0 ? (($currentPrice - $entryPrice) / $entryPrice) * 100 : 0;
                    } else {
                        $order['unrealized_pnl'] = ($entryPrice - $currentPrice) * $remainingQty;
                        $order['unrealized_pnl_percent'] = $entryPrice > 0 ? (($entryPrice - $currentPrice) / $entryPrice) * 100 : 0;
                    }
                    $openPositions[] = $order;
                }
            }
            
            echo json_encode([
                'success' => true,
                'positions' => $openPositions
            ], JSON_NUMERIC_CHECK);
            break;
            
        case 'close_position':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $orderId = (int)($data['order_id'] ?? 0);
            
            if (!$orderId) {
                throw new Exception('Order ID required', 400);
            }
            
            $db = getDB();
            
            // Получаем открытую позицию
            $stmt = $db->prepare('
                SELECT o.*, a.symbol
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.id = ? AND o.user_id = ? AND o.status = "filled"
            ');
            $stmt->execute([$orderId, $user['id']]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Position not found', 404);
            }
            
            // Проверяем, сколько осталось закрыть
            $oppositeSide = $order['side'] === 'buy' ? 'sell' : 'buy';
            $checkStmt = $db->prepare('
                SELECT SUM(filled_quantity) as closed_qty
                FROM orders
                WHERE user_id = ? 
                AND asset_id = ?
                AND side = ?
                AND status = "filled"
                AND created_at > ?
            ');
            $checkStmt->execute([$user['id'], $order['asset_id'], $oppositeSide, $order['created_at']]);
            $closed = $checkStmt->fetch();
            $closedQty = (float)($closed['closed_qty'] ?? 0);
            $remainingQty = (float)$order['filled_quantity'] - $closedQty;
            
            if ($remainingQty <= 0) {
                throw new Exception('Position already closed', 400);
            }
            
            $currentPrice = getMarketPrice($order['symbol']);
            $db->beginTransaction();
            
            try {
                if ($oppositeSide === 'sell') {
                    // Продаем актив
                    $assetBalance = getBalance($user['id'], $order['symbol']);
                    if ($assetBalance < $remainingQty) {
                        throw new Exception('Insufficient ' . $order['symbol'] . ' balance', 400);
                    }
                    
                    $closeTotal = $remainingQty * $currentPrice;
                    $closeFee = $closeTotal * 0.001;
                    
                    // Списываем актив (пропускаем синхронизацию, будет выполнена после commit)
                    updateBalance($user['id'], $order['symbol'], $remainingQty, false, true);
                    // Начисляем USD (пропускаем синхронизацию, будет выполнена после commit)
                    updateBalance($user['id'], 'USD', $closeTotal - $closeFee, true, true);
                } else {
                    // Покупаем актив (закрываем короткую позицию)
                    $closeTotal = $remainingQty * $currentPrice;
                    $closeFee = $closeTotal * 0.001;
                    $requiredAmount = $closeTotal + $closeFee;
                    
                    $usdBalance = getBalance($user['id'], 'USD');
                    if ($usdBalance < $requiredAmount) {
                        throw new Exception('Insufficient USD balance', 400);
                    }
                    
                    // Списываем USD (пропускаем синхронизацию, будет выполнена после commit)
                    updateBalance($user['id'], 'USD', $requiredAmount, false, true);
                    // Начисляем актив (пропускаем синхронизацию, будет выполнена после commit)
                    updateBalance($user['id'], $order['symbol'], $remainingQty, true, true);
                }
                
                // Создаем закрывающий ордер
                $closeStmt = $db->prepare('
                    INSERT INTO orders (user_id, asset_id, type, side, status, quantity, filled_quantity, price, average_price, total, fee, filled_at)
                    VALUES (?, ?, "market", ?, "filled", ?, ?, ?, ?, ?, ?, NOW())
                ');
                $closeTotal = $remainingQty * $currentPrice;
                $closeFee = $closeTotal * 0.001;
                
                $closeStmt->execute([
                    $user['id'],
                    $order['asset_id'],
                    $oppositeSide,
                    $remainingQty,
                    $remainingQty,
                    $currentPrice,
                    $currentPrice,
                    $closeTotal,
                    $closeFee
                ]);
                
                $closeOrderId = (int) $db->lastInsertId();
                
                // Создаем транзакцию
                $transStmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, fee, status, order_id, description, completed_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                
                $transStmt->execute([
                    $user['id'],
                    'trade',
                    $order['symbol'],
                    $oppositeSide === 'sell' ? -$remainingQty : $remainingQty,
                    $closeFee,
                    'completed',
                    $closeOrderId,
                    "Закрытие позиции вручную: " . ($oppositeSide === 'sell' ? 'Продажа' : 'Покупка') . " {$remainingQty} {$order['symbol']} по {$currentPrice}"
                ]);
                
                $db->commit();
                
                // Синхронизация балансов с Supabase (в фоне, после commit)
                // Используем буферизацию вывода, чтобы curl ответ не попал в основной JSON ответ
                try {
                    $syncCurrencies = ['USD', $order['symbol']];
                    foreach ($syncCurrencies as $syncCurrency) {
                        $syncData = [
                            'user_id' => $user['id'],
                            'currency' => $syncCurrency
                        ];
                        
                        // Начинаем буферизацию вывода для этого curl запроса
                        ob_start();
                        
                        $ch = curl_init('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/api/sync.php?action=balance');
                        curl_setopt_array($ch, [
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($syncData),
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_RETURNTRANSFER => true, // Получаем ответ в переменную, а не выводим
                            CURLOPT_TIMEOUT => 1,
                            CURLOPT_NOSIGNAL => 1,
                        ]);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        // Очищаем буфер вывода (curl ответ не должен попасть в основной JSON)
                        ob_end_clean();
                        
                        // Проверяем ответ, но не выводим его
                        if ($httpCode !== 200) {
                            error_log("Supabase balance sync failed for currency $syncCurrency: HTTP $httpCode");
                        } else {
                            $syncResult = json_decode($response, true);
                            if (!$syncResult || !($syncResult['success'] ?? false)) {
                                error_log("Supabase balance sync failed for currency $syncCurrency: " . ($syncResult['error'] ?? 'Unknown error'));
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Очищаем буфер в случае ошибки
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    error_log('Supabase balance sync error: ' . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Position closed',
                    'close_order_id' => $closeOrderId
                ], JSON_NUMERIC_CHECK);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'closed_positions':
            if (!$user) {
                throw new Exception('Unauthorized', 401);
            }
            
            $period = $_GET['period'] ?? 'all'; // week, month, quarter, year, all
            $db = getDB();
            
            // Определяем дату начала периода
            $startDate = null;
            switch ($period) {
                case 'week':
                    $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
                    break;
                case 'month':
                    $startDate = date('Y-m-d H:i:s', strtotime('-1 month'));
                    break;
                case 'quarter':
                    $startDate = date('Y-m-d H:i:s', strtotime('-3 months'));
                    break;
                case 'year':
                    $startDate = date('Y-m-d H:i:s', strtotime('-1 year'));
                    break;
                default:
                    $startDate = null;
            }
            
            // Получаем закрытые позиции (пары открывающих и закрывающих ордеров)
            // Используем подзапрос для правильного сопоставления открывающих и закрывающих ордеров
            $sql = '
                SELECT 
                    open_order.id as open_order_id,
                    a.symbol,
                    open_order.side as open_side,
                    open_order.average_price as entry_price,
                    open_order.filled_quantity as open_quantity,
                    open_order.created_at as open_time,
                    close_order.id as close_order_id,
                    close_order.average_price as exit_price,
                    close_order.filled_quantity as close_quantity,
                    close_order.created_at as close_time,
                    close_order.fee as close_fee,
                    CASE 
                        WHEN open_order.side = "buy" THEN 
                            (close_order.average_price - open_order.average_price) * close_order.filled_quantity
                        ELSE 
                            (open_order.average_price - close_order.average_price) * close_order.filled_quantity
                    END as pnl,
                    CASE 
                        WHEN open_order.side = "buy" AND open_order.average_price > 0 THEN 
                            ((close_order.average_price - open_order.average_price) / open_order.average_price) * 100
                        WHEN open_order.side = "sell" AND open_order.average_price > 0 THEN 
                            ((open_order.average_price - close_order.average_price) / open_order.average_price) * 100
                        ELSE 0
                    END as pnl_percent
                FROM orders open_order
                JOIN assets a ON a.id = open_order.asset_id
                JOIN orders close_order ON close_order.asset_id = open_order.asset_id
                    AND close_order.user_id = open_order.user_id
                    AND close_order.side != open_order.side
                    AND close_order.status = "filled"
                    AND close_order.created_at > open_order.created_at
                WHERE open_order.user_id = ?
                AND open_order.status = "filled"
                AND close_order.id = (
                    SELECT MIN(oc.id)
                    FROM orders oc
                    WHERE oc.asset_id = open_order.asset_id
                    AND oc.user_id = open_order.user_id
                    AND oc.side != open_order.side
                    AND oc.status = "filled"
                    AND oc.created_at > open_order.created_at
                )
            ';
            
            $params = [$user['id']];
            if ($startDate) {
                $sql .= ' AND close_order.created_at >= ?';
                $params[] = $startDate;
            }
            
            $sql .= ' ORDER BY close_order.created_at DESC LIMIT 100';
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $closedPositions = $stmt->fetchAll();
            
            // Вычисляем общую прибыль
            $totalPnl = 0;
            foreach ($closedPositions as $pos) {
                $totalPnl += (float)$pos['pnl'];
            }
            
            echo json_encode([
                'success' => true,
                'positions' => $closedPositions,
                'total_pnl' => $totalPnl,
                'period' => $period
            ], JSON_NUMERIC_CHECK);
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
    ], JSON_NUMERIC_CHECK);
}
