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
 * Использует portal pattern - перемещает dropdown в body для выхода из stacking context
 */
function initUserMenu() {
    const trigger = document.querySelector('.user-menu-trigger');
    const dropdown = document.querySelector('.user-dropdown');
    
    if (!trigger || !dropdown) return;
    if (trigger.dataset.initialized === 'true') return;
    
    // Move dropdown to body to escape stacking context
    if (dropdown.parentElement !== document.body) {
        document.body.appendChild(dropdown);
    }
    
    // Position dropdown below trigger on click
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        
        const isOpen = dropdown.classList.contains('open');
        
        if (isOpen) {
            // Close menu and reset inline styles
            dropdown.classList.remove('open');
            dropdown.style.display = '';  // Reset to CSS default
            dropdown.style.visibility = '';  // Reset visibility
        } else {
            // Open menu - calculate position
            // First make dropdown visible temporarily to get its width
            dropdown.style.display = 'block';
            dropdown.style.visibility = 'hidden';
            
            const rect = trigger.getBoundingClientRect();
            const dropdownWidth = dropdown.offsetWidth;
            
            // Position dropdown: right edge of menu aligns with right edge of trigger
            dropdown.style.top = `${rect.bottom + 8}px`;
            dropdown.style.left = `${rect.right - dropdownWidth}px`;
            dropdown.style.right = 'auto';
            
            // Make it visible again and add open class
            dropdown.style.visibility = '';
            dropdown.classList.add('open');
        }
    });
    
    trigger.dataset.initialized = 'true';
    
    // Close on click outside (only one handler per document)
    if (!document.userMenuClickHandler) {
        document.userMenuClickHandler = (e) => {
            if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
                dropdown.classList.remove('open');
                // Reset inline styles when closing
                dropdown.style.display = '';
                dropdown.style.visibility = '';
            }
        };
        document.addEventListener('click', document.userMenuClickHandler);
    }
    
    // Close on dropdown item click
    dropdown.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
            dropdown.classList.remove('open');
            // Reset inline styles when closing
            dropdown.style.display = '';
            dropdown.style.visibility = '';
        });
    });
    
    // Reposition on window resize
    window.addEventListener('resize', () => {
        if (dropdown.classList.contains('open')) {
            const rect = trigger.getBoundingClientRect();
            const dropdownWidth = dropdown.offsetWidth;
            dropdown.style.top = `${rect.bottom + 8}px`;
            dropdown.style.left = `${rect.right - dropdownWidth}px`;
            dropdown.style.right = 'auto';
        }
    });
}

/**
 * Мобильное меню
 */
function initMobileMenu() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const nav = document.querySelector('.nav-main');
    let mobileMenu = document.querySelector('.mobile-menu');
    let mobileMenuOverlay = document.querySelector('.mobile-menu-overlay');
    
    // Создаем overlay, если его нет
    if (!mobileMenuOverlay) {
        mobileMenuOverlay = document.createElement('div');
        mobileMenuOverlay.className = 'mobile-menu-overlay';
        document.body.appendChild(mobileMenuOverlay);
    }
    
    // Создаем мобильное меню, если его нет
    if (!mobileMenu && nav) {
        mobileMenu = document.createElement('div');
        mobileMenu.className = 'mobile-menu';
        
        // Создаем header с логотипом и кнопкой закрытия
        const logo = document.querySelector('.logo');
        const logoHTML = logo ? logo.outerHTML : '<div class="logo"><span>ADX Finance</span></div>';
        
        mobileMenu.innerHTML = `
            <div class="mobile-menu-header">
                ${logoHTML}
                <button class="mobile-menu-close" aria-label="Закрыть меню">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="mobile-menu-content"></div>
        `;
        document.body.appendChild(mobileMenu);
        
        // Копируем навигационные ссылки в мобильное меню
        const menuContent = mobileMenu.querySelector('.mobile-menu-content');
        const navLinks = nav.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const clonedLink = link.cloneNode(true);
            clonedLink.addEventListener('click', () => {
                closeMobileMenu();
            });
            menuContent.appendChild(clonedLink);
        });
        
        // Обработчик закрытия через кнопку X
        const closeBtn = mobileMenu.querySelector('.mobile-menu-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeMobileMenu);
        }
        
        // Обработчик закрытия через overlay
        mobileMenuOverlay.addEventListener('click', closeMobileMenu);
    }
    
    if (!toggle || !mobileMenu) return;
    
    // Функция открытия меню
    function openMobileMenu() {
        mobileMenu.classList.add('open');
        mobileMenuOverlay.classList.add('open');
        document.body.classList.add('mobile-menu-open');
        const icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.remove('bi-list');
            icon.classList.add('bi-x');
        }
    }
    
    // Функция закрытия меню
    function closeMobileMenu() {
        mobileMenu.classList.remove('open');
        mobileMenuOverlay.classList.remove('open');
        document.body.classList.remove('mobile-menu-open');
        const icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.remove('bi-x');
            icon.classList.add('bi-list');
        }
    }
    
    // Переключение меню
    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        if (mobileMenu.classList.contains('open')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    });
    
    // Закрытие при нажатии Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenu.classList.contains('open')) {
            closeMobileMenu();
        }
    });
    
    // Предотвращаем закрытие при клике внутри меню
    mobileMenu.addEventListener('click', (e) => {
        e.stopPropagation();
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
    try { 
        initTheme(); 
    } catch(e) { 
        console.error('Theme init error:', e); 
    }
    
    try { 
        initUserMenu(); 
    } catch(e) { 
        console.error('UserMenu init error:', e); 
    }
    
    try { 
        initMobileMenu(); 
    } catch(e) { 
        console.error('MobileMenu init error:', e); 
    }
    
    try { 
        setActiveNav(); 
    } catch(e) { 
        console.error('ActiveNav error:', e); 
    }
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
