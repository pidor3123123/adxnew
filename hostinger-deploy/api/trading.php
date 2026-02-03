<?php
/**
 * ADX Finance - API торговли
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
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/supabase.php';

// Получение пользователя из токена
function getAuthUser(): ?array {
    $token = getAuthorizationToken();
    if (!$token) return null;
    return getUserByToken($token);
}

// Получение balance_available и balance_locked пользователя
function getUserBalances(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT balance_available, balance_locked FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return [
        'available' => isset($row['balance_available']) ? (float)$row['balance_available'] : 0,
        'locked' => isset($row['balance_locked']) ? (float)$row['balance_locked'] : 0
    ];
}

// Обновление balance_available и balance_locked пользователя
function updateUserBalances(int $userId, float $availableDelta, float $lockedDelta): bool {
    $db = getDB();
    $stmt = $db->prepare('
        UPDATE users 
        SET balance_available = balance_available + ?,
            balance_locked = balance_locked + ?
        WHERE id = ?
    ');
    return $stmt->execute([$availableDelta, $lockedDelta, $userId]);
}

// Получение ID актива
function getAssetId(string $symbol): ?int {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM assets WHERE symbol = ?');
    $stmt->execute([strtoupper($symbol)]);
    $result = $stmt->fetch();
    return $result ? (int)$result['id'] : null;
}

// Симуляция получения рыночной цены
function getMarketPrice(string $symbol): float {
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
    
    // Добавляем небольшую случайность
    $basePrice = $prices[strtoupper($symbol)] ?? 100.00;
    return $basePrice * (1 + (rand(-100, 100) / 10000));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

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
            $amountUsd = (float)($data['amount_usd'] ?? $data['amount'] ?? 0);
            
            if (!$symbol) {
                throw new Exception('Symbol required', 400);
            }
            
            if (!in_array($side, ['buy', 'sell'])) {
                throw new Exception('Invalid side (buy/sell)', 400);
            }
            
            if ($amountUsd <= 0) {
                throw new Exception('Invalid amount', 400);
            }
            
            $assetId = getAssetId($symbol);
            if (!$assetId) {
                throw new Exception('Unknown asset', 400);
            }
            
            $entryPrice = getMarketPrice($symbol);
            
            // Проверяем balance_available
            $balances = getUserBalances($user['id']);
            if ($balances['available'] < $amountUsd) {
                throw new Exception('Insufficient funds', 400);
            }
            
            $db = getDB();
            $db->beginTransaction();
            
            try {
                // 1. balance_available -= amount_usd, balance_locked += amount_usd
                if (!updateUserBalances($user['id'], -$amountUsd, $amountUsd)) {
                    throw new Exception('Failed to update balance', 500);
                }
                
                // 2. Создаём Order (status OPEN)
                $quantity = $amountUsd / $entryPrice;
                $stmt = $db->prepare('
                    INSERT INTO orders (user_id, asset_id, type, side, status, quantity, amount_usd, entry_price, filled_quantity, price, average_price, total, fee, filled_at)
                    VALUES (?, ?, "market", ?, "open", ?, ?, ?, ?, ?, ?, ?, 0, NOW())
                ');
                $stmt->execute([
                    $user['id'],
                    $assetId,
                    $side,
                    $quantity,
                    $amountUsd,
                    $entryPrice,
                    $quantity,
                    $entryPrice,
                    $entryPrice,
                    $amountUsd
                ]);
                
                $orderId = (int) $db->lastInsertId();
                
                // 3. Создаём Transaction TRADE_OPEN
                $stmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, status, order_id, description, completed_at)
                    VALUES (?, "TRADE_OPEN", "USD", ?, "completed", ?, ?, NOW())
                ');
                $stmt->execute([
                    $user['id'],
                    -$amountUsd,
                    $orderId,
                    ($side === 'buy' ? 'Buy' : 'Sell') . " $amountUsd USD {$symbol} @ {$entryPrice}"
                ]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'order' => [
                        'id' => $orderId,
                        'symbol' => $symbol,
                        'side' => $side,
                        'amount_usd' => $amountUsd,
                        'entry_price' => $entryPrice,
                        'quantity' => $quantity,
                        'status' => 'open'
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
                // Возвращаем зарезервированные средства (balance_locked -> balance_available)
                $amountUsd = (float)($order['amount_usd'] ?? ($order['quantity'] * ($order['price'] ?? $order['entry_price'] ?? 0)));
                if ($amountUsd <= 0 && isset($order['quantity'], $order['price'])) {
                    $amountUsd = (float)$order['quantity'] * (float)$order['price'];
                }
                updateUserBalances($user['id'], $amountUsd, -$amountUsd);
                
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
                
                // Проверяем TP/SL
                $shouldClose = false;
                $closeReason = '';
                
                if ($order['side'] === 'buy') {
                    // Для buy: TP срабатывает если цена >= TP, SL если цена <= SL
                    if ($order['take_profit'] && $currentPrice >= $order['take_profit']) {
                        $shouldClose = true;
                        $closeReason = 'Take Profit';
                    } elseif ($order['stop_loss'] && $currentPrice <= $order['stop_loss']) {
                        $shouldClose = true;
                        $closeReason = 'Stop Loss';
                    }
                } else {
                    // Для sell: TP срабатывает если цена <= TP, SL если цена >= SL
                    if ($order['take_profit'] && $currentPrice <= $order['take_profit']) {
                        $shouldClose = true;
                        $closeReason = 'Take Profit';
                    } elseif ($order['stop_loss'] && $currentPrice >= $order['stop_loss']) {
                        $shouldClose = true;
                        $closeReason = 'Stop Loss';
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
            // Получаем открытые позиции - ордера со статусом open (новая модель)
            $stmt = $db->prepare('
                SELECT 
                    o.*, 
                    a.symbol, 
                    a.name as asset_name
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.user_id = ? 
                AND o.status = "open"
                ORDER BY o.created_at DESC
            ');
            $stmt->execute([$user['id']]);
            $openPositions = [];
            foreach ($stmt->fetchAll() as $order) {
                $amountUsd = (float)($order['amount_usd'] ?? ($order['quantity'] * ($order['entry_price'] ?? $order['price'] ?? 1)));
                $entryPrice = (float)($order['entry_price'] ?? $order['price'] ?? $order['average_price'] ?? 0);
                if ($entryPrice <= 0) $entryPrice = (float)$order['price'];
                
                $currentPrice = getMarketPrice($order['symbol']);
                $order['amount_usd'] = $amountUsd;
                $order['entry_price'] = $entryPrice;
                $order['current_price'] = $currentPrice;
                
                if ($order['side'] === 'buy') {
                    $order['unrealized_pnl'] = ($currentPrice - $entryPrice) / $entryPrice * $amountUsd;
                } else {
                    $order['unrealized_pnl'] = ($entryPrice - $currentPrice) / $entryPrice * $amountUsd;
                }
                $order['unrealized_pnl_percent'] = $entryPrice > 0 
                    ? (($order['unrealized_pnl'] / $amountUsd) * 100) 
                    : 0;
                $order['remaining_quantity'] = (float)$order['quantity'];
                $openPositions[] = $order;
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
            
            // Получаем открытую позицию (status = open)
            $stmt = $db->prepare('
                SELECT o.*, a.symbol
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.id = ? AND o.user_id = ? AND o.status = "open"
            ');
            $stmt->execute([$orderId, $user['id']]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception('Position not found', 404);
            }
            
            $amountUsd = (float)($order['amount_usd'] ?? ($order['quantity'] * ($order['entry_price'] ?? $order['price'] ?? 1)));
            $entryPrice = (float)($order['entry_price'] ?? $order['price'] ?? $order['average_price'] ?? 0);
            if ($entryPrice <= 0) $entryPrice = (float)$order['price'];
            
            $currentPrice = getMarketPrice($order['symbol']);
            
            // Расчёт PnL
            if ($order['side'] === 'buy') {
                $pnl = ($currentPrice - $entryPrice) / $entryPrice * $amountUsd;
            } else {
                $pnl = ($entryPrice - $currentPrice) / $entryPrice * $amountUsd;
            }
            
            $db->beginTransaction();
            
            try {
                // balance_locked -= amount_usd, balance_available += amount_usd + pnl
                if (!updateUserBalances($user['id'], $amountUsd + $pnl, -$amountUsd)) {
                    throw new Exception('Failed to update balance', 500);
                }
                
                // Обновляем Order: status=CLOSED, profit_loss, closed_at
                $stmt = $db->prepare('
                    UPDATE orders SET status = "filled", profit_loss = ?, closed_at = NOW() WHERE id = ?
                ');
                $stmt->execute([$pnl, $orderId]);
                
                // Создаем Transaction TRADE_CLOSE
                $transStmt = $db->prepare('
                    INSERT INTO transactions (user_id, type, currency, amount, status, order_id, description, completed_at)
                    VALUES (?, "TRADE_CLOSE", "USD", ?, "completed", ?, ?, NOW())
                ');
                $transStmt->execute([
                    $user['id'],
                    $pnl,
                    $orderId,
                    "Close " . $order['symbol'] . ": PnL " . ($pnl >= 0 ? '+' : '') . number_format($pnl, 2) . " USD"
                ]);
                
                $db->commit();
                
                // Синхронизация баланса USD с Supabase (в фоне, после commit)
                try {
                    $syncCurrencies = ['USD'];
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
                    'order_id' => $orderId,
                    'profit_loss' => $pnl
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
