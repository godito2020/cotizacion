<?php
/**
 * Script para actualizar referencias de roles de inglés a español
 * Ejecutar una sola vez: http://localhost/cotizacion/update_roles_to_spanish.php
 */

// Mapeo de roles
$roleMapping = [
    "'System Admin'" => "'Administrador del Sistema'",
    '"System Admin"' => '"Administrador del Sistema"',
    "'Company Admin'" => "'Administrador de Empresa'",
    '"Company Admin"' => '"Administrador de Empresa"',
    "'Salesperson'" => "'Vendedor'",
    '"Salesperson"' => '"Vendedor"',
];

// Directorios a procesar
$directories = [
    __DIR__ . '/public',
    __DIR__ . '/lib'
];

$filesUpdated = 0;
$filesProcessed = 0;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Actualizar Roles a Español</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        .success { color: green; }
        .info { color: blue; }
        .error { color: red; }
    </style>
</head>
<body>
<h1>🔄 Actualizando Referencias de Roles a Español</h1>
<pre>";

function processDirectory($dir, &$roleMapping, &$filesUpdated, &$filesProcessed) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filesProcessed++;
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);
            $originalContent = $content;

            // Reemplazar cada mapeo
            foreach ($roleMapping as $old => $new) {
                $content = str_replace($old, $new, $content);
            }

            // Si hubo cambios, guardar el archivo
            if ($content !== $originalContent) {
                file_put_contents($filePath, $content);
                $filesUpdated++;
                $relativePath = str_replace(__DIR__ . '/', '', $filePath);
                echo "<span class='success'>✓ Actualizado: $relativePath</span>\n";
            }
        }
    }
}

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "<span class='info'>📁 Procesando: $dir</span>\n";
        processDirectory($dir, $roleMapping, $filesUpdated, $filesProcessed);
    }
}

echo "\n";
echo "<span class='success'>========================================</span>\n";
echo "<span class='success'>✅ Proceso Completado</span>\n";
echo "<span class='info'>📊 Archivos procesados: $filesProcessed</span>\n";
echo "<span class='success'>📝 Archivos actualizados: $filesUpdated</span>\n";
echo "<span class='success'>========================================</span>\n";

echo "</pre>
<p><strong>Importante:</strong> Este script solo debe ejecutarse una vez. Puede eliminarlo después.</p>
<p><a href='" . (defined('BASE_URL') ? BASE_URL : '/cotizacion/public') . "/admin/users.php'>Ir a Gestión de Usuarios</a></p>
</body>
</html>";
?>
