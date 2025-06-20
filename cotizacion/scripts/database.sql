-- SQL Schema for Cotizacion Application

-- Table: companies
-- Stores information about different companies using the system.
CREATE TABLE `companies` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `tax_id` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `phone` VARCHAR(50) NULL,
  `email` VARCHAR(100) NULL,
  `logo_url` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: users
-- Stores user accounts. Each user belongs to a company.
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NULL,
  `last_name` VARCHAR(50) NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
);
CREATE INDEX `idx_users_company_id` ON `users`(`company_id`);

-- Table: roles
-- Stores user roles for role-based access control.
CREATE TABLE `roles` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL
);

-- Table: user_roles
-- Maps users to roles (many-to-many relationship).
CREATE TABLE `user_roles` (
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
);

-- Table: customers
-- Stores customer information. Each customer belongs to a company.
CREATE TABLE `customers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `contact_person` VARCHAR(255) NULL,
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `tax_id` VARCHAR(20) NULL, -- For SUNAT/RENIEC (Peru specific) or other tax IDs
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
);
CREATE INDEX `idx_customers_company_id` ON `customers`(`company_id`);

-- Table: products
-- Stores product information. Each product belongs to a company.
CREATE TABLE `products` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `sku` VARCHAR(100) NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_sku_per_company` (`company_id`, `sku`)
);
CREATE INDEX `idx_products_company_id` ON `products`(`company_id`);
CREATE INDEX `idx_products_name` ON `products`(`name`);

-- Table: warehouses
-- Stores warehouse information. Each warehouse belongs to a company.
CREATE TABLE `warehouses` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `location` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
);
CREATE INDEX `idx_warehouses_company_id` ON `warehouses`(`company_id`);

-- Table: stock
-- Manages stock levels of products in different warehouses.
CREATE TABLE `stock` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `warehouse_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `product_warehouse_unique` (`product_id`, `warehouse_id`)
);
CREATE INDEX `idx_stock_product_id` ON `stock`(`product_id`);
CREATE INDEX `idx_stock_warehouse_id` ON `stock`(`warehouse_id`);

-- Table: quotations
-- Stores quotation details. Each quotation is associated with a company, customer, and user.
CREATE TABLE `quotations` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `quotation_number` VARCHAR(50) NOT NULL,
  `quotation_date` DATE NOT NULL,
  `valid_until` DATE NULL,
  `subtotal` DECIMAL(10, 2) DEFAULT 0.00,
  `global_discount_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `global_discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(10, 2) DEFAULT 0.00,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Draft', -- e.g., Draft, Sent, Accepted, Rejected, Invoiced
  `notes` TEXT NULL,
  `terms_and_conditions` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  UNIQUE KEY `unique_quotation_number_per_company` (`company_id`, `quotation_number`)
);
CREATE INDEX `idx_quotations_company_id` ON `quotations`(`company_id`);
CREATE INDEX `idx_quotations_customer_id` ON `quotations`(`customer_id`);
CREATE INDEX `idx_quotations_user_id` ON `quotations`(`user_id`);
CREATE INDEX `idx_quotations_status` ON `quotations`(`status`);
CREATE INDEX `idx_quotations_date` ON `quotations`(`quotation_date`);

-- Table: quotation_items
-- Stores individual items within a quotation.
CREATE TABLE `quotation_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `quotation_id` INT NOT NULL,
  `product_id` INT NULL, -- Can be NULL if it's a custom item not in products table
  `description` VARCHAR(255) NULL, -- Can be product name or custom description
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `discount_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `line_total` DECIMAL(10, 2) NOT NULL, -- Calculated: (quantity * unit_price) - discount_amount
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL -- If product is deleted, keep item but nullify product link
);
CREATE INDEX `idx_quotation_items_quotation_id` ON `quotation_items`(`quotation_id`);
CREATE INDEX `idx_quotation_items_product_id` ON `quotation_items`(`product_id`);

-- Table: settings
-- Stores system-wide or company-specific settings.
-- For company_id IS NULL, setting_key must be unique (system-wide setting).
-- For a given company_id, setting_key must be unique (company-specific setting).
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `company_id` INT NULL, -- NULL for system-wide settings
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `description` TEXT NULL,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_setting_key_per_company_or_system` (`company_id`, `setting_key`)
);
CREATE INDEX `idx_settings_company_id` ON `settings`(`company_id`);

-- Insert Default Roles
INSERT INTO `roles` (`role_name`, `description`) VALUES
('System Admin', 'Manages system-wide settings and companies'),
('Company Admin', 'Manages a specific company, users, and settings'),
('Salesperson', 'Creates and manages quotations');

-- Table: api_tokens
-- Stores API tokens for external application access.
CREATE TABLE `api_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(128) NOT NULL UNIQUE,
  `permissions` TEXT, -- e.g., JSON array: ["products:read", "customers:read"]
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL, -- Optional: for token expiry
  `name` VARCHAR(100) NULL, -- User-friendly name for the token
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX `idx_api_tokens_user_id` ON `api_tokens`(`user_id`);


-- Note on SQL Dialect:
-- This schema uses backticks for table and column names, which is common in MySQL.
-- For other SQL databases (like PostgreSQL, SQL Server, SQLite), double quotes are standard for identifiers, or no quotes if names are not reserved words and follow case rules.
-- Data types like TEXT, BOOLEAN, TIMESTAMP DEFAULT CURRENT_TIMESTAMP might have slight variations across SQL databases.
-- Consider `SERIAL` or `IDENTITY` for auto-incrementing primary keys in PostgreSQL or SQL Server respectively.
-- `ON UPDATE CURRENT_TIMESTAMP` is MySQL-specific. A trigger would be needed for other DBs to achieve the same for `stock.last_updated`.
-- The `UNIQUE KEY unique_setting_key_per_company_or_system (company_id, setting_key)` should work correctly in most RDBMS
-- to ensure `setting_key` is unique when `company_id` is NULL, and unique for each `company_id` otherwise,
-- because NULL is generally not considered equal to other NULLs in unique constraints.
-- If a stricter "setting_key is globally unique if company_id is NULL" is needed beyond this,
-- database-specific features like filtered unique indexes (PostgreSQL, SQL Server) or triggers might be required.
-- For this project, we'll assume this combined unique key is sufficient.
-- All columns that are not explicitly NOT NULL are implicitly NULLABLE. Added NULL where it makes sense for clarity.
-- Price columns are NOT NULL. Description columns are NULLABLE.
-- Foreign key constraints are defined with appropriate ON DELETE actions.
-- Indexes are added for foreign keys and commonly queried columns.
-- SKU in products is now nullable, and unique per company.
-- Quotation number is unique per company.
-- Description in quotation_items is nullable.
-- product_id in quotation_items is nullable.
-- tax_id in companies is nullable.
-- All other optional fields (address, phone, email etc.) in companies and customers are nullable.
-- password_hash is NOT NULL.
-- Global discount and total fields in quotations are set to default 0.00.
-- Line total in quotation_items is NOT NULL.
-- Setting_value in settings is nullable.
-- Description in roles is nullable.
-- First/last name in users is nullable.
-- Logo_url in companies is nullable.
-- Location in warehouses is nullable.
-- Valid_until in quotations is nullable.
-- Notes and terms_and_conditions in quotations are nullable.
-- Description in products is nullable.
-- Contact_person in customers is nullable.
-- tax_id in customers is nullable.
-- Email, phone, address in customers are nullable.
-- is_active in users defaults to TRUE.
-- quantity in stock defaults to 0.
-- status in quotations defaults to 'Draft'.
-- discount_percentage and discount_amount in quotation_items and quotations default to 0.00.
-- subtotal in quotations defaults to 0.00.
-- total in quotations defaults to 0.00.
-- All created_at fields default to CURRENT_TIMESTAMP.
-- stock.last_updated defaults to CURRENT_TIMESTAMP and updates on change.
