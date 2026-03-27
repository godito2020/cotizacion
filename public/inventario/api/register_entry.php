<?php
/**
 * API: Registrar entrada de inventario
 * POST /inventario/api/register_entry.php
 */

error_reporting(E_ERROR | E_PARSE);
ob_start();

require_once __DIR__ . '/../../../includes/init.php';

header('Content-Type: application/json; charset=utf-8');
ob_clean();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!Permissions::canRegisterInventory($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para registrar inventario']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $companyId = $auth->getCompanyId();
    $userId = $auth->getUserId();

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $productCode = trim($input['product_code'] ?? '');
    $countedQuantity = (float)($input['counted_quantity'] ?? 0);
    $comments = trim($input['comments'] ?? '') ?: null;
    $warehouseNumber = (int)($input['warehouse_number'] ?? ($_SESSION['inventory_warehouse'] ?? 0));
    $zoneId = !empty($input['zone_id']) ? (int)$input['zone_id'] : null;

    // Validaciones
    if (empty($productCode)) {
        echo json_encode(['success' => false, 'message' => 'Código de producto requerido']);
        exit;
    }

    if ($countedQuantity < 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad no puede ser negativa']);
        exit;
    }

    if ($warehouseNumber <= 0) {
        echo json_encode(['success' => false, 'message' => 'Almacén no válido']);
        exit;
    }

    // Verificar sesión activa
    $session = new InventorySession();
    $activeSession = $session->getActiveSession($companyId);

    if (!$activeSession) {
        echo json_encode(['success' => false, 'message' => 'No hay una sesión de inventario activa']);
        exit;
    }

    // Verificar que el usuario puede registrar
    if (!$session->canUserRegister($activeSession['id'], $userId, $warehouseNumber)) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para registrar en este almacén']);
        exit;
    }

    // Registrar entrada
    $entry = new InventoryEntry();
    $entryId = $entry->create(
        $activeSession['id'],
        $userId,
        $warehouseNumber,
        $productCode,
        $countedQuantity,
        $comments,
        $zoneId
    );

    if ($entryId) {
        $newEntry = $entry->getById($entryId);
        echo json_encode([
            'success' => true,
            'message' => 'Registro guardado correctamente',
            'data' => [
                'id' => $entryId,
                'product_code' => $newEntry['product_code'],
                'system_stock' => (float)$newEntry['system_stock'],
                'counted_quantity' => (float)$newEntry['counted_quantity'],
                'difference' => (float)$newEntry['difference'],
                'created_at' => $newEntry['created_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar el registro']);
    }

} catch (Exception $e) {
    error_log("API register_entry Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
