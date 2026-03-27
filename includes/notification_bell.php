<?php
/**
 * Notification Bell Component
 * Include this file in the navbar/header
 */

// Ensure user is logged in
if (!isset($auth) || !$auth->isLoggedIn()) {
    return;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

// Get unread count
$notification = new Notification();
$unreadCount = $notification->getUnreadCount($userId, $companyId);
?>

<style>
.notification-bell {
    position: relative;
    cursor: pointer;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: 700;
    min-width: 18px;
    text-align: center;
    line-height: 1.2;
    display: none;
}

.notification-badge.pulse {
    animation: pulse 1s ease-in-out 2;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.2);
    }
}

.notification-dropdown {
    width: 380px;
    max-width: 90vw;
    max-height: 500px;
    overflow-y: auto;
    padding: 0;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.notification-header {
    padding: 16px;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
    font-size: 16px;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s;
    text-decoration: none;
    color: inherit;
    display: block;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #e7f3ff;
}

.notification-item.unread:hover {
    background-color: #d4e9ff;
}

.notification-icon {
    width: 40px;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    color: #212529;
}

.notification-message {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 4px;
    line-height: 1.4;
}

.notification-time {
    font-size: 11px;
    color: #999;
}

.unread-dot {
    width: 8px;
    height: 8px;
    background: #0d6efd;
    border-radius: 50%;
    flex-shrink: 0;
    margin-left: 8px;
}

.notification-footer {
    padding: 12px 16px;
    text-align: center;
    border-top: 1px solid #dee2e6;
    background: #f8f9fa;
    border-radius: 0 0 12px 12px;
}

.notification-footer a {
    color: #0d6efd;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.notification-footer a:hover {
    text-decoration: underline;
}
</style>

<div class="dropdown notification-bell" id="notificationBell">
    <button class="btn btn-link nav-link position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell" style="font-size: 20px;"></i>
        <span class="notification-badge" id="notification-badge" style="<?= $unreadCount > 0 ? 'display: inline-block;' : 'display: none;' ?>">
            <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
        </span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
        <li class="notification-header">
            <i class="fas fa-bell"></i> Notificaciones
        </li>
        <li>
            <div id="notification-list">
                <!-- Notifications will be loaded here by JavaScript -->
                <div class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </li>
        <li class="notification-footer">
            <a href="<?= BASE_URL ?>/notifications/index.php">Ver todas las notificaciones</a>
        </li>
    </ul>
</div>

<script>
// Define BASE_URL for notifications.js
if (typeof BASE_URL === 'undefined') {
    const BASE_URL = '<?= BASE_URL ?>';
}
</script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
