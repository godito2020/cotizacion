<?php
/**
 * Dynamic PWA Manifest — per-company name and icon.
 */
ob_start(); // capture any stray PHP output/warnings before we send JSON headers
require_once __DIR__ . '/../includes/init.php';
// Keep buffering active so any PHP notices/warnings from our own code are also captured.

// ── Read company settings ────────────────────────────────────────────────────
$companyName  = 'COTIZACION GSM';
$shortName    = 'Coti GSM';
$logoUrl      = null;
$faviconUrl   = null;
$themeColor   = '#0d6efd';

try {
    $db        = getDBConnection();
    $companyId = (int)($_GET['c'] ?? $_SESSION['company_id'] ?? 1);
    if ($companyId <= 0) $companyId = 1;

    $stmt = $db->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE company_id = ?
           AND setting_key IN ('company_name','company_logo_url','company_favicon_url','pdf_header_color')"
    );
    $stmt->execute([$companyId]);
    $cfg = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($cfg['company_name']))        { $companyName = $cfg['company_name']; $shortName = $cfg['company_name']; }
    if (!empty($cfg['company_logo_url']))    $logoUrl     = $cfg['company_logo_url'];
    if (!empty($cfg['company_favicon_url'])) $faviconUrl  = $cfg['company_favicon_url'];
    if (!empty($cfg['pdf_header_color']))    $themeColor  = $cfg['pdf_header_color'];
} catch (Exception $e) {
    error_log('manifest.php DB error: ' . $e->getMessage());
}

// ── Build icon list ──────────────────────────────────────────────────────────
$iconBase       = BASE_URL . '/assets/icons';
$companyIconUrl = $faviconUrl ?: $logoUrl;   // favicon first, then logo
$icons          = [];

// Rutas de íconos pre-generados al subir el favicon/logo
$pwaDir        = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company';
$preGen192Path = $pwaDir . DIRECTORY_SEPARATOR . "pwa_{$companyId}_192x192.png";
$preGen512Path = $pwaDir . DIRECTORY_SEPARATOR . "pwa_{$companyId}_512x512.png";
$hasPreGen192  = file_exists($preGen192Path);
$hasPreGen512  = file_exists($preGen512Path);

// Lazy generation: si no existen los PNG pre-generados pero sí hay ícono de empresa, generarlos ahora
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
        error_log('manifest.php lazy icon generation failed: ' . $e->getMessage());
    }
}

if ($hasPreGen192 || $hasPreGen512) {
    // Íconos redimensionados automáticamente al subir — tamaños garantizados
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
    // No hay íconos pre-generados — usar imagen original con su tamaño real
    $src  = upload_url($companyIconUrl);
    $ext  = strtolower(pathinfo($companyIconUrl, PATHINFO_EXTENSION));
    $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg'
          : ($ext === 'webp' ? 'image/webp'
          : ($ext === 'svg'  ? 'image/svg+xml'
          : 'image/png'));

    if ($ext === 'svg') {
        $icons[] = ['src' => $src, 'sizes' => 'any',    'type' => $mime, 'purpose' => 'any'];
        $icons[] = ['src' => $src, 'sizes' => 'any',    'type' => $mime, 'purpose' => 'maskable'];
    } else {
        $physicalPath = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR
                      . str_replace('/', DIRECTORY_SEPARATOR, preg_replace('#^uploads/#', '', ltrim($companyIconUrl, '/')));
        $imgInfo  = @getimagesize($physicalPath);
        $realW    = $imgInfo ? (int)$imgInfo[0] : 0;
        $realH    = $imgInfo ? (int)$imgInfo[1] : 0;
        $realSize = min($realW, $realH);

        // Siempre incluir el ícono original de la empresa (a su tamaño real)
        $declaredSize = ($realSize > 0) ? "{$realSize}x{$realSize}" : 'any';
        $icons[] = ['src' => $src, 'sizes' => $declaredSize, 'type' => $mime, 'purpose' => 'any'];

        if ($realSize >= 192) {
            $icons[] = ['src' => $src, 'sizes' => '192x192', 'type' => $mime, 'purpose' => 'maskable'];
        }
        if ($realSize >= 512) {
            $icons[] = ['src' => $src, 'sizes' => '512x512', 'type' => $mime, 'purpose' => 'maskable'];
        }

        // Añadir fallbacks estáticos si la imagen no llega a 192px
        if ($realSize < 192) {
            $icons[] = ['src' => "$iconBase/icon-192x192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => "$iconBase/icon-512x512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
        }
    }
} else {
    // Sin ícono de empresa — usar fallbacks genéricos
    $icons[] = ['src' => "$iconBase/icon.svg",         'sizes' => 'any',     'type' => 'image/svg+xml', 'purpose' => 'any'];
    $icons[] = ['src' => "$iconBase/icon.svg",         'sizes' => 'any',     'type' => 'image/svg+xml', 'purpose' => 'maskable'];
    $icons[] = ['src' => "$iconBase/icon-192x192.png", 'sizes' => '192x192', 'type' => 'image/png',     'purpose' => 'any'];
    $icons[] = ['src' => "$iconBase/icon-192x192.png", 'sizes' => '192x192', 'type' => 'image/png',     'purpose' => 'maskable'];
    $icons[] = ['src' => "$iconBase/icon-512x512.png", 'sizes' => '512x512', 'type' => 'image/png',     'purpose' => 'any'];
    $icons[] = ['src' => "$iconBase/icon-512x512.png", 'sizes' => '512x512', 'type' => 'image/png',     'purpose' => 'maskable'];
}

// ── Send manifest JSON ───────────────────────────────────────────────────────
// Discard ALL output (PHP warnings, notices, debug output) before sending clean JSON
ob_end_clean();

$manifest = json_encode([
    'id'               => '/public/dashboard_mobile.php',
    'name'             => $companyName,
    'short_name'       => $shortName,
    'description'      => "Sistema de gestión de cotizaciones — {$shortName}",
    'start_url'        => '/public/dashboard_mobile.php',
    'display'          => 'fullscreen',
    'background_color' => '#ffffff',
    'theme_color'      => $themeColor,
    'orientation'      => 'any',
    'scope'            => '/public/',
    'lang'             => 'es',
    'dir'              => 'ltr',
    'categories'       => ['business', 'productivity'],
    'icons'            => $icons,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $manifest;
