/**
 * ADX Finance - Модуль графиков (TradingView Lightweight Charts)
 */

let chart = null;
let candlestickSeries = null;
let volumeSeries = null;
let currentSymbol = 'BTC';
let currentInterval = '5m';
let lastCandle = null;
let realtimeUpdateInterval = null;

// Интервалы в миллисекундах
const intervalMs = {
    '1m': 60 * 1000,
    '5m': 5 * 60 * 1000,
    '15m': 15 * 60 * 1000,
    '1h': 60 * 60 * 1000,
    '4h': 4 * 60 * 60 * 1000,
    '1d': 24 * 60 * 60 * 1000,
    '1w': 7 * 24 * 60 * 60 * 1000
};

/**
 * Инициализация графика
 */
async function initChart() {
    const container = document.getElementById('tradingChart');
    if (!container) return;
    
    // Получаем цвета темы
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    
    const chartOptions = {
        layout: {
            background: { type: 'solid', color: 'transparent' },
            textColor: isDark ? '#a1a1aa' : '#495057',
        },
        grid: {
            vertLines: { color: isDark ? '#1f1f25' : '#e9ecef' },
            horzLines: { color: isDark ? '#1f1f25' : '#e9ecef' },
        },
        crosshair: {
            mode: 1, // Normal mode
            vertLine: {
                width: 1,
                color: isDark ? '#6366f1' : '#5046e5',
                style: 1, // Dashed
            },
            horzLine: {
                width: 1,
                color: isDark ? '#6366f1' : '#5046e5',
                style: 1, // Dashed
            },
        },
        rightPriceScale: {
            borderColor: isDark ? '#2d2d35' : '#dee2e6',
        },
        timeScale: {
            borderColor: isDark ? '#2d2d35' : '#dee2e6',
            timeVisible: true,
            secondsVisible: false,
        },
        handleScroll: {
            mouseWheel: true,
            pressedMouseMove: true,
        },
        handleScale: {
            axisPressedMouseMove: true,
            mouseWheel: true,
            pinch: true,
        },
    };
    
    chart = LightweightCharts.createChart(container, chartOptions);
    
    // Свечной график (новый API v4+)
    candlestickSeries = chart.addSeries(LightweightCharts.CandlestickSeries, {
        upColor: isDark ? '#22c55e' : '#16a34a',
        downColor: isDark ? '#ef4444' : '#dc2626',
        borderUpColor: isDark ? '#22c55e' : '#16a34a',
        borderDownColor: isDark ? '#ef4444' : '#dc2626',
        wickUpColor: isDark ? '#22c55e' : '#16a34a',
        wickDownColor: isDark ? '#ef4444' : '#dc2626',
    });
    
    // Гистограмма объёмов (новый API v4+)
    volumeSeries = chart.addSeries(LightweightCharts.HistogramSeries, {
        color: isDark ? 'rgba(99, 102, 241, 0.3)' : 'rgba(80, 70, 229, 0.2)',
        priceFormat: {
            type: 'volume',
        },
        priceScaleId: 'volume',
    });
    
    // Настраиваем шкалу объёмов
    volumeSeries.priceScale().applyOptions({
        scaleMargins: {
            top: 0.85,
            bottom: 0,
        },
    });
    
    // Адаптивный размер с debounce для предотвращения частых обновлений
    let resizeTimeout = null;
    const resizeChart = () => {
        if (resizeTimeout) {
            clearTimeout(resizeTimeout);
        }
        resizeTimeout = setTimeout(() => {
            const width = container.clientWidth || container.offsetWidth || 800;
            // Ограничиваем максимальную высоту 450px
            const height = Math.min(container.clientHeight || container.offsetHeight || 450, 450);
            if (chart && width > 0 && height > 0) {
                chart.applyOptions({
                    width: width,
                    height: height,
                });
            }
        }, 150);
    };
    
    // Устанавливаем начальные размеры
    const initialWidth = container.clientWidth || container.offsetWidth || 800;
    // Ограничиваем максимальную высоту 450px
    const initialHeight = Math.min(container.clientHeight || container.offsetHeight || 450, 450);
    chart.applyOptions({
        width: initialWidth,
        height: initialHeight,
    });
    
    // Подписываемся на изменение размера окна
    window.addEventListener('resize', resizeChart);
    
    // Используем ResizeObserver для более точного отслеживания изменений размера контейнера
    if (window.ResizeObserver) {
        const resizeObserver = new ResizeObserver(() => {
            resizeChart();
        });
        resizeObserver.observe(container);
    } else {
        // Fallback для старых браузеров
        setTimeout(resizeChart, 100);
    }
    
    // Наблюдатель за изменением темы
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'data-theme') {
                updateChartTheme();
            }
        });
    });
    
    observer.observe(document.documentElement, { attributes: true });
}

/**
 * Обновление темы графика
 */
function updateChartTheme() {
    if (!chart) return;
    
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    
    chart.applyOptions({
        layout: {
            textColor: isDark ? '#a1a1aa' : '#495057',
        },
        grid: {
            vertLines: { color: isDark ? '#1f1f25' : '#e9ecef' },
            horzLines: { color: isDark ? '#1f1f25' : '#e9ecef' },
        },
        rightPriceScale: {
            borderColor: isDark ? '#2d2d35' : '#dee2e6',
        },
        timeScale: {
            borderColor: isDark ? '#2d2d35' : '#dee2e6',
        },
    });
    
    if (candlestickSeries) {
        candlestickSeries.applyOptions({
            upColor: isDark ? '#22c55e' : '#16a34a',
            downColor: isDark ? '#ef4444' : '#dc2626',
            borderUpColor: isDark ? '#22c55e' : '#16a34a',
            borderDownColor: isDark ? '#ef4444' : '#dc2626',
            wickUpColor: isDark ? '#22c55e' : '#16a34a',
            wickDownColor: isDark ? '#ef4444' : '#dc2626',
        });
    }
    
    if (volumeSeries) {
        volumeSeries.applyOptions({
            color: isDark ? 'rgba(99, 102, 241, 0.3)' : 'rgba(80, 70, 229, 0.2)',
        });
    }
}

/**
 * Загрузка данных графика
 */
async function loadChartData(symbol, interval) {
    if (!candlestickSeries || !volumeSeries) return;
    
    currentSymbol = symbol;
    currentInterval = interval;
    
    // Останавливаем предыдущее real-time обновление
    stopRealtimeUpdates();
    
    try {
        // Получаем исторические данные
        const data = await MarketAPI.getChartData(symbol, interval);
        
        if (!data || !Array.isArray(data) || data.length === 0) {
            console.error('No chart data received or data is empty');
            return;
        }
        
        console.log(`Formatting ${data.length} candles for chart`);
        
        // Форматируем данные для свечей
        const candleData = data.map(item => ({
            time: item.time,
            open: item.open,
            high: item.high,
            low: item.low,
            close: item.close,
        }));
        
        // Форматируем данные для объёмов
        const volumeData = data.map(item => ({
            time: item.time,
            value: item.volume || 0,
            color: item.close >= item.open 
                ? 'rgba(34, 197, 94, 0.5)' 
                : 'rgba(239, 68, 68, 0.5)',
        }));
        
        // Устанавливаем данные в график
        candlestickSeries.setData(candleData);
        volumeSeries.setData(volumeData);
        
        console.log('Chart data set successfully');
        
        // Центрируем на последних данных с небольшой задержкой для корректного отображения
        setTimeout(() => {
            if (chart && chart.timeScale()) {
                chart.timeScale().fitContent();
            }
        }, 50);
        
        // Сохраняем последнюю свечу для real-time обновлений
        if (data.length > 0) {
            lastCandle = { ...data[data.length - 1] };
            updatePriceDisplay(lastCandle.close, data);
        }
        
        // Запускаем real-time обновления
        startRealtimeUpdates();
        
        // WebSocket больше не используется - цены обновляются через API опрос
        
    } catch (error) {
        console.error('Error loading chart data:', error);
        console.error('Error details:', error.message, error.stack);
    }
}

/**
 * Запуск real-time обновлений
 */
function startRealtimeUpdates() {
    if (realtimeUpdateInterval) return;
    
    // Флаг для отслеживания, находится ли график в реальном времени (прокручен к концу)
    let isScrolledToRealTime = true;
    let userScrolled = false; // Флаг ручной прокрутки пользователем
    let lastScrollTime = Date.now();
    
    // Отслеживаем изменения скролла пользователем
    if (chart) {
        let subscriptionId = null;
        
        // Отслеживаем ручную прокрутку через события мыши и колесика
        const container = document.getElementById('tradingChart');
        if (container) {
            const handleUserInteraction = () => {
                userScrolled = true;
                lastScrollTime = Date.now();
                // Проверяем позицию через небольшую задержку после прокрутки
                setTimeout(() => {
                    if (chart && lastCandle) {
                        const visibleRange = chart.timeScale().getVisibleRange();
                        if (visibleRange) {
                            const lastTime = lastCandle.time;
                            const timeRange = visibleRange.to - visibleRange.from;
                            // Если пользователь прокрутил далеко от последней свечи, отключаем автоскролл
                            isScrolledToRealTime = (visibleRange.to >= lastTime - timeRange * 0.15);
                        }
                    }
                }, 100);
            };
            
            container.addEventListener('wheel', handleUserInteraction, { passive: true });
            container.addEventListener('mousedown', () => {
                const handleMouseMove = () => {
                    handleUserInteraction();
                    container.removeEventListener('mousemove', handleMouseMove);
                };
                container.addEventListener('mousemove', handleMouseMove, { once: true });
            });
        }
        
        // Подписка на изменения видимого диапазона (только для обновления флага, не для автоскролла)
        subscriptionId = chart.timeScale().subscribeVisibleTimeRangeChange(() => {
            // Игнорируем изменения, вызванные программно (scrollToRealTime)
            if (Date.now() - lastScrollTime < 200) {
                return; // Это программное изменение, игнорируем
            }
            
            if (userScrolled && chart && lastCandle) {
                const visibleRange = chart.timeScale().getVisibleRange();
                if (visibleRange) {
                    const lastTime = lastCandle.time;
                    const timeRange = visibleRange.to - visibleRange.from;
                    // Если пользователь прокрутил далеко от последней свечи, отключаем автоскролл
                    isScrolledToRealTime = (visibleRange.to >= lastTime - timeRange * 0.15);
                }
            }
        });
    }
    
    // WebSocket больше не используется - цены обновляются через API опрос в setInterval ниже
    
    realtimeUpdateInterval = setInterval(() => {
        if (!lastCandle || !candlestickSeries || !chart) return;
        
        const now = Date.now();
        const interval = intervalMs[currentInterval] || intervalMs['5m'];
        const currentCandleTime = Math.floor(now / interval) * interval;
        const lastCandleTimeMs = lastCandle.time * 1000;
        
        // Получаем текущую цену из trading.js если доступна (из WebSocket или API)
        const currentPrice = window.currentAsset?.price || lastCandle.close;
        
        // Проверяем, нужно ли создать новую свечу
        if (currentCandleTime > lastCandleTimeMs) {
            // Создаём новую свечу
            const newCandleTime = Math.floor(currentCandleTime / 1000);
            lastCandle = {
                time: newCandleTime,
                open: currentPrice,
                high: currentPrice,
                low: currentPrice,
                close: currentPrice,
                volume: Math.floor(Math.random() * 100000)
            };
            
            // Добавляем новую свечу через update (автоматически создастся если не существует)
            candlestickSeries.update({
                time: lastCandle.time,
                open: lastCandle.open,
                high: lastCandle.high,
                low: lastCandle.low,
                close: lastCandle.close
            });
            
            volumeSeries.update({
                time: lastCandle.time,
                value: lastCandle.volume,
                color: lastCandle.close >= lastCandle.open 
                    ? 'rgba(34, 197, 94, 0.5)' 
                    : 'rgba(239, 68, 68, 0.5)'
            });
            
            // Прокручиваем к реальному времени только если пользователь был в реальном времени
            // И только если прошло достаточно времени с последней ручной прокрутки
            if (isScrolledToRealTime && !userScrolled) {
                try {
                    chart.timeScale().scrollToRealTime();
                } catch (e) {
                    // Игнорируем ошибки скролла
                }
            } else if (userScrolled && Date.now() - lastScrollTime > 5000) {
                // Если пользователь не прокручивал 5 секунд, снова включаем автоскролл
                userScrolled = false;
                isScrolledToRealTime = true;
            }
        } else {
            // Обновляем текущую свечу
            lastCandle.close = currentPrice;
            lastCandle.high = Math.max(lastCandle.high, currentPrice);
            lastCandle.low = Math.min(lastCandle.low, currentPrice);
            lastCandle.volume += Math.floor(Math.random() * 1000);
            
            // Обновляем существующую свечу БЕЗ изменения позиции скролла
            candlestickSeries.update({
                time: lastCandle.time,
                open: lastCandle.open,
                high: lastCandle.high,
                low: lastCandle.low,
                close: lastCandle.close
            });
            
            volumeSeries.update({
                time: lastCandle.time,
                value: lastCandle.volume,
                color: lastCandle.close >= lastCandle.open 
                    ? 'rgba(34, 197, 94, 0.5)' 
                    : 'rgba(239, 68, 68, 0.5)'
            });
        }
        
        // Обновляем отображение цены
        updatePriceDisplay(lastCandle.close, null);
        
    }, 1000); // Обновляем каждую секунду
}

/**
 * Остановка real-time обновлений
 */
function stopRealtimeUpdates() {
    if (realtimeUpdateInterval) {
        clearInterval(realtimeUpdateInterval);
        realtimeUpdateInterval = null;
    }
    
    // WebSocket больше не используется
}

/**
 * Обновление графика с реальной ценой из WebSocket
 */
function updateChartWithPrice(price, currentCandleTime, lastCandleTimeMs) {
    if (!lastCandle || !candlestickSeries || !chart) return;
    
    // Проверяем, нужно ли создать новую свечу
    if (currentCandleTime > lastCandleTimeMs) {
        // Создаём новую свечу
        const newCandleTime = Math.floor(currentCandleTime / 1000);
        lastCandle = {
            time: newCandleTime,
            open: price,
            high: price,
            low: price,
            close: price,
            volume: Math.floor(Math.random() * 100000)
        };
        
        // Добавляем новую свечу
        candlestickSeries.update({
            time: lastCandle.time,
            open: lastCandle.open,
            high: lastCandle.high,
            low: lastCandle.low,
            close: lastCandle.close
        });
        
        volumeSeries.update({
            time: lastCandle.time,
            value: lastCandle.volume,
            color: lastCandle.close >= lastCandle.open 
                ? 'rgba(34, 197, 94, 0.5)' 
                : 'rgba(239, 68, 68, 0.5)'
        });
        
        // Прокручиваем к реальному времени
        if (isScrolledToRealTime && !userScrolled) {
            try {
                chart.timeScale().scrollToRealTime();
            } catch (e) {
                // Игнорируем ошибки скролла
            }
        }
    } else {
        // Обновляем текущую свечу
        lastCandle.close = price;
        lastCandle.high = Math.max(lastCandle.high, price);
        lastCandle.low = Math.min(lastCandle.low, price);
        lastCandle.volume += Math.floor(Math.random() * 1000);
        
        // Обновляем существующую свечу
        candlestickSeries.update({
            time: lastCandle.time,
            open: lastCandle.open,
            high: lastCandle.high,
            low: lastCandle.low,
            close: lastCandle.close
        });
        
        volumeSeries.update({
            time: lastCandle.time,
            value: lastCandle.volume,
            color: lastCandle.close >= lastCandle.open 
                ? 'rgba(34, 197, 94, 0.5)' 
                : 'rgba(239, 68, 68, 0.5)'
        });
    }
    
    // Обновляем отображение цены
    updatePriceDisplay(price, null);
}

/**
 * Обновление отображения цены
 */
function updatePriceDisplay(price, data) {
    const priceEl = document.getElementById('currentPrice');
    const changeEl = document.getElementById('currentChange');
    
    if (!priceEl || !changeEl) return;
    
    // Используем актуальную цену из currentAsset, если она доступна
    const actualPrice = window.currentAsset?.price || price;
    priceEl.textContent = NovaTrade.formatCurrency(actualPrice);
    
    // Обновляем currentAsset.price, если она отличается
    if (window.currentAsset && Math.abs((window.currentAsset.price || 0) - actualPrice) > 0.01) {
        window.currentAsset.price = actualPrice;
    }
    
    // Вычисляем изменение за период
    if (data && data.length > 1) {
        const firstPrice = data[0].open;
        const change = ((actualPrice - firstPrice) / firstPrice) * 100;
        const changeAmount = actualPrice - firstPrice;
        
        changeEl.innerHTML = `
            <i class="bi bi-caret-${change >= 0 ? 'up' : 'down'}-fill"></i>
            ${change >= 0 ? '+' : ''}${change.toFixed(2)}% (${NovaTrade.formatCurrency(changeAmount)})
        `;
        changeEl.className = `change ${change >= 0 ? 'up' : 'down'}`;
    } else if (window.currentAsset && window.currentAsset.change !== undefined) {
        // Если данных нет, используем изменение из currentAsset
        const change = window.currentAsset.change;
        const changeAmount = actualPrice * (change / 100);
        
        changeEl.innerHTML = `
            <i class="bi bi-caret-${change >= 0 ? 'up' : 'down'}-fill"></i>
            ${change >= 0 ? '+' : ''}${change.toFixed(2)}% (${NovaTrade.formatCurrency(changeAmount)})
        `;
        changeEl.className = `change ${change >= 0 ? 'up' : 'down'}`;
    }
}

/**
 * Обновление интервала графика
 */
function updateChartInterval(interval) {
    currentInterval = interval;
    loadChartData(currentSymbol, interval);
}

/**
 * Обновление данных в реальном времени
 */
function updateChartRealtime(tick) {
    if (!candlestickSeries) return;
    
    candlestickSeries.update({
        time: tick.time,
        open: tick.open,
        high: tick.high,
        low: tick.low,
        close: tick.close,
    });
    
    if (volumeSeries) {
        volumeSeries.update({
            time: tick.time,
            value: tick.volume,
            color: tick.close >= tick.open 
                ? 'rgba(34, 197, 94, 0.5)' 
                : 'rgba(239, 68, 68, 0.5)',
        });
    }
}

/**
 * Добавление линии на график
 */
function addPriceLine(price, title, color = '#6366f1') {
    if (!candlestickSeries) return null;
    
    return candlestickSeries.createPriceLine({
        price: price,
        color: color,
        lineWidth: 1,
        lineStyle: 1, // Dashed
        axisLabelVisible: true,
        title: title,
    });
}

/**
 * Удаление линии с графика
 */
function removePriceLine(line) {
    if (!candlestickSeries || !line) return;
    candlestickSeries.removePriceLine(line);
}

/**
 * Создание мини-графика (спарклайн)
 */
function createMiniChart(container, data, isPositive = true) {
    const miniChart = LightweightCharts.createChart(container, {
        width: container.clientWidth,
        height: container.clientHeight,
        layout: {
            background: { type: 'solid', color: 'transparent' },
        },
        grid: {
            vertLines: { visible: false },
            horzLines: { visible: false },
        },
        rightPriceScale: { visible: false },
        timeScale: { visible: false },
        crosshair: { mode: 0 },
        handleScroll: false,
        handleScale: false,
    });
    
    const series = miniChart.addSeries(LightweightCharts.AreaSeries, {
        lineColor: isPositive ? '#22c55e' : '#ef4444',
        topColor: isPositive ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)',
        bottomColor: isPositive ? 'rgba(34, 197, 94, 0)' : 'rgba(239, 68, 68, 0)',
        lineWidth: 2,
        crosshairMarkerVisible: false,
    });
    
    const chartData = data.map((value, index) => ({
        time: Math.floor(Date.now() / 1000) - (data.length - index) * 3600,
        value: value,
    }));
    
    series.setData(chartData);
    miniChart.timeScale().fitContent();
    
    return miniChart;
}

// Экспорт
window.initChart = initChart;
window.loadChartData = loadChartData;
window.updateChartInterval = updateChartInterval;
window.updateChartRealtime = updateChartRealtime;
window.addPriceLine = addPriceLine;
window.removePriceLine = removePriceLine;
window.createMiniChart = createMiniChart;
window.startRealtimeUpdates = startRealtimeUpdates;
window.stopRealtimeUpdates = stopRealtimeUpdates;