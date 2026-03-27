<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Verificar si el usuario SOLO tiene rol de Facturación (no debe acceder a cotizaciones)
$userRepo = new User();
$userRoles = $userRepo->getRoles($auth->getUserId());

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

// Redirect to mobile version if on mobile device (unless explicitly disabled)
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . BASE_URL . '/quotations/index_mobile.php' . ($queryString ? '?' . $queryString : ''));
        exit;
    }
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Get filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);

// Si no hay filtros aplicados, mostrar solo del mes actual
$hasFilters = !empty($status) || !empty($search) || !empty($dateFrom) || !empty($dateTo);
if (!$hasFilters && !isset($_GET['all'])) {
    // Por defecto mostrar cotizaciones del mes actual
    $dateFrom = date('Y-m-01'); // Primer día del mes
    $dateTo = date('Y-m-t');     // Último día del mes
}

$filters = array_filter([
    'status' => $status,
    'search' => $search,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

// Check if user can view all quotations (admins always can)
if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']) && !($user['can_view_all_quotations'] ?? 0)) {
    $filters['user_id'] = $user['id'];
}

// Get quotations
$quotationRepo = new Quotation();
$result = $quotationRepo->getQuotationsWithFilters($companyId, $filters, $page, 20);

$pageTitle = ($user['can_view_all_quotations'] ?? 0) ? 'Cotizaciones' : 'Mis Cotizaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <?php include __DIR__ . '/../../includes/pwa_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        /* Force light theme for this page */
        body {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .card {
            background-color: #ffffff !important;
            color: #212529 !important;
            border-color: #dee2e6 !important;
        }

        .form-control, .form-select {
            background-color: #ffffff !important;
            color: #212529 !important;
            border-color: #dee2e6 !important;
        }

        .table {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .table td, .table th {
            background-color: #ffffff !important;
            color: #212529 !important;
            border-color: #dee2e6 !important;
        }

        .table-hover tbody tr:hover td {
            background-color: #f5f5f5 !important;
        }

        .modal-content {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .modal-body {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .modal-footer {
            background-color: #ffffff !important;
            color: #212529 !important;
        }
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

    /* Fix dropdown being cut off in table-responsive */
    .table-responsive {
        overflow: visible !important;
    }
    .card-body {
        overflow: visible !important;
    }
    .actions-dropdown .dropdown-menu[data-bs-popper] {
        position: absolute !important;
        inset: auto !important;
        transform: none !important;
        top: 100% !important;
        right: 0 !important;
        left: auto !important;
        z-index: 9999 !important;
        background-color: #fff !important;
        border: 1px solid #dee2e6 !important;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important;
        min-width: 180px;
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

                <!-- Facturación Dropdown -->
                <?php
                // Check if user has billing role
                $hasBillingRole = $auth->hasRole(['Facturación', 'Administrador del Sistema', 'Administrador de Empresa']);
                ?>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="billingDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-file-invoice-dollar"></i> Facturación
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/billing/history.php">
                            <i class="fas fa-history"></i> Mi Historial
                        </a></li>
                        <?php if ($hasBillingRole): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/billing/pending.php">
                                <i class="fas fa-clock"></i> Solicitudes Pendientes
                            </a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/customers/index.php">Clientes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li id="menu-install-pwa" style="display: none;">
                            <a class="dropdown-item" href="#" onclick="installPWA(); return false;">
                                <i class="fas fa-download me-2"></i>Instalar Aplicación
                            </a>
                        </li>
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
                    <h1><i class="fas fa-file-invoice"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nueva Cotización
                    </a>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status">
                                    <option value="">Todos los estados</option>
                                    <option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Borrador</option>
                                    <option value="Sent" <?= $status === 'Sent' ? 'selected' : '' ?>>Enviada</option>
                                    <option value="Accepted" <?= $status === 'Accepted' ? 'selected' : '' ?>>Aceptada</option>
                                    <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rechazada</option>
                                    <option value="Invoiced" <?= $status === 'Invoiced' ? 'selected' : '' ?>>Facturada</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search"
                                       placeholder="Número o cliente..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Desde</label>
                                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hasta</label>
                                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Info message if showing only current month -->
                <?php if (!$hasFilters && !isset($_GET['all'])): ?>
                    <?php
                    $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    $mesActual = $meses[date('n', strtotime($dateFrom)) - 1];
                    $anioActual = date('Y', strtotime($dateFrom));
                    ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Mostrando cotizaciones de <strong><?= $mesActual ?> <?= $anioActual ?></strong>
                        <a href="?all=1" class="btn btn-sm btn-outline-primary float-end">
                            <i class="fas fa-list"></i> Ver todas
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Quotations Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($result['quotations'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Número</th>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Válida hasta</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th>Facturación</th>
                                            <th>Crédito</th>
                                            <th>Creado por</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($result['quotations'] as $quotation): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($quotation['quotation_number']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($quotation['customer_name']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></td>
                                                <td>
                                                    <?php if ($quotation['valid_until']): ?>
                                                        <?= date('d/m/Y', strtotime($quotation['valid_until'])) ?>
                                                        <?php if (strtotime($quotation['valid_until']) < time()): ?>
                                                            <small class="text-danger">(Vencida)</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong>S/ <?= number_format($quotation['total'], 2) ?></strong></td>
                                                <td>
                                                    <?php
                                                    $statusClasses = [
                                                        'Draft' => 'bg-secondary',
                                                        'Sent' => 'bg-info',
                                                        'Accepted' => 'bg-success',
                                                        'Rejected' => 'bg-danger',
                                                        'Invoiced' => 'bg-primary'
                                                    ];
                                                    $statusNames = [
                                                        'Draft' => 'Borrador',
                                                        'Sent' => 'Enviada',
                                                        'Accepted' => 'Aceptada',
                                                        'Rejected' => 'Rechazada',
                                                        'Invoiced' => 'Facturada'
                                                    ];
                                                    $class = $statusClasses[$quotation['status']] ?? 'bg-secondary';
                                                    $name = $statusNames[$quotation['status']] ?? $quotation['status'];
                                                    ?>
                                                    <span class="badge <?= $class ?>"><?= $name ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Show billing status if quotation is accepted
                                                    if ($quotation['status'] === 'Accepted' && !empty($quotation['billing_status'])):
                                                        $billingClasses = [
                                                            'Pending_Invoice' => 'bg-warning text-dark',
                                                            'Invoiced' => 'bg-success',
                                                            'Invoice_Rejected' => 'bg-danger'
                                                        ];
                                                        $billingNames = [
                                                            'Pending_Invoice' => 'Pendiente',
                                                            'Invoiced' => 'Facturado',
                                                            'Invoice_Rejected' => 'Rechazado'
                                                        ];
                                                        $billingClass = $billingClasses[$quotation['billing_status']] ?? 'bg-secondary';
                                                        $billingName = $billingNames[$quotation['billing_status']] ?? $quotation['billing_status'];
                                                    ?>
                                                        <span class="badge <?= $billingClass ?>">
                                                            <?= $billingName ?>
                                                        </span>
                                                        <?php if ($quotation['billing_status'] === 'Invoiced' && !empty($quotation['invoice_number'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($quotation['invoice_number']) ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Show credit status if quotation is credit-based
                                                    $paymentCondition = $quotation['payment_condition'] ?? 'cash';
                                                    $creditStatus = $quotation['credit_status'] ?? null;

                                                    if ($paymentCondition === 'credit'):
                                                        if ($creditStatus):
                                                            $creditClasses = [
                                                                'Pending_Credit' => 'bg-warning text-dark',
                                                                'Credit_Approved' => 'bg-success',
                                                                'Credit_Rejected' => 'bg-danger'
                                                            ];
                                                            $creditNames = [
                                                                'Pending_Credit' => 'Pendiente',
                                                                'Credit_Approved' => 'Aprobado',
                                                                'Credit_Rejected' => 'Rechazado'
                                                            ];
                                                            $creditClass = $creditClasses[$creditStatus] ?? 'bg-secondary';
                                                            $creditName = $creditNames[$creditStatus] ?? $creditStatus;
                                                    ?>
                                                        <span class="badge <?= $creditClass ?>"><?= $creditName ?></span>
                                                        <?php if (!empty($quotation['credit_days'])): ?>
                                                            <br><small class="text-muted"><?= $quotation['credit_days'] ?> días</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-info"><?= $quotation['credit_days'] ?? 0 ?> días</span>
                                                        <br><small class="text-muted">Sin solicitar</small>
                                                    <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Contado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($quotation['creator_username']) ?></td>
                                                <td>
                                                    <div class="btn-group actions-dropdown">
                                                        <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotation['id'] ?>"
                                                           class="btn btn-sm btn-outline-info" title="Ver">
                                                            <i class="fas fa-eye"></i>
                                                        </a>

                                                        <?php if ($quotation['status'] === 'Draft'): ?>
                                                            <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $quotation['id'] ?>"
                                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <button class="btn btn-sm btn-outline-success"
                                                                onclick="duplicateQuotation(<?= $quotation['id'] ?>)" title="Duplicar">
                                                            <i class="fas fa-copy"></i>
                                                        </button>

                                                        <div class="btn-group actions-dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                                    type="button" data-bs-toggle="dropdown">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                                <li>
                                                                    <a class="dropdown-item" href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>">
                                                                        <i class="fas fa-file-pdf"></i> Descargar PDF
                                                                    </a>
                                                                </li>
                                                                <?php if ($quotation['status'] !== 'Sent'): ?>
                                                                    <li>
                                                                        <button class="dropdown-item" onclick="changeStatus(<?= $quotation['id'] ?>, 'Sent')">
                                                                            <i class="fas fa-paper-plane"></i> Marcar como Enviada
                                                                        </button>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <?php if ($quotation['status'] === 'Sent'): ?>
                                                                    <li>
                                                                        <button class="dropdown-item" onclick="changeStatus(<?= $quotation['id'] ?>, 'Accepted')">
                                                                            <i class="fas fa-check"></i> Marcar como Aceptada
                                                                        </button>
                                                                    </li>
                                                                    <li>
                                                                        <button class="dropdown-item" onclick="changeStatus(<?= $quotation['id'] ?>, 'Rejected')">
                                                                            <i class="fas fa-times"></i> Marcar como Rechazada
                                                                        </button>
                                                                    </li>
                                                                <?php endif; ?>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <button class="dropdown-item text-danger"
                                                                            onclick="deleteQuotation(<?= $quotation['id'] ?>)">
                                                                        <i class="fas fa-trash"></i> Eliminar
                                                                    </button>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($result['total_pages'] > 1): ?>
                                <nav class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $result['total_pages']; $i++): ?>
                                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <h3 class="text-muted">No se encontraron cotizaciones</h3>
                                <?php if (empty($filters)): ?>
                                    <p>Aún no has creado cotizaciones.</p>
                                    <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Crear primera cotización
                                    </a>
                                <?php else: ?>
                                    <p>No se encontraron cotizaciones que coincidan con los filtros aplicados.</p>
                                    <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-primary">
                                        Ver todas las cotizaciones
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Summary -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Total de Cotizaciones</h5>
                                <h2 class="text-primary"><?= number_format($result['total']) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>Valor Total</h5>
                                <h2 class="text-success">
                                    S/ <?php
                                    $totalValue = 0;
                                    foreach ($result['quotations'] as $q) {
                                        $totalValue += $q['total'];
                                    }
                                    echo number_format($totalValue, 2);
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeStatus(quotationId, newStatus) {
            if (confirm(`¿Cambiar estado de la cotización?`)) {
                fetch(`<?= BASE_URL ?>/quotations/change_status.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${quotationId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function duplicateQuotation(quotationId) {
            if (confirm('¿Duplicar esta cotización?')) {
                fetch(`<?= BASE_URL ?>/quotations/duplicate.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${quotationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = `<?= BASE_URL ?>/quotations/edit.php?id=${data.new_id}`;
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function deleteQuotation(quotationId) {
            if (confirm('¿Eliminar esta cotización? Esta acción no se puede deshacer.')) {
                fetch(`<?= BASE_URL ?>/quotations/delete.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${quotationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>

    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>

    <!-- Boton flotante de instalacion PWA -->
    <button id="pwa-install-btn" onclick="installPWA()" class="btn btn-primary position-fixed d-none align-items-center gap-2"
            style="bottom: 20px; right: 20px; z-index: 1000; border-radius: 50px; padding: 12px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <i class="fas fa-download"></i>
        <span class="d-none d-sm-inline">Instalar App</span>
    </button>
</body>
</html>