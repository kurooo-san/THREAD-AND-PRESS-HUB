-- ====================================================================
-- Migration: System Fixes
-- Adds: stock to products, coupons table, coupon fields to orders,
--       remember_tokens table, status to users, payment verification
-- ====================================================================

-- 1. Product Stock / Inventory
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `stock` INT NOT NULL DEFAULT 100 AFTER `image`;

-- 2. User account status (for ban / activate)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `status` ENUM('active','banned') NOT NULL DEFAULT 'active' AFTER `user_type`;

-- 3. Coupons table
CREATE TABLE IF NOT EXISTS `coupons` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `discount_type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_uses` INT(11) DEFAULT NULL,
    `times_used` INT(11) NOT NULL DEFAULT 0,
    `valid_from` DATETIME DEFAULT NULL,
    `valid_until` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample coupons (valid for 1 year)
INSERT IGNORE INTO `coupons` (`code`, `description`, `discount_type`, `discount_value`, `min_subtotal`, `max_uses`, `valid_from`, `valid_until`, `is_active`) VALUES
('SPRING40',  'Spring Sale 40% off',                  'percent', 40.00, 1000.00, 200, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1),
('WELCOME10', '10% off your first order',             'percent', 10.00,    0.00, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1),
('FREESHIP',  'PHP 50 off (covers shipping)',         'fixed',   50.00,  500.00, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1),
('VIP15',     'VIP members 15% off',                  'percent', 15.00,    0.00, NULL, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 1);

-- 4. Coupon fields on orders
ALTER TABLE `orders`
    ADD COLUMN IF NOT EXISTS `coupon_code`     VARCHAR(50)    DEFAULT NULL AFTER `discount_type`,
    ADD COLUMN IF NOT EXISTS `coupon_discount` DECIMAL(10,2)  NOT NULL DEFAULT 0.00 AFTER `coupon_code`,
    ADD COLUMN IF NOT EXISTS `payment_status`  ENUM('unpaid','pending_verification','verified','rejected') NOT NULL DEFAULT 'unpaid' AFTER `payment_reference`,
    ADD COLUMN IF NOT EXISTS `payment_proof`   VARCHAR(255)   DEFAULT NULL AFTER `payment_status`;

-- 5. Remember-me tokens
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `selector` VARCHAR(32) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `selector` (`selector`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
