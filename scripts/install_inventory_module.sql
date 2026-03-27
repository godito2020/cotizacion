-- =====================================================
-- MODULO DE INVENTARIO FISICO
-- Script de instalación - Sistema COTI
-- =====================================================
-- IMPORTANTE: Revisar antes de ejecutar en producción
-- Este script crea las tablas y roles necesarios para
-- el módulo de inventario físico colaborativo
-- =====================================================

-- =====================================================
-- 1. CREAR NUEVOS ROLES
-- =====================================================

INSERT INTO roles (role_name, description)
VALUES
    ('Supervisor Inventario', 'Administrador de sesiones de inventario. Puede abrir/cerrar sesiones, ver progreso en tiempo real y generar reportes.'),
    ('Usuario Inventario', 'Operador que registra conteos físicos durante una sesión de inventario activa.')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- =====================================================
-- 2. TABLA DE SESIONES DE INVENTARIO
-- =====================================================
-- Una sesión representa un período de inventario activo
-- Solo puede haber una sesión abierta por empresa a la vez

CREATE TABLE IF NOT EXISTS `inventory_sessions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `company_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo: Inventario Enero 2026',
    `description` TEXT NULL COMMENT 'Descripción o notas adicionales',
    `status` ENUM('Open', 'Closed', 'Cancelled') DEFAULT 'Open',
    `created_by` INT NOT NULL COMMENT 'User ID del supervisor que creó la sesión',
    `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha/hora de apertura',
    `closed_at` TIMESTAMP NULL COMMENT 'Fecha/hora de cierre',
    `closed_by` INT NULL COMMENT 'User ID del supervisor que cerró la sesión',
    `close_notes` TEXT NULL COMMENT 'Notas del cierre',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_inv_session_company` (`company_id`),
    INDEX `idx_inv_session_status` (`status`),
    INDEX `idx_inv_session_created_by` (`created_by`),
    INDEX `idx_inv_session_opened_at` (`opened_at`),

    CONSTRAINT `fk_inv_session_company` FOREIGN KEY (`company_id`)
        REFERENCES `companies`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_session_created_by` FOREIGN KEY (`created_by`)
        REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_inv_session_closed_by` FOREIGN KEY (`closed_by`)
        REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TABLA DE ALMACENES POR SESION
-- =====================================================
-- Define qué almacenes están habilitados para inventariar en cada sesión

CREATE TABLE IF NOT EXISTS `inventory_session_warehouses` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `session_id` INT NOT NULL,
    `warehouse_number` INT NOT NULL COMMENT 'Número de almacén (de desc_almacen)',
    `warehouse_name` VARCHAR(255) NOT NULL COMMENT 'Nombre cacheado al momento de crear sesión',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_session_warehouse` (`session_id`, `warehouse_number`),
    INDEX `idx_inv_sw_session` (`session_id`),
    INDEX `idx_inv_sw_warehouse` (`warehouse_number`),

    CONSTRAINT `fk_inv_sw_session` FOREIGN KEY (`session_id`)
        REFERENCES `inventory_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. TABLA DE REGISTROS DE INVENTARIO (ENTRADAS)
-- =====================================================
-- Cada registro representa un conteo físico de un producto

CREATE TABLE IF NOT EXISTS `inventory_entries` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'Usuario que registró el conteo',
    `warehouse_number` INT NOT NULL COMMENT 'Almacén donde se contó',
    `product_code` VARCHAR(100) NOT NULL COMMENT 'Código del producto (de vista_productos)',
    `product_description` VARCHAR(500) NULL COMMENT 'Descripción cacheada al momento del registro',
    `system_stock` DECIMAL(12, 2) NOT NULL COMMENT 'Stock del sistema al momento del registro',
    `counted_quantity` DECIMAL(12, 2) NOT NULL COMMENT 'Cantidad física contada',
    `difference` DECIMAL(12, 2) AS (counted_quantity - system_stock) STORED COMMENT 'Diferencia calculada',
    `comments` TEXT NULL COMMENT 'Comentarios opcionales del usuario',
    `is_edited` BOOLEAN DEFAULT FALSE COMMENT 'Flag si fue editado después de crearse',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_inv_entry_session` (`session_id`),
    INDEX `idx_inv_entry_user` (`user_id`),
    INDEX `idx_inv_entry_warehouse` (`warehouse_number`),
    INDEX `idx_inv_entry_product` (`product_code`),
    INDEX `idx_inv_entry_difference` (`difference`),
    INDEX `idx_inv_entry_created` (`created_at`),
    INDEX `idx_inv_entry_session_product` (`session_id`, `product_code`),
    INDEX `idx_inv_entry_session_user` (`session_id`, `user_id`),
    INDEX `idx_inv_entry_session_warehouse` (`session_id`, `warehouse_number`),

    CONSTRAINT `fk_inv_entry_session` FOREIGN KEY (`session_id`)
        REFERENCES `inventory_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_entry_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. TABLA DE HISTORIAL DE EDICIONES
-- =====================================================
-- Trazabilidad completa de cambios en las entradas

CREATE TABLE IF NOT EXISTS `inventory_entry_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `entry_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'Usuario que realizó la acción',
    `action` ENUM('created', 'updated', 'deleted') NOT NULL,
    `old_counted_quantity` DECIMAL(12, 2) NULL,
    `new_counted_quantity` DECIMAL(12, 2) NULL,
    `old_comments` TEXT NULL,
    `new_comments` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_inv_history_entry` (`entry_id`),
    INDEX `idx_inv_history_user` (`user_id`),
    INDEX `idx_inv_history_action` (`action`),
    INDEX `idx_inv_history_created` (`created_at`),

    CONSTRAINT `fk_inv_history_entry` FOREIGN KEY (`entry_id`)
        REFERENCES `inventory_entries`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_history_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. TABLA DE USUARIOS ASIGNADOS A SESION (OPCIONAL)
-- =====================================================
-- Control granular de qué usuarios pueden participar en cada sesión
-- Si no hay registros, cualquier usuario con rol puede participar

CREATE TABLE IF NOT EXISTS `inventory_session_users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `session_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `assigned_warehouse_number` INT NULL COMMENT 'Si es NULL, puede registrar en cualquier almacén de la sesión',
    `assigned_by` INT NOT NULL COMMENT 'Supervisor que asignó al usuario',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_active` BOOLEAN DEFAULT TRUE,

    UNIQUE KEY `uk_session_user` (`session_id`, `user_id`),
    INDEX `idx_inv_su_session` (`session_id`),
    INDEX `idx_inv_su_user` (`user_id`),
    INDEX `idx_inv_su_warehouse` (`assigned_warehouse_number`),

    CONSTRAINT `fk_inv_su_session` FOREIGN KEY (`session_id`)
        REFERENCES `inventory_sessions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_su_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_su_assigned_by` FOREIGN KEY (`assigned_by`)
        REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. VISTAS UTILES PARA REPORTES
-- =====================================================

-- Vista de resumen por sesión
CREATE OR REPLACE VIEW `v_inventory_session_summary` AS
SELECT
    s.id AS session_id,
    s.company_id,
    s.name AS session_name,
    s.status,
    s.opened_at,
    s.closed_at,
    creator.username AS created_by_username,
    closer.username AS closed_by_username,
    COUNT(DISTINCT e.id) AS total_entries,
    COUNT(DISTINCT e.user_id) AS total_users,
    COUNT(DISTINCT e.product_code) AS total_products,
    SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
    SUM(CASE WHEN e.difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
    SUM(CASE WHEN e.difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count
FROM inventory_sessions s
LEFT JOIN users creator ON s.created_by = creator.id
LEFT JOIN users closer ON s.closed_by = closer.id
LEFT JOIN inventory_entries e ON s.id = e.session_id
GROUP BY s.id;

-- Vista de estadísticas por usuario en sesión
CREATE OR REPLACE VIEW `v_inventory_user_stats` AS
SELECT
    e.session_id,
    e.user_id,
    u.username,
    u.first_name,
    u.last_name,
    COUNT(*) AS total_entries,
    COUNT(DISTINCT e.product_code) AS unique_products,
    SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
    SUM(CASE WHEN e.difference != 0 THEN 1 ELSE 0 END) AS discrepancy_count,
    ROUND(SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS accuracy_percentage,
    MIN(e.created_at) AS first_entry_at,
    MAX(e.created_at) AS last_entry_at,
    TIMESTAMPDIFF(MINUTE, MIN(e.created_at), MAX(e.created_at)) AS active_minutes
FROM inventory_entries e
JOIN users u ON e.user_id = u.id
GROUP BY e.session_id, e.user_id;

-- Vista de discrepancias
CREATE OR REPLACE VIEW `v_inventory_discrepancies` AS
SELECT
    e.id AS entry_id,
    e.session_id,
    e.user_id,
    u.username,
    e.warehouse_number,
    e.product_code,
    e.product_description,
    e.system_stock,
    e.counted_quantity,
    e.difference,
    CASE
        WHEN e.difference = 0 THEN 'Coincide'
        WHEN e.difference < 0 THEN 'Faltante'
        ELSE 'Sobrante'
    END AS status,
    e.comments,
    e.created_at
FROM inventory_entries e
JOIN users u ON e.user_id = u.id;

-- =====================================================
-- 8. INDICES ADICIONALES PARA RENDIMIENTO
-- =====================================================

-- Índice para búsqueda rápida de sesión activa por empresa
-- Nota: MySQL no soporta índices parciales (WHERE clause),
-- así que usamos un índice compuesto normal
CREATE INDEX `idx_inv_session_active`
ON `inventory_sessions`(`company_id`, `status`);

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================
-- Verificar que se crearon correctamente:
-- SHOW TABLES LIKE 'inventory%';
-- SELECT * FROM roles WHERE role_name LIKE '%Inventario%';
-- =====================================================
