<?php
/**
 * API: Abrir/Crear sesión de inventario
 * POST /inventario/api/open_session.php
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

if (!Permissions::canManageInventorySessions($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para gestionar sesiones de inventario']);
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

    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '') ?: null;
    $warehouses = $input['warehouses'] ?? [];

    // Validaciones
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre de la sesión es requerido']);
        exit;
    }

    if (empty($warehouses) || !is_array($warehouses)) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar al menos un almacén']);
        exit;
    }

    // Convertir a enteros
    $warehouseNumbers = array_map('intval', $warehouses);
    $warehouseNumbers = array_filter($warehouseNumbers, fn($w) => $w > 0);

    if (empty($warehouseNumbers)) {
        echo json_encode(['success' => false, 'message' => 'Almacenes no válidos']);
        exit;
    }

    // Verificar que no haya sesión activa
    $session = new InventorySession();
    $activeSession = $session->getActiveSession($companyId);

    if ($activeSession) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya existe una sesión activa: ' . $activeSession['name']
        ]);
        exit;
    }

    // Crear sesión
    $sessionId = $session->create($companyId, $userId, $name, $description, $warehouseNumbers);

    if ($sessionId) {
        $newSession = $session->getById($sessionId);
        echo json_encode([
            'success' => true,
            'message' => 'Sesión de inventario creada correctamente',
            'data' => [
                'id' => $sessionId,
                'name' => $newSession['name'],
                'status' => $newSession['status'],
                'opened_at' => $newSession['opened_at'],
                'warehouses' => $newSession['warehouses']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear la sesión de inventario']);
    }

} catch (Exception $e) {
    error_log("API open_session Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
