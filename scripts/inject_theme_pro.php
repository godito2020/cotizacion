<?php
/**
 * Inyector del tema profesional (theme-pro) en paginas de escritorio.
 * - Aditivo: inserta el script anti-parpadeo + el link a theme-pro.css
 *   justo despues del link de Bootstrap 5.3.0.
 * - Idempotente: omite paginas que ya lo tienen.
 * - Hace backup de cada archivo modificado.
 * - Excluye moviles / dev / test / debug para no alterar UIs sin revisar.
 *
 * Uso (CLI):  php scripts/inject_theme_pro.php           (modo prueba, solo lista)
 *             php scripts/inject_theme_pro.php --apply    (aplica cambios)
 */

$root      = realpath(__DIR__ . '/../public');
$backupDir = __DIR__ . '/../backups/style_rollout_2026-06-21';
$apply     = in_array('--apply', $argv, true);

// Patron del link de Bootstrap (ancla de insercion)
$bootstrapRe = '#(<link[^>]*bootstrap@5\.3\.0/dist/css/bootstrap\.min\.css[^>]*>)#i';

// Construir la etiqueta echo de BASE_URL por partes (evitar cerrar el modo PHP aqui)
$echoOpen  = '<' . '?= BASE_URL ' . '?' . '>';

// Bloque a inyectar (queda como PHP valido en el archivo destino)
$inject = "\n    <!-- Tema profesional COTI (no-flash + marca) -->\n"
        . "    <script>(function(){var t=localStorage.getItem('coti-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-bs-theme',t);})();</script>\n"
        . "    <link rel=\"stylesheet\" href=\"{$echoOpen}/assets/css/theme-pro.css\">";

// Exclusiones por ruta de archivo
$exclude = ['/_mobile\.php$/', '/debug/i', '/test/i', '/create_dev\.php$/', '/offline\.html$/', '/manifest/i', '/install_/i'];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$injected = []; $skipped = []; $noBootstrap = [];

foreach ($rii as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    $rel  = ltrim(str_replace($root, '', $path), '\\/');

    foreach ($exclude as $ex) { if (preg_match($ex, $path)) { continue 2; } }

    $content = file_get_contents($path);

    if (strpos($content, 'assets/css/theme-pro.css') !== false) { $skipped[] = $rel; continue; }
    if (!preg_match($bootstrapRe, $content)) { $noBootstrap[] = $rel; continue; }

    $new = preg_replace($bootstrapRe, '$1' . $inject, $content, 1);
    if ($new === null || $new === $content) { $skipped[] = $rel . ' (sin cambio)'; continue; }

    if ($apply) {
        $bkPath = $backupDir . '/' . $rel;
        @mkdir(dirname($bkPath), 0755, true);
        copy($path, $bkPath);
        file_put_contents($path, $new);
    }
    $injected[] = $rel;
}

$mode = $apply ? 'APLICADO' : 'PRUEBA (usar --apply para aplicar)';
echo "=== Inyeccion theme-pro [$mode] ===\n";
echo "Inyectadas: " . count($injected) . "\n";
foreach ($injected as $f) echo "  + $f\n";
echo "Omitidas (ya tenian / sin cambio): " . count($skipped) . "\n";
echo "Sin Bootstrap (no tocadas): " . count($noBootstrap) . "\n";
