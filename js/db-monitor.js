/**
 * ADX Finance - Database Connection Monitor
 * Мониторинг соединений с MySQL и Supabase с автоматическим восстановлением
 */

const DBMonitor = {
    CHECK_INTERVAL: 30000, // 30 секунд
    RECOVERY_INTERVAL: 1000, // 1 секунда между попытками восстановления
    MAX_RECOVERY_ATTEMPTS: 5,
    HEALTH_CHECK_URL: '/api/health.php',
    
    monitoringInterval: null,
    recoveryAttempts: 0,
    lastStatus: {
        mysql: null,
        supabase: null,
        timestamp: null
    },
    
    /**
     * Проверка соединений с базами данных
     */
    async checkConnections() {
        const fullUrl = this.HEALTH_CHECK_URL + '?_t=' + Date.now();
        
        try {
            const startTime = performance.now();
            
            // Создаем AbortController для таймаута (совместимость со старыми браузерами)
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            let response;
            try {
                response = await fetch(fullUrl, {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache'
                    },
                    cache: 'no-store',
                    signal: controller.signal
                });
            } catch (fetchError) {
                clearTimeout(timeoutId);
                const responseTime = Math.round(performance.now() - startTime);
                
                // Детальное логирование ошибки fetch
                if (window.Logger) {
                    window.Logger.error('Database health check failed (fetch error)', {
                        error: fetchError.message,
                        name: fetchError.name,
                        url: fullUrl,
                        responseTime: responseTime + 'ms',
                        type: fetchError.name === 'AbortError' ? 'timeout' : 'network'
                    });
                }
                
                throw new Error(`Network error: ${fetchError.message} (${fetchError.name})`);
            }
            
            clearTimeout(timeoutId);
            
            const responseTime = Math.round(performance.now() - startTime);
            
            // Проверяем статус ответа
            if (!response.ok) {
                // Пытаемся получить тело ответа для диагностики
                let responseText = '';
                try {
                    responseText = await response.text();
                } catch (e) {
                    responseText = 'Could not read response body';
                }
                
                // Детальное логирование ошибки HTTP
                if (window.Logger) {
                    window.Logger.error('Database health check failed (HTTP error)', {
                        status: response.status,
                        statusText: response.statusText,
                        url: fullUrl,
                        responseTime: responseTime + 'ms',
                        contentType: response.headers.get('content-type'),
                        responsePreview: responseText.substring(0, 200)
                    });
                }
                
                throw new Error(`HTTP ${response.status} ${response.statusText}: ${responseText.substring(0, 100)}`);
            }
            
            // Пытаемся распарсить JSON
            let data;
            try {
                const responseText = await response.text();
                data = JSON.parse(responseText);
            } catch (parseError) {
                // Детальное логирование ошибки парсинга
                if (window.Logger) {
                    window.Logger.error('Database health check failed (JSON parse error)', {
                        error: parseError.message,
                        url: fullUrl,
                        responseTime: responseTime + 'ms',
                        contentType: response.headers.get('content-type')
                    });
                }
                
                throw new Error(`Invalid JSON response: ${parseError.message}`);
            }
            
            const currentStatus = {
                mysql: data.mysql === true,
                supabase: data.supabase === true,
                timestamp: data.timestamp || new Date().toISOString(),
                responseTime
            };
            
            // Проверяем изменения статуса
            const mysqlChanged = this.lastStatus.mysql !== currentStatus.mysql;
            const supabaseChanged = this.lastStatus.supabase !== currentStatus.supabase;
            
            // Логируем изменения статуса
            if (mysqlChanged || supabaseChanged || !this.lastStatus.timestamp) {
                if (window.Logger) {
                    window.Logger.info('Database connection status changed', {
                        mysql: currentStatus.mysql,
                        supabase: currentStatus.supabase,
                        mysqlChanged,
                        supabaseChanged,
                        responseTime: currentStatus.responseTime
                    });
                }
            }
            
            // Если соединения восстановлены, сбрасываем счетчик попыток
            if (currentStatus.mysql && currentStatus.supabase) {
                if (this.recoveryAttempts > 0) {
                    if (window.Logger) {
                        window.Logger.info('Database connections recovered', {
                            recoveryAttempts: this.recoveryAttempts
                        });
                    }
                    this.recoveryAttempts = 0;
                }
            }
            
            // Если обнаружен обрыв соединения
            if (!currentStatus.mysql || !currentStatus.supabase) {
                if (window.Logger) {
                    window.Logger.warn('Database connection issue detected', {
                        mysql: currentStatus.mysql,
                        supabase: currentStatus.supabase,
                        mysqlError: data.mysql_error,
                        supabaseError: data.supabase_error
                    });
                }
                
                // Запускаем восстановление
                this.recoverConnection();
            }
            
            // Сохраняем статус
            this.lastStatus = currentStatus;
            this.saveStatus(currentStatus);
            
            return currentStatus;
        } catch (error) {
            // Ошибка сети, таймаут или парсинг
            const errorStatus = {
                mysql: false,
                supabase: false,
                timestamp: new Date().toISOString(),
                error: error.message,
                errorType: error.name,
                url: fullUrl
            };
            
            // Детальное логирование с полной информацией
            if (window.Logger) {
                window.Logger.error('Database health check failed', {
                    error: error.message,
                    name: error.name,
                    url: fullUrl,
                    stack: error.stack ? error.stack.split('\n').slice(0, 3).join(' | ') : null
                });
            } else {
                // Fallback логирование в консоль
                console.error('[DBMonitor] Health check failed:', {
                    error: error.message,
                    name: error.name,
                    url: fullUrl
                });
            }
            
            // Сохраняем статус ошибки
            this.lastStatus = errorStatus;
            this.saveStatus(errorStatus);
            
            // Запускаем восстановление
            this.recoverConnection();
            
            return errorStatus;
        }
    },
    
    /**
     * Автоматическое восстановление соединения
     */
    async recoverConnection() {
        if (this.recoveryAttempts >= this.MAX_RECOVERY_ATTEMPTS) {
            if (window.Logger) {
                window.Logger.error('Max recovery attempts reached', {
                    attempts: this.recoveryAttempts
                });
            }
            return;
        }
        
        this.recoveryAttempts++;
        
        // Экспоненциальная задержка: 1s, 2s, 4s, 8s, max 30s
        const delay = Math.min(1000 * Math.pow(2, this.recoveryAttempts - 1), 30000);
        
        if (window.Logger) {
            window.Logger.info('Attempting to recover database connection', {
                attempt: this.recoveryAttempts,
                maxAttempts: this.MAX_RECOVERY_ATTEMPTS,
                delay: delay + 'ms'
            });
        }
        
        setTimeout(async () => {
            const status = await this.checkConnections();
            
            // Если соединения восстановлены, сбрасываем счетчик
            if (status.mysql && status.supabase) {
                this.recoveryAttempts = 0;
            } else if (this.recoveryAttempts < this.MAX_RECOVERY_ATTEMPTS) {
                // Продолжаем попытки восстановления
                this.recoverConnection();
            }
        }, delay);
    },
    
    /**
     * Сохранение статуса в localStorage
     */
    saveStatus(status) {
        try {
            localStorage.setItem('db_monitor_status', JSON.stringify({
                ...status,
                lastCheck: Date.now()
            }));
        } catch (error) {
            // Игнорируем ошибки сохранения
            console.warn('Failed to save DB monitor status:', error);
        }
    },
    
    /**
     * Получение сохраненного статуса
     */
    getSavedStatus() {
        try {
            const saved = localStorage.getItem('db_monitor_status');
            if (saved) {
                return JSON.parse(saved);
            }
        } catch (error) {
            console.warn('Failed to read saved DB monitor status:', error);
        }
        return null;
    },
    
    /**
     * Запуск мониторинга
     */
    startMonitoring() {
        if (this.monitoringInterval) {
            // Мониторинг уже запущен
            return;
        }
        
        if (window.Logger) {
            window.Logger.info('Starting database connection monitoring', {
                interval: this.CHECK_INTERVAL + 'ms'
            });
        }
        
        // Первая проверка сразу
        this.checkConnections();
        
        // Периодические проверки
        this.monitoringInterval = setInterval(() => {
            this.checkConnections();
        }, this.CHECK_INTERVAL);
        
        // Проверка при возврате фокуса на вкладку
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Вкладка стала видимой - проверяем соединения
                this.checkConnections();
            }
        });
        
        // Проверка при фокусе окна
        window.addEventListener('focus', () => {
            this.checkConnections();
        });
    },
    
    /**
     * Остановка мониторинга
     */
    stopMonitoring() {
        if (this.monitoringInterval) {
            clearInterval(this.monitoringInterval);
            this.monitoringInterval = null;
            
            if (window.Logger) {
                window.Logger.info('Database connection monitoring stopped');
            }
        }
    },
    
    /**
     * Получение текущего статуса
     */
    getStatus() {
        return { ...this.lastStatus };
    },
    
    /**
     * Инициализация
     */
    init() {
        // Загружаем сохраненный статус
        const saved = this.getSavedStatus();
        if (saved) {
            this.lastStatus = saved;
        }
        
        // Запускаем мониторинг
        this.startMonitoring();
    }
};

// Экспорт
window.DBMonitor = DBMonitor;

// Автоматическая инициализация при загрузке
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        DBMonitor.init();
    });
} else {
    DBMonitor.init();
}
