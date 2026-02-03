-- ================================================
-- ADX Finance - Миграция схемы для торговой платформы
-- Запустить на существующей БД
-- При ошибке "Duplicate column" - колонка уже существует, пропустить строку
-- ================================================

SET NAMES utf8mb4;

-- 1. Добавить balance_available и balance_locked в users
-- При ошибке 1060 (Duplicate column) - колонка уже существует
ALTER TABLE `users` ADD COLUMN `balance_available` DECIMAL(20,2) DEFAULT 0;
ALTER TABLE `users` ADD COLUMN `balance_locked` DECIMAL(20,2) DEFAULT 0;

-- 2. Создать таблицу deposit_requests
CREATE TABLE IF NOT EXISTS `deposit_requests` (
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

-- 3. Добавить колонки в orders
ALTER TABLE `orders` ADD COLUMN `amount_usd` DECIMAL(20,2) DEFAULT 0 COMMENT 'Сумма сделки в USD' AFTER `quantity`;
ALTER TABLE `orders` ADD COLUMN `entry_price` DECIMAL(20,8) DEFAULT NULL COMMENT 'Цена входа' AFTER `amount_usd`;
ALTER TABLE `orders` ADD COLUMN `profit_loss` DECIMAL(20,2) DEFAULT 0 COMMENT 'Прибыль/убыток при закрытии' AFTER `entry_price`;
ALTER TABLE `orders` ADD COLUMN `closed_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Время закрытия' AFTER `filled_at`;

-- 4. Модифицировать transactions - добавить новые типы
ALTER TABLE `transactions` MODIFY COLUMN `type` ENUM(
  'deposit', 'withdrawal', 'trade', 'DEPOSIT', 'TRADE_OPEN', 'TRADE_CLOSE', 
  'fee', 'bonus', 'referral'
) NOT NULL;
