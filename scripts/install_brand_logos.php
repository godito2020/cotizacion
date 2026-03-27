<?php
/**
 * Instalador: Tabla company_brands para logos de marcas por empresa
 * Ejecutar una sola vez desde el navegador: /scripts/install_brand_logos.php
 */
require_once __DIR__ . '/../includes/init.php';

$db = getDBConnection();

$sql = "
CREATE TABLE IF NOT EXISTS company_brands (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    company_id  INT NOT NULL,
    brand_name  VARCHAR(100) NOT NULL,
    logo_url    VARCHAR(500) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company (company_id),
    INDEX idx_company_active (company_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $db->exec($sql);
    echo '<p style="color:green;font-family:sans-serif;">✅ Tabla <strong>company_brands</strong> creada correctamente.</p>';
} catch (Exception $e) {
    echo '<p style="color:red;font-family:sans-serif;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
