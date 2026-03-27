<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

try {
    $notificationRepo = new Notification();
    $unreadCount = $notificationRepo->getUnreadCount($userId, $companyId);
    $latestNotifications = $notificationRepo->getLatestNotifications($userId, $companyId, 5);

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'new_notifications' => $unreadCount,
        'latest' => $latestNotifications
    ]);

} catch (Exception $e) {
    error_log("Error checking notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
?>