<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    die('No autorizado');
}

$db = getDBConnection();
$stmt = $db->query("SELECT id, name, logo_url FROM companies");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Debug de Logos de Empresas</h2>";
echo "<p>BASE_URL: " . BASE_URL . "</p>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Nombre</th><th>logo_url (DB)</th><th>Ruta Completa</th><th>Archivo Info</th><th>Preview</th></tr>";

foreach ($companies as $company) {
    $logoUrl = $company['logo_url'] ?? '';

    // Build full path
    $fullUrl = '';
    $fileInfo = 'N/A';

    if (!empty($logoUrl)) {
        // Ensure starts with /
        if (strpos($logoUrl, '/') !== 0 && strpos($logoUrl, 'http') !== 0) {
            $logoUrl = '/' . $logoUrl;
        }

        if (strpos($logoUrl, 'http') !== 0) {
            $fullUrl = BASE_URL . $logoUrl;
            $filePath = __DIR__ . '/..' . $logoUrl;

            if (file_exists($filePath)) {
                $size = filesize($filePath);
                $mime = mime_content_type($filePath);
                $perms = substr(sprintf('%o', fileperms($filePath)), -4);
                $fileInfo = "SÍ - Size: {$size} bytes, MIME: {$mime}, Perms: {$perms}";
            } else {
                $fileInfo = 'NO EXISTE';
            }
        } else {
            $fullUrl = $logoUrl;
        }
    }

    echo "<tr>";
    echo "<td>" . $company['id'] . "</td>";
    echo "<td>" . htmlspecialchars($company['name']) . "</td>";
    echo "<td>" . htmlspecialchars($company['logo_url'] ?? 'NULL') . "</td>";
    echo "<td><a href='" . htmlspecialchars($fullUrl) . "' target='_blank'>" . htmlspecialchars($fullUrl) . "</a></td>";
    echo "<td>" . $fileInfo . "</td>";
    echo "<td>";
    if (!empty($fullUrl)) {
        echo "<img src='" . htmlspecialchars($fullUrl) . "' style='max-height: 50px;' onerror=\"this.alt='Error al cargar'\">";
    } else {
        echo "Sin logo";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Also check settings table
echo "<h2>Settings de Logos</h2>";
$stmt2 = $db->query("SELECT company_id, setting_value FROM settings WHERE setting_key = 'company_logo_url'");
$settings = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Company ID</th><th>setting_value</th></tr>";
foreach ($settings as $setting) {
    echo "<tr>";
    echo "<td>" . $setting['company_id'] . "</td>";
    echo "<td>" . htmlspecialchars($setting['setting_value'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";
