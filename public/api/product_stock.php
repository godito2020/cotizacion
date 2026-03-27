<?php
/**
 * API para obtener stock de un producto por almacén
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
    $stockRepo = new Stock();

    // Obtener información del producto
    $producto = $productRepo->getByCode($codigo);
    if (!$producto) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }

    // Obtener stock por almacén
    $stock = $stockRepo->getStockByProduct($codigo);

    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'stock' => $stock
    ]);
} catch (Exception $e) {
    error_log("Error getting product stock: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener stock: ' . $e->getMessage()
    ]);
}
