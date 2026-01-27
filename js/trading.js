/**
 * ADX Finance - Trading Module
 */

let currentAsset = {
    symbol: 'BTC',
    name: 'Bitcoin',
    price: 43250.00,
    change: 2.45,
    decimals: 8
};

// Make currentAsset globally available
window.currentAsset = currentAsset;

let userBalances = {
    USD: 10000.00,
    BTC: 0,
    ETH: 0
};

let tradeSide = 'buy';
let orderType = 'market';
let assetList = [];

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
    
    // Update global reference for charts.js
    window.currentAsset = currentAsset;
    
    // Update UI
    document.getElementById('currentAsset').textContent = asset.market === 'forex' ? asset.symbol : `${asset.symbol}/USD`;
    document.getElementById('currentAssetName').textContent = asset.name;
    document.getElementById('quantitySuffix').textContent = asset.symbol;
    
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
    
    // Load chart with current selected interval
    const activeInterval = document.querySelector('.chart-timeframe.active');
    const interval = activeInterval ? activeInterval.dataset.interval : '4h';
    await loadChartData(symbol, interval);
    
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
    tradeSide = side;
    
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
    
    // Update available balance
    const availableElId = isQuick ? 'quickAvailableBalance' : 'availableBalance';
    const availableEl = document.getElementById(availableElId);
    if (availableEl) {
        if (side === 'buy') {
            availableEl.textContent = NovaTrade.formatCurrency(userBalances.USD);
        } else {
            const assetBalance = userBalances[currentAsset.symbol] || 0;
            availableEl.textContent = `${assetBalance.toFixed(8)} ${currentAsset.symbol}`;
        }
    }
    
    // Update suffix
    const quantitySuffixId = isQuick ? 'quickQuantitySuffix' : 'quantitySuffix';
    const quantitySuffix = document.getElementById(quantitySuffixId);
    if (quantitySuffix) {
        quantitySuffix.textContent = currentAsset.symbol;
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
        submitBtn.innerHTML = `
            <i class="bi bi-${tradeSide === 'buy' ? 'cart-plus' : 'cart-dash'}"></i>
            ${tradeSide === 'buy' ? 'Купить' : 'Продать'} ${currentAsset.symbol}
        `;
    }
}

/**
 * Calculate quantity by percent
 */
function calculateQuantityByPercent(percent, mode = 'normal') {
    const quantityInputId = mode === 'quick' ? 'quickQuantity' : 'quantity';
    const quantityInput = document.getElementById(quantityInputId);
    if (!quantityInput) return;
    
    // Получаем активную сторону из соответствующей формы
    const modeSelector = mode === 'quick' ? '#quickTradeMode' : '#normalTradeMode';
    const activeTab = document.querySelector(`${modeSelector} .trade-tab.active`);
    const side = activeTab?.dataset.side || tradeSide;
    
    // Если percent === 'max', используем весь доступный баланс
    if (percent === 'max' || percent === 'max') {
        if (side === 'buy') {
            const availableUSD = userBalances.USD || 0;
            // Для покупки: используем весь доступный USD с учетом комиссии
            // Комиссия 0.1%, значит можем использовать 99.9% баланса
            const usableUSD = availableUSD * 0.999; // Оставляем 0.1% на комиссию
            const maxQuantity = usableUSD / currentAsset.price;
            quantityInput.value = Math.max(0, maxQuantity).toFixed(8);
        } else {
            // Для продажи: используем весь доступный баланс актива
            const availableAsset = userBalances[currentAsset.symbol] || 0;
            quantityInput.value = Math.max(0, availableAsset).toFixed(8);
        }
    } else {
        // Обычный расчет по проценту
        if (side === 'buy') {
            const availableUSD = userBalances.USD || 0;
            const maxQuantity = availableUSD / currentAsset.price;
            quantityInput.value = (maxQuantity * percent).toFixed(8);
        } else {
            const availableAsset = userBalances[currentAsset.symbol] || 0;
            quantityInput.value = (availableAsset * percent).toFixed(8);
        }
    }
    
    if (mode === 'quick') {
        updateQuickTradeSummary();
    } else {
        updateTradeSummary();
    }
}

/**
 * Update trade summary
 */
function updateTradeSummary() {
    const quantity = parseFloat(document.getElementById('quantity')?.value) || 0;
    const limitPrice = parseFloat(document.getElementById('limitPrice')?.value) || currentAsset.price;
    const price = orderType === 'limit' ? limitPrice : currentAsset.price;
    
    const total = quantity * price;
    const fee = total * 0.001; // 0.1% fee
    const finalTotal = tradeSide === 'buy' ? total + fee : total - fee;
    
    // Update display
    document.getElementById('tradePrice').textContent = NovaTrade.formatCurrency(price);
    document.getElementById('tradeFee').textContent = NovaTrade.formatCurrency(fee);
    document.getElementById('tradeTotal').textContent = NovaTrade.formatCurrency(finalTotal);
}

/**
 * Update quick trade summary
 */
function updateQuickTradeSummary() {
    const quantity = parseFloat(document.getElementById('quickQuantity')?.value) || 0;
    const limitPrice = parseFloat(document.getElementById('quickLimitPrice')?.value) || currentAsset.price;
    const activeOrderType = document.querySelector('#quickTradeForm .tab.active');
    const type = activeOrderType?.dataset.type || 'market';
    const price = type === 'limit' ? limitPrice : currentAsset.price;
    
    // Получаем активную сторону
    const activeTab = document.querySelector('#quickTradeMode .trade-tab.active');
    const side = activeTab?.dataset.side || 'buy';
    
    const total = quantity * price;
    const fee = total * 0.001; // 0.1% fee
    const finalTotal = side === 'buy' ? total + fee : total - fee;
    
    // Update display
    const priceEl = document.getElementById('quickTradePrice');
    const feeEl = document.getElementById('quickTradeFee');
    const totalEl = document.getElementById('quickTradeTotal');
    
    if (priceEl) priceEl.textContent = NovaTrade.formatCurrency(price);
    if (feeEl) feeEl.textContent = NovaTrade.formatCurrency(fee);
    if (totalEl) totalEl.textContent = NovaTrade.formatCurrency(finalTotal);
}

window.updateQuickTradeSummary = updateQuickTradeSummary;

/**
 * Submit order
 */
async function submitOrder(isQuick = false) {
    const quantityId = isQuick ? 'quickQuantity' : 'quantity';
    const quantity = parseFloat(document.getElementById(quantityId)?.value);
    
    if (!quantity || quantity <= 0) {
        NovaTrade.showToast('Ошибка', 'Введите количество', 'error');
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
        const side = activeMode?.dataset.side || tradeSide;
        
        // Получаем тип ордера
        const activeOrderType = isQuick ? document.querySelector('#quickTradeForm .tab.active') : document.querySelector('#tradeForm .tab.active');
        const type = activeOrderType?.dataset.type || orderType;
        
        const orderData = {
            symbol: currentAsset.symbol,
            side: side,
            type: type,
            quantity: quantity,
            price: type === 'limit' ? limitPrice : null,
            current_price: currentAsset.price || null, // Отправляем текущую цену для валидации
            take_profit: takeProfit || null,
            stop_loss: stopLoss || null
        };
        
        const result = await TradingAPI.createOrder(orderData);
        
        if (result.success) {
            const tpSlInfo = (takeProfit || stopLoss) ? 
                ` (TP: ${takeProfit || '—'}, SL: ${stopLoss || '—'})` : '';
            
            NovaTrade.showToast(
                'Ордер создан', 
                `${side === 'buy' ? 'Покупка' : 'Продажа'} ${quantity} ${currentAsset.symbol}${tpSlInfo}`,
                'success'
            );
            
            // Clear form
            document.getElementById(quantityId).value = '';
            if (isQuick) {
                document.getElementById('quickTakeProfit').value = '';
                document.getElementById('quickStopLoss').value = '';
                if (typeof updateQuickTPSLInfo === 'function') updateQuickTPSLInfo();
            }
            
            // Update balances
            await loadUserBalances();
        } else {
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
async function loadUserBalances() {
    if (!Auth.isAuthenticated()) return;
    
    try {
        const result = await Auth.fetchUser();
        
        if (result && result.balances) {
            result.balances.forEach(balance => {
                userBalances[balance.currency] = parseFloat(balance.available) || 0;
            });
            
            // Update UI
            updateTradeUI(tradeSide);
            
            // Update header balance
            const headerBalance = document.getElementById('headerBalance');
            if (headerBalance) {
                headerBalance.textContent = (userBalances.USD || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
            }
        }
    } catch (error) {
        console.error('Error loading balances:', error);
    }
}

/**
 * Realtime price updates simulation
 */
function startPriceUpdates() {
    setInterval(() => {
        // Simulate small price change
        const change = (Math.random() - 0.5) * 0.002;
        currentAsset.price = currentAsset.price * (1 + change);
        
        // Update global reference
        window.currentAsset = currentAsset;
        
        // Update price display
        const priceEl = document.getElementById('currentPrice');
        if (priceEl) {
            priceEl.textContent = NovaTrade.formatCurrency(currentAsset.price);
        }
        
        // Update trade summary if quantity entered
        updateTradeSummary();
    }, 1500); // Update every 1.5 seconds
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