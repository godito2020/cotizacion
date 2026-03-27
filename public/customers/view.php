<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$customerId = $_GET['id'] ?? 0;
$isAdmin = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']);

// Redirect to mobile version if on mobile device
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        header('Location: ' . BASE_URL . '/customers/view_mobile.php?id=' . $customerId);
        exit;
    }
}

$customerRepo = new Customer();
if ($isAdmin) {
    $customer = $customerRepo->getByIdGlobal($customerId);
} else {
    $customer = $customerRepo->getById($customerId, $companyId);
}

if (!$customer) {
    $_SESSION['error_message'] = 'Cliente no encontrado';
    $auth->redirect(BASE_URL . '/customers/index.php');
}

// Use the customer's own company_id for quotation queries
$customerCompanyId = $customer['company_id'];

// Get date filters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Si no hay filtros de fecha, mostrar solo del mes actual
$hasDateFilters = !empty($dateFrom) || !empty($dateTo);
if (!$hasDateFilters && !isset($_GET['all'])) {
    $dateFrom = date('Y-m-01'); // Primer día del mes
    $dateTo = date('Y-m-t');     // Último día del mes
}

// Get customer quotations
$quotationRepo = new Quotation();
$filters = array_filter([
    'customer_id' => $customerId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);
$quotations = $quotationRepo->getQuotationsWithFilters($customerCompanyId, $filters, 1, 50);

$pageTitle = 'Ver Cliente: ' . $customer['name'];
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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/customers/index.php">Clientes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-user"></i> <?= htmlspecialchars($customer['name']) ?></h1>
                    <div class="btn-group">
                        <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customer['id'] ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nueva Cotización
                        </a>
                    </div>
                </div>

                <div class="row">
                    <!-- Customer Information -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información del Cliente</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Nombre:</th>
                                        <td><?= htmlspecialchars($customer['name']) ?></td>
                                    </tr>
                                    <?php if ($customer['contact_person']): ?>
                                    <tr>
                                        <th>Contacto:</th>
                                        <td><?= htmlspecialchars($customer['contact_person']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($customer['email']): ?>
                                    <tr>
                                        <th>Email:</th>
                                        <td>
                                            <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                                                <?= htmlspecialchars($customer['email']) ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($customer['phone']): ?>
                                    <tr>
                                        <th>Teléfono:</th>
                                        <td>
                                            <a href="tel:<?= htmlspecialchars($customer['phone']) ?>">
                                                <?= htmlspecialchars($customer['phone']) ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($customer['tax_id']): ?>
                                    <tr>
                                        <th>Documento:</th>
                                        <td><?= htmlspecialchars($customer['tax_id']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($customer['address']): ?>
                                    <tr>
                                        <th>Dirección:</th>
                                        <td><?= nl2br(htmlspecialchars($customer['address'])) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Registrado:</th>
                                        <td><?= date('d/m/Y H:i', strtotime($customer['created_at'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Estadísticas</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4 col-6">
                                        <div class="border-end">
                                            <div class="h4 text-primary"><?= $quotations['total'] ?? 0 ?></div>
                                            <small class="text-muted">Cotizaciones</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <div class="border-end">
                                            <div class="h4 text-success">
                                                <?php
                                                $acceptedQuotations = 0;
                                                $totalAmount = 0;
                                                if (!empty($quotations['quotations'])) {
                                                    foreach ($quotations['quotations'] as $q) {
                                                        if ($q['status'] === 'Accepted') {
                                                            $acceptedQuotations++;
                                                            $totalAmount += $q['total'];
                                                        }
                                                    }
                                                }
                                                echo $acceptedQuotations;
                                                ?>
                                            </div>
                                            <small class="text-muted">Aceptadas</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-12 mt-3 mt-md-0">
                                        <div class="h4 text-info">
                                            S/ <?= number_format($totalAmount, 2) ?>
                                        </div>
                                        <small class="text-muted">Total Aceptado</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Quotations -->
                    <div class="col-md-8">
                        <!-- Date Filter -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <input type="hidden" name="id" value="<?= $customerId ?>">
                                    <div class="col-md-5">
                                        <label class="form-label">Desde</label>
                                        <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Hasta</label>
                                        <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-search"></i> Filtrar
                                        </button>
                                    </div>
                                    <?php if ($hasDateFilters): ?>
                                        <div class="col-12">
                                            <a href="?id=<?= $customerId ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-times"></i> Limpiar filtros
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Info message if showing only current month -->
                        <?php if (!$hasDateFilters && !isset($_GET['all'])): ?>
                            <?php
                            $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                            $mesActual = $meses[date('n', strtotime($dateFrom)) - 1];
                            $anioActual = date('Y', strtotime($dateFrom));
                            ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Mostrando cotizaciones de <strong><?= $mesActual ?> <?= $anioActual ?></strong>
                                <a href="?id=<?= $customerId ?>&all=1" class="btn btn-sm btn-outline-primary float-end">
                                    <i class="fas fa-list"></i> Ver todas
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Cotizaciones</h5>
                                <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus"></i> Nueva Cotización
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($quotations['quotations'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Número</th>
                                                    <th>Fecha</th>
                                                    <th>Total</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($quotations['quotations'] as $quotation): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?= htmlspecialchars($quotation['quotation_number']) ?></strong>
                                                        </td>
                                                        <td><?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></td>
                                                        <td>S/ <?= number_format($quotation['total'], 2) ?></td>
                                                        <td>
                                                            <?php
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
                                                            $class = $statusClasses[$quotation['status']] ?? 'bg-secondary';
                                                            $name = $statusNames[$quotation['status']] ?? $quotation['status'];
                                                            ?>
                                                            <span class="badge <?= $class ?>"><?= $name ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotation['id'] ?>"
                                                                   class="btn btn-sm btn-outline-primary" title="Ver">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php if ($quotation['status'] === 'Draft'): ?>
                                                                    <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $quotation['id'] ?>"
                                                                       class="btn btn-sm btn-outline-warning" title="Editar">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if ($quotations['total'] > 10): ?>
                                        <div class="text-center mt-3">
                                            <a href="<?= BASE_URL ?>/quotations/index.php?customer_id=<?= $customer['id'] ?>"
                                               class="btn btn-outline-primary">
                                                Ver todas las cotizaciones
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">Sin cotizaciones</h5>
                                        <p>Este cliente aún no tiene cotizaciones registradas.</p>
                                        <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customer['id'] ?>"
                                           class="btn btn-success">
                                            <i class="fas fa-plus"></i> Crear primera cotización
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>