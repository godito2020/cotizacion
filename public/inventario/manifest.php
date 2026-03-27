<?php
/**
 * Manifest dinámico para PWA - Usa el icono personalizado de inventario
 */

require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache, must-revalidate');

$auth = new Auth();
$companyId = $auth->isLoggedIn() ? $auth->getCompanyId() : 1;

// URL base completa
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;

// Buscar icono personalizado de inventario
$customIconPath = __DIR__ . '/../../uploads/pwa_icons/inventory_icon_' . $companyId . '.png';
$iconUrl = file_exists($customIconPath)
    ? $baseUrl . '/uploads/pwa_icons/inventory_icon_' . $companyId . '.png'
    : $baseUrl . '/assets/icons/icon-512x512.png';

$manifest = [
    'name' => 'Inventario Físico',
    'short_name' => 'Inventario',
    'description' => 'Sistema de inventario físico para conteo de productos',
    'start_url' => './dashboard.php',
    'display' => 'fullscreen',
    'display_override' => ['fullscreen', 'standalone'],
    'background_color' => '#f0f2f5',
    'theme_color' => '#0d6efd',
    'orientation' => 'portrait',
    'scope' => './',
    'lang' => 'es',
    'categories' => ['business', 'productivity'],
    'icons' => [
        [
            'src' => $iconUrl,
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '152x152',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '384x384',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconUrl,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ]
    ],
    'shortcuts' => [
        [
            'name' => 'Buscar Producto',
            'short_name' => 'Buscar',
            'description' => 'Buscar y contar productos',
            'url' => './dashboard.php',
            'icons' => [
                ['src' => $iconUrl, 'sizes' => '96x96']
            ]
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
