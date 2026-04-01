<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Verificar roles exclusivos
$userRepo = new User();
$userRoles = $userRepo->getRoles($userId);

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Créditos y Cobranzas') {
    header('Location: ' . BASE_URL . '/credits/pending.php');
    exit;
}

// Determinar roles del usuario
$isSystemAdmin = $auth->hasRole('Administrador del Sistema');
$isCompanyAdmin = $auth->hasRole('Administrador de Empresa');
$isAdmin = $isSystemAdmin || $isCompanyAdmin;
$isSeller = $auth->hasRole('Vendedor');
$isBilling = $auth->hasRole('Facturación');
$isCredits = $auth->hasRole('Créditos y Cobranzas');

$db = getDBConnection();

// --- Datos básicos ---
$totalQuotations = 0;
$totalCustomers = 0;
$totalProducts = 0;
$unreadNotifications = 0;
$recentNotifications = [];

// --- Datos para alertas ---
$statusCounts = [];
$billingStats = ['pending' => 0, 'in_process' => 0, 'invoiced' => 0, 'rejected' => 0];
$creditStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$expiringQuotations = [];
$expiredQuotations = 0;
$recentQuotations = [];
$conversionRate = 0;
$pendingDrafts = 0;

try {
    // Filtro por usuario: admins ven todo, el resto solo lo suyo
    $userFilter = $isAdmin ? null : $userId;

    // Totales básicos filtrados por usuario
    if ($userFilter) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM quotations WHERE company_id = ? AND user_id = ?");
        $stmt->execute([$companyId, $userFilter]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM quotations WHERE company_id = ?");
        $stmt->execute([$companyId]);
    }
    $totalQuotations = $stmt->fetchColumn();

    if ($userFilter) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM customers c INNER JOIN quotations q ON c.id = q.customer_id WHERE q.company_id = ? AND q.user_id = ?");
        $stmt->execute([$companyId, $userFilter]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ?");
        $stmt->execute([$companyId]);
    }
    $totalCustomers = $stmt->fetchColumn();

    if ($userFilter) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT qi.product_id) FROM quotation_items qi INNER JOIN quotations q ON qi.quotation_id = q.id WHERE q.company_id = ? AND q.user_id = ?");
        $stmt->execute([$companyId, $userFilter]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
        $stmt->execute([$companyId]);
    }
    $totalProducts = $stmt->fetchColumn();

    // Notificaciones
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND company_id = ? AND read_at IS NULL");
    $stmt->execute([$userId, $companyId]);
    $unreadNotifications = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND company_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId, $companyId]);
    $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo por estado de cotización
    $quotation = new Quotation();
    $statusData = $quotation->getCountByStatus($companyId, null, null, $userFilter);
    foreach ($statusData as $row) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }

    // Tasa de conversión
    $conversionRate = $quotation->getConversionRate($companyId, null, null, $userFilter);

    // Cotizaciones recientes
    $recentQuotations = $quotation->getRecent($companyId, 5, null, null, $userFilter);

    // Borradores pendientes
    $pendingDrafts = $statusCounts['Draft'] ?? 0;

    // Cotizaciones por vencer (próximos 3 días) y vencidas
    $stmt = $db->prepare("
        SELECT q.id, q.quotation_number, q.valid_until, q.total, q.currency, c.name as customer_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.company_id = ? AND q.status IN ('Draft', 'Sent')
        AND q.valid_until IS NOT NULL AND q.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        " . ($userFilter ? " AND q.user_id = ?" : "") . "
        ORDER BY q.valid_until ASC LIMIT 10
    ");
    $userFilter ? $stmt->execute([$companyId, $userFilter]) : $stmt->execute([$companyId]);
    $expiringQuotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cotizaciones ya vencidas (enviadas o borrador)
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM quotations
        WHERE company_id = ? AND status IN ('Draft', 'Sent')
        AND valid_until IS NOT NULL AND valid_until < CURDATE()
        " . ($userFilter ? " AND user_id = ?" : "")
    );
    $userFilter ? $stmt->execute([$companyId, $userFilter]) : $stmt->execute([$companyId]);
    $expiredQuotations = (int)$stmt->fetchColumn();

    // Stats de facturación
    try {
        $billingManager = new BillingManager();
        $billingRole = $isAdmin ? null : ($isBilling ? 'billing' : 'seller');
        $billingUserId = $isAdmin ? null : $userId;
        $billingStats = $billingManager->getBillingStats($companyId, $billingUserId, $billingRole);
    } catch (Exception $e) {
        // Tabla puede no existir
    }

    // Stats de créditos
    try {
        $creditManager = new CreditManager();
        $creditRole = $isAdmin ? null : ($isCredits ? 'credit' : 'seller');
        $creditUserId = $isAdmin ? null : $userId;
        $creditStats = $creditManager->getCreditStats($companyId, $creditUserId, $creditRole);
    } catch (Exception $e) {
        // Tabla puede no existir
    }

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Construir lista de alertas
$alerts = [];

// Cada alerta tiene un 'key' único: tipo + valor. Si el valor cambia, reaparece.
// 1. Cotizaciones vencidas
if ($expiredQuotations > 0) {
    $alerts[] = [
        'key' => "expired_{$expiredQuotations}",
        'type' => 'danger', 'icon' => 'fa-calendar-xmark',
        'title' => "$expiredQuotations cotización(es) vencida(s)",
        'message' => 'Tienen fecha de validez pasada y siguen en Borrador o Enviada.',
        'url' => BASE_URL . '/quotations/index.php',
    ];
}

// 2. Cotizaciones por vencer
if (!empty($expiringQuotations)) {
    $count = count($expiringQuotations);
    $alerts[] = [
        'key' => "expiring_{$count}",
        'type' => 'warning', 'icon' => 'fa-clock',
        'title' => "$count cotización(es) por vencer en 3 días",
        'message' => 'Requieren atención antes de que expiren.',
        'url' => BASE_URL . '/quotations/index.php',
        'details' => $expiringQuotations
    ];
}

// 3. Facturación pendiente
if ($billingStats['pending'] > 0 && ($isAdmin || $isBilling || $isSeller)) {
    $alerts[] = [
        'key' => "bill_pending_{$billingStats['pending']}",
        'type' => 'info', 'icon' => 'fa-file-invoice-dollar',
        'title' => $billingStats['pending'] . " solicitud(es) de facturación pendiente(s)",
        'message' => $isBilling ? 'Tienes solicitudes esperando tu aprobación.' : 'Solicitudes de facturación en espera.',
        'url' => BASE_URL . '/billing/pending.php',
    ];
}

// 4. Facturación rechazada
if ($billingStats['rejected'] > 0 && ($isAdmin || $isSeller)) {
    $alerts[] = [
        'key' => "bill_rejected_{$billingStats['rejected']}",
        'type' => 'danger', 'icon' => 'fa-ban',
        'title' => $billingStats['rejected'] . " solicitud(es) de facturación rechazada(s)",
        'message' => 'Revisa el motivo del rechazo y corrige si es necesario.',
        'url' => BASE_URL . '/billing/pending.php',
    ];
}

// 5. Créditos pendientes
if ($creditStats['pending'] > 0 && ($isAdmin || $isCredits || $isSeller)) {
    $alerts[] = [
        'key' => "credit_pending_{$creditStats['pending']}",
        'type' => 'warning', 'icon' => 'fa-credit-card',
        'title' => $creditStats['pending'] . " solicitud(es) de crédito pendiente(s)",
        'message' => $isCredits ? 'Tienes solicitudes de crédito esperando evaluación.' : 'Solicitudes de crédito en espera de aprobación.',
        'url' => BASE_URL . '/credits/pending.php',
    ];
}

// 6. Créditos rechazados
if ($creditStats['rejected'] > 0 && ($isAdmin || $isSeller)) {
    $alerts[] = [
        'key' => "credit_rejected_{$creditStats['rejected']}",
        'type' => 'danger', 'icon' => 'fa-times-circle',
        'title' => $creditStats['rejected'] . " solicitud(es) de crédito rechazada(s)",
        'message' => 'Revisa los motivos y considera alternativas de pago.',
        'url' => BASE_URL . '/credits/pending.php',
    ];
}

// 7. Borradores sin enviar
if ($pendingDrafts > 5) {
    $alerts[] = [
        'key' => "drafts_{$pendingDrafts}",
        'type' => 'secondary', 'icon' => 'fa-file-pen',
        'title' => "$pendingDrafts cotizaciones en borrador",
        'message' => 'Tienes borradores acumulados. Revisa si alguno debe enviarse o eliminarse.',
        'url' => BASE_URL . '/quotations/index.php',
    ];
}

// 8. Cotizaciones aceptadas sin facturar
$acceptedCount = $statusCounts['Accepted'] ?? 0;
if ($acceptedCount > 0 && ($isAdmin || $isSeller)) {
    $alerts[] = [
        'key' => "accepted_{$acceptedCount}",
        'type' => 'success', 'icon' => 'fa-check-circle',
        'title' => "$acceptedCount cotización(es) aceptada(s) pendiente(s) de facturación",
        'message' => 'El cliente aceptó. Solicita la facturación para concretar la venta.',
        'url' => BASE_URL . '/quotations/index.php',
    ];
}

// 9. Facturación en proceso
if ($billingStats['in_process'] > 0) {
    $alerts[] = [
        'key' => "bill_process_{$billingStats['in_process']}",
        'type' => 'primary', 'icon' => 'fa-spinner',
        'title' => $billingStats['in_process'] . " facturación(es) en proceso",
        'message' => 'Se están procesando solicitudes de facturación.',
        'url' => BASE_URL . '/billing/pending.php',
    ];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Hace menos de 1 minuto';
    if ($time < 3600) { $m = floor($time / 60); return "Hace $m minuto" . ($m > 1 ? 's' : ''); }
    if ($time < 86400) { $h = floor($time / 3600); return "Hace $h hora" . ($h > 1 ? 's' : ''); }
    if ($time < 2592000) { $d = floor($time / 86400); return "Hace $d día" . ($d > 1 ? 's' : ''); }
    return date('d/m/Y H:i', strtotime($datetime));
}

function statusLabel($status) {
    $map = [
        'Draft' => ['Borrador', 'bg-secondary'],
        'Sent' => ['Enviada', 'bg-primary'],
        'Accepted' => ['Aceptada', 'bg-success'],
        'Rejected' => ['Rechazada', 'bg-danger'],
        'Invoiced' => ['Facturada', 'bg-info'],
    ];
    $s = $map[$status] ?? [$status, 'bg-secondary'];
    return '<span class="badge ' . $s[1] . '">' . $s[0] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Cotizaciones</title>
    <?php include __DIR__ . '/../includes/pwa_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-card { border-radius: 10px; padding: 1.5rem; color: white; }
        .stats-number { font-size: 2rem; font-weight: bold; }
        .card { transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        .workflow-stat { text-align: center; padding: 0.75rem; border-radius: 8px; }
        .workflow-stat .number { font-size: 1.5rem; font-weight: bold; }
        .workflow-stat .label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        #notificationDropdown .fa-shake { animation: fa-shake 1.5s ease infinite; }
        @keyframes fa-shake {
            0%, 100% { transform: rotate(0deg); }
            10%, 30% { transform: rotate(-10deg); }
            20%, 40% { transform: rotate(10deg); }
            50% { transform: rotate(0deg); }
        }
        .dropdown-menu .dropdown-item:hover { background-color: #f0f4ff !important; }
        .dropdown-menu .border-start { border-left-width: 3px !important; }
    </style>
    <style>
    html, body { background-color: #ffffff !important; color: #212529 !important; }
    html[data-theme="dark"] body { background-color: #121212 !important; color: #e0e0e0 !important; }
    body:not([data-theme="dark"]) * {
        --bs-body-bg: #ffffff !important; --bs-body-color: #212529 !important; --bs-border-color: #dee2e6 !important;
    }
    body:not([data-theme="dark"]) .card,
    body:not([data-theme="dark"]) .dropdown-menu,
    body:not([data-theme="dark"]) .list-group-item,
    body:not([data-theme="dark"]) .table,
    body:not([data-theme="dark"]) .table td,
    body:not([data-theme="dark"]) .table th {
        background-color: #ffffff !important; color: #212529 !important; border-color: #dee2e6 !important;
    }
    .navbar, .navbar-dark, .navbar-light { background-color: #0d6efd !important; }
    .navbar .navbar-brand, .navbar .navbar-nav .nav-link { color: #ffffff !important; }
    </style>
    <script>
    (function() {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme !== 'dark') {
            localStorage.setItem('theme', 'light');
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/quotations/create.php">
                            <i class="fas fa-plus"></i> Nueva Cotización
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/customers/index.php">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/products/index.php">
                            <i class="fas fa-box"></i> Productos
                        </a>
                    </li>
                </ul>

                <?php $totalBellCount = count($alerts) + $unreadNotifications; ?>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell<?= $totalBellCount > 0 ? ' fa-shake' : '' ?>"></i>
                            <?php if ($totalBellCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="bellBadge">
                                    <?= $totalBellCount > 99 ? '99+' : $totalBellCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="width: 400px; max-height: 80vh; overflow-y: auto;">
                            <!-- Alertas del sistema -->
                            <?php if (!empty($alerts)): ?>
                            <li class="dropdown-header d-flex justify-content-between align-items-center alert-header" style="background:#fff3cd;">
                                <span><i class="fas fa-exclamation-triangle text-warning"></i> Alertas</span>
                                <span class="badge bg-warning text-dark" id="alertCountBadge"><?= count($alerts) ?></span>
                            </li>
                            <?php foreach ($alerts as $alert): ?>
                                <li class="alert-item" data-alert-key="<?= htmlspecialchars($alert['key']) ?>">
                                    <div class="dropdown-item py-2 border-start border-3 border-<?= $alert['type'] ?>" style="white-space: normal;">
                                        <div class="d-flex align-items-start">
                                            <i class="fas <?= $alert['icon'] ?> text-<?= $alert['type'] ?> me-2 mt-1"></i>
                                            <div class="flex-grow-1">
                                                <a href="<?= $alert['url'] ?>" class="text-decoration-none text-dark">
                                                    <div class="small fw-bold"><?= htmlspecialchars($alert['title']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars($alert['message']) ?></div>
                                                </a>
                                                <?php if (!empty($alert['details'])): ?>
                                                    <div class="mt-1">
                                                        <?php foreach (array_slice($alert['details'], 0, 2) as $detail): ?>
                                                            <span class="badge bg-light text-dark me-1" style="font-size:0.65rem;">
                                                                <?= htmlspecialchars($detail['quotation_number']) ?> (<?= date('d/m', strtotime($detail['valid_until'])) ?>)
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($alert['details']) > 2): ?>
                                                            <span class="text-muted" style="font-size:0.65rem;">+<?= count($alert['details']) - 2 ?> más</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm text-muted p-0 ms-2 mt-1 dismiss-alert"
                                                    onclick="dismissAlert('<?= htmlspecialchars($alert['key']) ?>', this); event.stopPropagation();"
                                                    title="Descartar alerta">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <li class="alert-divider"><hr class="dropdown-divider my-1"></li>
                            <li class="no-alerts-msg d-none text-center py-2 text-muted small">
                                <i class="fas fa-check-circle text-success"></i> Sin alertas pendientes
                            </li>
                            <?php endif; ?>

                            <!-- Notificaciones recientes -->
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-bell"></i> Notificaciones</span>
                                <?php if ($unreadNotifications > 0): ?>
                                    <span class="badge bg-primary"><?= $unreadNotifications ?></span>
                                <?php endif; ?>
                            </li>
                            <?php if (empty($recentNotifications)): ?>
                                <li class="text-center py-3 text-muted">
                                    <i class="fas fa-bell-slash"></i><br>
                                    <span class="small">No hay notificaciones</span>
                                </li>
                            <?php else: ?>
                                <?php foreach (array_slice($recentNotifications, 0, 4) as $notification): ?>
                                    <li>
                                        <a class="dropdown-item py-2 <?= !$notification['read_at'] ? 'bg-light' : '' ?>"
                                           href="<?= !empty($notification['related_url']) ? htmlspecialchars($notification['related_url']) : BASE_URL . '/notifications/index.php' ?>"
                                           style="white-space: normal;">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-shrink-0 me-2 mt-1">
                                                    <?php
                                                    $icon = 'fa-info-circle text-info';
                                                    switch ($notification['type']) {
                                                        case 'quotation_created': $icon = 'fa-file-invoice text-info'; break;
                                                        case 'quotation_accepted': $icon = 'fa-check-circle text-success'; break;
                                                        case 'quotation_rejected': $icon = 'fa-times-circle text-danger'; break;
                                                        case 'quotation_invoiced': $icon = 'fa-file-invoice-dollar text-primary'; break;
                                                        case 'billing_request': $icon = 'fa-file-invoice-dollar text-warning'; break;
                                                        case 'billing_approved': $icon = 'fa-check-double text-success'; break;
                                                        case 'billing_rejected': $icon = 'fa-ban text-danger'; break;
                                                        case 'credit_request': $icon = 'fa-credit-card text-warning'; break;
                                                        case 'credit_approved': $icon = 'fa-thumbs-up text-success'; break;
                                                        case 'credit_rejected': $icon = 'fa-thumbs-down text-danger'; break;
                                                        case 'low_stock': $icon = 'fa-exclamation-triangle text-warning'; break;
                                                        case 'customer_created': $icon = 'fa-user-plus text-success'; break;
                                                    }
                                                    ?>
                                                    <i class="fas <?= $icon ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="small fw-bold"><?= htmlspecialchars($notification['title']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars(substr($notification['message'], 0, 60)) ?></div>
                                                    <div class="small text-muted"><i class="fas fa-clock"></i> <?= timeAgo($notification['created_at']) ?></div>
                                                </div>
                                                <?php if (!$notification['read_at']): ?>
                                                    <span class="badge bg-primary rounded-pill ms-1" style="font-size:0.5rem;">&bull;</span>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item text-center py-2 text-primary fw-bold" href="<?= BASE_URL ?>/notifications/index.php">
                                    <i class="fas fa-list"></i> Ver todas las notificaciones
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">
                                <i class="fas fa-user-edit"></i> Mi Perfil
                            </a></li>
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">Panel Admin</a></li>
                            <?php endif; ?>
                            <?php if ($isCredits || $isAdmin): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/credits/pending.php"><i class="fas fa-credit-card"></i> Panel de Créditos</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/notifications/index.php">Notificaciones</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/activities/index.php">Actividades</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/reports/index.php">Reportes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid py-4">
        <!-- Welcome -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-1">
                    <i class="fas fa-tachometer-alt text-primary"></i> Dashboard
                </h1>
                <p class="lead mb-0">Bienvenido, <?= htmlspecialchars(($user['first_name'] ?: $user['username']) ?: 'Usuario') ?>!</p>
            </div>
        </div>


        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card" style="background: linear-gradient(135deg, #007bff, #6f42c1);">
                    <div class="card-body text-center">
                        <div class="stats-number"><?= number_format($totalQuotations) ?></div>
                        <div><?= $isAdmin ? 'Cotizaciones' : 'Mis Cotizaciones' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success">
                    <div class="card-body text-center">
                        <div class="stats-number"><?= number_format($totalCustomers) ?></div>
                        <div><?= $isAdmin ? 'Clientes' : 'Mis Clientes' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning">
                    <div class="card-body text-center">
                        <div class="stats-number"><?= number_format($totalProducts) ?></div>
                        <div><?= $isAdmin ? 'Productos' : 'Mis Productos' ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card" style="background: linear-gradient(135deg, #20c997, #0d6efd);">
                    <div class="card-body text-center">
                        <div class="stats-number"><?= number_format($conversionRate, 1) ?>%</div>
                        <div><?= $isAdmin ? 'Tasa de Conversión' : 'Mi Conversión' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pipeline de Cotizaciones + Workflow -->
        <div class="row mb-4">
            <!-- Pipeline de estados -->
            <div class="col-md-7 mb-3">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-check"></i> Estados de Cotización</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col">
                                <div class="workflow-stat bg-light">
                                    <div class="number text-secondary"><?= $statusCounts['Draft'] ?? 0 ?></div>
                                    <div class="label text-muted">Borrador</div>
                                </div>
                            </div>
                            <div class="col d-flex align-items-center justify-content-center p-0" style="max-width:20px;">
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                            <div class="col">
                                <div class="workflow-stat bg-light">
                                    <div class="number text-primary"><?= $statusCounts['Sent'] ?? 0 ?></div>
                                    <div class="label text-muted">Enviada</div>
                                </div>
                            </div>
                            <div class="col d-flex align-items-center justify-content-center p-0" style="max-width:20px;">
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                            <div class="col">
                                <div class="workflow-stat bg-light">
                                    <div class="number text-success"><?= $statusCounts['Accepted'] ?? 0 ?></div>
                                    <div class="label text-muted">Aceptada</div>
                                </div>
                            </div>
                            <div class="col d-flex align-items-center justify-content-center p-0" style="max-width:20px;">
                                <i class="fas fa-chevron-right text-muted"></i>
                            </div>
                            <div class="col">
                                <div class="workflow-stat bg-light">
                                    <div class="number text-info"><?= $statusCounts['Invoiced'] ?? 0 ?></div>
                                    <div class="label text-muted">Facturada</div>
                                </div>
                            </div>
                        </div>
                        <?php if (($statusCounts['Rejected'] ?? 0) > 0): ?>
                        <div class="mt-2 text-center">
                            <span class="badge bg-danger"><i class="fas fa-times"></i> <?= $statusCounts['Rejected'] ?> rechazada(s)</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($billingStats['total'] > 0 || $creditStats['total'] > 0): ?>
                        <hr class="my-3">
                        <div class="row">
                            <?php if ($billingStats['total'] > 0 && ($isAdmin || $isBilling || $isSeller)): ?>
                            <div class="col-md-6 mb-2">
                                <h6 class="text-muted"><i class="fas fa-file-invoice-dollar"></i> Facturación</h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($billingStats['pending'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><?= $billingStats['pending'] ?> pendiente(s)</span>
                                    <?php endif; ?>
                                    <?php if ($billingStats['in_process'] > 0): ?>
                                        <span class="badge bg-primary"><?= $billingStats['in_process'] ?> en proceso</span>
                                    <?php endif; ?>
                                    <?php if ($billingStats['invoiced'] > 0): ?>
                                        <span class="badge bg-success"><?= $billingStats['invoiced'] ?> facturada(s)</span>
                                    <?php endif; ?>
                                    <?php if ($billingStats['rejected'] > 0): ?>
                                        <span class="badge bg-danger"><?= $billingStats['rejected'] ?> rechazada(s)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($creditStats['total'] > 0 && ($isAdmin || $isCredits || $isSeller)): ?>
                            <div class="col-md-6 mb-2">
                                <h6 class="text-muted"><i class="fas fa-credit-card"></i> Créditos</h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($creditStats['pending'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><?= $creditStats['pending'] ?> pendiente(s)</span>
                                    <?php endif; ?>
                                    <?php if ($creditStats['approved'] > 0): ?>
                                        <span class="badge bg-success"><?= $creditStats['approved'] ?> aprobado(s)</span>
                                    <?php endif; ?>
                                    <?php if ($creditStats['rejected'] > 0): ?>
                                        <span class="badge bg-danger"><?= $creditStats['rejected'] ?> rechazado(s)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Cotizaciones recientes -->
            <div class="col-md-5 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Últimas Cotizaciones</h5>
                        <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentQuotations)): ?>
                            <p class="text-muted text-center py-4">No hay cotizaciones aún.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentQuotations as $q): ?>
                                <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $q['id'] ?>" class="list-group-item list-group-item-action py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="small"><?= htmlspecialchars($q['quotation_number']) ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($q['customer_name'] ?? 'Sin cliente') ?></div>
                                        </div>
                                        <div class="text-end">
                                            <?= statusLabel($q['status']) ?>
                                            <div class="small text-muted"><?= $q['currency'] === 'USD' ? '$' : 'S/' ?> <?= number_format($q['total'], 2) ?></div>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt"></i> Acciones Rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (Permissions::userCan($auth, 'manage_quotations')): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-plus"></i><br>Nueva Cotización
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::userCan($auth, 'manage_customers')): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-success btn-lg w-100">
                                    <i class="fas fa-user-plus"></i><br>Nuevo Cliente
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::userCan($auth, 'view_products') || Permissions::userCan($auth, 'manage_products')): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-info btn-lg w-100">
                                    <i class="fas fa-search"></i><br>Consultar Stock
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::userCan($auth, 'billing_panel') || Permissions::userCan($auth, 'process_billing')): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/billing/pending.php" class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-file-invoice-dollar"></i><br>Facturación
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::userCan($auth, 'credits_panel') || Permissions::userCan($auth, 'process_credits')): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/credits/pending.php" class="btn btn-danger btn-lg w-100">
                                    <i class="fas fa-credit-card"></i><br>Créditos
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::canAccessInventoryPanel($auth)): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/inventario/index.php" class="btn btn-secondary btn-lg w-100">
                                    <i class="fas fa-warehouse"></i><br>Inventario
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::canAccessCostAnalysis($auth)): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/cost-analysis/index.php" class="btn btn-outline-info btn-lg w-100">
                                    <i class="fas fa-calculator"></i><br>Análisis de Costos
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if (Permissions::canAccessAdminPanel($auth)): ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/admin/exchange_rate.php" class="btn btn-outline-success btn-lg w-100">
                                    <i class="fas fa-money-bill-transfer"></i><br>Tipo de Cambio
                                </a>
                            </div>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-dark btn-lg w-100">
                                    <i class="fas fa-cog"></i><br>Administración
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="col mb-2">
                                <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-outline-primary btn-lg w-100">
                                    <i class="fas fa-chart-bar"></i><br>Reportes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Alertas descartables ---
        function getDismissedAlerts() {
            try { return JSON.parse(localStorage.getItem('dismissed_alerts') || '{}'); }
            catch(e) { return {}; }
        }

        function dismissAlert(key, btn) {
            var dismissed = getDismissedAlerts();
            dismissed[key] = Date.now();
            localStorage.setItem('dismissed_alerts', JSON.stringify(dismissed));

            // Ocultar el item
            var li = btn.closest('.alert-item');
            if (li) li.style.display = 'none';

            updateAlertCount();
        }

        function updateAlertCount() {
            var visible = document.querySelectorAll('.alert-item:not([style*="display: none"])');
            var countBadge = document.getElementById('alertCountBadge');
            var bellBadge = document.getElementById('bellBadge');
            var alertHeader = document.querySelector('.alert-header');
            var alertDivider = document.querySelector('.alert-divider');
            var noAlertsMsg = document.querySelector('.no-alerts-msg');
            var bellIcon = document.querySelector('#notificationDropdown i');

            var alertCount = visible.length;

            // Actualizar badge de alertas
            if (countBadge) countBadge.textContent = alertCount;

            // Si no quedan alertas, ocultar header y divider, mostrar mensaje
            if (alertCount === 0) {
                if (alertHeader) alertHeader.style.display = 'none';
                if (alertDivider) alertDivider.style.display = 'none';
                if (noAlertsMsg) noAlertsMsg.classList.remove('d-none');
            }

            // Actualizar badge de la campana (alertas + notificaciones no leídas)
            var unreadNotifs = <?= (int)$unreadNotifications ?>;
            var total = alertCount + unreadNotifs;
            if (bellBadge) {
                if (total > 0) {
                    bellBadge.textContent = total > 99 ? '99+' : total;
                    bellBadge.style.display = '';
                } else {
                    bellBadge.style.display = 'none';
                }
            }

            // Quitar animación de campana si no hay nada
            if (bellIcon && total === 0) {
                bellIcon.classList.remove('fa-shake');
            }
        }

        // Al cargar, ocultar alertas previamente descartadas
        document.addEventListener('DOMContentLoaded', function() {
            var dismissed = getDismissedAlerts();
            document.querySelectorAll('.alert-item').forEach(function(li) {
                if (dismissed[li.dataset.alertKey]) {
                    li.style.display = 'none';
                }
            });
            updateAlertCount();
        });

        // --- Auto-refresh notificaciones ---
        setInterval(function() {
            fetch('<?= BASE_URL ?>/api/check_notifications.php')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        var badge = document.getElementById('bellBadge');
                        var visible = document.querySelectorAll('.alert-item:not([style*="display: none"])');
                        var total = visible.length + (data.unread_count || 0);
                        if (badge) {
                            if (total > 0) {
                                badge.textContent = total > 99 ? '99+' : total;
                                badge.style.display = '';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(function(error) { console.log('Error checking notifications:', error); });
        }, 30000);
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>

    <button id="pwa-install-btn" onclick="installPWA()"
            class="btn btn-success d-none align-items-center gap-2 shadow"
            style="position:fixed;bottom:20px;right:20px;z-index:9999;border-radius:50px;padding:10px 20px;">
        <i class="fas fa-download"></i> Instalar app
    </button>
</body>
</html>
