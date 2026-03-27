-- Migración: Sistema de Zonas para Inventario
-- Fecha: 2024-02-06

-- Tabla de zonas de almacén (sin foreign keys para mayor compatibilidad)
CREATE TABLE IF NOT EXISTS inventory_zones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    warehouse_number INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    UNIQUE KEY unique_zone (company_id, warehouse_number, name),
    INDEX idx_zones_warehouse (company_id, warehouse_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar columna zone_id a inventory_entries si no existe
-- Ejecutar por separado si da error
ALTER TABLE inventory_entries
ADD COLUMN zone_id INT NULL AFTER warehouse_number;

-- Tabla pivote para sesiones y zonas seleccionadas por usuario
CREATE TABLE IF NOT EXISTS inventory_session_user_zones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    zone_id INT NOT NULL,
    selected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_zone (session_id, user_id, zone_id),
    INDEX idx_user_zones_session (session_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
