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
        
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...(token && { 'Authorization': `Bearer ${token}` }),
                ...options.headers
            },
            ...options
        };
        
        try {
            const response = await fetch(this.baseUrl + endpoint, config);
            
            // Проверяем Content-Type перед парсингом JSON
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                const text = await response.text();
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    throw new Error(`Invalid JSON response: ${text.substring(0, 100)}`);
                }
            } else {
                // Если ответ не JSON, читаем как текст для отладки
                const text = await response.text();
                throw new Error(`Server returned non-JSON response (${contentType || 'unknown'}): ${text.substring(0, 200)}`);
            }
            
            if (!response.ok) {
                throw new Error(data.error || `Request failed: ${response.status} ${response.statusText}`);
            }
            
            return data;
        } catch (error) {
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
            const maxRetries = 3;
            const retryDelay = 1000; // 1 секунда
            
            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    // Создаем AbortController для таймаута
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 секунд таймаут
                    
                    // Используем наш PHP API вместо прямого запроса к CoinGecko (избегаем CORS)
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
            { id: 'bitcoin', symbol: 'btc', name: 'Bitcoin', current_price: 43250.00, price_change_percentage_24h: 2.45, market_cap: 847000000000, total_volume: 28000000000, sparkline_in_7d: { price: this.generateSparkline(43250) } },
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
     * Получить данные акций (моковые данные)
     */
    async getStockPrices() {
        return this.getCached('stock_prices', async () => {
            // В реальном проекте здесь будет запрос к Alpha Vantage или другому API
            return [
                { symbol: 'AAPL', name: 'Apple Inc.', price: 178.52, change: 1.24, changePercent: 0.70 },
                { symbol: 'GOOGL', name: 'Alphabet Inc.', price: 141.80, change: -0.95, changePercent: -0.67 },
                { symbol: 'MSFT', name: 'Microsoft Corp.', price: 378.91, change: 4.52, changePercent: 1.21 },
                { symbol: 'AMZN', name: 'Amazon.com Inc.', price: 155.34, change: 2.18, changePercent: 1.42 },
                { symbol: 'TSLA', name: 'Tesla Inc.', price: 248.50, change: -5.30, changePercent: -2.09 },
                { symbol: 'META', name: 'Meta Platforms', price: 355.67, change: 7.23, changePercent: 2.08 },
                { symbol: 'NVDA', name: 'NVIDIA Corp.', price: 495.22, change: 12.45, changePercent: 2.58 },
                { symbol: 'JPM', name: 'JPMorgan Chase', price: 172.85, change: 0.95, changePercent: 0.55 },
                { symbol: 'V', name: 'Visa Inc.', price: 258.30, change: 1.67, changePercent: 0.65 },
                { symbol: 'JNJ', name: 'Johnson & Johnson', price: 156.42, change: -0.28, changePercent: -0.18 }
            ];
        });
    },
    
    /**
     * Получить курсы форекс (моковые данные)
     */
    async getForexRates() {
        return this.getCached('forex_rates', async () => {
            return [
                { symbol: 'EURUSD', name: 'EUR/USD', price: 1.0872, change: 0.0015, changePercent: 0.14 },
                { symbol: 'GBPUSD', name: 'GBP/USD', price: 1.2698, change: -0.0023, changePercent: -0.18 },
                { symbol: 'USDJPY', name: 'USD/JPY', price: 148.52, change: 0.45, changePercent: 0.30 },
                { symbol: 'USDCHF', name: 'USD/CHF', price: 0.8742, change: 0.0012, changePercent: 0.14 },
                { symbol: 'AUDUSD', name: 'AUD/USD', price: 0.6578, change: 0.0028, changePercent: 0.43 },
                { symbol: 'USDCAD', name: 'USD/CAD', price: 1.3485, change: -0.0018, changePercent: -0.13 },
                { symbol: 'NZDUSD', name: 'NZD/USD', price: 0.6142, change: 0.0035, changePercent: 0.57 },
                { symbol: 'EURGBP', name: 'EUR/GBP', price: 0.8561, change: 0.0008, changePercent: 0.09 },
                { symbol: 'EURJPY', name: 'EUR/JPY', price: 161.42, change: 0.62, changePercent: 0.39 },
                { symbol: 'GBPJPY', name: 'GBP/JPY', price: 188.58, change: -0.35, changePercent: -0.19 }
            ];
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
        'BTC': 43250.00,
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
        'AAPL': 178.52,
        'GOOGL': 141.80,
        'MSFT': 378.91,
        'AMZN': 155.34,
        'TSLA': 248.50,
        'META': 355.67,
        'NVDA': 495.22,
        'JPM': 172.85,
        'V': 258.30,
        'JNJ': 156.42,
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
