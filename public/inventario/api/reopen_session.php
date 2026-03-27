<?php
/**
 * API para reabrir una sesión de inventario cerrada
 * Requiere verificación de contraseña del admin
 *
 * IMPORTANTE: Ejecutar esta migración si es la primera vez:
 * ALTER TABLE inventory_sessions
 *   ADD COLUMN reopened_at TIMESTAMP NULL AFTER close_notes,
 *   ADD COLUMN reopened_by INT NULL AFTER reopened_at,
 *   ADD COLUMN reopen_reason TEXT NULL AFTER reopened_by,
 *   ADD CONSTRAINT fk_inv_session_reopened_by FOREIGN KEY (reopened_by) REFERENCES users(id) ON DELETE SET NULL;
 */

require_once __DIR__ . '/../../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para gestionar sesiones']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);

$sessionId = (int)($input['session_id'] ?? 0);
$password = trim($input['password'] ?? '');
$reason = trim($input['reason'] ?? '');

// Validar datos
if (!$sessionId) {
    echo json_encode(['success' => false, 'message' => 'ID de sesión requerido']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Contraseña requerida']);
    exit;
}

if (empty($reason) || strlen($reason) < 10) {
    echo json_encode(['success' => false, 'message' => 'Motivo requerido (mínimo 10 caracteres)']);
    exit;
}

// Verificar contraseña del usuario actual
if (!$auth->verifyCurrentUserPassword($password)) {
    echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
    exit;
}

// Verificar que la sesión pertenece a la empresa del usuario
$session = new InventorySession();
$sessionData = $session->getById($sessionId);

if (!$sessionData) {
    echo json_encode(['success' => false, 'message' => 'Sesión no encontrada']);
    exit;
}

if ((int)$sessionData['company_id'] !== $auth->getCompanyId()) {
    echo json_encode(['success' => false, 'message' => 'No tienes acceso a esta sesión']);
    exit;
}

// Intentar reabrir la sesión
$result = $session->reopen($sessionId, $auth->getUserId(), $reason);

echo json_encode($result);
