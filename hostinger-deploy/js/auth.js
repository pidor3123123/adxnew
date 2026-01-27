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
        if (!this.isAuthenticated()) return;
        
        try {
            const result = await this.fetchUser();
            if (result && result.balances) {
                const usd = result.balances.find(b => b.currency === 'USD');
                if (usd) {
                    const balance = parseFloat(usd.available || 0);
                    const formatted = balance.toLocaleString('en-US', { minimumFractionDigits: 2 });
                    
                    // Обновляем баланс в шапке на всех страницах
                    const headerBalance = document.getElementById('headerBalance');
                    if (headerBalance) {
                        headerBalance.textContent = formatted;
                    }
                    
                    // Обновляем данные пользователя в localStorage
                    const user = this.getUser();
                    if (user) {
                        this.setUser({ ...user, balance: balance });
                    }
                }
            }
        } catch (error) {
            console.error('Error loading balance:', error);
        }
    },
    
    /**
     * Обновление UI для авторизованного пользователя
     */
    updateUI() {
        const user = this.getUser();
        const isAuth = this.isAuthenticated();
        
        // Показать/скрыть элементы для авторизованных
        document.querySelectorAll('[data-auth="true"]').forEach(el => {
            el.style.display = isAuth ? '' : 'none';
        });
        
        document.querySelectorAll('[data-auth="false"]').forEach(el => {
            el.style.display = isAuth ? 'none' : '';
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
            this.loadBalance();
        }
    }
};

// Экспорт
window.Auth = Auth;

// Автоматическое обновление баланса для авторизованных пользователей
(function() {
    // Функция для инициализации обновления баланса
    function initBalanceUpdates() {
        if (!Auth.isAuthenticated()) return;
        
        // Загружаем баланс сразу при загрузке страницы
        Auth.loadBalance();
        
        // Обновляем каждые 30 секунд
        setInterval(() => {
            if (Auth.isAuthenticated()) {
                Auth.loadBalance();
            }
        }, 30000);
        
        // Обновляем при возврате фокуса на вкладку
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && Auth.isAuthenticated()) {
                Auth.loadBalance();
            }
        });
        
        // Обновляем при возврате фокуса на окно
        window.addEventListener('focus', () => {
            if (Auth.isAuthenticated()) {
                Auth.loadBalance();
            }
        });
    }
    
    // Инициализируем при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBalanceUpdates);
    } else {
        initBalanceUpdates();
    }
})();
