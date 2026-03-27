-- Performance indexes migration
-- Adds composite and missing indexes to speed up common queries.

-- Quotations: ORDER BY created_at is very common; composite with company_id covers the WHERE+ORDER
ALTER TABLE `quotations` ADD INDEX `idx_quotations_company_created` (`company_id`, `created_at` DESC);

-- Quotations: status filter + date range queries
ALTER TABLE `quotations` ADD INDEX `idx_quotations_company_status_date` (`company_id`, `status`, `quotation_date` DESC);

-- Customers: ORDER BY created_at
ALTER TABLE `customers` ADD INDEX `idx_customers_company_created` (`company_id`, `created_at` DESC);

-- Imagenes: used heavily in product image lookups (N+1 fix uses batch IN query)
-- Add index on (codigo_producto, imagen_principal) if table exists
-- Run this manually if the imagenes table exists in your local DB:
-- ALTER TABLE `imagenes` ADD INDEX `idx_imagenes_codigo_principal` (`codigo_producto`, `imagen_principal`);

-- Activity logs: queried by entity
-- ALTER TABLE `activity_logs` ADD INDEX `idx_activity_entity` (`entity_type`, `entity_id`, `action`);
