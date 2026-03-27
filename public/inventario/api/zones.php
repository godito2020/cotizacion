<?php
/**
 * API: Gestión de Zonas de Inventario
 * POST /inventario/api/zones.php
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

try {
    $companyId = $auth->getCompanyId();
    $userId = $auth->getUserId();

    // GET request - obtener zonas
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $warehouseNumber = (int)($_GET['warehouse'] ?? 0);
        $sessionId = (int)($_GET['session_id'] ?? 0);
        $onlyActive = ($_GET['active'] ?? '1') === '1';

        $zone = new InventoryZone();

        if ($warehouseNumber > 0) {
            $zones = $zone->getByWarehouse($companyId, $warehouseNumber, $onlyActive);
        } else {
            $zones = $zone->getByCompany($companyId, $onlyActive);
        }

        // Si hay sesión, agregar información de zonas seleccionadas por el usuario
        if ($sessionId > 0) {
            $userZones = $zone->getUserZones($sessionId, $userId);
            $userZoneIds = array_column($userZones, 'id');

            foreach ($zones as &$z) {
                $z['is_selected'] = in_array($z['id'], $userZoneIds);
            }
        }

        echo json_encode(['success' => true, 'data' => $zones]);
        exit;
    }

    // POST request - crear/actualizar/eliminar
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    $zone = new InventoryZone();

    switch ($action) {
        case 'create':
            // Solo admin puede crear zonas
            if (!Permissions::canManageInventorySessions($auth)) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos para crear zonas']);
                exit;
            }

            $warehouseNumber = (int)($input['warehouse_number'] ?? 0);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $color = $input['color'] ?? '#6c757d';

            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
                exit;
            }

            if ($warehouseNumber <= 0) {
                echo json_encode(['success' => false, 'message' => 'Almacén no válido']);
                exit;
            }

            $zoneId = $zone->create($companyId, $warehouseNumber, $name, $description, $color, $userId);

            if ($zoneId) {
                echo json_encode(['success' => true, 'message' => 'Zona creada', 'zone_id' => $zoneId]);
            } else {
                $errorDetail = $zone->getLastError();
                echo json_encode(['success' => false, 'message' => 'Error al crear zona: ' . ($errorDetail ?: 'Error desconocido')]);
            }
            break;

        case 'update':
            if (!Permissions::canManageInventorySessions($auth)) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos para editar zonas']);
                exit;
            }

            $zoneId = (int)($input['zone_id'] ?? 0);
            $name = trim($input['name'] ?? '');
            $description = trim($input['description'] ?? '');
            $color = $input['color'] ?? '#6c757d';

            if ($zoneId <= 0 || empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit;
            }

            if ($zone->update($zoneId, $name, $description, $color)) {
                echo json_encode(['success' => true, 'message' => 'Zona actualizada']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar zona']);
            }
            break;

        case 'toggle':
            if (!Permissions::canManageInventorySessions($auth)) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }

            $zoneId = (int)($input['zone_id'] ?? 0);
            $isActive = $input['is_active'] === true || $input['is_active'] === 'true';

            if ($zone->toggleActive($zoneId, $isActive)) {
                echo json_encode(['success' => true, 'message' => $isActive ? 'Zona activada' : 'Zona desactivada']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al cambiar estado']);
            }
            break;

        case 'delete':
            if (!Permissions::canManageInventorySessions($auth)) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }

            $zoneId = (int)($input['zone_id'] ?? 0);

            if ($zone->delete($zoneId)) {
                echo json_encode(['success' => true, 'message' => 'Zona eliminada']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar: tiene registros asociados']);
            }
            break;

        case 'save_user_zones':
            // Cualquier usuario de inventario puede seleccionar zonas
            if (!Permissions::canAccessInventoryPanel($auth)) {
                echo json_encode(['success' => false, 'message' => 'Sin permisos']);
                exit;
            }

            $sessionId = (int)($input['session_id'] ?? 0);
            $zoneIds = $input['zone_ids'] ?? [];

            if ($sessionId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
                exit;
            }

            // Validar que las zonas existan
            $zoneIds = array_filter(array_map('intval', $zoneIds));

            if ($zone->saveUserZones($sessionId, $userId, $zoneIds)) {
                echo json_encode(['success' => true, 'message' => 'Zonas guardadas']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al guardar zonas']);
            }
            break;

        case 'get_user_zones':
            $sessionId = (int)($input['session_id'] ?? 0);

            if ($sessionId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
                exit;
            }

            $userZones = $zone->getUserZones($sessionId, $userId);
            echo json_encode(['success' => true, 'data' => $userZones]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }

} catch (Exception $e) {
    error_log("API zones Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
