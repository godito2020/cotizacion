<?php
require_once __DIR__ . '/../includes/init.php';

echo "Creating settings table for company configuration...\n";

try {
    $db = getDBConnection();

    // Create settings table
    echo "Creating settings table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_id INT NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT DEFAULT NULL,
        setting_type ENUM('string', 'number', 'boolean', 'json', 'file') DEFAULT 'string',
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        UNIQUE KEY unique_company_setting (company_id, setting_key),
        INDEX idx_company_key (company_id, setting_key)
    )";

    $db->exec($sql);
    echo "✓ Settings table created successfully\n";

    // Insert default company settings for existing companies
    echo "Inserting default settings for existing companies...\n";

    $companies = $db->query("SELECT id FROM companies")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($companies as $company) {
        $companyId = $company['id'];

        $defaultSettings = [
            ['company_name', '', 'string', 'Nombre de la empresa'],
            ['company_tax_id', '', 'string', 'RUC de la empresa'],
            ['company_address', '', 'string', 'Dirección de la empresa'],
            ['company_phone', '', 'string', 'Teléfono de la empresa'],
            ['company_email', '', 'string', 'Email de la empresa'],
            ['company_website', '', 'string', 'Sitio web de la empresa'],
            ['company_whatsapp', '', 'string', 'WhatsApp de la empresa'],
            ['company_logo_url', '', 'file', 'URL del logo de la empresa'],
            ['company_favicon_url', '', 'file', 'URL del favicon de la empresa'],
            ['smtp_enabled', '0', 'boolean', 'Habilitar SMTP'],
            ['smtp_host', '', 'string', 'Servidor SMTP'],
            ['smtp_port', '587', 'number', 'Puerto SMTP'],
            ['smtp_username', '', 'string', 'Usuario SMTP'],
            ['smtp_password', '', 'string', 'Contraseña SMTP'],
            ['smtp_encryption', 'tls', 'string', 'Encriptación SMTP'],
            ['email_from', '', 'string', 'Email remitente'],
            ['email_from_name', '', 'string', 'Nombre remitente'],
            ['email_reply_to', '', 'string', 'Email de respuesta'],
            ['sunat_api_enabled', '0', 'boolean', 'API SUNAT habilitada'],
            ['sunat_api_url', 'https://dniruc.apisperu.com/api/v1/ruc/{ruc}', 'string', 'URL API SUNAT'],
            ['sunat_api_token', '', 'string', 'Token API SUNAT'],
            ['sunat_timeout', '30', 'number', 'Timeout API SUNAT'],
            ['reniec_api_enabled', '0', 'boolean', 'API RENIEC habilitada'],
            ['reniec_api_url', 'https://dniruc.apisperu.com/api/v1/dni/{dni}', 'string', 'URL API RENIEC'],
            ['reniec_api_token', '', 'string', 'Token API RENIEC'],
            ['reniec_timeout', '30', 'number', 'Timeout API RENIEC'],
            ['whatsapp_api_enabled', '0', 'boolean', 'API WhatsApp habilitada'],
            ['whatsapp_api_url', '', 'string', 'URL API WhatsApp'],
            ['whatsapp_api_token', '', 'string', 'Token API WhatsApp'],
            ['whatsapp_phone', '', 'string', 'Número WhatsApp'],
            ['currency_default', 'PEN', 'string', 'Moneda predeterminada'],
            ['timezone', 'America/Lima', 'string', 'Zona horaria'],
            ['language', 'es', 'string', 'Idioma del sistema'],
            ['quotation_validity_days', '30', 'number', 'Días validez cotización'],
            ['quotation_prefix', 'COT', 'string', 'Prefijo cotizaciones'],
            ['low_stock_threshold', '10', 'number', 'Umbral stock bajo'],
            ['backup_enabled', '1', 'boolean', 'Respaldos automáticos'],
            ['notifications_enabled', '1', 'boolean', 'Notificaciones habilitadas']
        ];

        foreach ($defaultSettings as $setting) {
            $insertSql = "INSERT IGNORE INTO settings (company_id, setting_key, setting_value, setting_type, description)
                          VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($insertSql);
            $stmt->execute([
                $companyId,
                $setting[0], // key
                $setting[1], // value
                $setting[2], // type
                $setting[3]  // description
            ]);
        }
    }

    echo "✓ Default settings inserted for " . count($companies) . " companies\n";
    echo "Settings table created and configured successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>