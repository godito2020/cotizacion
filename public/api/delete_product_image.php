<?php
/**
 * API para eliminar imágenes de productos
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

$input = json_decode(file_get_contents('php://input'), true);
$imageId = (int)($input['id'] ?? 0);

if ($imageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de imagen requerido']);
    exit;
}

try {
    $db = getDBConnection();

    // Obtener URL de la imagen antes de eliminar
    $stmt = $db->prepare("SELECT imagen_url FROM imagenes WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        echo json_encode(['success' => false, 'message' => 'Imagen no encontrada']);
        exit;
    }

    // Eliminar registro de BD
    $productRepo = new Product();
    if ($productRepo->deleteImage($imageId)) {
        // Intentar eliminar archivo físico
        $imageUrl = $image['imagen_url'];
        if (strpos($imageUrl, '/uploads/products/') !== false) {
            $filename = basename($imageUrl);
            $filepath = PUBLIC_PATH . '/uploads/products/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Imagen eliminada']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar imagen']);
    }
} catch (Exception $e) {
    error_log("Error deleting product image: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
