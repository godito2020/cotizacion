<?php
/**
 * API para descargar plantilla de importación de imágenes
 * Genera un archivo CSV con las columnas codigo e imagen_url
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo 'No autorizado';
    exit;
}

// Verificar rol de administrador
if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
    http_response_code(403);
    echo 'Sin permisos';
    exit;
}

// Configurar headers para descarga CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="plantilla_imagenes_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Crear output stream
$output = fopen('php://output', 'w');

// Agregar BOM para UTF-8 (para que Excel lo reconozca correctamente)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir encabezados
fputcsv($output, ['codigo', 'imagen_url'], ';');

// Escribir filas de ejemplo
$ejemplos = [
    ['ABC123', 'https://ejemplo.com/imagenes/producto1.jpg'],
    ['XYZ789', 'https://ejemplo.com/imagenes/producto2.png'],
    ['DEF456', 'https://drive.google.com/uc?id=XXXXX'],
];

foreach ($ejemplos as $ejemplo) {
    fputcsv($output, $ejemplo, ';');
}

fclose($output);
exit;
