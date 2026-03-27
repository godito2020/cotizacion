<?php
/**
 * API para guardar imagen de producto desde URL
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
$imageUrl = trim($_POST['imagen_url'] ?? '');
$principal = isset($_POST['principal']) && ($_POST['principal'] === 'on' || $_POST['principal'] === '1' || $_POST['principal'] === true);

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código de producto requerido']);
    exit;
}

if (empty($imageUrl)) {
    echo json_encode(['success' => false, 'message' => 'URL de imagen requerida']);
    exit;
}

// Validar que sea una URL válida
if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'URL de imagen no válida']);
    exit;
}

// Verificar que el producto existe en COBOL
try {
    $productRepo = new Product();
    $product = $productRepo->getByCode($codigo);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado: ' . $codigo]);
        exit;
    }

    // Guardar imagen en BD
    $imageId = $productRepo->addImage($codigo, $imageUrl, $principal);

    if ($imageId) {
        echo json_encode([
            'success' => true,
            'message' => 'Imagen guardada correctamente',
            'image_id' => $imageId,
            'image_url' => $imageUrl,
            'codigo' => $codigo
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar imagen en base de datos']);
    }
} catch (Exception $e) {
    error_log("Error saving image URL: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
