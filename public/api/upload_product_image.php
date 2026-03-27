<?php
/**
 * API para subir imágenes de productos
 */
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar rol de administrador
if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$codigo = trim($_POST['codigo'] ?? '');
$principal = isset($_POST['principal']) && $_POST['principal'] === 'on';

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código de producto requerido']);
    exit;
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
    exit;
}

$file = $_FILES['imagen'];

// Validar tipo de archivo
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
    exit;
}

// Validar tamaño (máximo 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Archivo muy grande (máx 5MB)']);
    exit;
}

// Crear directorio de uploads si no existe
$uploadDir = PUBLIC_PATH . '/uploads/products/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generar nombre único
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $codigo . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Mover archivo
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar archivo']);
    exit;
}

// URL relativa para guardar en BD
$imageUrl = BASE_URL . '/uploads/products/' . $filename;

try {
    $productRepo = new Product();
    $imageId = $productRepo->addImage($codigo, $imageUrl, $principal);

    if ($imageId) {
        echo json_encode([
            'success' => true,
            'message' => 'Imagen subida correctamente',
            'image_id' => $imageId,
            'image_url' => $imageUrl
        ]);
    } else {
        // Eliminar archivo si falló el registro en BD
        unlink($filepath);
        echo json_encode(['success' => false, 'message' => 'Error al registrar imagen']);
    }
} catch (Exception $e) {
    // Eliminar archivo si hubo error
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    error_log("Error uploading product image: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
