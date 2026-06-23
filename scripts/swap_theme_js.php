<?php
/**
 * Cambia el theme.js viejo (inerte con el nuevo sistema) por theme-pro.js
 * en las paginas que aun lo cargan. Backup + idempotente.
 * Uso: php scripts/swap_theme_js.php --apply
 */
$root      = realpath(__DIR__ . '/../public');
$backupDir = __DIR__ . '/../backups/dark_cleanup_2026-06-21';
$apply     = in_array('--apply', $argv, true);

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$changed = [];
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    if (strpos($content, 'assets/js/theme.js') === false) continue;

    $new = str_replace('assets/js/theme.js', 'assets/js/theme-pro.js', $content);
    $rel = ltrim(str_replace($root, '', $path), '\\/');
    if ($apply) {
        $bkPath = $backupDir . '/' . $rel;
        @mkdir(dirname($bkPath), 0755, true);
        if (!file_exists($bkPath)) copy($path, $bkPath);
        file_put_contents($path, $new);
    }
    $changed[] = $rel;
}
$mode = $apply ? 'APLICADO' : 'PRUEBA';
echo "=== Swap theme.js -> theme-pro.js [$mode] ===\nModificadas: " . count($changed) . "\n";
foreach ($changed as $f) echo "  * $f\n";
