<?php
/**
 * Manifest dinámico para PWA - Usa el favicon de la empresa activa
 */

ob_start();
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$companyId = $auth->isLoggedIn() ? $auth->getCompanyId() : 1;

// ── Leer configuración de la empresa ────────────────────────────────────────
$companyName  = 'Inventario Físico';
$faviconUrl   = null;
$logoUrl      = null;
$themeColor   = '#0d6efd';

try {
    $db = getDBConnection();
    $stmt = $db->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE company_id = ?
           AND setting_key IN ('company_name','company_logo_url','company_favicon_url','pdf_header_color')"
    );
    $stmt->execute([$companyId]);
    $cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($cfg['company_name']))        $companyName = $cfg['company_name'] . ' - Inventario';
    if (!empty($cfg['company_favicon_url'])) $faviconUrl  = $cfg['company_favicon_url'];
    if (!empty($cfg['company_logo_url']))    $logoUrl     = $cfg['company_logo_url'];
    if (!empty($cfg['pdf_header_color']))    $themeColor  = $cfg['pdf_header_color'];
} catch (Exception $e) {
    error_log('inventario/manifest.php DB error: ' . $e->getMessage());
}

// ── Construir lista de iconos ───────────────────────────────────────────────
$iconBase       = BASE_URL . '/assets/icons';
$companyIconUrl = $faviconUrl ?: $logoUrl;   // favicon primero, luego logo
$icons          = [];

// Rutas de iconos PWA pre-generados de la empresa
$pwaDir        = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company';
$preGen192Path = $pwaDir . DIRECTORY_SEPARATOR . "pwa_{$companyId}_192x192.png";
$preGen512Path = $pwaDir . DIRECTORY_SEPARATOR . "pwa_{$companyId}_512x512.png";
$hasPreGen192  = file_exists($preGen192Path);
$hasPreGen512  = file_exists($preGen512Path);

// Generar iconos PWA si no existen
if (!$hasPreGen192 && $companyIconUrl) {
    try {
        $companySettings  = new CompanySettings();
        $physicalIconPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
                          . str_replace('/', DIRECTORY_SEPARATOR, preg_replace('#^uploads/#', '', ltrim($companyIconUrl, '/')));
        if (file_exists($physicalIconPath)) {
            $companySettings->generatePwaIcons($physicalIconPath, $companyId);
            $hasPreGen192 = file_exists($preGen192Path);
            $hasPreGen512 = file_exists($preGen512Path);
        }
    } catch (Exception $e) {
        error_log('inventario/manifest.php lazy icon generation failed: ' . $e->getMessage());
    }
}

if ($hasPreGen192 || $hasPreGen512) {
    // Iconos redimensionados automáticamente — usar upload_url() para proxy PHP
    if ($hasPreGen192) {
        $src192 = upload_url("uploads/company/pwa_{$companyId}_192x192.png");
        $icons[] = ['src' => $src192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
        $icons[] = ['src' => $src192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'];
    }
    if ($hasPreGen512) {
        $src512 = upload_url("uploads/company/pwa_{$companyId}_512x512.png");
        $icons[] = ['src' => $src512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
        $icons[] = ['src' => $src512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
    }
} elseif ($companyIconUrl) {
    // No hay iconos pre-generados — usar imagen original
    $src = upload_url($companyIconUrl);
    $icons[] = ['src' => $src, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
    $icons[] = ['src' => $src, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
    $icons[] = ['src' => $src, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
} else {
    // Sin icono de empresa — usar fallbacks genéricos
    $icons[] = ['src' => "$iconBase/icon-192x192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
    $icons[] = ['src' => "$iconBase/icon-192x192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'];
    $icons[] = ['src' => "$iconBase/icon-512x512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
    $icons[] = ['src' => "$iconBase/icon-512x512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
}

// ── Enviar manifest JSON ────────────────────────────────────────────────────
ob_end_clean();

$manifest = [
    'name' => $companyName,
    'short_name' => 'Inventario',
    'description' => 'Sistema de inventario físico para conteo de productos',
    'start_url' => './dashboard.php',
    'display' => 'fullscreen',
    'display_override' => ['fullscreen', 'standalone'],
    'background_color' => '#f0f2f5',
    'theme_color' => $themeColor,
    'orientation' => 'portrait',
    'scope' => './',
    'lang' => 'es',
    'categories' => ['business', 'productivity'],
    'icons' => $icons,
    'shortcuts' => [
        [
            'name' => 'Buscar Producto',
            'short_name' => 'Buscar',
            'description' => 'Buscar y contar productos',
            'url' => './dashboard.php'
        ]
    ]
];

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
