<?php
/**
 * Elimina el bloque <style> con "/* Force light theme ..." que fuerza el modo
 * claro (#ffffff !important / #212529 !important) y rompe el modo oscuro.
 * Variante del parche EMERGENCY con otro comentario. Hace backup. Idempotente.
 *
 * Uso:  php scripts/clean_force_light.php            (prueba)
 *       php scripts/clean_force_light.php --apply     (aplica)
 */
$root      = realpath(__DIR__ . '/../public');
$backupDir = __DIR__ . '/../backups/dark_cleanup_2026-06-21';
$apply     = in_array('--apply', $argv, true);

$reStyle = '#[ \t]*<style>(?:(?!</style>).)*?Force light theme(?:(?!</style>).)*?</style>\R?#s';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$changed = [];
foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $rel  = ltrim(str_replace($root, '', $path), '\\/');
    $content = file_get_contents($path);
    if (strpos($content, 'Force light theme') === false) continue;

    $new = preg_replace($reStyle, '', $content, 1);
    if ($new === $content) continue;

    if ($apply) {
        $bkPath = $backupDir . '/' . $rel;
        @mkdir(dirname($bkPath), 0755, true);
        if (!file_exists($bkPath)) copy($path, $bkPath);
        file_put_contents($path, $new);
    }
    $changed[] = $rel;
}
$mode = $apply ? 'APLICADO' : 'PRUEBA';
echo "=== Limpieza 'Force light theme' [$mode] ===\nModificadas: " . count($changed) . "\n";
foreach ($changed as $f) echo "  * $f\n";
