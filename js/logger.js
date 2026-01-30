/**
 * ADX Finance - Модуль логирования событий
 * Логирует все события на сайте в реальном времени в localStorage
 */

const Logger = {
    MAX_LOGS: 1000,
    STORAGE_KEY: 'adx_finance_logs',
    ENABLED: true,
    
    /**
     * Основной метод логирования
     */
    log(type, message, data = null) {
        if (!this.ENABLED) return;
        
        try {
            const timestamp = new Date().toISOString();
            let logEntry = `[${timestamp}] [${type}] ${message}`;
            
            if (data) {
                // Форматируем данные для читаемости
                let dataStr = '';
                if (typeof data === 'object') {
                    try {
                        // Упрощаем объекты для читаемости
                        const simplified = this.simplifyObject(data);
                        dataStr = ' ' + JSON.stringify(simplified, null, 0);
                    } catch (e) {
                        dataStr = ' ' + String(data);
                    }
                } else {
                    dataStr = ' ' + String(data);
                }
                logEntry += dataStr;
            }
            
            // Сохраняем в localStorage
            this.saveLog(logEntry);
            
            // Также выводим в консоль для разработки
            if (type === 'ERROR' || type === 'API_ERROR') {
                console.error(logEntry);
            } else if (type === 'WARN') {
                console.warn(logEntry);
            } else {
                console.log(logEntry);
            }
        } catch (error) {
            // Если логирование не работает, не ломаем приложение
            console.error('Logger error:', error);
        }
    },
    
    /**
     * Упрощение объекта для логирования (убираем циклические ссылки, большие данные)
     */
    simplifyObject(obj, depth = 0) {
        if (depth > 3) return '[Max depth reached]';
        if (obj === null || obj === undefined) return obj;
        if (typeof obj !== 'object') return obj;
        if (Array.isArray(obj)) {
            return obj.slice(0, 10).map(item => this.simplifyObject(item, depth + 1));
        }
        
        const simplified = {};
        const keys = Object.keys(obj).slice(0, 20); // Ограничиваем количество ключей
        for (const key of keys) {
            const value = obj[key];
            if (typeof value === 'string' && value.length > 200) {
                simplified[key] = value.substring(0, 200) + '...';
            } else if (typeof value === 'object' && value !== null) {
                simplified[key] = this.simplifyObject(value, depth + 1);
            } else {
                simplified[key] = value;
            }
        }
        return simplified;
    },
    
    /**
     * Сохранение лога в localStorage
     */
    saveLog(entry) {
        try {
            // Получаем текущие логи
            const logs = this.getLogs();
            
            // Добавляем новую запись
            logs.push(entry);
            
            // Ограничиваем размер
            if (logs.length > this.MAX_LOGS) {
                // Удаляем самые старые записи (оставляем последние MAX_LOGS)
                logs.splice(0, logs.length - this.MAX_LOGS);
            }
            
            // Сохраняем обратно
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(logs));
        } catch (error) {
            // Если localStorage переполнен, очищаем старые логи
            if (error.name === 'QuotaExceededError') {
                console.warn('LocalStorage quota exceeded, clearing old logs');
                const logs = this.getLogs();
                // Оставляем только последние 500 записей
                const recentLogs = logs.slice(-500);
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(recentLogs));
            }
        }
    },
    
    /**
     * Получение всех логов
     */
    getLogs() {
        try {
            const saved = localStorage.getItem(this.STORAGE_KEY);
            return saved ? JSON.parse(saved) : [];
        } catch (error) {
            console.error('Error reading logs:', error);
            return [];
        }
    },
    
    /**
     * Очистка логов
     */
    clearLogs() {
        localStorage.removeItem(this.STORAGE_KEY);
        this.log('SYSTEM', 'Logs cleared');
    },
    
    /**
     * Экспорт логов в текстовый формат
     */
    exportLogs() {
        const logs = this.getLogs();
        return logs.join('\n');
    },
    
    /**
     * Действия пользователя
     */
    userAction(action, details = null) {
        this.log('USER_ACTION', action, details);
    },
    
    /**
     * API запрос
     */
    apiRequest(url, method, params = null, responseTime = null, status = null) {
        const data = {
            method,
            url,
            ...(params && { params: this.simplifyObject(params) }),
            ...(responseTime !== null && { responseTime: `${responseTime}ms` }),
            ...(status !== null && { status })
        };
        this.log('API_REQUEST', `${method} ${url}`, data);
    },
    
    /**
     * Ошибка API
     */
    apiError(url, error, details = null) {
        const data = {
            url,
            error: error?.message || String(error),
            ...(error?.stack && { stack: error.stack.split('\n').slice(0, 3).join(' | ') }),
            ...(details && { details: this.simplifyObject(details) })
        };
        this.log('API_ERROR', `Error in ${url}`, data);
    },
    
    /**
     * Ошибка JavaScript
     */
    error(message, error = null) {
        const data = {
            message,
            ...(error?.message && { error: error.message }),
            ...(error?.stack && { stack: error.stack.split('\n').slice(0, 5).join(' | ') }),
            ...(error?.name && { name: error.name })
        };
        this.log('ERROR', message, data);
    },
    
    /**
     * Предупреждение
     */
    warn(message, data = null) {
        this.log('WARN', message, data);
    },
    
    /**
     * Информационное сообщение
     */
    info(message, data = null) {
        this.log('INFO', message, data);
    },
    
    /**
     * Изменение состояния
     */
    stateChange(type, oldValue, newValue) {
        this.log('STATE_CHANGE', type, {
            oldValue: this.simplifyObject(oldValue),
            newValue: this.simplifyObject(newValue)
        });
    },
    
    /**
     * Навигация
     */
    navigation(from, to) {
        this.log('NAVIGATION', `From ${from} to ${to}`);
    },
    
    /**
     * Торговая операция
     */
    trade(action, details) {
        this.log('TRADE', action, this.simplifyObject(details));
    },
    
    /**
     * Инициализация логирования
     */
    init() {
        this.log('SYSTEM', 'Logger initialized');
        
        // Перехват глобальных ошибок
        window.addEventListener('error', (event) => {
            this.error('Global error', {
                message: event.message,
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                error: event.error
            });
        });
        
        // Перехват необработанных Promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            this.error('Unhandled promise rejection', event.reason);
        });
        
        // Логирование загрузки страницы
        this.log('SYSTEM', `Page loaded: ${window.location.href}`);
    }
};

// Экспорт
window.Logger = Logger;

// Автоматическая инициализация при загрузке
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        Logger.init();
    });
} else {
    Logger.init();
}
