-- Fix billing tables - Version without foreign keys (simpler, avoids index issues)
-- Run this script to fix the "Field 'id' doesn't have a default value" error

-- First, drop tables if they exist with incorrect structure
DROP TABLE IF EXISTS quotation_billing_history;
DROP TABLE IF EXISTS quotation_billing_tracking;

-- Create quotation_billing_tracking table (without foreign keys)
CREATE TABLE `quotation_billing_tracking` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `quotation_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `seller_id` INT NOT NULL,
  `billing_user_id` INT NULL,
  `status` ENUM('Pending', 'In_Process', 'Invoiced', 'Rejected') DEFAULT 'Pending',
  `observations` TEXT NULL,
  `invoice_number` VARCHAR(50) NULL,
  `rejection_reason` TEXT NULL,
  `requested_at` TIMESTAMP NULL,
  `processed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX `idx_billing_tracking_quotation` ON `quotation_billing_tracking`(`quotation_id`);
CREATE INDEX `idx_billing_tracking_company` ON `quotation_billing_tracking`(`company_id`);
CREATE INDEX `idx_billing_tracking_seller` ON `quotation_billing_tracking`(`seller_id`);
CREATE INDEX `idx_billing_tracking_status` ON `quotation_billing_tracking`(`status`);

-- Create quotation_billing_history table (without foreign keys)
CREATE TABLE `quotation_billing_history` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `tracking_id` INT NOT NULL,
  `quotation_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `previous_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `observations` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX `idx_billing_history_tracking` ON `quotation_billing_history`(`tracking_id`);
CREATE INDEX `idx_billing_history_quotation` ON `quotation_billing_history`(`quotation_id`);
