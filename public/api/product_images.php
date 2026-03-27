<?php
/**
 * API para obtener imágenes de un producto
 */
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$codigo = trim($_GET['codigo'] ?? '');

if (empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Código de producto requerido']);
    exit;
}

try {
    $productRepo = new Product();
    $images = $productRepo->getProductImages($codigo);

    echo json_encode([
        'success' => true,
        'images' => $images
    ]);
} catch (Exception $e) {
    error_log("Error getting product images: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener imágenes'
    ]);
}
