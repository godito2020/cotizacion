-- =====================================================
-- Sistema de Créditos y Cobranzas
-- Script de instalación para crear tablas y rol
-- =====================================================

-- 1. Crear rol "Créditos y Cobranzas"
INSERT INTO roles (role_name, description)
VALUES ('Créditos y Cobranzas', 'Usuario encargado de aprobar créditos antes de facturación')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- 2. Crear tabla de seguimiento de créditos
DROP TABLE IF EXISTS quotation_credit_history;
DROP TABLE IF EXISTS quotation_credit_tracking;

CREATE TABLE `quotation_credit_tracking` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `quotation_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `seller_id` INT NOT NULL,
  `credit_user_id` INT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `credit_days` INT NULL,
  `total_amount` DECIMAL(12,2) NULL,
  `currency` VARCHAR(3) NULL,
  `observations` TEXT NULL,
  `rejection_reason` TEXT NULL,
  `requested_at` TIMESTAMP NULL,
  `processed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX `idx_credit_tracking_quotation` ON `quotation_credit_tracking`(`quotation_id`);
CREATE INDEX `idx_credit_tracking_company` ON `quotation_credit_tracking`(`company_id`);
CREATE INDEX `idx_credit_tracking_seller` ON `quotation_credit_tracking`(`seller_id`);
CREATE INDEX `idx_credit_tracking_status` ON `quotation_credit_tracking`(`status`);

-- 3. Crear tabla de historial de créditos
CREATE TABLE `quotation_credit_history` (
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

CREATE INDEX `idx_credit_history_tracking` ON `quotation_credit_history`(`tracking_id`);
CREATE INDEX `idx_credit_history_quotation` ON `quotation_credit_history`(`quotation_id`);

-- 4. Agregar columna credit_status a quotations (si no existe)
SET @dbname = DATABASE();

SELECT COUNT(*) INTO @colExists
FROM information_schema.columns
WHERE table_schema = @dbname
AND table_name = 'quotations'
AND column_name = 'credit_status';

SET @query = IF(@colExists = 0,
    'ALTER TABLE quotations ADD COLUMN credit_status VARCHAR(50) NULL AFTER billing_status',
    'SELECT "Column credit_status already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Crear índice en credit_status
SET @indexExists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @dbname
    AND table_name = 'quotations'
    AND index_name = 'idx_quotations_credit_status'
);

SET @query = IF(@indexExists = 0,
    'CREATE INDEX idx_quotations_credit_status ON quotations(credit_status)',
    'SELECT "Index idx_quotations_credit_status already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
