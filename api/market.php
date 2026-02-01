<?php
/**
 * ADX Finance - API рыночных данных
 */

require_once __DIR__ . '/../config/database.php';

// Finnhub API key
define('FINNHUB_API_KEY', 'd5kp6a9r01qt47mel7vgd5kp6a9r01qt47mel800');

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
    $cacheKey = 'crypto_prices';
    // Уменьшаем время кэширования до 30 секунд для более актуальных данных
    $cached = getCachedData($cacheKey);
    if ($cached !== null) {
        error_log("[API] Cache hit for crypto_prices");
        return $cached;
    }
    
    $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=bitcoin,ethereum,binancecoin,ripple,solana,cardano,dogecoin,polkadot,polygon-ecosystem-token,litecoin&order=market_cap_desc&sparkline=true&price_change_percentage=24h';
    
    $maxRetries = 3;
    $retryDelay = 1; // секунды
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ],
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($data) && count($data) > 0) {
                error_log("[API] Successfully fetched crypto prices from CoinGecko (attempt $attempt)");
                setCachedData($cacheKey, $data, 30); // Кэш на 30 секунд
                return $data;
            } else {
                $error = json_last_error_msg();
                error_log("[API] Invalid JSON response from CoinGecko (attempt $attempt): $error");
            }
        } else {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            error_log("[API] CoinGecko API request failed (attempt $attempt): $errorMsg");
        }
        
        // Если не последняя попытка, ждем перед повтором
        if ($attempt < $maxRetries) {
            sleep($retryDelay * $attempt);
        }
    }
    
    // Если все попытки провалились, возвращаем моковые данные
    error_log("[API] All attempts failed, using mock data");
    $data = getMockCryptoPrices();
    setCachedData($cacheKey, $data, 30); // Кэш на 30 секунд
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
 * Получение реальных данных акций через Finnhub API
 */
function getStockPrices(): array {
    $cacheKey = 'stock_prices';
    // Кэширование на 30 секунд для соблюдения лимитов API (60 запросов/минуту)
    $cached = getCachedData($cacheKey);
    if ($cached !== null) {
        error_log("[API] Cache hit for stock_prices");
        return $cached;
    }
    
    $symbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'JPM', 'V', 'JNJ'];
    $stockNames = [
        'AAPL' => 'Apple Inc.',
        'GOOGL' => 'Alphabet Inc.',
        'MSFT' => 'Microsoft Corporation',
        'AMZN' => 'Amazon.com Inc.',
        'TSLA' => 'Tesla Inc.',
        'META' => 'Meta Platforms Inc.',
        'NVDA' => 'NVIDIA Corporation',
        'JPM' => 'JPMorgan Chase & Co.',
        'V' => 'Visa Inc.',
        'JNJ' => 'Johnson & Johnson'
    ];
    
    $results = [];
    $maxRetries = 2;
    $retryDelay = 0.5; // секунды
    
    foreach ($symbols as $symbol) {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token=" . FINNHUB_API_KEY;
                
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'method' => 'GET',
                        'header' => [
                            'Accept: application/json',
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        ],
                        'ignore_errors' => true
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response !== false) {
                    $data = json_decode($response, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['c']) && $data['c'] > 0) {
                        $results[] = [
                            'symbol' => $symbol,
                            'name' => $stockNames[$symbol] ?? $symbol,
                            'price' => round($data['c'], 2),
                            'change' => round($data['d'] ?? 0, 2),
                            'changePercent' => round($data['dp'] ?? 0, 2)
                        ];
                        error_log("[API] Successfully fetched stock price for {$symbol} (attempt $attempt)");
                        break; // Успешно получили данные, выходим из цикла попыток
                    } else {
                        error_log("[API] Invalid response for {$symbol} (attempt $attempt): " . json_last_error_msg());
                    }
                } else {
                    $error = error_get_last();
                    $errorMsg = $error ? $error['message'] : 'Unknown error';
                    error_log("[API] Finnhub API request failed for {$symbol} (attempt $attempt): $errorMsg");
                }
                
                // Если не последняя попытка, ждем перед повтором
                if ($attempt < $maxRetries) {
                    usleep($retryDelay * 1000000 * $attempt); // Микросекунды
                }
            } catch (Exception $e) {
                error_log("[API] Exception fetching stock price for {$symbol}: " . $e->getMessage());
            }
        }
        
        // Если не удалось получить данные, используем моковые значения
        if (empty(array_filter($results, function($r) use ($symbol) { return $r['symbol'] === $symbol; }))) {
            $mockPrices = [
                'AAPL' => 178.52, 'GOOGL' => 141.80, 'MSFT' => 378.91, 'AMZN' => 155.34,
                'TSLA' => 248.50, 'META' => 355.67, 'NVDA' => 495.22, 'JPM' => 172.85,
                'V' => 258.30, 'JNJ' => 156.42
            ];
            $results[] = [
                'symbol' => $symbol,
                'name' => $stockNames[$symbol] ?? $symbol,
                'price' => $mockPrices[$symbol] ?? 100.00,
                'change' => 0,
                'changePercent' => 0
            ];
        }
    }
    
    // Сохраняем в кэш на 30 секунд
    setCachedData($cacheKey, $results, 30);
    return $results;
}

/**
 * Получение цены одной акции через Finnhub API
 */
function getStockQuote(string $symbol): ?array {
    $cacheKey = 'stock_quote_' . strtoupper($symbol);
    // Кэширование на 15 секунд для более частых обновлений
    $cached = getCachedData($cacheKey);
    if ($cached !== null) {
        error_log("[API] Cache hit for stock_quote_{$symbol}");
        return $cached;
    }
    
    $symbol = strtoupper($symbol);
    $maxRetries = 2;
    $retryDelay = 0.5;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $url = "https://finnhub.io/api/v1/quote?symbol={$symbol}&token=" . FINNHUB_API_KEY;
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET',
                    'header' => [
                        'Accept: application/json',
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ],
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($data['c']) && $data['c'] > 0) {
                    $result = [
                        'symbol' => $symbol,
                        'price' => round($data['c'], 2),
                        'change' => round($data['d'] ?? 0, 2),
                        'changePercent' => round($data['dp'] ?? 0, 2),
                        'open' => round($data['o'] ?? $data['c'], 2),
                        'high' => round($data['h'] ?? $data['c'], 2),
                        'low' => round($data['l'] ?? $data['c'], 2),
                        'previousClose' => round($data['pc'] ?? $data['c'], 2)
                    ];
                    
                    // Сохраняем в кэш на 15 секунд
                    setCachedData($cacheKey, $result, 15);
                    error_log("[API] Successfully fetched stock quote for {$symbol}");
                    return $result;
                }
            }
            
            if ($attempt < $maxRetries) {
                usleep($retryDelay * 1000000 * $attempt);
            }
        } catch (Exception $e) {
            error_log("[API] Exception fetching stock quote for {$symbol}: " . $e->getMessage());
        }
    }
    
    return null;
}

/**
 * Получение реальных данных форекса через Finnhub API
 */
function getForexRates(): array {
    $cacheKey = 'forex_rates';
    // Кэширование на 30 секунд
    $cached = getCachedData($cacheKey);
    if ($cached !== null) {
        error_log("[API] Cache hit for forex_rates");
        return $cached;
    }
    
    // Finnhub использует формат OANDA: для форекса
    $pairs = [
        'EURUSD' => ['finnhub' => 'OANDA:EUR_USD', 'name' => 'EUR/USD'],
        'GBPUSD' => ['finnhub' => 'OANDA:GBP_USD', 'name' => 'GBP/USD'],
        'USDJPY' => ['finnhub' => 'OANDA:USD_JPY', 'name' => 'USD/JPY'],
        'USDCHF' => ['finnhub' => 'OANDA:USD_CHF', 'name' => 'USD/CHF'],
        'AUDUSD' => ['finnhub' => 'OANDA:AUD_USD', 'name' => 'AUD/USD'],
        'USDCAD' => ['finnhub' => 'OANDA:USD_CAD', 'name' => 'USD/CAD'],
        'NZDUSD' => ['finnhub' => 'OANDA:NZD_USD', 'name' => 'NZD/USD'],
        'EURGBP' => ['finnhub' => 'OANDA:EUR_GBP', 'name' => 'EUR/GBP'],
        'EURJPY' => ['finnhub' => 'OANDA:EUR_JPY', 'name' => 'EUR/JPY'],
        'GBPJPY' => ['finnhub' => 'OANDA:GBP_JPY', 'name' => 'GBP/JPY']
    ];
    
    $results = [];
    $maxRetries = 2;
    $retryDelay = 0.5;
    
    foreach ($pairs as $symbol => $pair) {
        $finnhubSymbol = $pair['finnhub'];
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $url = "https://finnhub.io/api/v1/forex/quote?symbol={$finnhubSymbol}&token=" . FINNHUB_API_KEY;
                
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'method' => 'GET',
                        'header' => [
                            'Accept: application/json',
                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                        ],
                        'ignore_errors' => true
                    ]
                ]);
                
                $response = @file_get_contents($url, false, $context);
                
                if ($response !== false) {
                    $data = json_decode($response, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['c']) && $data['c'] > 0) {
                        $results[] = [
                            'symbol' => $symbol,
                            'name' => $pair['name'],
                            'price' => round($data['c'], 4),
                            'change' => round($data['d'] ?? 0, 4),
                            'changePercent' => round($data['dp'] ?? 0, 2)
                        ];
                        error_log("[API] Successfully fetched forex rate for {$symbol} (attempt $attempt)");
                        break; // Успешно получили данные
                    }
                }
                
                if ($attempt < $maxRetries) {
                    usleep($retryDelay * 1000000 * $attempt);
                }
            } catch (Exception $e) {
                error_log("[API] Exception fetching forex rate for {$symbol}: " . $e->getMessage());
            }
        }
        
        // Если не удалось получить данные, используем моковые значения
        if (empty(array_filter($results, function($r) use ($symbol) { return $r['symbol'] === $symbol; }))) {
            $mockRates = [
                'EURUSD' => 1.0872, 'GBPUSD' => 1.2698, 'USDJPY' => 148.52, 'USDCHF' => 0.8742,
                'AUDUSD' => 0.6578, 'USDCAD' => 1.3485, 'NZDUSD' => 0.6142, 'EURGBP' => 0.8561,
                'EURJPY' => 161.42, 'GBPJPY' => 188.58
            ];
            $results[] = [
                'symbol' => $symbol,
                'name' => $pair['name'],
                'price' => $mockRates[$symbol] ?? 1.0,
                'change' => 0,
                'changePercent' => 0
            ];
        }
    }
    
    // Сохраняем в кэш на 30 секунд
    setCachedData($cacheKey, $results, 30);
    return $results;
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

$action = $_GET['action'] ?? '';

// Включаем отображение ошибок только для разработки (не для production)
if (getenv('APP_ENV') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Не показываем ошибки в выводе, только логируем
}

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
            
        case 'stock_quote':
            $symbol = strtoupper($_GET['symbol'] ?? '');
            if (empty($symbol)) {
                throw new Exception('Symbol parameter is required', 400);
            }
            $quote = getStockQuote($symbol);
            if ($quote === null) {
                throw new Exception('Stock quote not found', 404);
            }
            echo json_encode([
                'success' => true,
                'data' => $quote
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
            $cryptoData = getCryptoPrices();
            $stocksData = getStockPrices();
            $forexData = getForexRates();
            $indicesData = getIndicesPrices();
            
            // Валидация данных перед отправкой
            if (!is_array($cryptoData)) $cryptoData = [];
            if (!is_array($stocksData)) $stocksData = [];
            if (!is_array($forexData)) $forexData = [];
            if (!is_array($indicesData)) $indicesData = [];
            
            echo json_encode([
                'success' => true,
                'crypto' => $cryptoData,
                'stocks' => $stocksData,
                'forex' => $forexData,
                'indices' => $indicesData
            ], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
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
    
    // Логируем ошибку для диагностики
    error_log("API Error in market.php: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
    
    // Убеждаемся, что ответ валидный JSON
    $errorResponse = [
        'success' => false,
        'error' => (getenv('APP_ENV') === 'production') ? 'An unexpected error occurred.' : $e->getMessage()
    ];
    
    $jsonResponse = json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    if ($jsonResponse === false) {
        // Если JSON encoding провалился, отправляем простой ответ
        http_response_code(500);
        header('Content-Type: application/json');
        echo '{"success":false,"error":"Internal server error"}';
    } else {
        header('Content-Type: application/json');
        echo $jsonResponse;
    }
}
