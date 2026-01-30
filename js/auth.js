/**
 * ADX Finance - Модуль авторизации
 */

const Auth = {
    TOKEN_KEY: 'novatrade_token',
    USER_KEY: 'novatrade_user',
    
    /**
     * Проверка авторизации
     */
    isAuthenticated() {
        return !!this.getToken();
    },
    
    /**
     * Получение токена
     */
    getToken() {
        return localStorage.getItem(this.TOKEN_KEY);
    },
    
    /**
     * Сохранение токена
     */
    setToken(token) {
        localStorage.setItem(this.TOKEN_KEY, token);
    },
    
    /**
     * Получение данных пользователя
     */
    getUser() {
        const userData = localStorage.getItem(this.USER_KEY);
        return userData ? JSON.parse(userData) : null;
    },
    
    /**
     * Сохранение данных пользователя
     */
    setUser(user) {
        localStorage.setItem(this.USER_KEY, JSON.stringify(user));
        
        // Если баланс сохранен в user, обновляем его в шапке сразу
        if (user && user.balance !== undefined) {
            const headerBalance = document.getElementById('headerBalance');
            if (headerBalance) {
                const formatted = parseFloat(user.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                headerBalance.textContent = formatted;
            }
        }
    },
    
    /**
     * Регистрация
     */
    async register(data) {
        try {
            const response = await fetch('/api/auth.php?action=register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            // Читаем ответ один раз
            let result;
            try {
                const text = await response.text();
                
                // Проверяем HTTP статус
                if (!response.ok) {
                    // Пытаемся распарсить как JSON для получения сообщения об ошибке
                    try {
                        result = JSON.parse(text);
                        return { 
                            success: false, 
                            error: result.error || `Ошибка сервера: ${response.status} ${response.statusText}` 
                        };
                    } catch (parseError) {
                        // Если не JSON, возвращаем общую ошибку
                        return { 
                            success: false, 
                            error: `Ошибка сервера: ${response.status} ${response.statusText}` 
                        };
                    }
                }
                
                // Парсим JSON ответ
                if (!text) {
                    return { 
                        success: false, 
                        error: 'Пустой ответ от сервера' 
                    };
                }
                
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                return { 
                    success: false, 
                    error: 'Ошибка обработки ответа сервера' 
                };
            }
            
            if (result.success) {
                if (!result.token) {
                    console.error('Registration successful but token missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: токен не получен от сервера' 
                    };
                }
                
                if (!result.user) {
                    console.error('Registration successful but user data missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: данные пользователя не получены от сервера' 
                    };
                }
                
                // Сохраняем токен и данные пользователя
                this.setToken(result.token);
                this.setUser(result.user);
                
                // Загружаем баланс после успешной регистрации
                if (result.success) {
                    setTimeout(() => {
                        this.loadBalance();
                    }, 100);
                }
                
                // Проверяем, что токен действительно был сохранен
                const savedToken = this.getToken();
                if (!savedToken || savedToken !== result.token) {
                    console.error('Token not saved correctly. Expected:', result.token, 'Got:', savedToken);
                    return { 
                        success: false, 
                        error: 'Ошибка сохранения токена' 
                    };
                }
            }
            
            return result;
        } catch (error) {
            console.error('Registration request error:', error);
            return { 
                success: false, 
                error: error.message || 'Ошибка подключения к серверу' 
            };
        }
    },
    
    /**
     * Вход
     */
    async login(email, password, remember = false) {
        try {
            const response = await fetch('/api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, password, remember })
            });
            
            // Читаем ответ один раз
            let result;
            try {
                const text = await response.text();
                
                // Проверяем HTTP статус
                if (!response.ok) {
                    // Пытаемся распарсить как JSON для получения сообщения об ошибке
                    try {
                        result = JSON.parse(text);
                        return { 
                            success: false, 
                            error: result.error || `Ошибка сервера: ${response.status} ${response.statusText}` 
                        };
                    } catch (parseError) {
                        // Если не JSON, возвращаем общую ошибку
                        return { 
                            success: false, 
                            error: `Ошибка сервера: ${response.status} ${response.statusText}` 
                        };
                    }
                }
                
                // Парсим JSON ответ
                if (!text) {
                    return { 
                        success: false, 
                        error: 'Пустой ответ от сервера' 
                    };
                }
                
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                return { 
                    success: false, 
                    error: 'Ошибка обработки ответа сервера' 
                };
            }
            
            // Если требуется 2FA, возвращаем результат без установки токена
            if (result.success && result.requires_2fa) {
                // Проверяем наличие обязательных полей для 2FA
                if (!result.tfa_token) {
                    console.error('2FA required but tfa_token missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: токен 2FA не получен' 
                    };
                }
                return result;
            }
            
            // Обычный вход без 2FA - проверяем наличие обязательных полей
            if (result.success) {
                if (!result.token) {
                    console.error('Login successful but token missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: токен не получен от сервера' 
                    };
                }
                
                if (!result.user) {
                    console.error('Login successful but user data missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: данные пользователя не получены от сервера' 
                    };
                }
                
                // Сохраняем токен и данные пользователя
                this.setToken(result.token);
                this.setUser(result.user);
                
                // Загружаем баланс после успешного входа
                if (result.success) {
                    setTimeout(() => {
                        this.loadBalance();
                    }, 100);
                }
                
                // Проверяем, что токен действительно был сохранен
                const savedToken = this.getToken();
                if (!savedToken || savedToken !== result.token) {
                    console.error('Token not saved correctly. Expected:', result.token, 'Got:', savedToken);
                    return { 
                        success: false, 
                        error: 'Ошибка сохранения токена' 
                    };
                }
            }
            
            return result;
        } catch (error) {
            console.error('Login request error:', error);
            return { 
                success: false, 
                error: error.message || 'Ошибка подключения к серверу' 
            };
        }
    },
    
    /**
     * Вход с 2FA кодом
     */
    async login2FA(tfaToken, code, remember = false) {
        try {
            const response = await fetch('/api/auth.php?action=login_2fa', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ tfa_token: tfaToken, code, remember })
            });
            
            // Читаем ответ один раз
            let result;
            try {
                const text = await response.text();
                
                // Проверяем HTTP статус
                if (!response.ok) {
                    // Пытаемся распарсить как JSON для получения сообщения об ошибке
                    try {
                        result = JSON.parse(text);
                        return { 
                            success: false, 
                            error: result.error || `Ошибка сервера: ${response.status} ${response.statusText}` 
                        };
                    } catch (parseError) {
                        // Если не JSON, возвращаем общую ошибку
                        return { 
                            success: false, 
                            error: `Ошибка сервера: ${response.status} ${response.statusText}` 
                        };
                    }
                }
                
                // Парсим JSON ответ
                if (!text) {
                    return { 
                        success: false, 
                        error: 'Пустой ответ от сервера' 
                    };
                }
                
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                return { 
                    success: false, 
                    error: 'Ошибка обработки ответа сервера' 
                };
            }
            
            if (result.success) {
                if (!result.token) {
                    console.error('2FA login successful but token missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: токен не получен от сервера' 
                    };
                }
                
                if (!result.user) {
                    console.error('2FA login successful but user data missing:', result);
                    return { 
                        success: false, 
                        error: 'Ошибка: данные пользователя не получены от сервера' 
                    };
                }
                
                // Сохраняем токен и данные пользователя
                this.setToken(result.token);
                this.setUser(result.user);
                
                // Проверяем, что токен действительно был сохранен
                const savedToken = this.getToken();
                if (!savedToken || savedToken !== result.token) {
                    console.error('Token not saved correctly. Expected:', result.token, 'Got:', savedToken);
                    return { 
                        success: false, 
                        error: 'Ошибка сохранения токена' 
                    };
                }
            }
            
            return result;
        } catch (error) {
            console.error('2FA login request error:', error);
            return { 
                success: false, 
                error: error.message || 'Ошибка подключения к серверу' 
            };
        }
    },
    
    /**
     * Выход
     */
    async logout() {
        try {
            const token = this.getToken();
            
            if (token) {
                await fetch('/api/auth.php?action=logout', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });
            }
        } catch (e) {
            // Игнорируем ошибки при выходе
        } finally {
            localStorage.removeItem(this.TOKEN_KEY);
            localStorage.removeItem(this.USER_KEY);
            window.location.href = 'login.html';
        }
    },
    
    /**
     * Получение данных текущего пользователя с сервера
     */
    async fetchUser() {
        const token = this.getToken();
        
        if (!token) {
            return null;
        }
        
        try {
            const response = await fetch('/api/auth.php?action=me', {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            // Проверяем HTTP статус
            if (!response.ok) {
                // Если токен недействителен, очищаем данные
                if (response.status === 401) {
                    localStorage.removeItem(this.TOKEN_KEY);
                    localStorage.removeItem(this.USER_KEY);
                }
                return null;
            }
            
            // Парсим JSON ответ
            let result;
            try {
                const text = await response.text();
                if (!text) {
                    return null;
                }
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error in fetchUser:', parseError);
                return null;
            }
            
            if (result.success && result.user) {
                // Сохраняем баланс в user объект если он есть в результате
                if (result.balances) {
                    const usd = result.balances.find(b => b.currency === 'USD');
                    if (usd) {
                        result.user.balance = parseFloat(usd.available || 0);
                    }
                }
                this.setUser(result.user);
                return result;
            }
            
            return null;
        } catch (error) {
            console.error('fetchUser error:', error);
            return null;
        }
    },
    
    /**
     * Проверка токена
     */
    async checkAuth() {
        const token = this.getToken();
        
        if (!token) {
            return false;
        }
        
        try {
            const response = await fetch('/api/auth.php?action=check', {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            // Проверяем HTTP статус
            if (!response.ok) {
                return false;
            }
            
            // Парсим JSON ответ
            let result;
            try {
                const text = await response.text();
                if (!text) {
                    return false;
                }
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('JSON parse error in checkAuth:', parseError);
                return false;
            }
            
            return result.authenticated === true;
        } catch (error) {
            console.error('checkAuth error:', error);
            return false;
        }
    },
    
    /**
     * Требование авторизации для страницы
     */
    async requireAuth() {
        const isAuth = await this.checkAuth();
        
        if (!isAuth) {
            window.location.href = 'login.html';
            return false;
        }
        
        return true;
    },
    
    /**
     * Редирект если уже авторизован
     */
    async redirectIfAuth() {
        const isAuth = await this.checkAuth();
        
        if (isAuth) {
            window.location.href = 'index.html';
            return true;
        }
        
        return false;
    },
    
    /**
     * Загрузка баланса пользователя
     */
    async loadBalance() {
        console.log('[loadBalance] Starting balance load...');
        
        if (!this.isAuthenticated()) {
            console.log('[loadBalance] User not authenticated, skipping');
            return;
        }
        
        try {
            console.log('[loadBalance] Fetching user data...');
            const result = await this.fetchUser();
            console.log('[loadBalance] fetchUser result:', result);
            
            if (result && result.balances) {
                console.log('[loadBalance] Balances found:', result.balances);
                const usd = result.balances.find(b => b.currency === 'USD');
                console.log('[loadBalance] USD balance:', usd);
                
                if (usd) {
                    const balance = parseFloat(usd.available || 0);
                    const formatted = balance.toLocaleString('en-US', { minimumFractionDigits: 2 });
                    console.log('[loadBalance] Formatted balance:', formatted);
                    
                    // Обновляем баланс в шапке на всех страницах
                    const headerBalance = document.getElementById('headerBalance');
                    console.log('[loadBalance] headerBalance element:', headerBalance);
                    
                    if (headerBalance) {
                        headerBalance.textContent = formatted;
                        console.log('[loadBalance] Balance updated in header:', formatted);
                    } else {
                        console.warn('[loadBalance] Element #headerBalance not found!');
                    }
                    
                    // Обновляем данные пользователя в localStorage
                    const user = this.getUser();
                    if (user) {
                        this.setUser({ ...user, balance: balance });
                        console.log('[loadBalance] Balance saved to localStorage');
                    }
                } else {
                    console.warn('[loadBalance] USD balance not found in balances array');
                }
            } else {
                console.warn('[loadBalance] No balances in result:', result);
            }
        } catch (error) {
            console.error('[loadBalance] Error loading balance:', error);
        }
    },
    
    /**
     * Единая инициализация авторизации
     * Проверяет localStorage и обновляет UI
     */
    initAuth() {
        const token = this.getToken();
        const user = this.getUser();
        const isAuth = !!token;
        
        console.log('[initAuth] Initializing auth state:', { isAuth, hasUser: !!user });
        
        // Обновляем UI
        this.updateUI();
        
        // Возвращаем объект состояния
        return {
            isAuthenticated: isAuth,
            user: user
        };
    },
    
    /**
     * Обновление UI для авторизованного пользователя
     */
    updateUI() {
        const user = this.getUser();
        const isAuth = this.isAuthenticated();
        
        console.log('[updateUI] isAuth:', isAuth, 'user:', user);
        
        // Показать/скрыть элементы для авторизованных с !important для переопределения CSS
        document.querySelectorAll('[data-auth="true"]').forEach(el => {
            if (isAuth) {
                // Определяем правильное значение display на основе класса элемента
                if (el.classList.contains('user-menu')) {
                    el.style.cssText = 'display: flex !important;';
                } else if (el.parentElement && el.parentElement.classList.contains('header-actions')) {
                    el.style.cssText = 'display: flex !important;';
                } else {
                    el.style.cssText = 'display: block !important;';
                }
            } else {
                el.style.cssText = 'display: none !important;';
            }
        });
        
        document.querySelectorAll('[data-auth="false"]').forEach(el => {
            if (isAuth) {
                el.style.cssText = 'display: none !important;';
            } else {
                // Определяем правильное значение display на основе класса элемента
                if (el.parentElement && el.parentElement.classList.contains('header-actions')) {
                    el.style.cssText = 'display: flex !important;';
                } else {
                    el.style.cssText = 'display: block !important;';
                }
            }
        });
        
        // Обновить данные пользователя
        if (user) {
            document.querySelectorAll('[data-user-name]').forEach(el => {
                el.textContent = user.first_name || user.email;
            });
            
            document.querySelectorAll('[data-user-email]').forEach(el => {
                el.textContent = user.email;
            });
            
            document.querySelectorAll('[data-user-avatar]').forEach(el => {
                const initials = (user.first_name?.[0] || '') + (user.last_name?.[0] || '');
                el.textContent = initials.toUpperCase() || user.email[0].toUpperCase();
            });
        }
        
        // Автоматически загружаем баланс при обновлении UI
        if (isAuth) {
            // Используем setTimeout для гарантии, что DOM готов
            setTimeout(() => {
                this.loadBalance();
            }, 100);
        } else {
            // Если не авторизован, показываем 0.00
            const headerBalance = document.getElementById('headerBalance');
            if (headerBalance) {
                headerBalance.textContent = '0.00';
            }
        }
    }
};

// Экспорт
window.Auth = Auth;

// Автоматическое обновление баланса для авторизованных пользователей
(function() {
    // Функция для инициализации обновления баланса
    function initBalanceUpdates() {
        console.log('[initBalanceUpdates] Initializing balance updates...');
        
        if (!Auth.isAuthenticated()) {
            console.log('[initBalanceUpdates] User not authenticated');
            // Показываем баланс из localStorage если есть
            const user = Auth.getUser();
            if (user && user.balance !== undefined) {
                const headerBalance = document.getElementById('headerBalance');
                if (headerBalance) {
                    const formatted = parseFloat(user.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                    headerBalance.textContent = formatted;
                    console.log('[initBalanceUpdates] Displayed balance from localStorage:', formatted);
                }
            }
            return;
        }
        
        // Загружаем баланс сразу при загрузке страницы (с небольшой задержкой для гарантии готовности DOM)
        setTimeout(() => {
            console.log('[initBalanceUpdates] Loading balance on page load...');
            Auth.loadBalance();
        }, 200);
        
        // Обновляем каждые 30 секунд
        setInterval(() => {
            if (Auth.isAuthenticated()) {
                console.log('[initBalanceUpdates] Periodic balance update...');
                Auth.loadBalance();
            }
        }, 30000);
        
        // Обновляем при возврате фокуса на вкладку
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && Auth.isAuthenticated()) {
                console.log('[initBalanceUpdates] Visibility changed, updating balance...');
                Auth.loadBalance();
            }
        });
        
        // Обновляем при возврате фокуса на окно
        window.addEventListener('focus', () => {
            if (Auth.isAuthenticated()) {
                console.log('[initBalanceUpdates] Window focused, updating balance...');
                Auth.loadBalance();
            }
        });
    }
    
    // Инициализируем при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            console.log('[initBalanceUpdates] DOM loaded, initializing...');
            initBalanceUpdates();
        });
    } else {
        console.log('[initBalanceUpdates] DOM already loaded, initializing immediately...');
        initBalanceUpdates();
    }
})();
