<?php
/**
 * API: Obtener datos en tiempo real
 * GET /inventario/api/get_realtime_data.php?session_id=1
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

if (!Permissions::canAccessInventoryPanel($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos de inventario']);
    exit;
}

try {
    $companyId = $auth->getCompanyId();
    $sessionId = (int)($_GET['session_id'] ?? 0);

    $session = new InventorySession();

    // Si no se especifica sesión, usar la activa
    if ($sessionId <= 0) {
        $activeSession = $session->getActiveSession($companyId);
        $sessionId = $activeSession ? $activeSession['id'] : 0;
    }

    if ($sessionId <= 0) {
        echo json_encode([
            'success' => true,
            'has_session' => false,
            'data' => null
        ]);
        exit;
    }

    $reports = new InventoryReports();
    $data = $reports->getRealTimeData($sessionId);

    echo json_encode([
        'success' => true,
        'has_session' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    error_log("API get_realtime_data Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
