<?php
/**
 * API: Actualizar entrada de inventario
 * POST /inventario/api/update_entry.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $userId = $auth->getUserId();

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $entryId = (int)($input['entry_id'] ?? 0);
    $countedQuantity = (float)($input['counted_quantity'] ?? 0);
    $comments = trim($input['comments'] ?? '') ?: null;

    // Validaciones
    if ($entryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de entrada no válido']);
        exit;
    }

    if ($countedQuantity < 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad no puede ser negativa']);
        exit;
    }

    $entry = new InventoryEntry();
    $existingEntry = $entry->getById($entryId);

    if (!$existingEntry) {
        echo json_encode(['success' => false, 'message' => 'Entrada no encontrada']);
        exit;
    }

    // Verificar que el usuario puede editar (solo su propia entrada o admin)
    $canEdit = ($existingEntry['user_id'] == $userId && Permissions::userCan($auth, 'inventory_edit_own'))
               || Permissions::canViewAllInventory($auth);

    if (!$canEdit) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para editar esta entrada']);
        exit;
    }

    // Verificar que la sesión sigue abierta
    $session = new InventorySession();
    if (!$session->isOpen($existingEntry['session_id'])) {
        echo json_encode(['success' => false, 'message' => 'La sesión de inventario ya está cerrada']);
        exit;
    }

    // Actualizar
    $result = $entry->update($entryId, $userId, $countedQuantity, $comments);

    if ($result) {
        $updatedEntry = $entry->getById($entryId);
        echo json_encode([
            'success' => true,
            'message' => 'Registro actualizado correctamente',
            'data' => [
                'id' => $entryId,
                'counted_quantity' => (float)$updatedEntry['counted_quantity'],
                'difference' => (float)$updatedEntry['difference'],
                'updated_at' => $updatedEntry['updated_at']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el registro']);
    }

} catch (Exception $e) {
    error_log("API update_entry Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
