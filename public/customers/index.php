<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Verificar si el usuario SOLO tiene rol de Facturación (no debe acceder a clientes)
$userRepo = new User();
$userRoles = $userRepo->getRoles($auth->getUserId());

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Redirect to mobile version if on mobile device (unless explicitly disabled)
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . BASE_URL . '/customers/index_mobile.php' . ($queryString ? '?' . $queryString : ''));
        exit;
    }
}

// Get customers
$customerRepo = new Customer();
$isAdmin = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']);
if ($isAdmin) {
    $customers = $customerRepo->getAll();
} else {
    $customers = $customerRepo->getAllByCompany($companyId);
}

// Handle search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $customers = array_filter($customers, function($customer) use ($search) {
        return stripos($customer['name'], $search) !== false ||
               stripos($customer['email'], $search) !== false ||
               stripos($customer['tax_id'], $search) !== false;
    });
}

$pageTitle = 'Gestión de Clientes';
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
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-users"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nuevo Cliente
                    </a>
                </div>

                <!-- Search Bar -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control" name="search"
                                   placeholder="Buscar por nombre, email o documento..."
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-outline-primary ms-2">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">Total: <?= count($customers) ?> clientes</small>
                    </div>
                </div>

                <!-- Customers Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($customers)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Contacto</th>
                                            <th>Email</th>
                                            <th>Teléfono</th>
                                            <th>Documento</th>
                                            <th>Vendedor</th>
                                            <?php if ($isAdmin): ?>
                                                <th>Empresa</th>
                                            <?php endif; ?>
                                            <th>Fecha Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customers as $customer): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($customer['contact_person'] ?? '-') ?></td>
                                                <td>
                                                    <?php if ($customer['email']): ?>
                                                        <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                                                            <?= htmlspecialchars($customer['email']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($customer['phone']): ?>
                                                        <a href="tel:<?= htmlspecialchars($customer['phone']) ?>">
                                                            <?= htmlspecialchars($customer['phone']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                 <td><?= htmlspecialchars($customer['tax_id'] ?? '-') ?></td>
                                                 <td>
                                                     <?php if (!empty($customer['owner_name'])): ?>
                                                         <span class="badge bg-info">
                                                             <i class="fas fa-user-tie"></i> <?= htmlspecialchars($customer['owner_name']) ?>
                                                         </span>
                                                     <?php else: ?>
                                                         <span class="text-muted">-</span>
                                                     <?php endif; ?>
                                                 </td>
                                                 <?php if ($isAdmin): ?>
                                                     <td>
                                                         <span class="badge bg-secondary">
                                                             <?= htmlspecialchars($customer['company_name'] ?? '-') ?>
                                                         </span>
                                                     </td>
                                                 <?php endif; ?>
                                                 <td><?= date('d/m/Y', strtotime($customer['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customer['id'] ?>"
                                                           class="btn btn-sm btn-outline-info" title="Ver">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>"
                                                           class="btn btn-sm btn-outline-primary" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customer['id'] ?>"
                                                           class="btn btn-sm btn-outline-success" title="Nueva Cotización">
                                                            <i class="fas fa-file-invoice"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                                onclick="deleteCustomer(<?= $customer['id'] ?>)" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h3 class="text-muted">No se encontraron clientes</h3>
                                <?php if (empty($search)): ?>
                                    <p>Aún no has registrado clientes.</p>
                                    <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Registrar primer cliente
                                    </a>
                                <?php else: ?>
                                    <p>No se encontraron clientes que coincidan con "<?= htmlspecialchars($search) ?>"</p>
                                    <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-primary">
                                        Ver todos los clientes
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteCustomer(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este cliente?')) {
                fetch(`<?= BASE_URL ?>/customers/delete.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error al eliminar el cliente');
                });
            }
        }
    </script>

    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>