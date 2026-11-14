ALTER TABLE `quotations`
ADD COLUMN `customer_reference` VARCHAR(100) NULL DEFAULT NULL AFTER `customer_id`;
