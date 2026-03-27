<?php
/**
 * Versión simplificada para debug
 */
header('Content-Type: application/json');

// Log todo
error_log("=== SIMPLE IMPORT START ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("FILES count: " . count($_FILES));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar archivo
if (empty($_FILES)) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron archivos', 'debug' => 'FILES array vacío']);
    exit;
}

$file = $_FILES['excel_file'] ?? $_FILES['archivo'] ?? null;
if (!$file) {
    echo json_encode(['success' => false, 'message' => 'Campo de archivo no encontrado', 'keys' => array_keys($_FILES)]);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Excede upload_max_filesize en php.ini',
        UPLOAD_ERR_FORM_SIZE => 'Excede MAX_FILE_SIZE del formulario',
        UPLOAD_ERR_PARTIAL => 'Archivo subido parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se subió archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir archivo',
        UPLOAD_ERR_EXTENSION => 'Extensión PHP bloqueó subida'
    ];
    echo json_encode([
        'success' => false,
        'message' => $errors[$file['error']] ?? 'Error desconocido',
        'error_code' => $file['error']
    ]);
    exit;
}

// Archivo recibido OK
echo json_encode([
    'success' => true,
    'message' => 'Archivo recibido correctamente',
    'file_name' => $file['name'],
    'file_size' => $file['size'],
    'file_type' => $file['type'],
    'tmp_exists' => file_exists($file['tmp_name'])
]);
