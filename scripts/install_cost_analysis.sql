-- ============================================
-- Instalador del Módulo de Análisis de Costos
-- Ejecutar en la base de datos: cotizacion
-- ============================================

-- Tabla de acceso por usuario al módulo
CREATE TABLE IF NOT EXISTS `cost_analysis_access` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `granted_by` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_cost_analysis` (`user_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
