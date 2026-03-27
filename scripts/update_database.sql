-- Update script for enhanced Cotizacion System
-- Run this script to add new tables and columns to existing database

-- Add new columns to companies table
ALTER TABLE `companies`
ADD COLUMN `website` VARCHAR(255) NULL AFTER `email`,
ADD COLUMN `favicon_url` VARCHAR(255) NULL AFTER `logo_url`,
ADD COLUMN `whatsapp` VARCHAR(50) NULL AFTER `favicon_url`;

-- Update products table structure
ALTER TABLE `products`
DROP COLUMN `name`,
DROP COLUMN `sku`,
DROP COLUMN `price`,
ADD COLUMN `code` VARCHAR(100) NOT NULL AFTER `company_id`,
ADD COLUMN `brand` VARCHAR(100) NULL AFTER `description`,
ADD COLUMN `balance` DECIMAL(10, 2) DEFAULT 0.00 AFTER `brand`,
ADD COLUMN `premium_price` DECIMAL(10, 2) DEFAULT 0.00 AFTER `balance`,
ADD COLUMN `regular_price` DECIMAL(10, 2) DEFAULT 0.00 AFTER `premium_price`,
ADD COLUMN `last_cost` DECIMAL(10, 2) DEFAULT 0.00 AFTER `regular_price`,
ADD COLUMN `last_cost_date` DATE NULL AFTER `last_cost`,
ADD COLUMN `image_url` VARCHAR(255) NULL AFTER `last_cost_date`,
ADD COLUMN `unit` VARCHAR(50) NULL AFTER `image_url`,
ADD COLUMN `is_active` BOOLEAN DEFAULT TRUE AFTER `unit`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Drop old unique constraint and add new one
ALTER TABLE `products`
DROP INDEX `unique_sku_per_company`,
ADD UNIQUE KEY `unique_code_per_company` (`company_id`, `code`);

-- Update indexes
ALTER TABLE `products`
DROP INDEX `idx_products_name`,
ADD INDEX `idx_products_description` (`description`(255)),
ADD INDEX `idx_products_code` (`code`);

-- Table: bank_accounts
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `bank_name` VARCHAR(100) NOT NULL,
  `account_type` VARCHAR(50) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `cci` VARCHAR(20) NULL,
  `currency` VARCHAR(3) DEFAULT 'PEN',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
);
CREATE INDEX `idx_bank_accounts_company_id` ON `bank_accounts`(`company_id`);

-- Table: email_settings
CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `smtp_host` VARCHAR(255) NOT NULL,
  `smtp_port` INT DEFAULT 587,
  `smtp_username` VARCHAR(255) NOT NULL,
  `smtp_password` VARCHAR(255) NOT NULL,
  `from_email` VARCHAR(255) NOT NULL,
  `from_name` VARCHAR(255) NOT NULL,
  `encryption` ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
  `is_active` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_email_config_per_company` (`company_id`)
);

-- Table: api_settings
CREATE TABLE IF NOT EXISTS `api_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NULL,
  `api_name` VARCHAR(50) NOT NULL,
  `api_url` VARCHAR(255) NOT NULL,
  `api_key` VARCHAR(255) NULL,
  `api_secret` VARCHAR(255) NULL,
  `additional_config` JSON NULL,
  `is_active` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_api_per_company` (`company_id`, `api_name`)
);

-- Table: product_warehouse_stock
CREATE TABLE IF NOT EXISTS `product_warehouse_stock` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `warehouse_name` VARCHAR(100) NOT NULL,
  `stock_quantity` DECIMAL(10, 2) DEFAULT 0.00,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `product_warehouse_unique` (`product_id`, `warehouse_name`)
);
CREATE INDEX `idx_product_warehouse_stock_product` ON `product_warehouse_stock`(`product_id`);
CREATE INDEX `idx_product_warehouse_stock_company` ON `product_warehouse_stock`(`company_id`);

-- Insert sample data for testing
INSERT IGNORE INTO `companies` (`id`, `name`, `tax_id`, `email`, `phone`) VALUES
(1, 'Empresa Demo', '20100000001', 'info@empresademo.com', '01-234-5678');

INSERT IGNORE INTO `users` (`id`, `company_id`, `username`, `password_hash`, `email`, `first_name`, `last_name`) VALUES
(1, 1, 'admin_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'Admin', 'User'),
(2, 1, 'sales_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sales@example.com', 'Sales', 'User');

INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1), -- admin_user as System Admin
(2, 3); -- sales_user as Salesperson

-- Insert default API configurations
INSERT IGNORE INTO `api_settings` (`company_id`, `api_name`, `api_url`, `is_active`) VALUES
(NULL, 'SUNAT', 'https://api.apis.net.pe/v1', 0),
(NULL, 'RENIEC', 'https://api.apis.net.pe/v1', 0);

-- Create assets directories if they don't exist (this is handled by the application)

COMMIT;