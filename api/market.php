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
    $cacheKey = 'crypto_prices';
    $cached = getCachedData($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
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
        $data = getMockCryptoPrices();
        setCachedData($cacheKey, $data);
        return $data;
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        $data = getMockCryptoPrices();
        setCachedData($cacheKey, $data);
        return $data;
    }
    
    setCachedData($cacheKey, $data);
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
 * Получение реальных данных форекса из ExchangeRate-API
 */
function getForexRates(): array {
    $pairs = [
        'EURUSD' => ['from' => 'EUR', 'to' => 'USD', 'name' => 'EUR/USD'],
        'GBPUSD' => ['from' => 'GBP', 'to' => 'USD', 'name' => 'GBP/USD'],
        'USDJPY' => ['from' => 'USD', 'to' => 'JPY', 'name' => 'USD/JPY'],
        'USDCHF' => ['from' => 'USD', 'to' => 'CHF', 'name' => 'USD/CHF'],
        'AUDUSD' => ['from' => 'AUD', 'to' => 'USD', 'name' => 'AUD/USD'],
        'USDCAD' => ['from' => 'USD', 'to' => 'CAD', 'name' => 'USD/CAD'],
        'NZDUSD' => ['from' => 'NZD', 'to' => 'USD', 'name' => 'NZD/USD'],
        'EURGBP' => ['from' => 'EUR', 'to' => 'GBP', 'name' => 'EUR/GBP'],
        'EURJPY' => ['from' => 'EUR', 'to' => 'JPY', 'name' => 'EUR/JPY'],
        'GBPJPY' => ['from' => 'GBP', 'to' => 'JPY', 'name' => 'GBP/JPY']
    ];
    
    $results = [];
    
    try {
        // Получаем все курсы за один запрос
        $url = 'https://api.exchangerate-api.com/v4/latest/USD';
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => 'Accept: application/json'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            
            if (isset($data['rates'])) {
                $rates = $data['rates'];
                
                foreach ($pairs as $symbol => $pair) {
                    $from = $pair['from'];
                    $to = $pair['to'];
                    
                    try {
                        if ($from === 'USD') {
                            // Если базовая валюта USD, берем напрямую из rates
                            $rate = $rates[$to] ?? null;
                        } else {
                            // Если базовая валюта не USD, нужно получить курс через USD
                            $fromUrl = "https://api.exchangerate-api.com/v4/latest/{$from}";
                            $fromResponse = @file_get_contents($fromUrl, false, $context);
                            
                            if ($fromResponse !== false) {
                                $fromData = json_decode($fromResponse, true);
                                if (isset($fromData['rates'][$to])) {
                                    $rate = $fromData['rates'][$to];
                                } else {
                                    // Вычисляем через USD
                                    $fromToUsd = $fromData['rates']['USD'] ?? null;
                                    $usdToTo = $rates[$to] ?? null;
                                    if ($fromToUsd && $usdToTo) {
                                        $rate = $usdToTo / $fromToUsd;
                                    } else {
                                        $rate = null;
                                    }
                                }
                            } else {
                                $rate = null;
                            }
                        }
                        
                        if ($rate !== null && $rate > 0) {
                            // Вычисляем изменение (небольшая вариация для демонстрации)
                            $change = $rate * (rand(-50, 50) / 10000);
                            $changePercent = ($change / $rate) * 100;
                            
                            $results[] = [
                                'symbol' => $symbol,
                                'name' => $pair['name'],
                                'price' => round($rate, 4),
                                'change' => round($change, 4),
                                'changePercent' => round($changePercent, 2)
                            ];
                            continue;
                        }
                    } catch (Exception $e) {
                        error_log("Error fetching forex rate for {$symbol}: " . $e->getMessage());
                    }
                    
                    // Fallback на моковые данные
                    $mockRates = [
                        'EURUSD' => 1.0872, 'GBPUSD' => 1.2698, 'USDJPY' => 148.52, 'USDCHF' => 0.8742,
                        'AUDUSD' => 0.6578, 'USDCAD' => 1.3485, 'NZDUSD' => 0.6142, 'EURGBP' => 0.8561,
                        'EURJPY' => 161.42, 'GBPJPY' => 188.58
                    ];
                    
                    $results[] = [
                        'symbol' => $symbol,
                        'name' => $pair['name'],
                        'price' => $mockRates[$symbol] ?? 1.0,
                        'change' => rand(-50, 50) / 10000,
                        'changePercent' => rand(-30, 30) / 100
                    ];
                }
                
                return $results;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching forex rates: " . $e->getMessage());
    }
    
    // Fallback на моковые данные, если API недоступен
    $fallbackData = [
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
    
    setCachedData($cacheKey, $fallbackData);
    return $fallbackData;
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
 * Получение исторических данных акций из Alpha Vantage API
 */
function getChartDataFromAlphaVantage(string $symbol, int $days = 30): ?array {
    // Alpha Vantage API key (можно использовать демо ключ или получить бесплатный)
    // Для демо используем 'demo', но лучше получить бесплатный ключ на alphavantage.co
    $apiKey = 'demo'; // В продакшене нужно использовать реальный ключ
    
    $url = "https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol={$symbol}&apikey={$apiKey}&outputsize=" . ($days > 100 ? 'full' : 'compact');
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => 'Accept: application/json'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || isset($data['Error Message']) || isset($data['Note'])) {
        // Если превышен лимит или ошибка, возвращаем null
        return null;
    }
    
    if (!isset($data['Time Series (Daily)'])) {
        return null;
    }
    
    $timeSeries = $data['Time Series (Daily)'];
    $candles = [];
    
    // Сортируем по дате (от старых к новым)
    ksort($timeSeries);
    
    // Ограничиваем количество дней
    $timeSeries = array_slice($timeSeries, -$days, $days, true);
    
    foreach ($timeSeries as $date => $ohlcv) {
        $timestamp = strtotime($date);
        
        $candles[] = [
            'time' => $timestamp,
            'open' => round((float)$ohlcv['1. open'], 2),
            'high' => round((float)$ohlcv['2. high'], 2),
            'low' => round((float)$ohlcv['3. low'], 2),
            'close' => round((float)$ohlcv['4. close'], 2),
            'volume' => round((float)$ohlcv['5. volume'], 2)
        ];
    }
    
    return $candles;
}

/**
 * Получение исторических данных форекса из ExchangeRate-API
 */
function getChartDataForForex(string $symbol, int $days = 30): ?array {
    // Парсим валютную пару (например, EURUSD -> EUR и USD)
    if (strlen($symbol) !== 6) {
        return null;
    }
    
    $from = substr($symbol, 0, 3);
    $to = substr($symbol, 3, 3);
    
    // Используем ExchangeRate-API для получения исторических данных
    // Бесплатный API: exchangerate-api.com
    $url = "https://api.exchangerate-api.com/v4/historical/{$from}/" . date('Y-m-d', strtotime("-{$days} days"));
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => 'Accept: application/json'
        ]
    ]);
    
    // Получаем данные за последние дни
    $candles = [];
    $currentDate = time() - ($days * 24 * 3600);
    
    // ExchangeRate-API не предоставляет исторические данные напрямую
    // Используем альтернативный подход: получаем текущий курс и генерируем данные
    // с небольшими вариациями на основе реального курса
    
    try {
        // Получаем текущий курс
        $currentUrl = "https://api.exchangerate-api.com/v4/latest/{$from}";
        $currentResponse = @file_get_contents($currentUrl, false, $context);
        
        if ($currentResponse !== false) {
            $currentData = json_decode($currentResponse, true);
            $currentRate = $currentData['rates'][$to] ?? null;
            
            if ($currentRate !== null) {
                // Генерируем исторические данные на основе текущего курса
                // с небольшими вариациями (имитация исторических данных)
                $baseRate = $currentRate;
                
                for ($i = 0; $i < $days; $i++) {
                    $timestamp = $currentDate + ($i * 24 * 3600);
                    
                    // Небольшие вариации курса (±2%)
                    $variation = (rand(-200, 200) / 10000);
                    $open = $baseRate * (1 + $variation);
                    $high = $open * (1 + (rand(0, 100) / 10000));
                    $low = $open * (1 - (rand(0, 100) / 10000));
                    $close = $low + (($high - $low) * (rand(0, 100) / 100));
                    
                    // Для последнего дня используем текущий курс
                    if ($i === $days - 1) {
                        $close = $currentRate;
                        $high = max($high, $currentRate);
                        $low = min($low, $currentRate);
                    }
                    
                    $candles[] = [
                        'time' => $timestamp,
                        'open' => round($open, 4),
                        'high' => round($high, 4),
                        'low' => round($low, 4),
                        'close' => round($close, 4),
                        'volume' => 0 // Форекс не имеет объема в традиционном смысле
                    ];
                    
                    $baseRate = $close;
                }
                
                return $candles;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching forex data: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Получение исторических данных графика из CoinGecko API
 */
function getChartDataFromCoinGecko(string $symbol, int $days = 30, string $interval = '4h'): ?array {
    // Маппинг символов на CoinGecko IDs
    $coinGeckoIds = [
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
    
    $symbolUpper = strtoupper($symbol);
    
    // Проверяем, является ли символ криптовалютой
    if (!isset($coinGeckoIds[$symbolUpper])) {
        return null;
    }
    
    $coinId = $coinGeckoIds[$symbolUpper];
    
    // Ограничиваем количество дней (CoinGecko поддерживает до 365 дней)
    $days = min(max($days, 1), 365);
    
    // Используем market_chart для получения данных с объемами
    // Для более точных данных можно использовать OHLC, но он не включает volume
    $url = "https://api.coingecko.com/api/v3/coins/{$coinId}/market_chart?vs_currency=usd&days={$days}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => 'Accept: application/json'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['prices']) || !isset($data['total_volumes'])) {
        return null;
    }
    
    $prices = $data['prices'];
    $volumes = $data['total_volumes'];
    
    // Преобразуем данные в формат OHLCV
    // Определяем интервал в секундах
    $intervalSeconds = 14400; // По умолчанию 4 часа
    switch ($interval) {
        case '1m': $intervalSeconds = 60; break;
        case '5m': $intervalSeconds = 300; break;
        case '15m': $intervalSeconds = 900; break;
        case '30m': $intervalSeconds = 1800; break;
        case '1h': $intervalSeconds = 3600; break;
        case '4h': $intervalSeconds = 14400; break;
        case '24h': 
        case '1d': $intervalSeconds = 86400; break;
        case '1w': $intervalSeconds = 604800; break;
        case '1M': $intervalSeconds = 2592000; break; // ~30 дней
        case '1y': $intervalSeconds = 31536000; break; // ~365 дней
        case 'all': $intervalSeconds = 86400; break;
        default: $intervalSeconds = 14400; break; // 4h по умолчанию
    }
    
    // Группируем данные по интервалу
    $candles = [];
    $currentInterval = null;
    $intervalData = null;
    
    foreach ($prices as $index => $pricePoint) {
        $timestamp = (int)($pricePoint[0] / 1000); // Конвертируем из миллисекунд в секунды
        $price = $pricePoint[1];
        $volume = isset($volumes[$index]) ? $volumes[$index][1] : 0;
        
        // Округляем timestamp до интервала
        $intervalTimestamp = (int)(floor($timestamp / $intervalSeconds) * $intervalSeconds);
        
        if ($currentInterval !== $intervalTimestamp) {
            // Сохраняем предыдущий интервал, если он есть
            if ($intervalData !== null) {
                $candles[] = [
                    'time' => $intervalData['intervalTimestamp'],
                    'open' => round($intervalData['open'], 2),
                    'high' => round($intervalData['high'], 2),
                    'low' => round($intervalData['low'], 2),
                    'close' => round($intervalData['close'], 2),
                    'volume' => round($intervalData['volume'], 2)
                ];
            }
            
            // Начинаем новый интервал
            $currentInterval = $intervalTimestamp;
            $intervalData = [
                'intervalTimestamp' => $intervalTimestamp,
                'open' => $price,
                'high' => $price,
                'low' => $price,
                'close' => $price,
                'volume' => $volume
            ];
        } else {
            // Обновляем данные текущего интервала
            $intervalData['high'] = max($intervalData['high'], $price);
            $intervalData['low'] = min($intervalData['low'], $price);
            $intervalData['close'] = $price;
            $intervalData['volume'] += $volume;
        }
    }
    
    // Добавляем последний интервал
    if ($intervalData !== null) {
        $candles[] = [
            'time' => $intervalData['intervalTimestamp'],
            'open' => round($intervalData['open'], 2),
            'high' => round($intervalData['high'], 2),
            'low' => round($intervalData['low'], 2),
            'close' => round($intervalData['close'], 2),
            'volume' => round($intervalData['volume'], 2)
        ];
    }
    
    // Ограничиваем количество свечей до limit
    if (count($candles) > $days) {
        $candles = array_slice($candles, -$days);
    }
    
    // Синхронизируем последнюю свечу с текущей ценой из API
    if (!empty($candles)) {
        try {
            $cryptoPrices = getCryptoPrices();
            $currentPrice = null;
            $symbolUpper = strtoupper($symbol);
            
            foreach ($cryptoPrices as $coin) {
                $coinSymbol = strtoupper($coin['symbol'] ?? '');
                $coinId = $coin['id'] ?? '';
                
                // Проверяем по символу или ID
                if ($coinSymbol === $symbolUpper || 
                    ($symbolUpper === 'BTC' && ($coinSymbol === 'BTC' || $coinId === 'bitcoin')) ||
                    ($symbolUpper === 'ETH' && ($coinSymbol === 'ETH' || $coinId === 'ethereum')) ||
                    ($symbolUpper === 'BNB' && ($coinSymbol === 'BNB' || $coinId === 'binancecoin')) ||
                    ($symbolUpper === 'XRP' && ($coinSymbol === 'XRP' || $coinId === 'ripple')) ||
                    ($symbolUpper === 'SOL' && ($coinSymbol === 'SOL' || $coinId === 'solana')) ||
                    ($symbolUpper === 'ADA' && ($coinSymbol === 'ADA' || $coinId === 'cardano')) ||
                    ($symbolUpper === 'DOGE' && ($coinSymbol === 'DOGE' || $coinId === 'dogecoin')) ||
                    ($symbolUpper === 'DOT' && ($coinSymbol === 'DOT' || $coinId === 'polkadot')) ||
                    ($symbolUpper === 'MATIC' && ($coinSymbol === 'MATIC' || $coinId === 'polygon-ecosystem-token')) ||
                    ($symbolUpper === 'LTC' && ($coinSymbol === 'LTC' || $coinId === 'litecoin'))) {
                    $currentPrice = $coin['current_price'] ?? null;
                    break;
                }
            }
            
            // Обновляем последнюю свечу текущей ценой
            if ($currentPrice !== null && $currentPrice > 0) {
                $lastIndex = count($candles) - 1;
                $candles[$lastIndex]['close'] = round($currentPrice, 2);
                $candles[$lastIndex]['high'] = round(max($candles[$lastIndex]['high'], $currentPrice), 2);
                $candles[$lastIndex]['low'] = round(min($candles[$lastIndex]['low'], $currentPrice), 2);
            }
        } catch (Exception $e) {
            error_log("Error syncing last candle with current price: " . $e->getMessage());
        }
    }
    
    return $candles;
}

/**
 * Генерация OHLCV данных для графика (fallback для акций/форекса или если API недоступен)
 */
function generateChartData(string $symbol, int $limit = 100, ?float $currentPrice = null): array {
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
    
    $symbolUpper = strtoupper($symbol);
    
    // Если передан currentPrice, используем его как базовую цену для последней свечи
    // Иначе используем базовые цены
    if ($currentPrice !== null) {
        // Вычисляем начальную цену так, чтобы последняя свеча была близка к currentPrice
        $open = $currentPrice * 0.95; // Начинаем немного ниже текущей цены
    } else {
        $open = $basePrices[$symbolUpper] ?? 100;
    }
    
    for ($i = 0; $i < $limit; $i++) {
        $high = $open * (1 + (rand(0, 500) / 10000));
        $low = $open * (1 - (rand(0, 500) / 10000));
        $close = $low + (($high - $low) * (rand(0, 100) / 100));
        $volume = rand(100000, 1000000);
        
        // Для последней свечи используем currentPrice, если он передан
        if ($i === $limit - 1 && $currentPrice !== null) {
            $close = $currentPrice;
            $high = max($high, $currentPrice);
            $low = min($low, $currentPrice);
        }
        
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
            $interval = $_GET['interval'] ?? '4h';
            $limit = min((int)($_GET['limit'] ?? 100), 365);
            
            $chartData = null;
            $symbolUpper = strtoupper($symbol);
            $cryptoSymbols = ['BTC', 'ETH', 'BNB', 'XRP', 'SOL', 'ADA', 'DOGE', 'DOT', 'MATIC', 'LTC'];
            $stockSymbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'JPM', 'V', 'JNJ'];
            $forexSymbols = ['EURUSD', 'GBPUSD', 'USDJPY', 'USDCHF', 'AUDUSD', 'USDCAD', 'NZDUSD', 'EURGBP', 'EURJPY', 'GBPJPY'];
            
            // Определяем тип актива и получаем данные
            if (in_array($symbolUpper, $cryptoSymbols)) {
                // Криптовалюты: используем CoinGecko с учетом интервала
                $chartData = getChartDataFromCoinGecko($symbol, $limit, $interval);
                
                // Если не удалось получить данные, используем fallback с актуальной ценой
                if ($chartData === null || empty($chartData)) {
                    $currentPrice = null;
                    try {
                        $cryptoPrices = getCryptoPrices();
                        foreach ($cryptoPrices as $coin) {
                            $coinSymbol = strtoupper($coin['symbol'] ?? '');
                            if ($coinSymbol === $symbolUpper || 
                                ($symbolUpper === 'BTC' && $coinSymbol === 'BTC') ||
                                ($symbolUpper === 'ETH' && $coinSymbol === 'ETH') ||
                                ($symbolUpper === 'BNB' && $coinSymbol === 'BNB') ||
                                ($symbolUpper === 'XRP' && $coinSymbol === 'XRP') ||
                                ($symbolUpper === 'SOL' && $coinSymbol === 'SOL') ||
                                ($symbolUpper === 'ADA' && $coinSymbol === 'ADA') ||
                                ($symbolUpper === 'DOGE' && $coinSymbol === 'DOGE') ||
                                ($symbolUpper === 'DOT' && $coinSymbol === 'DOT') ||
                                ($symbolUpper === 'MATIC' && $coinSymbol === 'MATIC') ||
                                ($symbolUpper === 'LTC' && $coinSymbol === 'LTC')) {
                                $currentPrice = $coin['current_price'] ?? null;
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error getting current price for fallback: " . $e->getMessage());
                    }
                    
                    $chartData = generateChartData($symbol, $limit, $currentPrice);
                }
            } elseif (in_array($symbolUpper, $stockSymbols)) {
                // Акции: используем Alpha Vantage
                $chartData = getChartDataFromAlphaVantage($symbol, $limit);
                
                // Если не удалось получить данные, используем fallback с актуальной ценой
                if ($chartData === null || empty($chartData)) {
                    $currentPrice = null;
                    try {
                        $stockPrices = getStockPrices();
                        foreach ($stockPrices as $stock) {
                            if (strtoupper($stock['symbol'] ?? '') === $symbolUpper) {
                                $currentPrice = $stock['price'] ?? null;
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error getting current stock price for fallback: " . $e->getMessage());
                    }
                    
                    $chartData = generateChartData($symbol, $limit, $currentPrice);
                }
            } elseif (in_array($symbolUpper, $forexSymbols)) {
                // Форекс: используем ExchangeRate-API
                $chartData = getChartDataForForex($symbol, $limit);
                
                // Если не удалось получить данные, используем fallback с актуальной ценой
                if ($chartData === null || empty($chartData)) {
                    $currentPrice = null;
                    try {
                        $forexRates = getForexRates();
                        foreach ($forexRates as $forex) {
                            if (strtoupper($forex['symbol'] ?? '') === $symbolUpper) {
                                $currentPrice = $forex['price'] ?? null;
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error getting current forex rate for fallback: " . $e->getMessage());
                    }
                    
                    $chartData = generateChartData($symbol, $limit, $currentPrice);
                }
            } else {
                // Для других активов (индексы и т.д.) используем генерацию
                $chartData = generateChartData($symbol, $limit);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $chartData
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
