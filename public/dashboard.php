<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Redirect to mobile version if on mobile device
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        header('Location: ' . BASE_URL . '/dashboard_mobile.php');
        exit;
    }
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Optimized dashboard statistics - only basic counts
$db = getDBConnection();
$stats = [];

try {
    // Get quotation stats efficiently
    $stmt = $db->prepare("SELECT
        COUNT(*) as total_quotations,
        SUM(CASE WHEN status = 'Accepted' THEN total ELSE 0 END) as accepted_amount
        FROM quotations WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $quotationStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get other counts
    $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $customersCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $productsCount = $stmt->fetchColumn();

    $stats = [
        'quotations' => $quotationStats,
        'customers_count' => $customersCount,
        'products_count' => $productsCount
    ];

    // Get recent quotations (only 3 for performance)
    $stmt = $db->prepare("SELECT q.id, q.quotation_number, q.quotation_date, q.total, q.status, c.name as customer_name
        FROM quotations q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.company_id = ?
        ORDER BY q.created_at DESC LIMIT 3");
    $stmt->execute([$companyId]);
    $recentQuotations = ['quotations' => $stmt->fetchAll(PDO::FETCH_ASSOC)];

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = [
        'quotations' => ['total_quotations' => 0, 'accepted_amount' => 0],
        'customers_count' => 0,
        'products_count' => 0
    ];
    $recentQuotations = ['quotations' => []];
}

ob_start();
?>

<div class="row">
    <div class="col-12">
        <h1 class="mb-4">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
            <small class="text-muted">- Bienvenido, <?= htmlspecialchars($user['first_name'] ?: $user['username']) ?></small>
        </h1>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?= number_format($stats['quotations']['total_quotations'] ?? 0) ?></div>
                        <div class="stats-label">Total Cotizaciones</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stats-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?= formatCurrency($stats['quotations']['accepted_amount'] ?? 0) ?></div>
                        <div class="stats-label">Monto Aceptado</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-dollar-sign fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stats-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?= number_format($stats['customers_count']) ?></div>
                        <div class="stats-label">Clientes</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card stats-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stats-number"><?= number_format($stats['products_count']) ?></div>
                        <div class="stats-label">Productos</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-box fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt"></i> Acciones Rápidas
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-plus"></i><br>
                            Nueva Cotización
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-user-plus"></i><br>
                            Nuevo Cliente
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-info btn-lg w-100">
                            <i class="fas fa-search"></i><br>
                            Consultar Stock
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <?php if ($auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
                            <a href="<?= BASE_URL ?>/admin/import_products.php" class="btn btn-warning btn-lg w-100">
                                <i class="fas fa-upload"></i><br>
                                Importar Productos
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-secondary btn-lg w-100">
                                <i class="fas fa-chart-bar"></i><br>
                                Reportes
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clock"></i> Cotizaciones Recientes
                </h5>
                <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-sm btn-outline-primary">
                    Ver todas
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentQuotations['quotations'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentQuotations['quotations'] as $quotation): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($quotation['quotation_number']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($quotation['customer_name']) ?></td>
                                        <td><?= formatDate($quotation['quotation_date']) ?></td>
                                        <td><?= formatCurrency($quotation['total']) ?></td>
                                        <td><?= getStatusBadge($quotation['status']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotation['id'] ?>"
                                               class="btn btn-sm btn-outline-primary" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-file-invoice fa-3x mb-3"></i>
                        <p>No hay cotizaciones recientes.</p>
                        <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-primary">
                            Crear primera cotización
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Quotation Status Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie"></i> Estados de Cotizaciones
                </h5>
            </div>
            <div class="card-body">
                <?php
                $statusData = [
                    ['label' => 'Borradores', 'count' => $stats['quotations']['draft_count'] ?? 0, 'class' => 'secondary'],
                    ['label' => 'Enviadas', 'count' => $stats['quotations']['sent_count'] ?? 0, 'class' => 'info'],
                    ['label' => 'Aceptadas', 'count' => $stats['quotations']['accepted_count'] ?? 0, 'class' => 'success'],
                    ['label' => 'Rechazadas', 'count' => $stats['quotations']['rejected_count'] ?? 0, 'class' => 'danger']
                ];
                ?>
                <?php foreach ($statusData as $status): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-<?= $status['class'] ?>"><?= $status['label'] ?></span>
                        <strong><?= number_format($status['count']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-link"></i> Enlaces Rápidos
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="<?= BASE_URL ?>/products/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-search me-2"></i> Consultar Stock
                    </a>
                    <?php if ($auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i> Configuración
                        </a>
                        <a href="<?= BASE_URL ?>/admin/import_products.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-upload me-2"></i> Importar Productos
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/reports/index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar me-2"></i> Reportes
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Simple template rendering without heavy TemplateEngine
$pageTitle = 'Dashboard - Sistema de Cotizaciones';
$companyData = ['name' => 'Sistema de Cotizaciones'];
$userData = $user;
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
            <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard.php">
                Sistema de Cotizaciones
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
                        <?php if ($auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">Panel Admin</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <?= $content ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html><?php

// Helper functions
function formatCurrency($amount, $currency = 'S/') {
    return $currency . ' ' . number_format($amount, 2);
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

function getStatusBadge($status) {
    $statusClasses = [
        'Draft' => 'bg-secondary',
        'Sent' => 'bg-info',
        'Accepted' => 'bg-success',
        'Rejected' => 'bg-danger'
    ];
    $statusNames = [
        'Draft' => 'Borrador',
        'Sent' => 'Enviada',
        'Accepted' => 'Aceptada',
        'Rejected' => 'Rechazada'
    ];

    $class = $statusClasses[$status] ?? 'bg-secondary';
    $name = $statusNames[$status] ?? $status;

    return "<span class=\"badge {$class}\">{$name}</span>";
}
?>
