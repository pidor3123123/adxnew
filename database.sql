-- ================================================
-- ADX Finance - Биржевая платформа
-- Схема базы данных MySQL
-- ================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Удаление существующих таблиц
-- ----------------------------
DROP TABLE IF EXISTS `deposit_requests`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `balances`;
DROP TABLE IF EXISTS `watchlist`;
DROP TABLE IF EXISTS `price_alerts`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `assets`;
DROP TABLE IF EXISTS `markets`;

-- ----------------------------
-- Таблица рынков
-- ----------------------------
CREATE TABLE `markets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT 'Название рынка',
    `slug` VARCHAR(20) NOT NULL COMMENT 'URL slug',
    `icon` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заполнение рынков
INSERT INTO `markets` (`name`, `slug`, `icon`) VALUES
('Криптовалюты', 'crypto', 'bi-currency-bitcoin'),
('Акции', 'stocks', 'bi-graph-up-arrow'),
('Форекс', 'forex', 'bi-currency-exchange'),
('Индексы', 'indices', 'bi-bar-chart');

-- ----------------------------
-- Таблица активов
-- ----------------------------
CREATE TABLE `assets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `market_id` INT UNSIGNED NOT NULL,
    `symbol` VARCHAR(20) NOT NULL COMMENT 'Тикер (BTC, AAPL, EURUSD)',
    `name` VARCHAR(100) NOT NULL COMMENT 'Полное название',
    `icon` VARCHAR(255) DEFAULT NULL COMMENT 'URL иконки',
    `decimals` TINYINT UNSIGNED DEFAULT 8 COMMENT 'Количество десятичных знаков',
    `min_trade` DECIMAL(20,8) DEFAULT 0.00000001 COMMENT 'Минимальный объём сделки',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_symbol` (`symbol`),
    KEY `idx_market` (`market_id`),
    CONSTRAINT `fk_asset_market` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Заполнение криптовалют
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
(1, 'LTC', 'Litecoin', 8);

-- Заполнение акций
INSERT INTO `assets` (`market_id`, `symbol`, `name`, `decimals`) VALUES
(2, 'AAPL', 'Apple Inc.', 2),
(2, 'GOOGL', 'Alphabet Inc.', 2),
(2, 'MSFT', 'Microsoft Corporation', 2),
(2, 'AMZN', 'Amazon.com Inc.', 2),
(2, 'TSLA', 'Tesla Inc.', 2),
(2, 'META', 'Meta Platforms Inc.', 2),
(2, 'NVDA', 'NVIDIA Corporation', 2),
(2, 'JPM', 'JPMorgan Chase & Co.', 2),
(2, 'V', 'Visa Inc.', 2),
(2, 'JNJ', 'Johnson & Johnson', 2);

-- Заполнение форекс пар
INSERT INTO `assets` (`market_id`, `symbol`, `name`, `decimals`) VALUES
(3, 'EURUSD', 'Euro / US Dollar', 5),
(3, 'GBPUSD', 'British Pound / US Dollar', 5),
(3, 'USDJPY', 'US Dollar / Japanese Yen', 3),
(3, 'USDCHF', 'US Dollar / Swiss Franc', 5),
(3, 'AUDUSD', 'Australian Dollar / US Dollar', 5),
(3, 'USDCAD', 'US Dollar / Canadian Dollar', 5),
(3, 'NZDUSD', 'New Zealand Dollar / US Dollar', 5),
(3, 'EURGBP', 'Euro / British Pound', 5),
(3, 'EURJPY', 'Euro / Japanese Yen', 3),
(3, 'GBPJPY', 'British Pound / Japanese Yen', 3);

-- Заполнение индексов
INSERT INTO `assets` (`market_id`, `symbol`, `name`, `decimals`) VALUES
(4, 'SPX', 'S&P 500', 2),
(4, 'NDX', 'NASDAQ 100', 2),
(4, 'DJI', 'Dow Jones Industrial', 2),
(4, 'FTSE', 'FTSE 100', 2),
(4, 'DAX', 'DAX', 2);

-- ----------------------------
-- Таблица пользователей
-- ----------------------------
CREATE TABLE `users` (
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
    `balance_available` DECIMAL(20,2) DEFAULT 0 COMMENT 'Доступный баланс для торговли',
    `balance_locked` DECIMAL(20,2) DEFAULT 0 COMMENT 'Средства в открытых сделках',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица сессий пользователей
-- ----------------------------
CREATE TABLE `user_sessions` (
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
    KEY `idx_expires` (`expires_at`),
    CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица балансов пользователей
-- ----------------------------
CREATE TABLE `balances` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `currency` VARCHAR(20) NOT NULL COMMENT 'Валюта (USD, BTC, ETH...)',
    `available` DECIMAL(20,8) DEFAULT 0 COMMENT 'Доступный баланс',
    `reserved` DECIMAL(20,8) DEFAULT 0 COMMENT 'Зарезервировано в ордерах',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_currency` (`user_id`, `currency`),
    CONSTRAINT `fk_balance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица заявок на депозит
-- ----------------------------
CREATE TABLE `deposit_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(20,2) NOT NULL,
    `method` VARCHAR(50) NOT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `processed_at` TIMESTAMP NULL DEFAULT NULL,
    `processed_by` INT UNSIGNED NULL DEFAULT NULL,
    `notes` TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_deposit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица ордеров
-- ----------------------------
CREATE TABLE `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `asset_id` INT UNSIGNED NOT NULL,
    `type` ENUM('market', 'limit', 'stop', 'stop_limit') NOT NULL DEFAULT 'market',
    `side` ENUM('buy', 'sell') NOT NULL,
    `status` ENUM('pending', 'open', 'filled', 'partially_filled', 'cancelled') NOT NULL DEFAULT 'pending',
    `quantity` DECIMAL(20,8) NOT NULL COMMENT 'Количество актива',
    `amount_usd` DECIMAL(20,2) DEFAULT 0 COMMENT 'Сумма сделки в USD',
    `entry_price` DECIMAL(20,8) DEFAULT NULL COMMENT 'Цена входа',
    `profit_loss` DECIMAL(20,2) DEFAULT 0 COMMENT 'Прибыль/убыток при закрытии',
    `filled_quantity` DECIMAL(20,8) DEFAULT 0 COMMENT 'Исполненное количество',
    `price` DECIMAL(20,8) DEFAULT NULL COMMENT 'Цена для лимитного ордера',
    `stop_price` DECIMAL(20,8) DEFAULT NULL COMMENT 'Стоп-цена',
    `take_profit` DECIMAL(20,8) DEFAULT NULL COMMENT 'Take Profit - цена фиксации прибыли',
    `stop_loss` DECIMAL(20,8) DEFAULT NULL COMMENT 'Stop Loss - цена ограничения убытков',
    `average_price` DECIMAL(20,8) DEFAULT NULL COMMENT 'Средняя цена исполнения',
    `total` DECIMAL(20,8) DEFAULT NULL COMMENT 'Общая сумма сделки',
    `fee` DECIMAL(20,8) DEFAULT 0 COMMENT 'Комиссия',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `filled_at` TIMESTAMP NULL DEFAULT NULL,
    `closed_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Время закрытия сделки',
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_asset` (`asset_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица транзакций
-- ----------------------------
CREATE TABLE `transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('deposit', 'withdrawal', 'trade', 'DEPOSIT', 'TRADE_OPEN', 'TRADE_CLOSE', 'fee', 'bonus', 'referral') NOT NULL,
    `currency` VARCHAR(20) NOT NULL,
    `amount` DECIMAL(20,8) NOT NULL,
    `fee` DECIMAL(20,8) DEFAULT 0,
    `status` ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `order_id` INT UNSIGNED DEFAULT NULL COMMENT 'Связанный ордер',
    `description` VARCHAR(255) DEFAULT NULL,
    `tx_hash` VARCHAR(255) DEFAULT NULL COMMENT 'Hash транзакции (для крипто)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_type` (`type`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_transaction_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transaction_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица списка наблюдения
-- ----------------------------
CREATE TABLE `watchlist` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `asset_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_asset` (`user_id`, `asset_id`),
    CONSTRAINT `fk_watchlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_watchlist_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Таблица ценовых алертов
-- ----------------------------
CREATE TABLE `price_alerts` (
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
    KEY `idx_active` (`is_active`),
    CONSTRAINT `fk_alert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_alert_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
