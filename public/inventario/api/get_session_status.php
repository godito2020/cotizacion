<?php
/**
 * API: Obtener estado de sesión de inventario
 * GET /inventario/api/get_session_status.php
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

    if ($sessionId > 0) {
        $sessionData = $session->getById($sessionId);
    } else {
        $sessionData = $session->getActiveSession($companyId);
    }

    if (!$sessionData) {
        echo json_encode([
            'success' => true,
            'has_active_session' => false,
            'session' => null
        ]);
        exit;
    }

    $stats = $session->getSessionStats($sessionData['id']);

    echo json_encode([
        'success' => true,
        'has_active_session' => $sessionData['status'] === 'Open',
        'session' => [
            'id' => $sessionData['id'],
            'name' => $sessionData['name'],
            'status' => $sessionData['status'],
            'opened_at' => $sessionData['opened_at'],
            'closed_at' => $sessionData['closed_at'],
            'created_by' => $sessionData['created_by_username'] ?? null,
            'warehouses' => $sessionData['warehouses'] ?? []
        ],
        'stats' => [
            'total_entries' => (int)($stats['total_entries'] ?? 0),
            'total_users' => (int)($stats['total_users'] ?? 0),
            'total_products' => (int)($stats['total_products'] ?? 0),
            'matching' => (int)($stats['matching_count'] ?? 0),
            'faltantes' => (int)($stats['faltantes_count'] ?? 0),
            'sobrantes' => (int)($stats['sobrantes_count'] ?? 0)
        ]
    ]);

} catch (Exception $e) {
    error_log("API get_session_status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
