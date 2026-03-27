<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

try {
    $notification = new Notification();

    // Mark all unread notifications as read
    $notification->markAllAsRead($userId, $companyId);

    echo json_encode([
        'success' => true,
        'message' => 'Notificaciones marcadas como leídas'
    ]);

} catch (Exception $e) {
    error_log("Error in mark_notifications_read.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al marcar notificaciones'
    ]);
}
