<?php
/**
 * ADX Finance - Установка базы данных
 * Запустите этот файл один раз для создания таблиц
 * База данных должна быть создана заранее в панели Hostinger
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Загружаем конфигурацию базы данных
require_once __DIR__ . '/config/database.php';

echo "<h1>ADX Finance - Установка</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;background:#1a1a1f;color:#fff;}h1{color:#6366f1;}pre{background:#2a2a32;padding:15px;border-radius:8px;overflow-x:auto;}.success{color:#22c55e;}.error{color:#ef4444;}.info{color:#6366f1;}</style>";

try {
    // Используем функцию getDB() из config/database.php для подключения к существующей базе данных
    $pdo = getDB();
    
    echo "<p class='success'>✓ Подключение к базе данных '" . DB_NAME . "' успешно</p>";
    
    // SQL для создания таблиц
    $sql = "
    SET NAMES utf8mb4;
    SET FOREIGN_KEY_CHECKS = 0;

    -- Таблица рынков
    CREATE TABLE IF NOT EXISTS `markets` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(50) NOT NULL,
        `slug` VARCHAR(20) NOT NULL,
        `icon` VARCHAR(50) DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица активов
    CREATE TABLE IF NOT EXISTS `assets` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `market_id` INT UNSIGNED NOT NULL,
        `symbol` VARCHAR(20) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `icon` VARCHAR(255) DEFAULT NULL,
        `decimals` TINYINT UNSIGNED DEFAULT 8,
        `min_trade` DECIMAL(20,8) DEFAULT 0.00000001,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_symbol` (`symbol`),
        KEY `idx_market` (`market_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица пользователей
    CREATE TABLE IF NOT EXISTS `users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email` VARCHAR(255) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `avatar` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(20) DEFAULT NULL,
        `country` VARCHAR(100) DEFAULT NULL,
        `is_verified` TINYINT(1) DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `two_factor_enabled` TINYINT(1) DEFAULT 0,
        `two_factor_secret` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица сессий
    CREATE TABLE IF NOT EXISTS `user_sessions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `token` VARCHAR(255) NOT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` TEXT DEFAULT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_token` (`token`),
        KEY `idx_user` (`user_id`),
        KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица балансов
    CREATE TABLE IF NOT EXISTS `balances` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `currency` VARCHAR(20) NOT NULL,
        `available` DECIMAL(20,8) DEFAULT 0,
        `reserved` DECIMAL(20,8) DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_user_currency` (`user_id`, `currency`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица ордеров
    CREATE TABLE IF NOT EXISTS `orders` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `asset_id` INT UNSIGNED NOT NULL,
        `type` ENUM('market', 'limit', 'stop', 'stop_limit') NOT NULL DEFAULT 'market',
        `side` ENUM('buy', 'sell') NOT NULL,
        `status` ENUM('pending', 'open', 'filled', 'partially_filled', 'cancelled') NOT NULL DEFAULT 'pending',
        `quantity` DECIMAL(20,8) NOT NULL,
        `filled_quantity` DECIMAL(20,8) DEFAULT 0,
        `price` DECIMAL(20,8) DEFAULT NULL,
        `stop_price` DECIMAL(20,8) DEFAULT NULL,
        `take_profit` DECIMAL(20,8) DEFAULT NULL COMMENT 'Take Profit - цена фиксации прибыли',
        `stop_loss` DECIMAL(20,8) DEFAULT NULL COMMENT 'Stop Loss - цена ограничения убытков',
        `average_price` DECIMAL(20,8) DEFAULT NULL,
        `total` DECIMAL(20,8) DEFAULT NULL,
        `fee` DECIMAL(20,8) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `filled_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_asset` (`asset_id`),
        KEY `idx_status` (`status`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица транзакций
    CREATE TABLE IF NOT EXISTS `transactions` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `type` ENUM('deposit', 'withdrawal', 'trade', 'fee', 'bonus', 'referral') NOT NULL,
        `currency` VARCHAR(20) NOT NULL,
        `amount` DECIMAL(20,8) NOT NULL,
        `fee` DECIMAL(20,8) DEFAULT 0,
        `status` ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
        `order_id` INT UNSIGNED DEFAULT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `tx_hash` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `completed_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_type` (`type`),
        KEY `idx_status` (`status`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица списка наблюдения
    CREATE TABLE IF NOT EXISTS `watchlist` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `asset_id` INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_user_asset` (`user_id`, `asset_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Таблица алертов
    CREATE TABLE IF NOT EXISTS `price_alerts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `asset_id` INT UNSIGNED NOT NULL,
        `condition` ENUM('above', 'below') NOT NULL,
        `price` DECIMAL(20,8) NOT NULL,
        `is_triggered` TINYINT(1) DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `triggered_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user` (`user_id`),
        KEY `idx_asset` (`asset_id`),
        KEY `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    SET FOREIGN_KEY_CHECKS = 1;
    ";
    
    // Выполняем SQL
    $pdo->exec($sql);
    echo "<p class='success'>✓ Таблицы созданы</p>";
    
    // Проверяем наличие рынков и добавляем если нет
    $stmt = $pdo->query("SELECT COUNT(*) FROM markets");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO `markets` (`name`, `slug`, `icon`) VALUES
            ('Криптовалюты', 'crypto', 'bi-currency-bitcoin'),
            ('Акции', 'stocks', 'bi-graph-up-arrow'),
            ('Форекс', 'forex', 'bi-currency-exchange'),
            ('Индексы', 'indices', 'bi-bar-chart')
        ");
        echo "<p class='success'>✓ Рынки добавлены</p>";
    }
    
    // Проверяем наличие активов и добавляем если нет
    $stmt = $pdo->query("SELECT COUNT(*) FROM assets");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO `assets` (`market_id`, `symbol`, `name`, `decimals`) VALUES
            (1, 'BTC', 'Bitcoin', 8),
            (1, 'ETH', 'Ethereum', 8),
            (1, 'BNB', 'Binance Coin', 8),
            (1, 'XRP', 'Ripple', 6),
            (1, 'SOL', 'Solana', 8),
            (1, 'ADA', 'Cardano', 6),
            (1, 'DOGE', 'Dogecoin', 8),
            (1, 'DOT', 'Polkadot', 8),
            (1, 'MATIC', 'Polygon', 8),
            (1, 'LTC', 'Litecoin', 8),
            (2, 'AAPL', 'Apple Inc.', 2),
            (2, 'GOOGL', 'Alphabet Inc.', 2),
            (2, 'MSFT', 'Microsoft Corporation', 2),
            (2, 'AMZN', 'Amazon.com Inc.', 2),
            (2, 'TSLA', 'Tesla Inc.', 2),
            (2, 'META', 'Meta Platforms Inc.', 2),
            (2, 'NVDA', 'NVIDIA Corporation', 2),
            (2, 'JPM', 'JPMorgan Chase & Co.', 2),
            (2, 'V', 'Visa Inc.', 2),
            (2, 'JNJ', 'Johnson & Johnson', 2),
            (3, 'EURUSD', 'Euro / US Dollar', 5),
            (3, 'GBPUSD', 'British Pound / US Dollar', 5),
            (3, 'USDJPY', 'US Dollar / Japanese Yen', 3),
            (3, 'USDCHF', 'US Dollar / Swiss Franc', 5),
            (3, 'AUDUSD', 'Australian Dollar / US Dollar', 5),
            (3, 'USDCAD', 'US Dollar / Canadian Dollar', 5),
            (3, 'NZDUSD', 'New Zealand Dollar / US Dollar', 5),
            (3, 'EURGBP', 'Euro / British Pound', 5),
            (3, 'EURJPY', 'Euro / Japanese Yen', 3),
            (3, 'GBPJPY', 'British Pound / Japanese Yen', 3),
            (4, 'SPX', 'S&P 500', 2),
            (4, 'NDX', 'NASDAQ 100', 2),
            (4, 'DJI', 'Dow Jones Industrial', 2),
            (4, 'FTSE', 'FTSE 100', 2),
            (4, 'DAX', 'DAX', 2)
        ");
        echo "<p class='success'>✓ Активы добавлены</p>";
    }
    
    echo "<hr>";
    echo "<h2 class='success'>✓ Установка завершена!</h2>";
    echo "<p>Теперь вы можете <a href='register.html' style='color:#6366f1;'>зарегистрироваться</a> или <a href='index.html' style='color:#6366f1;'>перейти на главную</a>.</p>";
    echo "<p class='info'>Этот файл можно удалить после установки.</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Возможные решения:</h3>";
    echo "<ul>";
    echo "<li>Проверьте настройки в config/database.php (DB_HOST, DB_NAME, DB_USER, DB_PASS)</li>";
    echo "<li>Убедитесь, что база данных создана в панели Hostinger</li>";
    echo "<li>Проверьте, что пользователь БД имеет права на доступ к базе данных</li>";
    echo "<li>Проверьте логи ошибок PHP на сервере</li>";
    echo "</ul>";
}
