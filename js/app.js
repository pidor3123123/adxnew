/**
 * ADX Finance - Главный модуль приложения
 */

// Константы
const API_URL = '/api';
const THEME_KEY = 'novatrade_theme';
const TOKEN_KEY = 'novatrade_token';

/**
 * Инициализация темы
 */
function initTheme() {
    const savedTheme = localStorage.getItem(THEME_KEY) || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);
}

/**
 * Переключение темы
 */
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem(THEME_KEY, newTheme);
    updateThemeIcon(newTheme);
}

/**
 * Обновление иконки темы
 */
function updateThemeIcon(theme) {
    const icon = document.querySelector('.theme-toggle i');
    if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
}

/**
 * Форматирование числа как валюты
 */
function formatCurrency(amount, currency = 'USD', decimals = 2) {
    const num = parseFloat(amount) || 0;
    
    if (currency === 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(num);
    }
    
    return num.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }) + ' ' + currency;
}

/**
 * Форматирование процентного изменения
 */
function formatChange(change) {
    const num = parseFloat(change) || 0;
    const sign = num >= 0 ? '+' : '';
    return sign + num.toFixed(2) + '%';
}

/**
 * Форматирование большого числа
 */
function formatNumber(num) {
    const n = parseFloat(num) || 0;
    
    if (n >= 1e12) return (n / 1e12).toFixed(2) + 'T';
    if (n >= 1e9) return (n / 1e9).toFixed(2) + 'B';
    if (n >= 1e6) return (n / 1e6).toFixed(2) + 'M';
    if (n >= 1e3) return (n / 1e3).toFixed(2) + 'K';
    
    return n.toLocaleString();
}

/**
 * Форматирование даты
 */
function formatDate(date, options = {}) {
    const d = new Date(date);
    return d.toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...options
    });
}

/**
 * Форматирование времени
 */
function formatTime(date) {
    const d = new Date(date);
    return d.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

/**
 * Debounce функция
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle функция
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Показать toast уведомление
 */
function showToast(title, message, type = 'success') {
    let container = document.querySelector('.toast-container');
    
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    // Автоматическое удаление через 5 секунд
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

/**
 * API запрос
 */
async function apiRequest(endpoint, options = {}) {
    const token = localStorage.getItem(TOKEN_KEY);
    
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            ...(token && { 'Authorization': `Bearer ${token}` })
        }
    };
    
    const response = await fetch(API_URL + endpoint, {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    });
    
    const data = await response.json();
    
    if (!response.ok) {
        throw new Error(data.error || 'Ошибка запроса');
    }
    
    return data;
}

/**
 * Скелетон загрузки
 */
function createSkeleton(type = 'text', count = 1) {
    const skeletons = [];
    
    for (let i = 0; i < count; i++) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton';
        
        switch (type) {
            case 'text':
                skeleton.style.height = '16px';
                skeleton.style.width = Math.random() * 40 + 60 + '%';
                break;
            case 'title':
                skeleton.style.height = '24px';
                skeleton.style.width = '200px';
                break;
            case 'avatar':
                skeleton.style.width = '48px';
                skeleton.style.height = '48px';
                skeleton.style.borderRadius = '50%';
                break;
            case 'card':
                skeleton.style.height = '120px';
                break;
            case 'row':
                skeleton.style.height = '56px';
                break;
        }
        
        skeletons.push(skeleton);
    }
    
    return skeletons;
}

/**
 * Управление выпадающим меню пользователя
 */
function initUserMenu() {
    const userMenu = document.querySelector('.user-menu');
    if (!userMenu) return;
    
    const trigger = userMenu.querySelector('.user-menu-trigger');
    
    trigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('open');
    });
    
    document.addEventListener('click', () => {
        userMenu.classList.remove('open');
    });
}

/**
 * Мобильное меню
 */
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.nav-main');
    let mobileMenu = document.querySelector('.mobile-menu');
    
    // Создаем мобильное меню, если его нет
    if (!mobileMenu && nav) {
        mobileMenu = document.createElement('div');
        mobileMenu.className = 'mobile-menu';
        mobileMenu.innerHTML = '<div class="mobile-menu-content"></div>';
        document.body.appendChild(mobileMenu);
        
        // Копируем навигационные ссылки в мобильное меню
        const menuContent = mobileMenu.querySelector('.mobile-menu-content');
        const navLinks = nav.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            const clonedLink = link.cloneNode(true);
            clonedLink.addEventListener('click', () => {
                mobileMenu.classList.remove('open');
                toggle?.querySelector('i')?.classList.remove('bi-x');
                toggle?.querySelector('i')?.classList.add('bi-list');
            });
            menuContent.appendChild(clonedLink);
        });
    }
    
    if (!toggle || !mobileMenu) return;
    
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        mobileMenu.classList.toggle('open');
        const icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-list');
            icon.classList.toggle('bi-x');
        }
    });
    
    // Закрытие меню при клике вне его
    document.addEventListener('click', (e) => {
        if (!mobileMenu.contains(e.target) && !toggle.contains(e.target)) {
            mobileMenu.classList.remove('open');
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.classList.remove('bi-x');
                icon.classList.add('bi-list');
            }
        }
    });
}

/**
 * Активная навигация
 */
function setActiveNav() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });
}

/**
 * Инициализация при загрузке страницы
 */
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initUserMenu();
    initMobileMenu();
    setActiveNav();
});

// Экспорт для глобального использования
window.NovaTrade = {
    formatCurrency,
    formatChange,
    formatNumber,
    formatDate,
    formatTime,
    showToast,
    apiRequest,
    debounce,
    throttle,
    createSkeleton
};
