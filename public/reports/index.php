<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Verificar si el usuario SOLO tiene rol de Facturación (no debe acceder a reportes)
$userRepo = new User();
$userRoles = $userRepo->getRoles($auth->getUserId());

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();
$isAdmin = $auth->hasRole('Administrador del Sistema') || $auth->hasRole('Administrador de Empresa');

// Get date range from request
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$reportType = $_GET['type'] ?? 'dashboard';

// Solo admins pueden ver datos de otros vendedores
$sellers = [];
if ($isAdmin) {
    $sellerId = $_GET['seller_id'] ?? null;
    $db = getDBConnection();
    $sellersStmt = $db->prepare("
        SELECT DISTINCT u.id, u.username, u.first_name, u.last_name
        FROM users u
        INNER JOIN quotations q ON u.id = q.user_id
        WHERE u.company_id = ?
        ORDER BY u.first_name, u.last_name
    ");
    $sellersStmt->execute([$companyId]);
    $sellers = $sellersStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Usuario normal: forzar a ver solo sus propios datos
    $sellerId = $userId;
}

// Initialize repositories
$quotationRepo = new Quotation();
$customerRepo = new Customer();
$productRepo = new Product();

// Get report data based on type
$reportData = [];

switch ($reportType) {
    case 'quotations':
        $reportData = getQuotationReport($quotationRepo, $companyId, $startDate, $endDate, $sellerId);
        break;
    case 'customers':
        $reportData = getCustomerReport($customerRepo, $companyId, $startDate, $endDate, $sellerId);
        break;
    case 'products':
        $reportData = getProductReport($productRepo, $companyId, $startDate, $endDate, $sellerId);
        break;
    default:
        $reportData = getDashboardReport($quotationRepo, $customerRepo, $productRepo, $companyId, $startDate, $endDate, $sellerId);
}

$pageTitle = 'Reportes y Estadísticas';

// Helper functions
function getDashboardReport($quotationRepo, $customerRepo, $productRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    $data = [];

    // Quotation statistics
    $data['quotations'] = [
        'total' => $quotationRepo->getCount($companyId, $startDate, $endDate, $sellerId),
        'by_status' => $quotationRepo->getCountByStatus($companyId, $startDate, $endDate, $sellerId),
        'total_amount' => $quotationRepo->getTotalAmount($companyId, $startDate, $endDate, $sellerId),
        'average_amount' => $quotationRepo->getAverageAmount($companyId, $startDate, $endDate, $sellerId),
        'by_month' => $quotationRepo->getCountByMonth($companyId, $startDate, $endDate, $sellerId)
    ];

    // Customer statistics
    $data['customers'] = [
        'total' => $customerRepo->getCount($companyId),
        'new_customers' => $customerRepo->getNewCustomersCount($companyId, $startDate, $endDate),
        'top_customers' => $customerRepo->getTopCustomers($companyId, $startDate, $endDate, 5, $sellerId)
    ];

    // Product statistics
    $data['products'] = [
        'total' => $productRepo->getCount($companyId),
        'most_quoted' => $productRepo->getMostQuotedProducts($companyId, $startDate, $endDate, 10, $sellerId),
        'low_stock' => $productRepo->getLowStockProducts($companyId, 10)
    ];

    return $data;
}

function getQuotationReport($quotationRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    return [
        'summary' => [
            'total' => $quotationRepo->getCount($companyId, $startDate, $endDate, $sellerId),
            'by_status' => $quotationRepo->getCountByStatus($companyId, $startDate, $endDate, $sellerId),
            'total_amount' => $quotationRepo->getTotalAmount($companyId, $startDate, $endDate, $sellerId),
            'average_amount' => $quotationRepo->getAverageAmount($companyId, $startDate, $endDate, $sellerId),
        ],
        'by_month' => $quotationRepo->getCountByMonth($companyId, $startDate, $endDate, $sellerId),
        'by_user' => $quotationRepo->getCountByUser($companyId, $startDate, $endDate, $sellerId),
        'conversion_rate' => $quotationRepo->getConversionRate($companyId, $startDate, $endDate, $sellerId),
        'recent_quotations' => $quotationRepo->getRecent($companyId, 10, $startDate, $endDate, $sellerId)
    ];
}

function getCustomerReport($customerRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    return [
        'summary' => [
            'total' => $customerRepo->getCount($companyId),
            'new_customers' => $customerRepo->getNewCustomersCount($companyId, $startDate, $endDate),
            'active_customers' => $customerRepo->getActiveCustomersCount($companyId, $startDate, $endDate, $sellerId)
        ],
        'top_customers' => $customerRepo->getTopCustomers($companyId, $startDate, $endDate, 20, $sellerId),
        'by_type' => $customerRepo->getCountByType($companyId),
        'recent_customers' => $customerRepo->getRecent($companyId, 10)
    ];
}

function getProductReport($productRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    return [
        'summary' => [
            'total' => $productRepo->getCount($companyId),
            'with_stock' => $productRepo->getCountWithStock($companyId),
            'low_stock' => $productRepo->getLowStockCount($companyId)
        ],
        'most_quoted' => $productRepo->getMostQuotedProducts($companyId, $startDate, $endDate, 20, $sellerId),
        'low_stock_products' => $productRepo->getLowStockProducts($companyId, 20),
        'by_brand' => $productRepo->getCountByBrand($companyId)
    ];
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
        .chart-container {
            position: relative;
            height: 400px;
        }
        .report-filters {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        @media print {
            .no-print { display: none !important; }
            .chart-container { height: 300px; }
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
    <nav class="navbar navbar-expand-lg bg-primary no-print">
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1><i class="fas fa-chart-bar"></i> <?= $pageTitle ?></h1>
            <div class="btn-group">
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn btn-outline-success" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button class="btn btn-outline-danger" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="report-filters no-print">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-2">
                    <label for="type" class="form-label">Tipo de Reporte</label>
                    <select class="form-select" id="type" name="type">
                        <option value="dashboard" <?= $reportType === 'dashboard' ? 'selected' : '' ?>>Dashboard General</option>
                        <option value="quotations" <?= $reportType === 'quotations' ? 'selected' : '' ?>>Cotizaciones</option>
                        <option value="customers" <?= $reportType === 'customers' ? 'selected' : '' ?>>Clientes</option>
                        <option value="products" <?= $reportType === 'products' ? 'selected' : '' ?>>Productos</option>
                    </select>
                </div>
                <?php if ($isAdmin): ?>
                <div class="col-md-3">
                    <label for="seller_id" class="form-label">Vendedor</label>
                    <select class="form-select" id="seller_id" name="seller_id">
                        <option value="">Todos los vendedores</option>
                        <?php foreach ($sellers as $seller): ?>
                            <?php
                            $sellerName = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? ''));
                            if (empty($sellerName)) {
                                $sellerName = $seller['username'];
                            }
                            ?>
                            <option value="<?= $seller['id'] ?>" <?= $sellerId == $seller['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sellerName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generar Reporte
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Content -->
        <?php if ($reportType === 'dashboard'): ?>
            <?php include 'dashboard_report.php'; ?>
        <?php elseif ($reportType === 'quotations'): ?>
            <?php include 'quotations_report.php'; ?>
        <?php elseif ($reportType === 'customers'): ?>
            <?php include 'customers_report.php'; ?>
        <?php elseif ($reportType === 'products'): ?>
            <?php include 'products_report.php'; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportReport(format) {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            window.location.href = '<?= BASE_URL ?>/reports/export.php?' + params.toString();
        }

        // Auto-submit form when date changes
        document.getElementById('start_date').addEventListener('change', function() {
            if (document.getElementById('end_date').value) {
                this.form.submit();
            }
        });

        document.getElementById('end_date').addEventListener('change', function() {
            if (document.getElementById('start_date').value) {
                this.form.submit();
            }
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>