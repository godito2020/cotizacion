<?php
/**
 * Agrega cache-busting automatico a los CSS del tema:
 *   theme-pro.css  ->  theme-pro.css?v=<filemtime>
 *   style.css      ->  style.css?v=<filemtime>
 * Asi, cada vez que cambie el CSS, la URL cambia y el navegador/SW bajan
 * la version nueva sin limpiar cache manualmente. Backup + idempotente.
 *
 * Uso:  php scripts/add_css_version.php            (prueba)
 *       php scripts/add_css_version.php --apply     (aplica)
 */
$root      = realpath(__DIR__ . '/../public');
$backupDir = __DIR__ . '/../backups/css_version_2026-06-23';
$apply     = in_array('--apply', $argv, true);

// Snippet de version (se construye por partes para no cerrar el modo PHP aqui)
$verTP = '?v=<' . '?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ' . '?' . '>';
$verSC = '?v=<' . '?= @filemtime(APP_ROOT . "/public/assets/css/style.css") ' . '?' . '>';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$changed = [];
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $rel  = ltrim(str_replace($root, '', $path), '\\/');
    $content = file_get_contents($path);
    $orig = $content;

    if (strpos($content, 'assets/css/theme-pro.css"') !== false && strpos($content, 'theme-pro.css?v=') === false) {
        $content = str_replace('assets/css/theme-pro.css"', 'assets/css/theme-pro.css' . $verTP . '"', $content);
    }
    if (strpos($content, 'assets/css/style.css"') !== false && strpos($content, 'style.css?v=') === false) {
        $content = str_replace('assets/css/style.css"', 'assets/css/style.css' . $verSC . '"', $content);
    }

    if ($content === $orig) continue;

    if ($apply) {
        $bkPath = $backupDir . '/' . $rel;
        @mkdir(dirname($bkPath), 0755, true);
        if (!file_exists($bkPath)) copy($path, $bkPath);
        file_put_contents($path, $content);
    }
    $changed[] = $rel;
}
$mode = $apply ? 'APLICADO' : 'PRUEBA';
echo "=== Cache-busting CSS [$mode] ===\nModificadas: " . count($changed) . "\n";
foreach ($changed as $f) echo "  * $f\n";
