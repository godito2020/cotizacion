<?php
/**
 * API: Obtener entradas de inventario
 * GET /inventario/api/get_entries.php?page=1&per_page=50
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
    $userId = $auth->getUserId();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
    $sessionId = (int)($_GET['session_id'] ?? 0);
    $filterUserId = (int)($_GET['user_id'] ?? 0);
    $type = $_GET['type'] ?? 'all'; // all, matching, faltantes, sobrantes

    // Si no se especifica sesión, usar la activa
    $session = new InventorySession();
    if ($sessionId <= 0) {
        $activeSession = $session->getActiveSession($companyId);
        $sessionId = $activeSession ? $activeSession['id'] : 0;
    }

    if ($sessionId <= 0) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No hay sesión de inventario activa'
        ]);
        exit;
    }

    $entry = new InventoryEntry();

    // Si no puede ver todo, solo muestra sus propias entradas
    if (!Permissions::canViewAllInventory($auth)) {
        $filterUserId = $userId;
    }

    // Filtrar por tipo de discrepancia
    if ($type === 'matching') {
        $entries = $entry->getMatching($sessionId, $page, $perPage);
        $total = $entry->countMatching($sessionId);
    } elseif ($type === 'faltantes') {
        $entries = $entry->getDiscrepancies($sessionId, 'faltantes', $page, $perPage);
        $total = $entry->countDiscrepancies($sessionId, 'faltantes');
    } elseif ($type === 'sobrantes') {
        $entries = $entry->getDiscrepancies($sessionId, 'sobrantes', $page, $perPage);
        $total = $entry->countDiscrepancies($sessionId, 'sobrantes');
    } elseif ($filterUserId > 0) {
        $entries = $entry->getByUser($sessionId, $filterUserId, $page, $perPage);
        $total = $entry->countByUser($sessionId, $filterUserId);
    } else {
        $entries = $entry->getBySession($sessionId, $page, $perPage);
        $stats = $session->getSessionStats($sessionId);
        $total = (int)($stats['total_entries'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'data' => $entries,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);

} catch (Exception $e) {
    error_log("API get_entries Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
