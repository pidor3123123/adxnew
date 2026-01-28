<?php
/**
 * ADX Finance - API рыночных данных
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
setCorsHeaders();
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Получение данных криптовалют через CoinGecko API
 */
function getCryptoPrices(): array {
    $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=bitcoin,ethereum,binancecoin,ripple,solana,cardano,dogecoin,polkadot,polygon-ecosystem-token,litecoin&order=market_cap_desc&sparkline=true&price_change_percentage=24h';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => 'Accept: application/json'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        // Возвращаем моковые данные если API недоступен
        return getMockCryptoPrices();
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        return getMockCryptoPrices();
    }
    
    return $data;
}

/**
 * Моковые данные криптовалют (используются только если API недоступен)
 */
function getMockCryptoPrices(): array {
    return [
        ['id' => 'bitcoin', 'symbol' => 'btc', 'name' => 'Bitcoin', 'current_price' => 43250.00, 'price_change_percentage_24h' => 2.45, 'market_cap' => 847000000000, 'total_volume' => 28000000000],
        ['id' => 'ethereum', 'symbol' => 'eth', 'name' => 'Ethereum', 'current_price' => 2285.50, 'price_change_percentage_24h' => 1.82, 'market_cap' => 274000000000, 'total_volume' => 15000000000],
        ['id' => 'binancecoin', 'symbol' => 'bnb', 'name' => 'BNB', 'current_price' => 312.40, 'price_change_percentage_24h' => -0.54, 'market_cap' => 48000000000, 'total_volume' => 890000000],
        ['id' => 'ripple', 'symbol' => 'xrp', 'name' => 'XRP', 'current_price' => 0.62, 'price_change_percentage_24h' => 3.21, 'market_cap' => 33000000000, 'total_volume' => 1200000000],
        ['id' => 'solana', 'symbol' => 'sol', 'name' => 'Solana', 'current_price' => 98.75, 'price_change_percentage_24h' => 5.67, 'market_cap' => 42000000000, 'total_volume' => 2100000000],
        ['id' => 'cardano', 'symbol' => 'ada', 'name' => 'Cardano', 'current_price' => 0.58, 'price_change_percentage_24h' => -1.23, 'market_cap' => 20000000000, 'total_volume' => 450000000],
        ['id' => 'dogecoin', 'symbol' => 'doge', 'name' => 'Dogecoin', 'current_price' => 0.082, 'price_change_percentage_24h' => 1.45, 'market_cap' => 11500000000, 'total_volume' => 380000000],
        ['id' => 'polkadot', 'symbol' => 'dot', 'name' => 'Polkadot', 'current_price' => 7.85, 'price_change_percentage_24h' => -2.10, 'market_cap' => 9800000000, 'total_volume' => 280000000],
        ['id' => 'polygon', 'symbol' => 'matic', 'name' => 'Polygon', 'current_price' => 0.92, 'price_change_percentage_24h' => 4.32, 'market_cap' => 8500000000, 'total_volume' => 520000000],
        ['id' => 'litecoin', 'symbol' => 'ltc', 'name' => 'Litecoin', 'current_price' => 72.30, 'price_change_percentage_24h' => 0.87, 'market_cap' => 5300000000, 'total_volume' => 340000000],
    ];
}

/**
 * Моковые данные акций
 */
function getStockPrices(): array {
    return [
        ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'price' => 178.52, 'change' => 1.24, 'changePercent' => 0.70],
        ['symbol' => 'GOOGL', 'name' => 'Alphabet Inc.', 'price' => 141.80, 'change' => -0.95, 'changePercent' => -0.67],
        ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation', 'price' => 378.91, 'change' => 4.52, 'changePercent' => 1.21],
        ['symbol' => 'AMZN', 'name' => 'Amazon.com Inc.', 'price' => 155.34, 'change' => 2.18, 'changePercent' => 1.42],
        ['symbol' => 'TSLA', 'name' => 'Tesla Inc.', 'price' => 248.50, 'change' => -5.30, 'changePercent' => -2.09],
        ['symbol' => 'META', 'name' => 'Meta Platforms Inc.', 'price' => 355.67, 'change' => 7.23, 'changePercent' => 2.08],
        ['symbol' => 'NVDA', 'name' => 'NVIDIA Corporation', 'price' => 495.22, 'change' => 12.45, 'changePercent' => 2.58],
        ['symbol' => 'JPM', 'name' => 'JPMorgan Chase & Co.', 'price' => 172.85, 'change' => 0.95, 'changePercent' => 0.55],
        ['symbol' => 'V', 'name' => 'Visa Inc.', 'price' => 258.30, 'change' => 1.67, 'changePercent' => 0.65],
        ['symbol' => 'JNJ', 'name' => 'Johnson & Johnson', 'price' => 156.42, 'change' => -0.28, 'changePercent' => -0.18],
    ];
}

/**
 * Моковые данные форекс
 */
function getForexRates(): array {
    return [
        ['symbol' => 'EURUSD', 'name' => 'EUR/USD', 'price' => 1.0872, 'change' => 0.0015, 'changePercent' => 0.14],
        ['symbol' => 'GBPUSD', 'name' => 'GBP/USD', 'price' => 1.2698, 'change' => -0.0023, 'changePercent' => -0.18],
        ['symbol' => 'USDJPY', 'name' => 'USD/JPY', 'price' => 148.52, 'change' => 0.45, 'changePercent' => 0.30],
        ['symbol' => 'USDCHF', 'name' => 'USD/CHF', 'price' => 0.8742, 'change' => 0.0012, 'changePercent' => 0.14],
        ['symbol' => 'AUDUSD', 'name' => 'AUD/USD', 'price' => 0.6578, 'change' => 0.0028, 'changePercent' => 0.43],
        ['symbol' => 'USDCAD', 'name' => 'USD/CAD', 'price' => 1.3485, 'change' => -0.0018, 'changePercent' => -0.13],
        ['symbol' => 'NZDUSD', 'name' => 'NZD/USD', 'price' => 0.6142, 'change' => 0.0035, 'changePercent' => 0.57],
        ['symbol' => 'EURGBP', 'name' => 'EUR/GBP', 'price' => 0.8561, 'change' => 0.0008, 'changePercent' => 0.09],
        ['symbol' => 'EURJPY', 'name' => 'EUR/JPY', 'price' => 161.42, 'change' => 0.62, 'changePercent' => 0.39],
        ['symbol' => 'GBPJPY', 'name' => 'GBP/JPY', 'price' => 188.58, 'change' => -0.35, 'changePercent' => -0.19],
    ];
}

/**
 * Моковые данные индексов
 */
function getIndicesPrices(): array {
    return [
        ['symbol' => 'SPX', 'name' => 'S&P 500', 'price' => 4500.00, 'change' => 12.50, 'changePercent' => 0.28],
        ['symbol' => 'NDX', 'name' => 'NASDAQ 100', 'price' => 15000.00, 'change' => 45.20, 'changePercent' => 0.30],
        ['symbol' => 'DJI', 'name' => 'Dow Jones', 'price' => 35000.00, 'change' => 85.30, 'changePercent' => 0.24],
        ['symbol' => 'FTSE', 'name' => 'FTSE 100', 'price' => 7500.00, 'change' => -15.40, 'changePercent' => -0.21],
        ['symbol' => 'DAX', 'name' => 'DAX', 'price' => 16000.00, 'change' => 32.10, 'changePercent' => 0.20],
    ];
}

/**
 * Генерация OHLCV данных для графика
 */
function generateChartData(string $symbol, int $limit = 100): array {
    $data = [];
    $time = time() - ($limit * 24 * 3600);
    
    // Базовые цены
    $basePrices = [
        'BTC' => 43250, 'ETH' => 2285, 'BNB' => 312, 'XRP' => 0.62, 'SOL' => 98,
        'ADA' => 0.58, 'DOGE' => 0.082, 'DOT' => 7.85, 'MATIC' => 0.92, 'LTC' => 72,
        'AAPL' => 178, 'GOOGL' => 142, 'MSFT' => 379, 'AMZN' => 155, 'TSLA' => 248,
        'EURUSD' => 1.087, 'GBPUSD' => 1.27, 'USDJPY' => 148.5,
        'SPX' => 4500, 'NDX' => 15000, 'DJI' => 35000, 'FTSE' => 7500, 'DAX' => 16000
    ];
    
    $open = $basePrices[strtoupper($symbol)] ?? 100;
    
    for ($i = 0; $i < $limit; $i++) {
        $high = $open * (1 + (rand(0, 500) / 10000));
        $low = $open * (1 - (rand(0, 500) / 10000));
        $close = $low + (($high - $low) * (rand(0, 100) / 100));
        $volume = rand(100000, 1000000);
        
        $data[] = [
            'time' => $time,
            'open' => round($open, 2),
            'high' => round($high, 2),
            'low' => round($low, 2),
            'close' => round($close, 2),
            'volume' => $volume
        ];
        
        $time += 24 * 3600;
        $open = $close;
    }
    
    return $data;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'crypto':
            echo json_encode([
                'success' => true,
                'data' => getCryptoPrices()
            ]);
            break;
            
        case 'stocks':
            echo json_encode([
                'success' => true,
                'data' => getStockPrices()
            ]);
            break;
            
        case 'forex':
            echo json_encode([
                'success' => true,
                'data' => getForexRates()
            ]);
            break;
            
        case 'indices':
            echo json_encode([
                'success' => true,
                'data' => getIndicesPrices()
            ]);
            break;
            
        case 'chart':
            $symbol = $_GET['symbol'] ?? 'BTC';
            $limit = min((int)($_GET['limit'] ?? 100), 365);
            
            echo json_encode([
                'success' => true,
                'data' => generateChartData($symbol, $limit)
            ]);
            break;
            
        case 'price':
            $symbol = strtoupper($_GET['symbol'] ?? 'BTC');
            
            $prices = [
                'BTC' => 43250.00, 'ETH' => 2285.50, 'BNB' => 312.40, 'XRP' => 0.62,
                'SOL' => 98.75, 'ADA' => 0.58, 'DOGE' => 0.082, 'DOT' => 7.85,
                'MATIC' => 0.92, 'LTC' => 72.30, 'AAPL' => 178.52, 'GOOGL' => 141.80,
                'MSFT' => 378.91, 'AMZN' => 155.34, 'TSLA' => 248.50,
                'SPX' => 4500.00, 'NDX' => 15000.00, 'DJI' => 35000.00, 'FTSE' => 7500.00, 'DAX' => 16000.00
            ];
            
            $price = $prices[$symbol] ?? 0;
            // Добавляем небольшую случайность
            $price = $price * (1 + (rand(-100, 100) / 10000));
            
            echo json_encode([
                'success' => true,
                'symbol' => $symbol,
                'price' => $price
            ]);
            break;
            
        case 'all':
            echo json_encode([
                'success' => true,
                'crypto' => getCryptoPrices(),
                'stocks' => getStockPrices(),
                'forex' => getForexRates(),
                'indices' => getIndicesPrices()
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
