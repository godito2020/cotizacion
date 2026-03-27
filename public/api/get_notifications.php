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

    // Get unread count
    $unreadCount = $notification->getUnreadCount($userId, $companyId);

    // Get latest notifications (last 10)
    $notifications = $notification->getLatestNotifications($userId, $companyId, 10);

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    error_log("Error in get_notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener notificaciones'
    ]);
}
