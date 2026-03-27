<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    die('No autorizado');
}

$db = getDBConnection();

echo "<h2>Sincronización de Empresas</h2>";
echo "<hr>";

// 1. Copiar configuración de APIs de empresa 1 a todas las empresas
echo "<h3>1. Copiando configuración de APIs</h3>";

$companies = $db->query("SELECT id, name FROM companies WHERE id != 1")->fetchAll(PDO::FETCH_ASSOC);
$apiSettings = $db->query("SELECT * FROM api_settings WHERE company_id = 1")->fetchAll(PDO::FETCH_ASSOC);

foreach ($companies as $company) {
    echo "<p><strong>Empresa: {$company['name']} (ID: {$company['id']})</strong></p>";

    foreach ($apiSettings as $api) {
        // Check if already exists
        $checkStmt = $db->prepare("SELECT id FROM api_settings WHERE company_id = ? AND api_name = ?");
        $checkStmt->execute([$company['id'], $api['api_name']]);

        if ($checkStmt->fetch()) {
            // Update existing
            $updateStmt = $db->prepare("
                UPDATE api_settings
                SET api_url = ?, api_key = ?, api_secret = ?, additional_config = ?, is_active = ?
                WHERE company_id = ? AND api_name = ?
            ");
            $result = $updateStmt->execute([
                $api['api_url'],
                $api['api_key'],
                $api['api_secret'],
                $api['additional_config'],
                $api['is_active'],
                $company['id'],
                $api['api_name']
            ]);
            echo "  - Actualizado API: {$api['api_name']}<br>";
        } else {
            // Insert new - get next ID
            $maxIdStmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM api_settings");
            $nextId = $maxIdStmt->fetch(PDO::FETCH_ASSOC)['next_id'];

            $insertStmt = $db->prepare("
                INSERT INTO api_settings (id, company_id, api_name, api_url, api_key, api_secret, additional_config, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $insertStmt->execute([
                $nextId,
                $company['id'],
                $api['api_name'],
                $api['api_url'],
                $api['api_key'],
                $api['api_secret'],
                $api['additional_config'],
                $api['is_active']
            ]);
            echo "  - Creado API: {$api['api_name']}<br>";
        }
    }
}

echo "<hr>";

// 2. Sincronizar datos de empresas a settings
echo "<h3>2. Sincronizando datos de empresas a settings</h3>";

$allCompanies = $db->query("SELECT * FROM companies")->fetchAll(PDO::FETCH_ASSOC);

foreach ($allCompanies as $company) {
    echo "<p><strong>Empresa: {$company['name']} (ID: {$company['id']})</strong></p>";

    $settingsToSync = [
        'company_name' => $company['name'] ?? '',
        'company_tax_id' => $company['tax_id'] ?? '',
        'company_address' => $company['address'] ?? '',
        'company_phone' => $company['phone'] ?? '',
        'company_email' => $company['email'] ?? '',
        'company_logo_url' => $company['logo_url'] ? ltrim($company['logo_url'], '/') : ''
    ];

    foreach ($settingsToSync as $key => $value) {
        // Check if setting exists
        $checkStmt = $db->prepare("SELECT id FROM settings WHERE company_id = ? AND setting_key = ?");
        $checkStmt->execute([$company['id'], $key]);

        if ($checkStmt->fetch()) {
            // Update existing
            $updateStmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE company_id = ? AND setting_key = ?");
            $updateStmt->execute([$value, $company['id'], $key]);
            echo "  - Actualizado setting: {$key} = {$value}<br>";
        } else {
            // Insert new - get next ID
            $maxIdStmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM settings");
            $nextId = $maxIdStmt->fetch(PDO::FETCH_ASSOC)['next_id'];

            $insertStmt = $db->prepare("INSERT INTO settings (id, company_id, setting_key, setting_value) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$nextId, $company['id'], $key, $value]);
            echo "  - Creado setting: {$key} = {$value}<br>";
        }
    }
}

echo "<hr>";
echo "<h3>✅ Sincronización completada</h3>";
echo "<p><a href='companies.php' class='btn btn-primary'>Volver a Empresas</a></p>";
