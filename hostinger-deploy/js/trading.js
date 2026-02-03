/**
 * ADX Finance - Trading Module
 */

let currentAsset = {
    symbol: 'BTC',
    name: 'Bitcoin',
    price: 0, // Будет загружено из API
    change: 0,
    decimals: 8
};

// Make currentAsset globally available
window.currentAsset = currentAsset;

let userBalances = {
    USD: 0,
    BTC: 0,
    ETH: 0
};

// Используем глобальный объект TradingState вместо локальных переменных
// Если объект не существует, создаем его
if (!window.TradingState) {
    window.TradingState = {
        tradeSide: 'buy',
        orderType: 'market'
    };
}
let assetList = [];

// Reference MarketAPI from window with fallback (defined in api.js)
const MarketAPI = window.MarketAPI || {
    getCryptoPrices: async () => [],
    getStockPrices: async () => [],
    getForexRates: async () => [],
    getIndicesPrices: async () => [],
    getCommoditiesPrices: async () => [],
    basePrices: {}
};

// Balance API retry limit to prevent spam on repeated failures
let balanceLoadRetries = 0;
const MAX_BALANCE_RETRIES = 3;

// WebSocket больше не используется - используем только API опрос

/**
 * Load asset list (crypto, stocks, forex)
 */
async function loadAssetList() {
    const container = document.getElementById('assetList');
    if (!container) return;
    
    try {
        // Загружаем все типы активов
        const [cryptoData, stocksData, forexData] = await Promise.all([
            MarketAPI.getCryptoPrices(),
            MarketAPI.getStockPrices(),
            MarketAPI.getForexRates()
        ]);
        
        // Криптовалюты
        const cryptoAssets = cryptoData.map(coin => ({
            symbol: coin.symbol.toUpperCase(),
            name: coin.name,
            price: coin.current_price,
            change: coin.price_change_percentage_24h,
            market: 'crypto'
        }));
        
        // Акции
        const stockAssets = stocksData.map(stock => ({
            symbol: stock.symbol,
            name: stock.name,
            price: stock.price,
            change: stock.changePercent,
            market: 'stocks'
        }));
        
        // Форекс
        const forexAssets = forexData.map(pair => ({
            symbol: pair.symbol,
            name: pair.name,
            price: pair.price,
            change: pair.changePercent,
            market: 'forex'
        }));
        
        // Объединяем все активы
        assetList = [...cryptoAssets, ...stockAssets, ...forexAssets];
        
        // Обновляем глобальную ссылку для использования в trade.html
        window.assetList = assetList;
        
        renderAssetList(assetList);
    } catch (error) {
        console.error('Error loading asset list:', error);
    }
}

/**
 * Render asset list
 */
function renderAssetList(assets) {
    const container = document.getElementById('assetList');
    if (!container) return;
    
    container.innerHTML = assets.map(asset => `
        <div class="asset-list-item ${asset.symbol === currentAsset.symbol ? 'active' : ''}" 
             data-symbol="${asset.symbol}"
             onclick="selectAsset('${asset.symbol}')">
            <div class="asset-list-info">
                <div class="asset-list-icon">${asset.symbol.slice(0, 2)}</div>
                <div>
                    <div class="asset-list-symbol">${asset.symbol}</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div class="asset-list-price">${NovaTrade.formatCurrency(asset.price, 'USD', asset.price < 1 ? 6 : 2)}</div>
                <div class="asset-list-change ${asset.change >= 0 ? 'up' : 'down'}">
                    ${NovaTrade.formatChange(asset.change)}
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Filter asset list
 */
function filterAssetList(query) {
    const filtered = assetList.filter(asset => 
        asset.symbol.toLowerCase().includes(query.toLowerCase()) ||
        asset.name.toLowerCase().includes(query.toLowerCase())
    );
    renderAssetList(filtered);
}

/**
 * Select asset
 */
async function selectAsset(symbol) {
    const oldSymbol = currentAsset?.symbol;
    
    let asset = assetList.find(a => a.symbol === symbol || a.symbol === symbol.toUpperCase());
    
    // Если актив не найден в списке, создаём его с базовыми данными
    if (!asset) {
        const sym = symbol.toUpperCase();
        const basePrice = MarketAPI.basePrices[sym] || MarketAPI.basePrices[symbol] || 100;
        
        // Определяем тип рынка по символу
        let market = 'crypto';
        let name = sym;
        
        if (sym.includes('USD') || sym.includes('EUR') || sym.includes('GBP') || sym.includes('JPY') || sym.includes('CHF') || sym.includes('CAD') || sym.includes('AUD') || sym.includes('NZD')) {
            market = 'forex';
            name = sym.slice(0, 3) + '/' + sym.slice(3);
        } else if (['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'JPM', 'V', 'JNJ'].includes(sym)) {
            market = 'stocks';
            name = sym + ' Stock';
        }
        
        asset = {
            symbol: sym,
            name: name,
            price: basePrice,
            change: 0,
            market: market
        };
        assetList.push(asset);
    }
    
    currentAsset = {
        symbol: asset.symbol,
        name: asset.name,
        price: asset.price,
        change: asset.change,
        decimals: asset.price < 1 ? 6 : (asset.price < 10 ? 4 : 2)
    };
    
    window.currentAsset = currentAsset;
    
    // Update UI
    document.getElementById('currentAsset').textContent = asset.market === 'forex' ? asset.symbol : `${asset.symbol}/USD`;
    document.getElementById('currentAssetName').textContent = asset.name;
    const suffixEl = document.getElementById('quantitySuffix');
    if (suffixEl) suffixEl.textContent = 'USD';
    
    // Обновляем цену из API для всех активов (криптовалюты, акции, форекс)
    await updateAssetPrice();
    
    // Логируем выбор актива
    if (window.Logger) {
        window.Logger.userAction('Asset selected', { 
            symbol: currentAsset.symbol, 
            name: currentAsset.name,
            oldSymbol: oldSymbol 
        });
    }
    
    // Запускаем обновление цен в реальном времени через API
    startPriceUpdates();
    
    // Update icon based on market type
    const iconEl = document.getElementById('assetIcon');
    if (iconEl) {
        let iconClass = 'bi-currency-bitcoin';
        if (asset.market === 'stocks') iconClass = 'bi-graph-up-arrow';
        else if (asset.market === 'forex') iconClass = 'bi-currency-exchange';
        iconEl.innerHTML = `<i class="bi ${iconClass}"></i>`;
    }
    
    // Update list
    document.querySelectorAll('.asset-list-item').forEach(item => {
        item.classList.toggle('active', item.dataset.symbol === symbol);
    });
    
    // Update button
    updateTradeButton();
    
    // Update summary
    updateTradeSummary();
    
    // Update URL
    const url = new URL(window.location);
    url.searchParams.set('symbol', symbol);
    window.history.replaceState({}, '', url);
}

/**
 * Update trade UI
 */
function updateTradeUI(side, parentSection = null) {
    if (window.TradingState) {
        window.TradingState.tradeSide = side;
    }
    
    // Определяем, какая форма активна
    const isQuick = parentSection?.id === 'quickTradeMode';
    const submitBtnId = isQuick ? 'submitQuickTradeBtn' : 'submitTradeBtn';
    const submitBtn = document.getElementById(submitBtnId);
    
    if (submitBtn) {
        submitBtn.className = `btn btn-${side} btn-block btn-lg`;
        submitBtn.innerHTML = `
            <i class="bi bi-${side === 'buy' ? 'cart-plus' : 'cart-dash'}"></i>
            ${side === 'buy' ? 'Купить' : 'Продать'} ${currentAsset.symbol}
        `;
    }
    
    // Update available balance (both buy and sell use USD amount)
    const availableElId = isQuick ? 'quickAvailableBalance' : 'availableBalance';
    const availableEl = document.getElementById(availableElId);
    if (availableEl) {
        availableEl.textContent = NovaTrade.formatCurrency(userBalances.USD || 0);
    }
    
    // Update suffix (USD for amount)
    const quantitySuffixId = isQuick ? 'quickQuantitySuffix' : 'quantitySuffix';
    const quantitySuffix = document.getElementById(quantitySuffixId);
    if (quantitySuffix) {
        quantitySuffix.textContent = isQuick ? currentAsset.symbol : 'USD';
    }
    
    if (isQuick) {
        if (typeof updateQuickTradeSummary === 'function') updateQuickTradeSummary();
    } else {
        updateTradeSummary();
    }
}

/**
 * Update trade button
 */
function updateTradeButton() {
    const submitBtn = document.getElementById('submitTradeBtn');
    if (submitBtn) {
        const side = window.TradingState?.tradeSide || 'buy';
        submitBtn.innerHTML = `
            <i class="bi bi-${side === 'buy' ? 'cart-plus' : 'cart-dash'}"></i>
            ${side === 'buy' ? 'Купить' : 'Продать'} ${currentAsset.symbol}
        `;
    }
}

/**
 * Format quantity value without trailing zeros and round to step
 */
function formatQuantity(value, step = 0.0001) {
    // Если значение отрицательное или ноль, возвращаем '0'
    if (value < 0) return '0';
    
    // Если значение равно 0, возвращаем '0'
    if (value === 0) return '0';
    
    // Если значение меньше шага, но больше 0, возвращаем минимальное значение step
    if (value > 0 && value < step) {
        const stepStr = step.toString();
        const decimals = stepStr.includes('.') ? stepStr.split('.')[1].length : 0;
        return step.toFixed(decimals);
    }
    
    // Округляем до шага с использованием более точного метода
    // Используем Math.floor вместо Math.round для более консервативного округления
    // Но для торговли лучше использовать Math.round
    const rounded = Math.round(value / step) * step;
    
    // Проверяем, что округление не привело к 0 для положительных значений
    if (rounded <= 0 && value > 0) {
        // Если округление дало 0, но исходное значение было положительным,
        // возвращаем минимальное значение step
        const stepStr = step.toString();
        const decimals = stepStr.includes('.') ? stepStr.split('.')[1].length : 0;
        return step.toFixed(decimals);
    }
    
    // Определяем количество знаков после запятой на основе step
    const stepStr = step.toString();
    const decimals = stepStr.includes('.') ? stepStr.split('.')[1].length : 0;
    
    // Форматируем с нужным количеством знаков
    const formatted = rounded.toFixed(decimals);
    
    // Убираем trailing zeros используя parseFloat
    return parseFloat(formatted).toString();
}

/**
 * Calculate amount (USD) by percent - new model uses amount_usd for both buy and sell
 */
function calculateQuantityByPercent(percent, mode = 'normal') {
    const inputId = mode === 'quick' ? 'quickQuantity' : 'amountUsd';
    const inputEl = document.getElementById(inputId);
    if (!inputEl) return;
    
    const availableUSD = userBalances.USD || 0;
    const step = mode === 'quick' ? 0.0001 : 0.01;
    
    if (percent === 'max' || percent === 1) {
        inputEl.value = (availableUSD * 0.999).toFixed(2); // Leave 0.1% buffer
    } else {
        const amount = availableUSD * percent;
        inputEl.value = amount.toFixed(2);
    }
    
    if (mode === 'quick') {
        updateQuickTradeSummary();
    } else {
        updateTradeSummary();
    }
}

/**
 * Update trade summary - uses amount_usd
 */
function updateTradeSummary() {
    const amountUsd = parseFloat(document.getElementById('amountUsd')?.value) || 0;
    const fee = amountUsd * 0.001; // 0.1% fee
    
    const feeEl = document.getElementById('tradeFee');
    const totalEl = document.getElementById('tradeTotal');
    if (feeEl) feeEl.textContent = NovaTrade.formatCurrency(fee);
    if (totalEl) totalEl.textContent = NovaTrade.formatCurrency(amountUsd);
}

/**
 * Update quick trade summary
 */
function updateQuickTradeSummary() {
    const quantity = parseFloat(document.getElementById('quickQuantity')?.value) || 0;
    
    // Используем цену напрямую из currentAsset (синхронизируется с графиком)
    const price = currentAsset?.price || 0;
    
    // Получаем активную сторону из быстрой торговли
    const activeTab = document.querySelector('#quickTradeMode .trade-tab.active');
    const side = activeTab?.dataset.side || (window.TradingState?.tradeSide || 'buy');
    
    const total = quantity * price;
    const fee = total * 0.001; // 0.1% fee
    const finalTotal = side === 'buy' ? total + fee : total - fee;
    
    // Update display
    const feeEl = document.getElementById('quickTradeFee');
    const totalEl = document.getElementById('quickTradeTotal');
    
    // Цена уже должна быть установлена, не перезаписываем её
    if (feeEl) feeEl.textContent = NovaTrade.formatCurrency(fee);
    if (totalEl) totalEl.textContent = NovaTrade.formatCurrency(finalTotal);
}

window.updateQuickTradeSummary = updateQuickTradeSummary;

/**
 * Submit order - uses amount_usd for new trading model
 */
async function submitOrder(isQuick = false) {
    let amountUsd;
    if (isQuick) {
        const quantity = parseFloat(document.getElementById('quickQuantity')?.value) || 0;
        const price = currentAsset?.price || 0;
        amountUsd = quantity * price;
    } else {
        amountUsd = parseFloat(document.getElementById('amountUsd')?.value) || 0;
    }
    
    if (!amountUsd || amountUsd <= 0) {
        NovaTrade.showToast('Error', 'Enter amount (USD)', 'error');
        return;
    }
    
    const isAuth = Auth.isAuthenticated();
    
    if (!isAuth) {
        NovaTrade.showToast('Авторизация', 'Войдите для торговли', 'error');
        window.location.href = 'login.html';
        return;
    }
    
    const submitBtnId = isQuick ? 'submitQuickTradeBtn' : 'submitTradeBtn';
    const submitBtn = document.getElementById(submitBtnId);
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner" style="width:20px;height:20px;border-width:2px;"></span>';
    
    try {
        const limitPriceId = isQuick ? 'quickLimitPrice' : 'limitPrice';
        const limitPrice = parseFloat(document.getElementById(limitPriceId)?.value);
        
        // Для быстрой торговли TP/SL всегда включены
        const takeProfit = isQuick ? parseFloat(document.getElementById('quickTakeProfit')?.value) : null;
        const stopLoss = isQuick ? parseFloat(document.getElementById('quickStopLoss')?.value) : null;
        
        // Получаем активную вкладку Купить/Продать
        const activeMode = isQuick ? document.querySelector('#quickTradeMode .trade-tab.active') : document.querySelector('#normalTradeMode .trade-tab.active');
        const side = activeMode?.dataset.side || (window.TradingState?.tradeSide || 'buy');
        
        // Получаем тип ордера (быстрая торговля всегда рыночная)
        const type = isQuick ? 'market' : (window.TradingState?.orderType || 'market');
        
        // Определяем цену входа для валидации TP/SL
        let entryPrice;
        if (isQuick) {
            // Для быстрой торговли получаем цену из quickTradePrice
            const quickPriceEl = document.getElementById('quickTradePrice');
            entryPrice = quickPriceEl ? parseFloat(quickPriceEl.textContent.replace(/[^0-9.]/g, '')) : (currentAsset?.price || null);
        } else {
            entryPrice = type === 'limit' && limitPrice ? limitPrice : (currentAsset?.price || null);
        }
        
        // Валидация TP/SL для быстрой торговли
        if (isQuick && entryPrice) {
            if (takeProfit !== null && takeProfit !== undefined && !isNaN(takeProfit)) {
                if (side === 'buy' && takeProfit <= entryPrice) {
                    NovaTrade.showToast('Ошибка', 'Take Profit должен быть выше цены входа', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }
                if (side === 'sell' && takeProfit >= entryPrice) {
                    NovaTrade.showToast('Ошибка', 'Take Profit должен быть ниже цены входа', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }
            }
            
            if (stopLoss !== null && stopLoss !== undefined && !isNaN(stopLoss)) {
                if (side === 'buy' && stopLoss >= entryPrice) {
                    NovaTrade.showToast('Ошибка', 'Stop Loss должен быть ниже цены входа', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }
                if (side === 'sell' && stopLoss <= entryPrice) {
                    NovaTrade.showToast('Ошибка', 'Stop Loss должен быть выше цены входа', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }
            }
        }
        
        // Получаем время сделки для быстрой торговли
        let tradeDuration = null;
        if (isQuick) {
            const activeTimeBtn = document.querySelector('.trade-time-btn.active');
            if (activeTimeBtn) {
                const timeStr = activeTimeBtn.dataset.time;
                // Преобразуем время в секунды
                if (timeStr === '1m') tradeDuration = 60;
                else if (timeStr === '5m') tradeDuration = 300;
                else if (timeStr === '15m') tradeDuration = 900;
                else if (timeStr === '30m') tradeDuration = 1800;
                else if (timeStr === '1h') tradeDuration = 3600;
                else if (timeStr === '4h') tradeDuration = 14400;
            }
        }
        
        const orderData = isQuick ? {
            symbol: currentAsset.symbol,
            side: side,
            type: 'market',
            amount_usd: amountUsd, // Quick mode: quantity*price = amount_usd
            quantity: amountUsd / (currentAsset?.price || 1),
            price: null,
            current_price: entryPrice,
            take_profit: takeProfit || null,
            stop_loss: stopLoss || null,
            trade_duration: tradeDuration
        } : {
            symbol: currentAsset.symbol,
            side: side,
            type: 'market',
            amount_usd: amountUsd,
            current_price: currentAsset?.price
        };
        
        // Логируем попытку создания ордера
        if (window.Logger) {
            window.Logger.trade('Order submission', {
                symbol: currentAsset.symbol,
                side,
                type,
                quantity,
                isQuick,
                takeProfit,
                stopLoss,
                tradeDuration
            });
        }
        
        const result = await TradingAPI.createOrder(orderData);
        
        if (result.success) {
            const tpSlInfo = (takeProfit || stopLoss) ? 
                ` (TP: ${takeProfit || '—'}, SL: ${stopLoss || '—'})` : '';
            
            // Логируем успешное создание ордера
            if (window.Logger) {
                window.Logger.trade('Order created successfully', {
                    orderId: result.order?.id,
                    symbol: currentAsset.symbol,
                    side,
                    quantity,
                    isQuick
                });
            }
            
            NovaTrade.showToast(
                'Order created', 
                `${side === 'buy' ? 'Buy' : 'Sell'} $${amountUsd} ${currentAsset.symbol}${tpSlInfo}`,
                'success'
            );
            
            // Clear form
            if (isQuick) {
                document.getElementById('quickQuantity').value = '';
            } else {
                document.getElementById('amountUsd').value = '';
            }
            if (isQuick) {
                document.getElementById('quickTakeProfit').value = '';
                document.getElementById('quickStopLoss').value = '';
                if (typeof updateQuickTPSLInfo === 'function') updateQuickTPSLInfo();
            }
            
            // Update balances
            await loadUserBalances();
            
            // Обновляем баланс в header
            if (typeof Auth !== 'undefined' && typeof Auth.loadBalance === 'function') {
                await Auth.loadBalance();
            }
        } else {
            // Логируем ошибку создания ордера
            if (window.Logger) {
                window.Logger.trade('Order creation failed', {
                    symbol: currentAsset.symbol,
                    side,
                    error: result.error
                });
            }
            NovaTrade.showToast('Ошибка', result.error || 'Не удалось создать ордер', 'error');
        }
    } catch (error) {
        NovaTrade.showToast('Ошибка', error.message || 'Ошибка сервера', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

/**
 * Load user balances
 */
async function loadUserBalances(forceRefresh = false) {
    if (!Auth.isAuthenticated()) {
        console.log('[loadUserBalances] User not authenticated, skipping');
        return;
    }
    
    try {
        // Используем /api/wallet.php?action=balances как единый источник данных (Supabase)
        const url = forceRefresh 
            ? `/api/wallet.php?action=balances&_t=${Date.now()}`
            : '/api/wallet.php?action=balances';
        
        console.log('[loadUserBalances] Fetching balances from wallet.php...', { url });
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });
        
        if (!response.ok) {
            // Специальная обработка 401 - не прерываем выполнение, только логируем
            if (response.status === 401) {
                console.warn('[loadUserBalances] 401 Unauthorized - user may need to re-login');
                // Не выбрасываем ошибку, чтобы не прерывать другие функции
                return;
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('[loadUserBalances] API response received:', {
            success: result.success,
            balancesCount: result.balances?.length || 0,
            totalUsd: result.total_usd,
            balances: result.balances
        });
        
        if (result && result.success) {
            // New model: balance_available and balance_locked from users table
            const available = parseFloat(result.balance_available ?? result.balances?.[0]?.available ?? 0);
            userBalances.USD = available;
            
            if (result.balances && Array.isArray(result.balances)) {
                result.balances.forEach(balance => {
                    const currency = balance.currency || 'USD';
                    const av = parseFloat(balance.available) || 0;
                    if (currency === 'USD') userBalances.USD = av;
                    else userBalances[currency] = av;
                });
            }
            
            if (!userBalances.USD && result.total_usd !== undefined) {
                userBalances.USD = parseFloat(result.total_usd || 0);
            }
            
            // Получаем USD баланс
            const usdBalance = userBalances.USD || 0;
            const formattedBalance = usdBalance.toLocaleString('en-US', { minimumFractionDigits: 2 });
            console.log('[loadUserBalances] Formatted USD balance:', formattedBalance);
            
            // Update header balance (приоритет: обновляем из API)
            const headerBalance = document.getElementById('headerBalance');
            if (headerBalance) {
                headerBalance.textContent = formattedBalance;
                console.log('[loadUserBalances] Header balance updated to:', formattedBalance);
            } else {
                console.warn('[loadUserBalances] Header balance element not found');
            }
            
            // Update UI - обновляем отображение баланса в терминале
            const side = window.TradingState?.tradeSide || 'buy';
            updateTradeUI(side);
            
            console.log('[loadUserBalances] Balance loading completed successfully');
            balanceLoadRetries = 0; // Reset on success
        } else {
            console.warn('[loadUserBalances] API returned success=false or invalid response:', result);
            // Fallback: пытаемся получить баланс из Auth.getUser()
            const user = Auth.getUser();
            if (user && user.balance !== undefined) {
                const balance = parseFloat(user.balance) || 0;
                userBalances.USD = balance;
                const formattedBalance = balance.toLocaleString('en-US', { minimumFractionDigits: 2 });
                
                const headerBalance = document.getElementById('headerBalance');
                if (headerBalance) {
                    headerBalance.textContent = formattedBalance;
                    console.log('[loadUserBalances] Fallback: Updated header balance from Auth.getUser():', formattedBalance);
                }
                
                const side = window.TradingState?.tradeSide || 'buy';
                updateTradeUI(side);
            }
        }
    } catch (error) {
        if (balanceLoadRetries >= MAX_BALANCE_RETRIES) {
            console.warn('[loadUserBalances] Max retries reached, stopping balance polling');
            return;
        }
        balanceLoadRetries++;
        console.error('[loadUserBalances] Error loading balances:', error);
        // Fallback: пытаемся получить баланс из Auth.getUser()
        try {
            const user = Auth.getUser();
            if (user && user.balance !== undefined) {
                const balance = parseFloat(user.balance) || 0;
                userBalances.USD = balance;
                const formattedBalance = balance.toLocaleString('en-US', { minimumFractionDigits: 2 });
                
                const headerBalance = document.getElementById('headerBalance');
                if (headerBalance) {
                    headerBalance.textContent = formattedBalance;
                    console.log('[loadUserBalances] Error fallback: Updated header balance from Auth.getUser():', formattedBalance);
                }
                
                // Обновляем UI даже при ошибке, чтобы показать хотя бы fallback баланс
                const side = window.TradingState?.tradeSide || 'buy';
                updateTradeUI(side);
            }
        } catch (fallbackError) {
            console.error('[loadUserBalances] Fallback error:', fallbackError);
        }
    }
}

// WebSocket функции удалены - используем только API опрос для всех активов

/**
 * Обновление цены актива из API (fallback для акций и форекса)
 */
async function updateAssetPrice() {
    if (!currentAsset || !currentAsset.symbol) {
        console.warn('[updateAssetPrice] No current asset or symbol');
        return;
    }
    
    try {
        const symbol = currentAsset.symbol.toUpperCase();
        console.log(`[updateAssetPrice] Starting price update for ${symbol}, current price: $${currentAsset.price || 0}`);
        
        // Определяем тип рынка
        const cryptoSymbols = ['BTC', 'ETH', 'BNB', 'XRP', 'SOL', 'ADA', 'DOGE', 'DOT', 'MATIC', 'LTC'];
        const stockSymbols = ['AAPL', 'GOOGL', 'MSFT', 'AMZN', 'TSLA', 'META', 'NVDA', 'JPM', 'V', 'JNJ'];
        const forexSymbols = ['EURUSD', 'GBPUSD', 'USDJPY', 'USDCHF', 'AUDUSD', 'USDCAD', 'NZDUSD', 'EURGBP', 'EURJPY', 'GBPJPY'];
        
        let newPrice = null;
        
        if (cryptoSymbols.includes(symbol)) {
            // Криптовалюты: получаем из CoinGecko
            const cryptoData = await MarketAPI.getCryptoPrices();
            if (!cryptoData || !Array.isArray(cryptoData)) {
                console.error('Invalid crypto data received:', cryptoData);
                return;
            }
            
            const coin = cryptoData.find(c => {
                const coinSymbol = (c.symbol || '').toUpperCase();
                const coinId = (c.id || '').toLowerCase();
                return coinSymbol === symbol || 
                       (symbol === 'BTC' && (coinSymbol === 'BTC' || coinId === 'bitcoin')) ||
                       (symbol === 'ETH' && (coinSymbol === 'ETH' || coinId === 'ethereum')) ||
                       (symbol === 'BNB' && (coinSymbol === 'BNB' || coinId === 'binancecoin')) ||
                       (symbol === 'XRP' && (coinSymbol === 'XRP' || coinId === 'ripple')) ||
                       (symbol === 'SOL' && (coinSymbol === 'SOL' || coinId === 'solana')) ||
                       (symbol === 'ADA' && (coinSymbol === 'ADA' || coinId === 'cardano')) ||
                       (symbol === 'DOGE' && (coinSymbol === 'DOGE' || coinId === 'dogecoin')) ||
                       (symbol === 'DOT' && (coinSymbol === 'DOT' || coinId === 'polkadot')) ||
                       (symbol === 'MATIC' && (coinSymbol === 'MATIC' || coinId === 'polygon-ecosystem-token')) ||
                       (symbol === 'LTC' && (coinSymbol === 'LTC' || coinId === 'litecoin'));
            });
            
            if (coin && coin.current_price) {
                newPrice = coin.current_price;
                console.log(`[updateAssetPrice] Updated ${symbol} price from API: $${newPrice}`);
            } else {
                console.warn(`[updateAssetPrice] Coin not found for symbol ${symbol}`, cryptoData);
            }
        } else if (stockSymbols.includes(symbol)) {
            // Акции: получаем из Alpha Vantage или списка акций
            const stocksData = await MarketAPI.getStockPrices();
            console.log(`[updateAssetPrice] Fetched ${stocksData.length} stocks from API`);
            const stock = stocksData.find(s => s.symbol.toUpperCase() === symbol);
            if (stock && stock.price) {
                newPrice = stock.price;
                console.log(`[updateAssetPrice] Updated ${symbol} stock price from API: $${newPrice}`);
            } else {
                console.warn(`[updateAssetPrice] Stock not found for symbol ${symbol}`);
            }
        } else if (forexSymbols.includes(symbol)) {
            // Форекс: получаем из списка форекс
            const forexData = await MarketAPI.getForexRates();
            console.log(`[updateAssetPrice] Fetched ${forexData.length} forex pairs from API`);
            const forex = forexData.find(f => f.symbol.toUpperCase() === symbol);
            if (forex && forex.price) {
                newPrice = forex.price;
                console.log(`[updateAssetPrice] Updated ${symbol} forex price from API: $${newPrice}`);
            } else {
                console.warn(`[updateAssetPrice] Forex pair not found for symbol ${symbol}`);
            }
        } else {
            console.warn(`[updateAssetPrice] Unknown symbol type: ${symbol}`);
        }
        
        // Обновляем цену, если получили новую
        if (newPrice !== null && newPrice > 0) {
            const oldPrice = currentAsset.price || 0;
            
            // Обновляем цену только если она изменилась (более чем на 0.01%)
            if (oldPrice === 0 || Math.abs((newPrice - oldPrice) / oldPrice) > 0.0001) {
                currentAsset.price = newPrice;
                
                // Вычисляем изменение
                if (oldPrice > 0) {
                    currentAsset.change = ((newPrice - oldPrice) / oldPrice) * 100;
                } else {
                    // Если старая цена была 0, получаем изменение из API
                    if (cryptoSymbols.includes(symbol)) {
                        const cryptoData = await MarketAPI.getCryptoPrices();
                        const coin = cryptoData.find(c => {
                            const coinSymbol = (c.symbol || '').toUpperCase();
                            return coinSymbol === symbol;
                        });
                        if (coin && coin.price_change_percentage_24h !== undefined) {
                            currentAsset.change = coin.price_change_percentage_24h;
                        } else {
                            currentAsset.change = 0;
                        }
                    } else {
                        currentAsset.change = 0;
                    }
                }
                
                // Update global reference
                window.currentAsset = currentAsset;
                console.log(`[updateAssetPrice] Updated window.currentAsset.price to $${newPrice}`);
                
                // Обновляем цену в UI для обычной торговли
                const tradePriceEl = document.getElementById('tradePrice');
                if (tradePriceEl) {
                    const formattedPrice = NovaTrade.formatCurrency(newPrice);
                    tradePriceEl.textContent = formattedPrice;
                    console.log(`[updateAssetPrice] Updated tradePrice element: ${formattedPrice}`);
                } else {
                    console.warn('[updateAssetPrice] tradePrice element not found');
                }
                
                // Обновляем цену в UI для быстрой торговли (используем напрямую newPrice)
                const quickTradePriceEl = document.getElementById('quickTradePrice');
                if (quickTradePriceEl) {
                    const formattedPrice = NovaTrade.formatCurrency(newPrice);
                    quickTradePriceEl.textContent = formattedPrice;
                    console.log(`[updateAssetPrice] Updated quickTradePrice element: ${formattedPrice}`);
                    // Обновляем TP/SL и сводку для быстрой торговли
                    if (typeof window.updateQuickTradeTPSL === 'function') {
                        window.updateQuickTradeTPSL();
                    }
                    if (typeof window.updateQuickTradeSummary === 'function') {
                        window.updateQuickTradeSummary();
                    }
                } else {
                    console.warn('[updateAssetPrice] quickTradePrice element not found');
                }
                
                // Обновляем сводки
                if (typeof updateTradeSummary === 'function') {
                    updateTradeSummary();
                }
                
                // Update price display
                const priceEl = document.getElementById('currentPrice');
                const changeEl = document.getElementById('currentChange');
                
                if (priceEl) {
                    priceEl.textContent = NovaTrade.formatCurrency(currentAsset.price);
                }
                
                if (changeEl) {
                    if (oldPrice > 0) {
                        const changeAmount = newPrice - oldPrice;
                        changeEl.innerHTML = `
                            <i class="bi bi-caret-${currentAsset.change >= 0 ? 'up' : 'down'}-fill"></i>
                            ${currentAsset.change >= 0 ? '+' : ''}${currentAsset.change.toFixed(2)}% (${NovaTrade.formatCurrency(changeAmount)})
                        `;
                    } else {
                        // Если старая цена была 0, показываем только процент изменения
                        changeEl.innerHTML = `
                            <i class="bi bi-caret-${currentAsset.change >= 0 ? 'up' : 'down'}-fill"></i>
                            ${currentAsset.change >= 0 ? '+' : ''}${currentAsset.change.toFixed(2)}%
                        `;
                    }
                    changeEl.className = `change ${currentAsset.change >= 0 ? 'up' : 'down'}`;
                }
            }
        } else {
            console.warn(`Failed to get price for ${symbol}, newPrice:`, newPrice);
        }
    } catch (error) {
        console.error('[updateAssetPrice] Error updating asset price:', error);
        console.error('[updateAssetPrice] Error stack:', error.stack);
    }
}

/**
 * Realtime price updates from API
 */
let priceUpdateInterval = null;

function startPriceUpdates() {
    // Останавливаем предыдущий интервал, если он есть
    if (priceUpdateInterval) {
        clearInterval(priceUpdateInterval);
    }
    
    // Обновляем цену сразу для всех активов
    updateAssetPrice();
    
    // Обновляем цену каждые 2 секунды для всех активов (криптовалюты, акции, форекс)
    priceUpdateInterval = setInterval(() => {
        updateAssetPrice();
        
        // Update trade summary if quantity entered
        updateTradeSummary();
    }, 2000); // Update every 2 seconds
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
    loadUserBalances();
    startPriceUpdates();
});

/**
 * Asset dropdown functions
 */
let dropdownMarketFilter = 'all';

function toggleAssetDropdown() {
    const dropdown = document.getElementById('assetDropdown');
    const selector = document.getElementById('assetSelector');
    
    if (dropdown.classList.contains('show')) {
        closeAssetDropdown();
    } else {
        dropdown.classList.add('show');
        selector.classList.add('open');
        renderDropdownAssets();
        document.getElementById('dropdownSearch').focus();
    }
}

function closeAssetDropdown() {
    const dropdown = document.getElementById('assetDropdown');
    const selector = document.getElementById('assetSelector');
    dropdown.classList.remove('show');
    selector.classList.remove('open');
    document.getElementById('dropdownSearch').value = '';
    dropdownMarketFilter = 'all';
    
    // Reset tab buttons
    document.querySelectorAll('.asset-dropdown-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.filter === 'all');
    });
}

function filterByMarket(market) {
    dropdownMarketFilter = market;
    
    // Update tab buttons
    document.querySelectorAll('.asset-dropdown-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.filter === market);
    });
    
    renderDropdownAssets(document.getElementById('dropdownSearch').value);
}

function filterDropdownAssets(query) {
    renderDropdownAssets(query);
}

function renderDropdownAssets(searchQuery = '') {
    const container = document.getElementById('assetDropdownList');
    if (!container) return;
    
    let filteredAssets = assetList;
    
    // Filter by market
    if (dropdownMarketFilter !== 'all') {
        filteredAssets = filteredAssets.filter(a => a.market === dropdownMarketFilter);
    }
    
    // Filter by search query
    if (searchQuery) {
        const query = searchQuery.toLowerCase();
        filteredAssets = filteredAssets.filter(a => 
            a.symbol.toLowerCase().includes(query) ||
            a.name.toLowerCase().includes(query)
        );
    }
    
    if (filteredAssets.length === 0) {
        container.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--text-tertiary);">Ничего не найдено</div>';
        return;
    }
    
    container.innerHTML = filteredAssets.map(asset => {
        const iconClass = asset.market === 'crypto' ? 'crypto' : (asset.market === 'stocks' ? 'stocks' : 'forex');
        const isActive = asset.symbol === currentAsset.symbol;
        const changeClass = asset.change >= 0 ? 'up' : 'down';
        const changePrefix = asset.change >= 0 ? '+' : '';
        
        return `
            <div class="asset-dropdown-item ${isActive ? 'active' : ''}" onclick="selectAssetFromDropdown('${asset.symbol}')">
                <div class="asset-dropdown-item-left">
                    <div class="asset-dropdown-item-icon ${iconClass}">${asset.symbol.slice(0, 2)}</div>
                    <div class="asset-dropdown-item-info">
                        <div class="asset-dropdown-item-symbol">${asset.symbol}</div>
                        <div class="asset-dropdown-item-name">${asset.name}</div>
                    </div>
                </div>
                <div class="asset-dropdown-item-right">
                    <div class="asset-dropdown-item-price">${NovaTrade.formatCurrency(asset.price, 'USD', asset.price < 1 ? 6 : 2)}</div>
                    <div class="asset-dropdown-item-change ${changeClass}">${changePrefix}${asset.change.toFixed(2)}%</div>
                </div>
            </div>
        `;
    }).join('');
}

function selectAssetFromDropdown(symbol) {
    closeAssetDropdown();
    selectAsset(symbol);
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const wrapper = document.querySelector('.asset-selector-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        closeAssetDropdown();
    }
});

// Export
window.loadAssetList = loadAssetList;
window.selectAsset = selectAsset;
window.updateTradeUI = updateTradeUI;
window.calculateQuantityByPercent = calculateQuantityByPercent;
window.submitOrder = submitOrder;
window.filterAssetList = filterAssetList;
window.toggleAssetDropdown = toggleAssetDropdown;
window.closeAssetDropdown = closeAssetDropdown;
window.filterByMarket = filterByMarket;
window.filterDropdownAssets = filterDropdownAssets;
window.selectAssetFromDropdown = selectAssetFromDropdown;
window.updateAssetPrice = updateAssetPrice;
window.assetList = assetList; // Экспортируем assetList для использования в trade.html