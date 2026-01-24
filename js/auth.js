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
            
            const result = await response.json();
            
            if (result.success) {
                this.setToken(result.token);
                this.setUser(result.user);
            }
            
            return result;
        } catch (error) {
            return { success: false, error: error.message };
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
            
            const result = await response.json();
            
            // Если требуется 2FA, возвращаем результат без установки токена
            if (result.success && result.requires_2fa) {
                return result;
            }
            
            // Обычный вход без 2FA
            if (result.success && result.token) {
                this.setToken(result.token);
                this.setUser(result.user);
            }
            
            return result;
        } catch (error) {
            return { success: false, error: error.message };
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
            
            const result = await response.json();
            
            if (result.success && result.token) {
                this.setToken(result.token);
                this.setUser(result.user);
            }
            
            return result;
        } catch (error) {
            return { success: false, error: error.message };
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
            
            const result = await response.json();
            
            if (result.success) {
                this.setUser(result.user);
                return result;
            }
            
            // Если токен недействителен, очищаем данные
            if (response.status === 401) {
                localStorage.removeItem(this.TOKEN_KEY);
                localStorage.removeItem(this.USER_KEY);
            }
            
            return null;
        } catch (error) {
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
            
            const result = await response.json();
            return result.authenticated;
        } catch (error) {
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
    }
};

// Экспорт
window.Auth = Auth;
