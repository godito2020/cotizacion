<?php
require_once __DIR__ . '/../includes/init.php';

echo "Fixing settings table...\n";

try {
    $db = getDBConnection();

    // Drop and recreate settings table with simpler structure
    echo "Recreating settings table...\n";
    $db->exec("DROP TABLE IF EXISTS settings");

    $sql = "CREATE TABLE settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_id INT NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        UNIQUE KEY unique_company_setting (company_id, setting_key)
    )";

    $db->exec($sql);
    echo "✓ Settings table created successfully\n";

    // Insert default company settings for existing companies
    echo "Inserting default settings for existing companies...\n";

    $companies = $db->query("SELECT id FROM companies")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($companies as $company) {
        $companyId = $company['id'];

        $defaultSettings = [
            'company_name' => '',
            'company_tax_id' => '',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_website' => '',
            'company_whatsapp' => '',
            'company_logo_url' => '',
            'company_favicon_url' => '',
            'smtp_enabled' => '0',
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'email_from' => '',
            'email_from_name' => '',
            'email_reply_to' => '',
            'sunat_api_enabled' => '0',
            'sunat_api_url' => 'https://dniruc.apisperu.com/api/v1/ruc/{ruc}',
            'sunat_api_token' => '',
            'sunat_timeout' => '30',
            'reniec_api_enabled' => '0',
            'reniec_api_url' => 'https://dniruc.apisperu.com/api/v1/dni/{dni}',
            'reniec_api_token' => '',
            'reniec_timeout' => '30',
            'whatsapp_api_enabled' => '0',
            'whatsapp_api_url' => '',
            'whatsapp_api_token' => '',
            'whatsapp_phone' => '',
            'currency_default' => 'PEN',
            'timezone' => 'America/Lima',
            'quotation_validity_days' => '30',
            'quotation_prefix' => 'COT',
            'low_stock_threshold' => '10'
        ];

        foreach ($defaultSettings as $key => $value) {
            $insertSql = "INSERT IGNORE INTO settings (company_id, setting_key, setting_value)
                          VALUES (?, ?, ?)";
            $stmt = $db->prepare($insertSql);
            $stmt->execute([$companyId, $key, $value]);
        }
    }

    echo "✓ Default settings inserted for " . count($companies) . " companies\n";
    echo "Settings table fixed and configured successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>