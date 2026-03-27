<?php
/**
 * API: Cerrar sesión de inventario
 * POST /inventario/api/close_session.php
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
    $userId = $auth->getUserId();

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $sessionId = (int)($input['session_id'] ?? 0);
    $notes = trim($input['notes'] ?? '') ?: null;

    if ($sessionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de sesión no válido']);
        exit;
    }

    $session = new InventorySession();
    $sessionData = $session->getById($sessionId);

    if (!$sessionData) {
        echo json_encode(['success' => false, 'message' => 'Sesión no encontrada']);
        exit;
    }

    if ($sessionData['status'] !== 'Open') {
        echo json_encode(['success' => false, 'message' => 'La sesión ya está cerrada']);
        exit;
    }

    // Cerrar sesión
    $result = $session->close($sessionId, $userId, $notes);

    if ($result) {
        $closedSession = $session->getById($sessionId);
        $stats = $session->getSessionStats($sessionId);

        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
            'data' => [
                'id' => $sessionId,
                'name' => $closedSession['name'],
                'status' => $closedSession['status'],
                'closed_at' => $closedSession['closed_at'],
                'stats' => [
                    'total_entries' => (int)($stats['total_entries'] ?? 0),
                    'total_users' => (int)($stats['total_users'] ?? 0),
                    'matching' => (int)($stats['matching_count'] ?? 0),
                    'faltantes' => (int)($stats['faltantes_count'] ?? 0),
                    'sobrantes' => (int)($stats['sobrantes_count'] ?? 0)
                ]
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al cerrar la sesión']);
    }

} catch (Exception $e) {
    error_log("API close_session Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
