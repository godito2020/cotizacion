<?php
/**
 * Instalador del Sistema de Facturación
 * Ejecutar desde: http://localhost/cotizacion/install_billing_system.php
 */

require_once __DIR__ . '/includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole(['System Admin', 'Company Admin'])) {
    die('Acceso denegado. Solo administradores pueden ejecutar este script.');
}

$db = getDBConnection();

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalador Sistema de Facturación</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        h2 { color: #333; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .step { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
    </style>
</head>
<body>
    <h1>🔧 Instalador del Sistema de Facturación</h1>";

try {
    echo "<div class='alert alert-info'><strong>Iniciando instalación...</strong></div>";

    // Paso 1: Crear rol de Facturación
    echo "<div class='step'>";
    echo "<h2>📝 Paso 1: Crear Rol de Facturación</h2>";

    $sql = "INSERT INTO roles (role_name, description)
            VALUES ('Facturación', 'Usuario encargado de facturar las cotizaciones aceptadas')
            ON DUPLICATE KEY UPDATE description = 'Usuario encargado de facturar las cotizaciones aceptadas'";

    $db->exec($sql);
    echo "<p>✅ Rol 'Facturación' creado exitosamente</p>";
    echo "</div>";

    // Paso 2: Modificar tabla quotations
    echo "<div class='step'>";
    echo "<h2>📝 Paso 2: Actualizar tabla quotations</h2>";

    // Verificar si las columnas ya existen
    $stmt = $db->query("SHOW COLUMNS FROM quotations LIKE 'billing_status'");
    if ($stmt->rowCount() == 0) {
        $sql = "ALTER TABLE quotations
                MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Draft',
                ADD COLUMN billing_status VARCHAR(20) NULL DEFAULT NULL COMMENT 'Pending_Invoice, Invoiced, Invoice_Rejected' AFTER status,
                ADD COLUMN invoice_number VARCHAR(50) NULL COMMENT 'Número de factura' AFTER billing_status,
                ADD COLUMN invoiced_at TIMESTAMP NULL COMMENT 'Fecha de facturación' AFTER invoice_number";

        $db->exec($sql);
        echo "<p>✅ Columnas agregadas a tabla quotations</p>";
    } else {
        echo "<p>ℹ️ Columnas ya existen en tabla quotations</p>";
    }

    // Crear índices
    try {
        $db->exec("CREATE INDEX idx_billing_status ON quotations(billing_status)");
        echo "<p>✅ Índice idx_billing_status creado</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>ℹ️ Índice idx_billing_status ya existe</p>";
        } else {
            throw $e;
        }
    }

    try {
        $db->exec("CREATE INDEX idx_invoice_number ON quotations(invoice_number)");
        echo "<p>✅ Índice idx_invoice_number creado</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "<p>ℹ️ Índice idx_invoice_number ya existe</p>";
        } else {
            throw $e;
        }
    }
    echo "</div>";

    // Paso 3: Crear tabla de seguimiento
    echo "<div class='step'>";
    echo "<h2>📝 Paso 3: Crear tabla quotation_billing_tracking</h2>";

    $sql = "CREATE TABLE IF NOT EXISTS quotation_billing_tracking (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        quotation_id INT(11) NOT NULL,
        company_id INT(11) NOT NULL,
        seller_id INT(11) NOT NULL COMMENT 'Usuario vendedor que solicita facturación',
        billing_user_id INT(11) NULL COMMENT 'Usuario de facturación asignado',
        status ENUM('Pending', 'In_Process', 'Invoiced', 'Rejected') NOT NULL DEFAULT 'Pending',
        requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL,
        invoice_number VARCHAR(50) NULL COMMENT 'Número de factura generada',
        rejection_reason TEXT NULL COMMENT 'Motivo de rechazo si aplica',
        observations TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (billing_user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_quotation (quotation_id),
        INDEX idx_company (company_id),
        INDEX idx_seller (seller_id),
        INDEX idx_billing_user (billing_user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<p>✅ Tabla quotation_billing_tracking creada</p>";
    echo "</div>";

    // Paso 4: Crear tabla de historial
    echo "<div class='step'>";
    echo "<h2>📝 Paso 4: Crear tabla quotation_billing_history</h2>";

    $sql = "CREATE TABLE IF NOT EXISTS quotation_billing_history (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        tracking_id INT(11) NOT NULL,
        quotation_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        action VARCHAR(50) NOT NULL COMMENT 'requested, approved, rejected, invoiced',
        previous_status VARCHAR(50) NULL,
        new_status VARCHAR(50) NOT NULL,
        observations TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tracking_id) REFERENCES quotation_billing_tracking(id) ON DELETE CASCADE,
        FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_tracking (tracking_id),
        INDEX idx_quotation (quotation_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<p>✅ Tabla quotation_billing_history creada</p>";
    echo "</div>";

    // Resumen
    echo "<div class='alert alert-success'>";
    echo "<h2>✅ ¡Instalación Completada Exitosamente!</h2>";
    echo "<h3>Componentes instalados:</h3>";
    echo "<ul>";
    echo "<li>✅ Rol de Facturación</li>";
    echo "<li>✅ Tabla quotations actualizada (billing_status, invoice_number, invoiced_at)</li>";
    echo "<li>✅ Tabla quotation_billing_tracking creada</li>";
    echo "<li>✅ Tabla quotation_billing_history creada</li>";
    echo "<li>✅ Índices optimizados</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='alert alert-info'>";
    echo "<h3>📋 Próximos pasos:</h3>";
    echo "<ol>";
    echo "<li>Asignar el rol 'Facturación' a los usuarios correspondientes</li>";
    echo "<li>Los vendedores podrán marcar cotizaciones aceptadas como 'Para Facturar'</li>";
    echo "<li>Los facturadores verán las solicitudes pendientes</li>";
    echo "<li>Los administradores verán todo el proceso</li>";
    echo "</ol>";
    echo "</div>";

    echo "<div class='alert alert-info'>";
    echo "<h3>🔄 Estados del sistema:</h3>";
    echo "<strong>Estados de billing_status en quotations:</strong>";
    echo "<ul>";
    echo "<li><code>NULL</code> - Sin solicitud de facturación</li>";
    echo "<li><code>Pending_Invoice</code> - Pendiente de facturar</li>";
    echo "<li><code>Invoiced</code> - Facturado</li>";
    echo "<li><code>Invoice_Rejected</code> - Rechazado por facturación</li>";
    echo "</ul>";
    echo "<strong>Estados en quotation_billing_tracking:</strong>";
    echo "<ul>";
    echo "<li><code>Pending</code> - Esperando atención</li>";
    echo "<li><code>In_Process</code> - En proceso</li>";
    echo "<li><code>Invoiced</code> - Completado</li>";
    echo "<li><code>Rejected</code> - Rechazado</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>❌ Error durante la instalación</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Nota:</strong> Este script puede ejecutarse múltiples veces de forma segura.</p>";
echo "</body></html>";
?>
