<?php
/**
 * Proxy para servir archivos de uploads a través de PHP.
 * Evita el problema de permisos NTFS en IIS donde IUSR no puede leer
 * archivos creados por el proceso PHP (app pool identity).
 *
 * Uso: /public/img.php?f=company/archivo.png
 */

// Capturar cualquier output previo (errores PHP, warnings) para no corromper la imagen
ob_start();

// Construir ruta base sin realpath() (falla en Windows sin permiso de listar)
$uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

$f = $_GET['f'] ?? '';

// Seguridad: eliminar traversal y null bytes
$f = str_replace(['..', "\0", '\\'], '', $f);
$f = ltrim(trim($f), '/');

if ($f === '') {
    http_response_code(400);
    exit;
}

// Convertir separadores y construir ruta absoluta
$relativePath = str_replace('/', DIRECTORY_SEPARATOR, $f);
$fullPath     = $uploadsDir . DIRECTORY_SEPARATOR . $relativePath;

// Seguridad: verificar que la ruta esté dentro de uploads/ (case-insensitive en Windows)
$normalBase = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $uploadsDir), DIRECTORY_SEPARATOR);
$normalFull = str_replace('/', DIRECTORY_SEPARATOR, $fullPath);
if (stripos($normalFull, $normalBase . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    exit;
}

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'pdf'  => 'application/pdf',
];

if (!isset($mimeMap[$ext])) {
    http_response_code(403);
    exit;
}

ob_end_clean(); // descartar cualquier output previo antes de enviar la imagen
header('Content-Type: ' . $mimeMap[$ext]);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=31536000, immutable');
readfile($fullPath);
