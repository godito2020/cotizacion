<?php
/**
 * Limpia el parche "EMERGENCY LIGHT THEME" que bloquea el modo oscuro.
 * Por cada pagina:
 *   1. Elimina el bloque <style>...EMERGENCY LIGHT THEME...</style>
 *   2. Elimina el bloque <script>...Emergency theme enforcement...</script>
 *   3. Cambia assets/js/theme.js -> assets/js/theme-pro.js (o lo inserta antes de </body>)
 * Hace backup de cada archivo. Idempotente.
 *
 * Uso:  php scripts/clean_emergency_theme.php            (prueba)
 *       php scripts/clean_emergency_theme.php --apply     (aplica)
 */

$root      = realpath(__DIR__ . '/../public');
$backupDir = __DIR__ . '/../backups/dark_cleanup_2026-06-21';
$apply     = in_array('--apply', $argv, true);

// Bloques a eliminar (lookahead para no cruzar el cierre del bloque)
$reStyle  = '#[ \t]*<style>(?:(?!</style>).)*?EMERGENCY LIGHT THEME(?:(?!</style>).)*?</style>\R?#s';
$reScript = '#[ \t]*<script>(?:(?!</script>).)*?Emergency theme enforcement(?:(?!</script>).)*?</script>\R?#s';

$themeProTag = '<script src="' . '<' . '?= BASE_URL ?' . '>' . '/assets/js/theme-pro.js"></script>';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$changed = []; $untouched = [];

foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $rel  = ltrim(str_replace($root, '', $path), '\\/');

    $content = file_get_contents($path);
    if (strpos($content, 'EMERGENCY LIGHT THEME') === false) { continue; }

    $new = $content;
    $new = preg_replace($reStyle, '', $new, 1);
    $new = preg_replace($reScript, '', $new, 1);

    // Asegurar theme-pro.js
    if (strpos($new, 'assets/js/theme.js') !== false) {
        $new = str_replace('assets/js/theme.js', 'assets/js/theme-pro.js', $new);
    } elseif (strpos($new, 'assets/js/theme-pro.js') === false) {
        // Insertar antes del ultimo </body>
        $pos = strripos($new, '</body>');
        if ($pos !== false) {
            $new = substr($new, 0, $pos) . "    " . $themeProTag . "\n" . substr($new, $pos);
        }
    }

    if ($new === $content) { $untouched[] = $rel . ' (sin cambio)'; continue; }

    if ($apply) {
        $bkPath = $backupDir . '/' . $rel;
        @mkdir(dirname($bkPath), 0755, true);
        copy($path, $bkPath);
        file_put_contents($path, $new);
    }
    $changed[] = $rel;
}

$mode = $apply ? 'APLICADO' : 'PRUEBA (usar --apply para aplicar)';
echo "=== Limpieza EMERGENCY / modo oscuro [$mode] ===\n";
echo "Modificadas: " . count($changed) . "\n";
foreach ($changed as $f) echo "  * $f\n";
if ($untouched) { echo "Sin cambio: " . count($untouched) . "\n"; foreach ($untouched as $f) echo "  - $f\n"; }
