<?php
/**
 * ADX Finance - API портфеля
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Получение рыночных цен
function getMarketPrices(): array {
    return [
        'USD' => ['price' => 1.0, 'change' => 0],
        'BTC' => ['price' => 43250.00, 'change' => 2.45],
        'ETH' => ['price' => 2285.50, 'change' => 1.82],
        'BNB' => ['price' => 312.40, 'change' => -0.54],
        'XRP' => ['price' => 0.62, 'change' => 3.21],
        'SOL' => ['price' => 98.75, 'change' => 5.67],
        'ADA' => ['price' => 0.58, 'change' => -1.23],
        'DOGE' => ['price' => 0.082, 'change' => 1.45],
        'DOT' => ['price' => 7.85, 'change' => -2.10],
        'MATIC' => ['price' => 0.92, 'change' => 4.32],
        'LTC' => ['price' => 72.30, 'change' => 0.87],
    ];
}

$action = $_GET['action'] ?? '';

try {
    $user = getAuthUser();
    
    if (!$user) {
        throw new Exception('Unauthorized', 401);
    }
    
    $db = getDB();
    
    switch ($action) {
        case 'summary':
            // Получаем балансы
            $stmt = $db->prepare('SELECT currency, available, reserved FROM balances WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $balances = $stmt->fetchAll();
            
            $prices = getMarketPrices();
            $portfolio = [];
            $totalValue = 0;
            $totalChange = 0;
            
            foreach ($balances as $balance) {
                $currency = $balance['currency'];
                $amount = (float)$balance['available'] + (float)$balance['reserved'];
                
                if ($amount <= 0) continue;
                
                $priceData = $prices[$currency] ?? ['price' => 0, 'change' => 0];
                $value = $amount * $priceData['price'];
                
                $portfolio[] = [
                    'currency' => $currency,
                    'amount' => $amount,
                    'available' => (float)$balance['available'],
                    'reserved' => (float)$balance['reserved'],
                    'price' => $priceData['price'],
                    'value' => $value,
                    'change' => $priceData['change']
                ];
                
                $totalValue += $value;
            }
            
            // Рассчитываем проценты распределения
            foreach ($portfolio as &$item) {
                $item['percentage'] = $totalValue > 0 ? ($item['value'] / $totalValue) * 100 : 0;
            }
            
            // Сортируем по стоимости
            usort($portfolio, function($a, $b) {
                return $b['value'] <=> $a['value'];
            });
            
            // Получаем статистику сделок
            $stmt = $db->prepare('
                SELECT 
                    COUNT(*) as total_trades,
                    SUM(CASE WHEN side = "buy" THEN total ELSE 0 END) as total_bought,
                    SUM(CASE WHEN side = "sell" THEN total ELSE 0 END) as total_sold,
                    SUM(fee) as total_fees
                FROM orders 
                WHERE user_id = ? AND status = "filled"
            ');
            $stmt->execute([$user['id']]);
            $stats = $stmt->fetch();
            
            setNoCacheHeaders();
            echo json_encode([
                'success' => true,
                'portfolio' => $portfolio,
                'total_value' => $totalValue,
                'stats' => [
                    'total_trades' => (int)$stats['total_trades'],
                    'total_bought' => (float)$stats['total_bought'],
                    'total_sold' => (float)$stats['total_sold'],
                    'total_fees' => (float)$stats['total_fees'],
                    'profit_loss' => (float)$stats['total_sold'] - (float)$stats['total_bought']
                ]
            ]);
            break;
            
        case 'history':
            $period = $_GET['period'] ?? '30d'; // 7d, 30d, 90d, 1y, all
            
            // Определяем дату начала
            switch ($period) {
                case '7d':
                    $startDate = date('Y-m-d', strtotime('-7 days'));
                    break;
                case '30d':
                    $startDate = date('Y-m-d', strtotime('-30 days'));
                    break;
                case '90d':
                    $startDate = date('Y-m-d', strtotime('-90 days'));
                    break;
                case '1y':
                    $startDate = date('Y-m-d', strtotime('-1 year'));
                    break;
                default:
                    $startDate = '2000-01-01';
            }
            
            // Получаем историю транзакций для построения графика баланса
            $stmt = $db->prepare('
                SELECT DATE(created_at) as date, SUM(amount) as daily_change
                FROM transactions
                WHERE user_id = ? AND created_at >= ? AND status = "completed"
                GROUP BY DATE(created_at)
                ORDER BY date
            ');
            $stmt->execute([$user['id'], $startDate]);
            $history = $stmt->fetchAll();
            
            // Генерируем данные для графика (упрощённо - симуляция)
            $chartData = [];
            $currentValue = 10000; // Начальный депозит
            
            $days = (new DateTime($startDate))->diff(new DateTime())->days;
            for ($i = 0; $i <= $days; $i++) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $change = (rand(-300, 500) / 100);
                $currentValue = max(0, $currentValue * (1 + $change / 100));
                
                $chartData[] = [
                    'date' => $date,
                    'value' => round($currentValue, 2)
                ];
            }
            
            // Сортируем по дате
            usort($chartData, function($a, $b) {
                return strtotime($a['date']) <=> strtotime($b['date']);
            });
            
            echo json_encode([
                'success' => true,
                'history' => $chartData
            ]);
            break;
            
        case 'performance':
            // Получаем статистику производительности
            $stmt = $db->prepare('
                SELECT 
                    a.symbol,
                    SUM(CASE WHEN o.side = "buy" THEN o.filled_quantity ELSE 0 END) as bought,
                    SUM(CASE WHEN o.side = "sell" THEN o.filled_quantity ELSE 0 END) as sold,
                    SUM(CASE WHEN o.side = "buy" THEN o.total ELSE -o.total END) as net_cost,
                    AVG(CASE WHEN o.side = "buy" THEN o.average_price END) as avg_buy_price,
                    AVG(CASE WHEN o.side = "sell" THEN o.average_price END) as avg_sell_price
                FROM orders o
                JOIN assets a ON a.id = o.asset_id
                WHERE o.user_id = ? AND o.status = "filled"
                GROUP BY a.symbol
            ');
            $stmt->execute([$user['id']]);
            $performance = $stmt->fetchAll();
            
            $prices = getMarketPrices();
            
            foreach ($performance as &$item) {
                $currentPrice = $prices[$item['symbol']]['price'] ?? 0;
                $holding = (float)$item['bought'] - (float)$item['sold'];
                $currentValue = $holding * $currentPrice;
                $invested = (float)$item['net_cost'];
                
                $item['holding'] = $holding;
                $item['current_price'] = $currentPrice;
                $item['current_value'] = $currentValue;
                $item['profit_loss'] = $currentValue - $invested;
                $item['profit_loss_percent'] = $invested != 0 ? (($currentValue - $invested) / abs($invested)) * 100 : 0;
            }
            
            echo json_encode([
                'success' => true,
                'performance' => $performance
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
