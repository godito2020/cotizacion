<?php
// includes/app_header.php
// Master App Shell Header - gstack Design System (Linear / Notion / Stripe Dashboard layout)

if (!isset($auth)) {
    require_once __DIR__ . '/init.php';
    $auth = new Auth();
}

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

$isSystemAdmin = $auth->hasRole('Administrador del Sistema');
$isCompanyAdmin = $auth->hasRole('Administrador de Empresa');
$isAdmin = $isSystemAdmin || $isCompanyAdmin;
$isSeller = $auth->hasRole('Vendedor');
$isBilling = $auth->hasRole('Facturación');
$isCredits = $auth->hasRole('Créditos y Cobranzas');

$companyName = $user['company_name'] ?? 'COTI Workspace';
$pageTitle = $pageTitle ?? 'Dashboard';

// Active page detection
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$activePage = $activePage ?? '';
if (empty($activePage)) {
    if (strpos($currentUri, 'dashboard_mobile.php') !== false) $activePage = 'dashboard_mobile';
    elseif (strpos($currentUri, 'dashboard') !== false) $activePage = 'dashboard';
    elseif (strpos($currentUri, '/quotations/create') !== false) $activePage = 'new_quotation';
    elseif (strpos($currentUri, '/quotations/') !== false) $activePage = 'quotations';
    elseif (strpos($currentUri, '/customers/') !== false) $activePage = 'customers';
    elseif (strpos($currentUri, '/products/') !== false) $activePage = 'products';
    elseif (strpos($currentUri, '/billing/') !== false) $activePage = 'billing';
    elseif (strpos($currentUri, '/credits/') !== false) $activePage = 'credits';
    elseif (strpos($currentUri, '/inventario/') !== false) $activePage = 'inventario';
    elseif (strpos($currentUri, '/cost-analysis/') !== false) $activePage = 'cost_analysis';
    elseif (strpos($currentUri, '/admin/exchange_rate') !== false) $activePage = 'exchange_rate';
    elseif (strpos($currentUri, '/reports/') !== false) $activePage = 'reports';
    elseif (strpos($currentUri, '/admin/') !== false) $activePage = 'admin';
    elseif (strpos($currentUri, 'profile.php') !== false) $activePage = 'profile';
    else $activePage = 'other';
}

// Unread notifications
$unreadNotifications = 0;
$recentNotifications = [];
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND company_id = ? AND read_at IS NULL");
    $stmt->execute([$userId, $companyId]);
    $unreadNotifications = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND company_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId, $companyId]);
    $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fail-safe
}

$initials = strtoupper(substr($user['username'] ?? 'U', 0, 2));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Sistema de Cotizaciones</title>

    <!-- Anti-Flash Theme Init (System default: zero-flash) -->
    <script>
        (function () {
            var mode = 'system';
            try {
                var saved = localStorage.getItem('coti-theme');
                if (saved === 'light' || saved === 'dark' || saved === 'system') mode = saved;
            } catch (e) {}
            var theme = mode === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.setAttribute('data-coti-theme-mode', mode);
        })();
    </script>

    <!-- gstack Industrial Typography: Satoshi (Display), DM Sans (Body/UI), JetBrains Mono (Data/Mono) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@700,900&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Font Awesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Core App Stylesheet (gstack Design System) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/style.css") ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">

    <!-- PWA Head -->
    <?php if (file_exists(dirname(__DIR__) . '/includes/pwa_head.php')) include_once dirname(__DIR__) . '/includes/pwa_head.php'; ?>

    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="gstack-app">
    <a href="#mainContent" class="skip-link">Saltar al contenido</a>

    <div class="app-shell">
        <!-- =========================================================
             1. LEFT WORKSPACE SIDEBAR (Notion / Linear / Stripe Architecture)
             ========================================================= -->
        <aside class="app-sidebar" id="appSidebar" aria-label="Navegación del espacio de trabajo">
            <div>
                <!-- Brand / Workspace Header -->
                <div class="sidebar-header">
                    <div class="sidebar-brand-avatar">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="sidebar-brand-info">
                        <div class="sidebar-brand-title">COTI Workspace</div>
                        <div class="sidebar-brand-sub"><?= htmlspecialchars($companyName) ?></div>
                    </div>
                </div>

                <!-- Navigation Sections -->
                <nav class="sidebar-nav-container">
                    <div class="sidebar-section-label">Principal</div>

                    <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-nav-item <?= $activePage === 'dashboard' ? 'is-active' : '' ?>" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>
                        <div class="nav-left">
                            <i class="fas fa-gauge-high"></i>
                            <span class="sidebar-nav-text">Dashboard</span>
                        </div>
                        <?php if ($activePage === 'dashboard'): ?>
                            <span class="live-pulse-dot" style="width:6px;height:6px;" title="Activo"></span>
                        <?php endif; ?>
                    </a>

                    <a href="<?= BASE_URL ?>/quotations/create.php" class="sidebar-nav-item <?= $activePage === 'new_quotation' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-plus-circle text-warning"></i>
                            <span class="sidebar-nav-text">Nueva Cotización</span>
                        </div>
                        <span class="badge bg-warning text-dark" style="font-size:0.65rem;padding:2px 5px;">+</span>
                    </a>

                    <a href="<?= BASE_URL ?>/quotations/index.php" class="sidebar-nav-item <?= $activePage === 'quotations' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-file-invoice"></i>
                            <span class="sidebar-nav-text"><?= $isAdmin ? 'Cotizaciones' : 'Mis Cotizaciones' ?></span>
                        </div>
                    </a>

                    <a href="<?= BASE_URL ?>/customers/index.php" class="sidebar-nav-item <?= $activePage === 'customers' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-users"></i>
                            <span class="sidebar-nav-text">Clientes</span>
                        </div>
                    </a>

                    <a href="<?= BASE_URL ?>/products/index.php" class="sidebar-nav-item <?= $activePage === 'products' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-boxes-stacked"></i>
                            <span class="sidebar-nav-text">Catálogo Productos</span>
                        </div>
                    </a>

                    <div class="sidebar-section-label mt-2">Operaciones</div>

                    <?php if (class_exists('Permissions') && (Permissions::userCan($auth, 'billing_panel') || Permissions::userCan($auth, 'process_billing') || $isAdmin || $isBilling)): ?>
                    <a href="<?= BASE_URL ?>/billing/pending.php" class="sidebar-nav-item <?= $activePage === 'billing' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span class="sidebar-nav-text">Facturación</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (class_exists('Permissions') && (Permissions::userCan($auth, 'credits_panel') || Permissions::userCan($auth, 'process_credits') || $isAdmin || $isCredits)): ?>
                    <a href="<?= BASE_URL ?>/credits/pending.php" class="sidebar-nav-item <?= $activePage === 'credits' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-credit-card"></i>
                            <span class="sidebar-nav-text">Créditos y Cobranzas</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (class_exists('Permissions') && Permissions::canAccessInventoryPanel($auth)): ?>
                    <a href="<?= BASE_URL ?>/inventario/index.php" class="sidebar-nav-item <?= $activePage === 'inventario' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-warehouse"></i>
                            <span class="sidebar-nav-text">Inventario</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if (class_exists('Permissions') && Permissions::canAccessCostAnalysis($auth)): ?>
                    <a href="<?= BASE_URL ?>/cost-analysis/index.php" class="sidebar-nav-item <?= $activePage === 'cost_analysis' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-calculator"></i>
                            <span class="sidebar-nav-text">Análisis de Costos</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <div class="sidebar-section-label mt-2">Sistema</div>

                    <?php if (class_exists('Permissions') && Permissions::canAccessAdminPanel($auth)): ?>
                    <a href="<?= BASE_URL ?>/admin/exchange_rate.php" class="sidebar-nav-item <?= $activePage === 'exchange_rate' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-money-bill-transfer"></i>
                            <span class="sidebar-nav-text">Tipo de Cambio</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/reports/index.php" class="sidebar-nav-item <?= $activePage === 'reports' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-chart-pie"></i>
                            <span class="sidebar-nav-text">Reportes</span>
                        </div>
                    </a>

                    <?php if ($isAdmin): ?>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-nav-item <?= $activePage === 'admin' ? 'is-active' : '' ?>">
                        <div class="nav-left">
                            <i class="fas fa-sliders"></i>
                            <span class="sidebar-nav-text">Administración</span>
                        </div>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Sidebar User Profile Footer -->
            <div class="sidebar-footer">
                <div class="sidebar-user-card">
                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                        <div class="sidebar-user-avatar">
                            <?= $initials ?>
                        </div>
                        <div class="sidebar-user-info overflow-hidden">
                            <div class="small fw-bold text-truncate" style="color:var(--color-ink);"><?= htmlspecialchars($user['username']) ?></div>
                            <div class="text-muted text-truncate" style="font-size:0.7rem;"><?= htmlspecialchars($user['active_role_name'] ?? 'Usuario') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <a href="<?= BASE_URL ?>/profile.php" class="btn btn-sm btn-ghost p-1 text-muted" title="Mi Perfil">
                            <i class="fas fa-gear"></i>
                        </a>
                        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-ghost p-1 text-danger" title="Cerrar Sesión">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- =========================================================
             2. MAIN WRAPPER (TOPBAR + CONTENT GRID)
             ========================================================= -->
        <div class="app-main-wrapper">
            <!-- Sleek Topbar Header -->
            <header class="app-topbar">
                <div class="topbar-left">
                    <button class="btn btn-sm btn-light d-lg-none p-1 px-2 border-0" id="mobileSidebarToggle" type="button" aria-label="Abrir menú lateral">
                        <i class="fas fa-bars"></i>
                    </button>
                    <button class="btn btn-sm btn-light d-none d-lg-inline-flex p-1 px-2 border-0" id="sidebarCollapseToggle" type="button" aria-label="Colapsar menú lateral" title="Colapsar menú lateral">
                        <i class="fas fa-bars-staggered"></i>
                    </button>
                    <div class="topbar-breadcrumb">
                        <span>COTI</span>
                        <i class="fas fa-chevron-right separator"></i>
                        <strong><?= htmlspecialchars($pageTitle) ?></strong>
                    </div>
                </div>

                <div class="topbar-search d-none d-md-flex">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Buscar cotizaciones, clientes..." id="globalQuickSearch" aria-label="Búsqueda rápida">
                </div>

                <div class="topbar-right">
                    <!-- Quick Action "+ Nueva Cotización" -->
                    <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-sm btn-primary d-none d-sm-inline-flex" style="padding: 5px 12px; font-size: 12.5px;">
                        <i class="fas fa-plus"></i>
                        <span>Cotización</span>
                    </a>

                    <!-- Notifications & Alerts Dropdown -->
                    <?php
                    $systemAlerts = $alerts ?? [];
                    $totalBellCount = count($systemAlerts) + $unreadNotifications;
                    ?>
                    <div class="dropdown me-1">
                        <a class="btn btn-sm btn-light border-0 position-relative p-2" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificaciones">
                            <i class="fas fa-bell<?= $totalBellCount > 0 ? ' text-warning' : ' text-muted' ?>"></i>
                            <?php if ($totalBellCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                                    <?= $totalBellCount > 99 ? '99+' : $totalBellCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="width: 340px; max-height: 75vh; overflow-y: auto;">
                            <?php if (!empty($systemAlerts)): ?>
                                <li class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-triangle-exclamation text-warning me-1"></i> Alertas del Sistema</span>
                                    <span class="badge bg-warning text-dark"><?= count($systemAlerts) ?></span>
                                </li>
                                <?php foreach ($systemAlerts as $alert): ?>
                                    <li class="alert-item" data-alert-key="<?= htmlspecialchars($alert['key'] ?? '') ?>">
                                        <div class="dropdown-item py-2 border-start border-3 border-<?= htmlspecialchars($alert['type'] ?? 'warning') ?>" style="white-space: normal;">
                                            <div class="d-flex align-items-start">
                                                <i class="fas <?= htmlspecialchars($alert['icon'] ?? 'fa-info-circle') ?> text-<?= htmlspecialchars($alert['type'] ?? 'warning') ?> me-2 mt-1"></i>
                                                <div class="flex-grow-1">
                                                    <a href="<?= htmlspecialchars($alert['url'] ?? '#') ?>" class="text-decoration-none text-body">
                                                        <div class="small fw-bold"><?= htmlspecialchars($alert['title'] ?? '') ?></div>
                                                        <div class="small text-muted"><?= htmlspecialchars($alert['message'] ?? '') ?></div>
                                                    </a>
                                                </div>
                                                <?php if (!empty($alert['key'])): ?>
                                                <button type="button" class="btn btn-sm text-muted p-0 ms-2 dismiss-alert"
                                                        onclick="dismissAlert('<?= htmlspecialchars($alert['key']) ?>', this); event.stopPropagation();"
                                                        title="Descartar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider my-1"></li>
                            <?php endif; ?>

                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-bell me-1"></i> Notificaciones</span>
                                <span class="badge bg-secondary"><?= $unreadNotifications ?></span>
                            </li>
                            <?php if (empty($recentNotifications)): ?>
                                <li class="text-center py-3 text-muted small"><i class="fas fa-bell-slash mb-1"></i><br>Sin notificaciones nuevas</li>
                            <?php else: ?>
                                <?php foreach ($recentNotifications as $n): ?>
                                    <li>
                                        <a class="dropdown-item py-2" href="<?= !empty($n['related_url']) ? htmlspecialchars($n['related_url']) : BASE_URL . '/notifications/index.php' ?>" style="white-space:normal;">
                                            <div class="small fw-semibold"><?= htmlspecialchars($n['title']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars(substr($n['message'], 0, 60)) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item text-center small text-primary" href="<?= BASE_URL ?>/notifications/index.php">Ver todas</a></li>
                        </ul>
                    </div>

                    <!-- 3-State Theme Toggle Pill (Claro / Sistema / Oscuro) -->
                    <div class="theme-toggle-pill" role="radiogroup" aria-label="Preferencia de tema">
                        <button type="button" class="theme-btn" data-theme-val="light" title="Modo Claro" aria-label="Modo Claro"><i class="fas fa-sun"></i></button>
                        <button type="button" class="theme-btn" data-theme-val="system" title="Tema del Sistema" aria-label="Tema del Sistema"><i class="fas fa-desktop"></i></button>
                        <button type="button" class="theme-btn" data-theme-val="dark" title="Modo Oscuro" aria-label="Modo Oscuro"><i class="fas fa-moon"></i></button>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <a class="d-flex align-items-center text-decoration-none dropdown-toggle p-1 rounded" href="#" id="topbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="color:var(--color-ink);">
                            <div class="sidebar-user-avatar" style="width:28px;height:28px;font-size:11px;">
                                <?= $initials ?>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="topbarUserDropdown">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold small"><?= htmlspecialchars($user['username']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                            </li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="fas fa-user-edit me-2"></i> Mi Perfil</a></li>
                            <?php if ($isAdmin): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php"><i class="fas fa-cog me-2"></i> Panel de Administración</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content Canvas -->
            <main class="app-content-body" id="mainContent">
                <!-- System Flash Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['warning_message'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i>
                        <span><?= htmlspecialchars($_SESSION['warning_message']) ?></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                    <?php unset($_SESSION['warning_message']); ?>
                <?php endif; ?>
