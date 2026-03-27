<?php
/**
 * API: Exportar inventario a Excel
 * GET /inventario/api/export_excel.php?session_id=1&type=complete
 */

error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!Permissions::canAccessInventoryPanel($auth)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos de inventario']);
    exit;
}

try {
    $companyId = $auth->getCompanyId();
    $userId = $auth->getUserId();

    $sessionId = (int)($_GET['session_id'] ?? 0);
    $type = $_GET['type'] ?? 'complete';
    $exportUserId = (int)($_GET['user_id'] ?? 0);

    // Si no se especifica sesión, usar la activa
    $session = new InventorySession();
    if ($sessionId <= 0) {
        $activeSession = $session->getActiveSession($companyId);
        $sessionId = $activeSession ? $activeSession['id'] : 0;
    }

    if ($sessionId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No hay sesión de inventario']);
        exit;
    }

    // Verificar permisos según tipo de exportación
    $validTypes = ['complete', 'user', 'discrepancies', 'matching', 'summary'];
    if (!in_array($type, $validTypes)) {
        $type = 'complete';
    }

    // Si es exportación de usuario, verificar permisos
    if ($type === 'user') {
        if ($exportUserId <= 0) {
            $exportUserId = $userId; // Exportar propios datos
        }

        // Solo puede exportar datos de otros si tiene permiso
        if ($exportUserId !== $userId && !Permissions::canViewAllInventory($auth)) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo puedes exportar tus propios datos']);
            exit;
        }
    }

    // Para otros tipos, requiere permiso de reportes
    if (in_array($type, ['complete', 'discrepancies', 'matching', 'summary'])) {
        if (!Permissions::canGenerateInventoryReports($auth) && !Permissions::canViewAllInventory($auth)) {
            // Si no tiene permisos, solo puede exportar sus propios datos
            $type = 'user';
            $exportUserId = $userId;
        }
    }

    $reports = new InventoryReports();
    $filePath = $reports->exportToExcel($sessionId, $type, $exportUserId > 0 ? $exportUserId : null);

    // Generar nombre del archivo
    $sessionData = $session->getById($sessionId);
    $sessionName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionData['name'] ?? 'Inventario');
    $filename = "Inventario_{$sessionName}_{$type}_" . date('Y-m-d_His') . ".xlsx";

    // Descargar archivo
    $reports->downloadExcel($filePath, $filename);

} catch (Exception $e) {
    error_log("API export_excel Error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al generar el archivo Excel']);
}
