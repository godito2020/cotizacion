<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

// Get notifications
$notificationRepo = new Notification();
$notifications = $notificationRepo->getUserNotifications($userId, $companyId);
$unreadCount = $notificationRepo->getUnreadCount($userId, $companyId);

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notificationRepo->markAsRead($_POST['notification_id'], $userId);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($_POST['action'] === 'mark_all_read') {
        $notificationRepo->markAllAsRead($userId, $companyId);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

$pageTitle = 'Notificaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .notification-item.unread {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        .notification-item:hover {
            background-color: #e9ecef;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        .notification-icon.info { background-color: #17a2b8; }
        .notification-icon.success { background-color: #28a745; }
        .notification-icon.warning { background-color: #ffc107; }
        .notification-icon.danger { background-color: #dc3545; }
    </style>
    <style>
    /* EMERGENCY LIGHT THEME ENFORCEMENT - Override ANY dark styles */
    html, body {
        background-color: #ffffff !important;
        color: #212529 !important;
    }

    html[data-theme="dark"] body {
        background-color: #121212 !important;
        color: #e0e0e0 !important;
    }

    /* Force all components to light theme unless in dark mode */
    body:not([data-theme="dark"]) * {
        --bs-body-bg: #ffffff !important;
        --bs-body-color: #212529 !important;
        --bs-border-color: #dee2e6 !important;
    }

    /* Ultra-specific overrides for stubborn dark elements */
    body:not([data-theme="dark"]) .card,
    body:not([data-theme="dark"]) .modal-content,
    body:not([data-theme="dark"]) .form-control,
    body:not([data-theme="dark"]) .form-select,
    body:not([data-theme="dark"]) .table,
    body:not([data-theme="dark"]) .table td,
    body:not([data-theme="dark"]) .table th,
    body:not([data-theme="dark"]) .dropdown-menu,
    body:not([data-theme="dark"]) .list-group-item,
    body:not([data-theme="dark"]) .page-link,
    body:not([data-theme="dark"]) .breadcrumb,
    body:not([data-theme="dark"]) .accordion-item,
    body:not([data-theme="dark"]) .offcanvas,
    body:not([data-theme="dark"]) .toast {
        background-color: #ffffff !important;
        color: #212529 !important;
        border-color: #dee2e6 !important;
    }

    /* Force navbar to be blue with white text */
    .navbar,
    .navbar-dark,
    .navbar-light {
        background-color: #0d6efd !important;
    }

    .navbar .navbar-brand,
    .navbar .navbar-nav .nav-link,
    .navbar-dark .navbar-brand,
    .navbar-dark .navbar-nav .nav-link,
    .navbar-light .navbar-brand,
    .navbar-light .navbar-nav .nav-link {
        color: #ffffff !important;
    }
    </style>

    <script>
    // Emergency theme enforcement
    (function() {
        // Remove any dark theme attributes on page load
        document.documentElement.removeAttribute('data-theme');

        // Set light theme in localStorage if not explicitly dark
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme !== 'dark') {
            localStorage.setItem('theme', 'light');
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Force body styles
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'light') {
                document.body.style.backgroundColor = '#ffffff';
                document.body.style.color = '#212529';
            }
        });
    })();
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>
            <div class="navbar-nav ms-auto">
                <button class="theme-toggle me-3" id="themeToggle" title="Cambiar tema">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/index.php">Cotizaciones</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        <i class="fas fa-bell"></i> <?= $pageTitle ?>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-primary"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </h1>
                    <div class="btn-group">
                        <a href="<?= BASE_URL ?>/dashboard_simple.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check-double"></i> Marcar Todo como Leído
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Mis Notificaciones</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No tienes notificaciones</h5>
                                <p class="text-muted">Las notificaciones aparecerán aquí cuando tengas actualizaciones importantes.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item p-3 border-bottom <?= !$notification['read_at'] ? 'unread' : '' ?>">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <?php
                                            $iconClass = 'info';
                                            $icon = 'fa-info';

                                            switch ($notification['type']) {
                                                case 'quotation_created':
                                                case 'quotation_updated':
                                                    $iconClass = 'info';
                                                    $icon = 'fa-file-invoice';
                                                    break;
                                                case 'quotation_accepted':
                                                    $iconClass = 'success';
                                                    $icon = 'fa-check-circle';
                                                    break;
                                                case 'quotation_rejected':
                                                    $iconClass = 'danger';
                                                    $icon = 'fa-times-circle';
                                                    break;
                                                case 'low_stock':
                                                    $iconClass = 'warning';
                                                    $icon = 'fa-exclamation-triangle';
                                                    break;
                                                case 'customer_created':
                                                    $iconClass = 'success';
                                                    $icon = 'fa-user-plus';
                                                    break;
                                                case 'system':
                                                    $iconClass = 'info';
                                                    $icon = 'fa-cog';
                                                    break;
                                            }
                                            ?>
                                            <div class="notification-icon <?= $iconClass ?>">
                                                <i class="fas <?= $icon ?>"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 <?= !$notification['read_at'] ? 'fw-bold' : '' ?>">
                                                        <?= htmlspecialchars($notification['title']) ?>
                                                    </h6>
                                                    <p class="mb-1 text-muted"><?= htmlspecialchars($notification['message']) ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock"></i>
                                                        <?= timeAgo($notification['created_at']) ?>
                                                    </small>
                                                </div>
                                                <div class="ms-3">
                                                    <?php if (!$notification['read_at']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Marcar como leído">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ($notification['related_url']): ?>
                                                        <?php
                                                        // Check if URL is absolute (starts with http) or relative
                                                        $url = $notification['related_url'];
                                                        $finalUrl = (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0)
                                                            ? $url
                                                            : BASE_URL . $url;
                                                        ?>
                                                        <a href="<?= htmlspecialchars($finalUrl) ?>"
                                                           class="btn btn-sm btn-outline-secondary ms-1" title="Ver detalles">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notification Types Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Tipos de Notificaciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-file-invoice text-info"></i> Cotizaciones</h6>
                                <ul class="small">
                                    <li>Nueva cotización creada</li>
                                    <li>Cotización actualizada</li>
                                    <li>Cambio de estado</li>
                                    <li>Cotización aceptada/rechazada</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-exclamation-triangle text-warning"></i> Alertas</h6>
                                <ul class="small">
                                    <li>Stock bajo de productos</li>
                                    <li>Cotizaciones próximas a vencer</li>
                                    <li>Nuevos clientes registrados</li>
                                    <li>Notificaciones del sistema</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // Check for new notifications without page reload
            fetch('<?= BASE_URL ?>/api/check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_notifications > 0) {
                        // Show a subtle notification or update badge
                        const title = document.title;
                        if (!title.includes('(')) {
                            document.title = `(${data.new_notifications}) ${title}`;
                        }
                    }
                })
                .catch(error => console.log('Error checking notifications:', error));
        }, 30000);
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>

<?php
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);

    if ($time < 60) {
        return 'Hace menos de 1 minuto';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Hace $minutes minuto" . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Hace $hours hora" . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "Hace $days día" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y H:i', strtotime($datetime));
    }
}
?>