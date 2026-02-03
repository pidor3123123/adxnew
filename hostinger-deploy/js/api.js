/**
 * ADX Finance - API модуль
 */

const API = {
    baseUrl: '/api',
    
    /**
     * Базовый запрос
     */
    async request(endpoint, options = {}) {
        const token = Auth.getToken();
        const startTime = performance.now();
        
        // Добавляем cache-busting параметр к URL
        const separator = endpoint.includes('?') ? '&' : '?';
        const cacheBuster = `_t=${Date.now()}&_r=${Math.random().toString(36).substring(7)}`;
        const urlWithCacheBuster = this.baseUrl + endpoint + separator + cacheBuster;
        const method = options.method || 'GET';
        
        // Параметры для логирования
        let requestParams = null;
        if (options.body) {
            try {
                if (typeof options.body === 'string') {
                    requestParams = JSON.parse(options.body);
                } else {
                    requestParams = options.body;
                }
            } catch (e) {
                requestParams = { body: '[Unable to parse]' };
            }
        }
        
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                ...(token && { 'Authorization': `Bearer ${token}` }),
                ...options.headers
            },
            cache: 'no-store', // Предотвращаем кеширование
            ...options
        };
        
        // Если есть body, добавляем его
        if (options.body) {
            config.body = options.body;
        }
        
        try {
            // Логируем запрос
            if (window.Logger) {
                window.Logger.apiRequest(urlWithCacheBuster, method, requestParams, null, null);
            }
            
            const response = await fetch(urlWithCacheBuster, config);
            const responseTime = Math.round(performance.now() - startTime);
            
            // Проверяем Content-Type перед парсингом JSON
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                const text = await response.text();
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    const error = new Error(`Invalid JSON response: ${text.substring(0, 100)}`);
                    if (window.Logger) {
                        window.Logger.apiError(urlWithCacheBuster, error, { responseText: text.substring(0, 200) });
                    }
                    throw error;
                }
            } else {
                // Если ответ не JSON, читаем как текст для отладки
                const text = await response.text();
                const error = new Error(`Server returned non-JSON response (${contentType || 'unknown'}): ${text.substring(0, 200)}`);
                if (window.Logger) {
                    window.Logger.apiError(urlWithCacheBuster, error, { contentType, responseText: text.substring(0, 200) });
                }
                throw error;
            }
            
            if (!response.ok) {
                const error = new Error(data.error || `Request failed: ${response.status} ${response.statusText}`);
                if (window.Logger) {
                    window.Logger.apiError(urlWithCacheBuster, error, { status: response.status, data });
                }
                throw error;
            }
            
            // Логируем успешный ответ
            if (window.Logger) {
                window.Logger.apiRequest(urlWithCacheBuster, method, requestParams, responseTime, response.status);
            }
            
            return data;
        } catch (error) {
            const responseTime = Math.round(performance.now() - startTime);
            
            // Логируем ошибку
            if (window.Logger) {
                window.Logger.apiError(urlWithCacheBuster, error, { responseTime, method });
            }
            
            console.error('API Error:', error);
            
            // Если это сетевая ошибка, логируем детали
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                console.error('[API] Network error - possible causes: server down, CORS issue, or network connectivity');
            }
            
            // Если это ошибка парсинга JSON, логируем детали
            if (error.message && error.message.includes('JSON')) {
                console.error('[API] JSON parsing error - invalid response format');
            }
            
            throw error;
        }
    },
    
    /**
     * GET запрос
     */
    get(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        if (!queryString) {
            return this.request(endpoint);
        }
        
        // Проверяем, содержит ли endpoint уже query string
        const separator = endpoint.includes('?') ? '&' : '?';
        const url = `${endpoint}${separator}${queryString}`;
        return this.request(url);
    },
    
    /**
     * POST запрос
     */
    post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * PUT запрос
     */
    put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * DELETE запрос
     */
    delete(endpoint) {
        return this.request(endpoint, {
            method: 'DELETE'
        });
    }
};

/**
 * Рыночные данные
 */
const MarketAPI = {
    cache: new Map(),
    cacheTime: 10000, // 10 секунд для более актуальных данных
    
    // Finnhub API теперь используется через PHP прокси (api/market.php)
    // Это позволяет избежать проблем с CORS
    
    /**
     * Получить котировку акции через PHP прокси (избегаем CORS)
     */
    async getStockQuote(symbol) {
        try {
            const response = await fetch(`/api/market.php?action=stock_quote&symbol=${encodeURIComponent(symbol)}`, {
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success && result.data && result.data.price > 0) {
                // Обновляем базовую цену
                this.basePrices[symbol] = result.data.price;
                return {
                    price: result.data.price,
                    change: result.data.change || 0,
                    changePercent: result.data.changePercent || 0,
                    high: result.data.high || result.data.price,
                    low: result.data.low || result.data.price,
                    open: result.data.open || result.data.price,
                    previousClose: result.data.previousClose || result.data.price
                };
            }
        } catch (error) {
            console.warn(`[MarketAPI] Failed to fetch stock quote for ${symbol}:`, error);
        }
        
        // Fallback на базовую цену
        const basePrice = this.basePrices[symbol] || 100;
        return {
            price: basePrice,
            change: 0,
            changePercent: 0,
            high: basePrice,
            low: basePrice,
            open: basePrice,
            previousClose: basePrice
        };
    },
    
    /**
     * Получить цены криптовалют через Binance API (точные real-time цены)
     */
    async getBinancePrices() {
        try {
            const res = await fetch('/api/market.php?action=binance_prices');
            const data = await res.json();
            return (data.success && Array.isArray(data.data)) ? data.data : [];
        } catch (e) {
            console.warn('[MarketAPI] getBinancePrices failed:', e);
            return [];
        }
    },
    
    /**
     * Получить данные с кэшированием
     */
    async getCached(key, fetcher) {
        const cached = this.cache.get(key);
        
        if (cached && Date.now() - cached.timestamp < this.cacheTime) {
            return cached.data;
        }
        
        try {
            const data = await fetcher();
            this.cache.set(key, { data, timestamp: Date.now() });
            
            // Логируем для диагностики
            if (key === 'crypto_prices' && Array.isArray(data)) {
                const btc = data.find(c => (c.symbol || '').toUpperCase() === 'BTC' || (c.id || '').toLowerCase() === 'bitcoin');
                if (btc && btc.current_price) {
                    console.log(`[MarketAPI] BTC price from cache/API: $${btc.current_price}`);
                }
            }
            
            return data;
        } catch (error) {
            console.error(`[MarketAPI] Error in getCached for ${key}:`, error);
            // Если есть кэшированные данные, возвращаем их даже если они устарели
            if (cached) {
                console.warn(`[MarketAPI] Using stale cache for ${key}`);
                return cached.data;
            }
            throw error;
        }
    },
    
    /**
     * Получить цены криптовалют через PHP API (избегаем CORS)
     */
    async getCryptoPrices(coins = ['bitcoin', 'ethereum', 'binancecoin', 'ripple', 'solana', 'cardano', 'dogecoin', 'polkadot', 'polygon', 'litecoin']) {
        return this.getCached('crypto_prices', async () => {
            // Try Binance first (more reliable, real-time, accurate like TradingView)
            try {
                const binanceData = await this.getBinancePrices();
                if (binanceData.length > 0) {
                    const mapped = binanceData.map(b => {
                        const sym = (b.symbol || '').toUpperCase();
                        const price = parseFloat(b.price) || 0;
                        if (price > 0) this.basePrices[sym] = price;
                        return {
                            id: (b.symbol || '').toLowerCase(),
                            symbol: (b.symbol || '').toLowerCase(),
                            current_price: price,
                            price_change_percentage_24h: parseFloat(b.change) || 0,
                            name: sym,
                            market_cap: 0,
                            total_volume: 0
                        };
                    });
                    console.log(`[MarketAPI] BTC price from Binance: $${mapped.find(m => m.symbol === 'btc')?.current_price || 'N/A'}`);
                    return mapped;
                }
            } catch (e) {
                console.warn('[MarketAPI] Binance fallback, trying CoinGecko:', e);
            }
            
            const maxRetries = 3;
            const retryDelay = 1000; // 1 секунда
            
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    // Создаем AbortController для таймаута
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 секунд таймаут
                    
                    // Fallback: CoinGecko API
                    const response = await fetch('/api/market.php?action=crypto', {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Cache-Control': 'no-cache'
                        }
                    });
                    
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) {
                        const errorText = await response.text().catch(() => 'Unknown error');
                        throw new Error(`Market API error: ${response.status} ${response.statusText} - ${errorText.substring(0, 100)}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        throw new Error(`Invalid content type: ${contentType}. Response: ${text.substring(0, 200)}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success && result.data && Array.isArray(result.data)) {
                        console.log(`[MarketAPI] Successfully fetched ${result.data.length} crypto prices`);
                        return result.data;
                    } else {
                        throw new Error('Invalid API response format');
                    }
                } catch (error) {
                    console.error(`[MarketAPI] Attempt ${attempt}/${maxRetries} failed:`, error);
                    
                    // Если это последняя попытка или ошибка не связана с сетью, возвращаем моковые данные
                    if (attempt === maxRetries || (error.name === 'AbortError' && attempt < maxRetries)) {
                        // Для AbortError (таймаут) делаем еще одну попытку
                        if (error.name === 'AbortError' && attempt < maxRetries) {
                            console.warn(`[MarketAPI] Request timeout, retrying in ${retryDelay}ms...`);
                            await new Promise(resolve => setTimeout(resolve, retryDelay));
                            continue;
                        }
                        
                        // Если это сетевая ошибка или последняя попытка, используем моковые данные
                        if (error.name === 'TypeError' || error.message.includes('Failed to fetch') || error.message.includes('ERR_CONNECTION_REFUSED')) {
                            console.warn('[MarketAPI] Network error, using mock data as fallback');
                        } else {
                            console.warn('[MarketAPI] API error, using mock data as fallback');
                        }
                        return this.getMockCryptoPrices();
                    }
                    
                    // Ждем перед следующей попыткой
                    await new Promise(resolve => setTimeout(resolve, retryDelay * attempt));
                }
            }
            
            // Если все попытки провалились, возвращаем моковые данные
            return this.getMockCryptoPrices();
        });
    },
    
    /**
     * Моковые данные криптовалют
     */
    getMockCryptoPrices() {
        return [
            { id: 'bitcoin', symbol: 'btc', name: 'Bitcoin', current_price: 78350.00, price_change_percentage_24h: 2.45, market_cap: 1535000000000, total_volume: 28000000000, sparkline_in_7d: { price: this.generateSparkline(78350) } },
            { id: 'ethereum', symbol: 'eth', name: 'Ethereum', current_price: 2285.50, price_change_percentage_24h: 1.82, market_cap: 274000000000, total_volume: 15000000000, sparkline_in_7d: { price: this.generateSparkline(2285) } },
            { id: 'binancecoin', symbol: 'bnb', name: 'BNB', current_price: 312.40, price_change_percentage_24h: -0.54, market_cap: 48000000000, total_volume: 890000000, sparkline_in_7d: { price: this.generateSparkline(312) } },
            { id: 'ripple', symbol: 'xrp', name: 'XRP', current_price: 0.62, price_change_percentage_24h: 3.21, market_cap: 33000000000, total_volume: 1200000000, sparkline_in_7d: { price: this.generateSparkline(0.62) } },
            { id: 'solana', symbol: 'sol', name: 'Solana', current_price: 98.75, price_change_percentage_24h: 5.67, market_cap: 42000000000, total_volume: 2100000000, sparkline_in_7d: { price: this.generateSparkline(98) } },
            { id: 'cardano', symbol: 'ada', name: 'Cardano', current_price: 0.58, price_change_percentage_24h: -1.23, market_cap: 20000000000, total_volume: 450000000, sparkline_in_7d: { price: this.generateSparkline(0.58) } },
            { id: 'dogecoin', symbol: 'doge', name: 'Dogecoin', current_price: 0.082, price_change_percentage_24h: 1.45, market_cap: 11500000000, total_volume: 380000000, sparkline_in_7d: { price: this.generateSparkline(0.082) } },
            { id: 'polkadot', symbol: 'dot', name: 'Polkadot', current_price: 7.85, price_change_percentage_24h: -2.10, market_cap: 9800000000, total_volume: 280000000, sparkline_in_7d: { price: this.generateSparkline(7.85) } },
            { id: 'polygon', symbol: 'matic', name: 'Polygon', current_price: 0.92, price_change_percentage_24h: 4.32, market_cap: 8500000000, total_volume: 520000000, sparkline_in_7d: { price: this.generateSparkline(0.92) } },
            { id: 'litecoin', symbol: 'ltc', name: 'Litecoin', current_price: 72.30, price_change_percentage_24h: 0.87, market_cap: 5300000000, total_volume: 340000000, sparkline_in_7d: { price: this.generateSparkline(72) } }
        ];
    },
    
    /**
     * Генерация спарклайна
     */
    generateSparkline(basePrice, points = 168) {
        const sparkline = [];
        let price = basePrice;
        
        for (let i = 0; i < points; i++) {
            price = price * (1 + (Math.random() - 0.5) * 0.02);
            sparkline.push(price);
        }
        
        return sparkline;
    },
    
    /**
     * Получить данные акций через PHP прокси (избегаем CORS)
     */
    async getStockPrices() {
        return this.getCached('stock_prices', async () => {
            const maxRetries = 3;
            const retryDelay = 1000;
            
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000);
                    
                    // Используем PHP прокси вместо прямого запроса к Finnhub
                    const response = await fetch('/api/market.php?action=stocks', {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Cache-Control': 'no-cache'
                        }
                    });
                    
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success && Array.isArray(result.data) && result.data.length > 0) {
                        // Обновляем базовые цены для fallback
                        result.data.forEach(stock => {
                            if (stock.price > 0) {
                                this.basePrices[stock.symbol] = stock.price;
                            }
                        });
                        return result.data;
                    }
                } catch (error) {
                    console.warn(`[MarketAPI] Failed to fetch stock prices (attempt ${attempt}):`, error);
                    if (attempt < maxRetries) {
                        await new Promise(resolve => setTimeout(resolve, retryDelay * attempt));
                    }
                }
            }
            
            // Fallback на базовые цены
            const stockList = [
                { symbol: 'AAPL', name: 'Apple Inc.' },
                { symbol: 'GOOGL', name: 'Alphabet Inc.' },
                { symbol: 'MSFT', name: 'Microsoft Corp.' },
                { symbol: 'AMZN', name: 'Amazon.com Inc.' },
                { symbol: 'TSLA', name: 'Tesla Inc.' },
                { symbol: 'META', name: 'Meta Platforms' },
                { symbol: 'NVDA', name: 'NVIDIA Corp.' },
                { symbol: 'JPM', name: 'JPMorgan Chase' },
                { symbol: 'V', name: 'Visa Inc.' },
                { symbol: 'JNJ', name: 'Johnson & Johnson' }
            ];
            
            return stockList.map(stock => ({
                symbol: stock.symbol,
                name: stock.name,
                price: this.basePrices[stock.symbol] || 100,
                change: 0,
                changePercent: 0
            }));
        });
    },
    
    /**
     * Получить курсы форекс через PHP прокси (избегаем CORS)
     */
    async getForexRates() {
        return this.getCached('forex_rates', async () => {
            const maxRetries = 3;
            const retryDelay = 1000;
            
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000);
                    
                    // Используем PHP прокси вместо прямого запроса к Finnhub
                    const response = await fetch('/api/market.php?action=forex', {
                        signal: controller.signal,
                        headers: {
                            'Accept': 'application/json',
                            'Cache-Control': 'no-cache'
                        }
                    });
                    
                    clearTimeout(timeoutId);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success && Array.isArray(result.data) && result.data.length > 0) {
                        // Обновляем базовые цены для fallback
                        result.data.forEach(forex => {
                            if (forex.price > 0) {
                                this.basePrices[forex.symbol] = forex.price;
                            }
                        });
                        return result.data;
                    }
                } catch (error) {
                    console.warn(`[MarketAPI] Failed to fetch forex rates (attempt ${attempt}):`, error);
                    if (attempt < maxRetries) {
                        await new Promise(resolve => setTimeout(resolve, retryDelay * attempt));
                    }
                }
            }
            
            // Fallback на базовые цены
            const forexList = [
                { symbol: 'EURUSD', name: 'EUR/USD' },
                { symbol: 'GBPUSD', name: 'GBP/USD' },
                { symbol: 'USDJPY', name: 'USD/JPY' },
                { symbol: 'USDCHF', name: 'USD/CHF' },
                { symbol: 'AUDUSD', name: 'AUD/USD' },
                { symbol: 'USDCAD', name: 'USD/CAD' },
                { symbol: 'NZDUSD', name: 'NZD/USD' },
                { symbol: 'EURGBP', name: 'EUR/GBP' },
                { symbol: 'EURJPY', name: 'EUR/JPY' },
                { symbol: 'GBPJPY', name: 'GBP/JPY' }
            ];
            
            return forexList.map(forex => ({
                symbol: forex.symbol,
                name: forex.name,
                price: this.basePrices[forex.symbol] || 1.0,
                change: 0,
                changePercent: 0
            }));
        });
    },
    
    /**
     * Получить индексы (моковые данные)
     */
    async getIndicesPrices() {
        try {
            const response = await API.get('/market.php?action=indices');
            if (response.success && response.data) {
                return response.data;
            }
        } catch (error) {
            console.warn('Failed to fetch indices from API, using mock data:', error);
        }
        
        return this.getCached('indices_prices', async () => {
            return [
                { symbol: 'SPX', name: 'S&P 500', price: 4500.00, change: 12.50, changePercent: 0.28 },
                { symbol: 'NDX', name: 'NASDAQ 100', price: 15000.00, change: 45.20, changePercent: 0.30 },
                { symbol: 'DJI', name: 'Dow Jones', price: 35000.00, change: 85.30, changePercent: 0.24 },
                { symbol: 'FTSE', name: 'FTSE 100', price: 7500.00, change: -15.40, changePercent: -0.21 },
                { symbol: 'DAX', name: 'DAX', price: 16000.00, change: 32.10, changePercent: 0.20 }
            ];
        });
    },
    
    /**
     * Get chart OHLCV data - same source as price for consistency
     */
    async getChartData(symbol, interval) {
        try {
            const res = await fetch(`/api/market.php?action=chart&symbol=${encodeURIComponent(symbol)}&limit=100`);
            const result = await res.json();
            if (result.success && result.data) return result.data;
        } catch (error) {
            console.warn('[MarketAPI] getChartData API failed, using mock data:', error);
        }

        // Fallback: generate mock OHLCV data
        const basePrice = this.basePrices[symbol.toUpperCase()] || 100;
        const candles = [];
        const now = Math.floor(Date.now() / 1000);
        let price = basePrice;

        for (let i = 0; i < 100; i++) {
            const open = price;
            const change = (Math.random() - 0.5) * basePrice * 0.02;
            const close = open + change;
            const high = Math.max(open, close) + Math.random() * basePrice * 0.005;
            const low = Math.min(open, close) - Math.random() * basePrice * 0.005;

            candles.push({
                time: now - (100 - i) * 60,
                open, high, low, close,
                volume: Math.random() * 1000000
            });
            price = close;
        }
        return candles;
    },

    /**
     * Интервалы в миллисекундах
     */
    intervals: {
        '1m': 60 * 1000,
        '5m': 5 * 60 * 1000,
        '15m': 15 * 60 * 1000,
        '1h': 60 * 60 * 1000,
        '4h': 4 * 60 * 60 * 1000,
        '1d': 24 * 60 * 60 * 1000,
        '1w': 7 * 24 * 60 * 60 * 1000
    },
    
    /**
     * Базовые цены активов
     */
    basePrices: {
        // Crypto
        'BTC': 78350.00,
        'ETH': 2285.50,
        'BNB': 312.40,
        'XRP': 0.62,
        'SOL': 98.75,
        'ADA': 0.58,
        'DOGE': 0.082,
        'DOT': 7.85,
        'MATIC': 0.92,
        'LTC': 72.30,
        // Stocks
        'AAPL': 225.50,
        'GOOGL': 195.20,
        'MSFT': 415.80,
        'AMZN': 228.40,
        'TSLA': 395.60,
        'META': 615.30,
        'NVDA': 191.50,
        'JPM': 255.40,
        'V': 325.20,
        'JNJ': 148.90,
        // Forex
        'EURUSD': 1.0872,
        'GBPUSD': 1.2698,
        'USDJPY': 148.52,
        'USDCHF': 0.8742,
        'AUDUSD': 0.6578,
        'USDCAD': 1.3485,
        'NZDUSD': 0.6142,
        'EURGBP': 0.8561,
        'EURJPY': 161.42,
        'GBPJPY': 188.58
    },
    
};

/**
 * Торговое API
 */
const TradingAPI = {
    /**
     * Получить балансы пользователя
     */
    async getBalances() {
        return API.get('/wallet.php?action=balances');
    },
    
    /**
     * Создать ордер
     */
    async createOrder(data) {
        return API.post('/trading.php?action=create', data);
    },
    
    /**
     * Получить открытые ордера
     */
    async getOpenOrders() {
        return API.get('/trading.php?action=open');
    },
    
    /**
     * Получить историю ордеров
     */
    async getOrderHistory(limit = 50) {
        return API.get('/trading.php?action=history', { limit });
    },
    
    /**
     * Отменить ордер
     */
    async cancelOrder(orderId) {
        return API.post('/trading.php?action=cancel', { orderId });
    },
    
    /**
     * Получить портфель
     */
    async getPortfolio() {
        return API.get('/portfolio.php?action=summary');
    },
    
    /**
     * Проверить и выполнить TP/SL ордеры
     */
    async checkTPSL() {
        return API.post('/trading.php?action=check_tp_sl');
    },
    
    /**
     * Получить открытые позиции
     */
    async getOpenPositions() {
        return API.get('/trading.php?action=open_positions');
    },
    
    /**
     * Закрыть позицию вручную
     */
    async closePosition(orderId) {
        return API.post('/trading.php?action=close_position', { order_id: orderId });
    },
    
    /**
     * Получить закрытые позиции
     */
    async getClosedPositions(period = 'all') {
        return API.get('/trading.php?action=closed_positions', { period });
    }
};

// Экспорт
window.API = API;
window.MarketAPI = MarketAPI;
window.TradingAPI = TradingAPI;
