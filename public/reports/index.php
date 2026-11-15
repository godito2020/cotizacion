<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Verificar si el usuario SOLO tiene rol de Facturación
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
    $sellerId = $userId;
}

// Initialize repositories
$quotationRepo = new Quotation();
$customerRepo = new Customer();
$productRepo = new Product();

// Helper functions
function getDashboardReport($quotationRepo, $customerRepo, $productRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    $data = [];

    $byStatusRaw = $quotationRepo->getCountByStatus($companyId, $startDate, $endDate, $sellerId);
    $byStatus = [];
    foreach ($byStatusRaw as $row) {
        $byStatus[$row['status']] = (int)$row['count'];
    }

    $byMonthRaw = $quotationRepo->getCountByMonth($companyId, $startDate, $endDate, $sellerId);
    $byMonth = [];
    foreach ($byMonthRaw as $row) {
        $byMonth[$row['month']] = (int)$row['count'];
    }

    $data['quotations'] = [
        'total' => $quotationRepo->getCount($companyId, $startDate, $endDate, $sellerId),
        'by_status' => $byStatus,
        'total_amount' => $quotationRepo->getTotalAmount($companyId, $startDate, $endDate, $sellerId),
        'average_amount' => $quotationRepo->getAverageAmount($companyId, $startDate, $endDate, $sellerId),
        'by_month' => $byMonth
    ];

    $data['customers'] = [
        'total' => $customerRepo->getCount($companyId),
        'new_customers' => $customerRepo->getNewCustomersCount($companyId, $startDate, $endDate),
        'top_customers' => $customerRepo->getTopCustomers($companyId, $startDate, $endDate, 5, $sellerId)
    ];

    $data['products'] = [
        'total' => $productRepo->getCount(),
        'most_quoted' => $productRepo->getMostQuotedProducts($startDate, $endDate, 10),
        'low_stock' => $productRepo->getLowStockProducts(10, 10)
    ];

    return $data;
}

function getQuotationReport($quotationRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    $byStatusRaw = $quotationRepo->getCountByStatus($companyId, $startDate, $endDate, $sellerId);
    $byStatus = [];
    foreach ($byStatusRaw as $row) {
        $byStatus[$row['status']] = (int)$row['count'];
    }

    $byMonthRaw = $quotationRepo->getCountByMonth($companyId, $startDate, $endDate, $sellerId);
    $byMonth = [];
    foreach ($byMonthRaw as $row) {
        $byMonth[$row['month']] = (int)$row['count'];
    }

    return [
        'summary' => [
            'total' => $quotationRepo->getCount($companyId, $startDate, $endDate, $sellerId),
            'by_status' => $byStatus,
            'total_amount' => $quotationRepo->getTotalAmount($companyId, $startDate, $endDate, $sellerId),
            'average_amount' => $quotationRepo->getAverageAmount($companyId, $startDate, $endDate, $sellerId),
        ],
        'by_month' => $byMonth,
        'by_user' => $quotationRepo->getCountByUser($companyId, $startDate, $endDate, $sellerId),
        'conversion_rate' => $quotationRepo->getConversionRate($companyId, $startDate, $endDate, $sellerId),
        'recent_quotations' => $quotationRepo->getRecent($companyId, 10, $startDate, $endDate, $sellerId)
    ];
}

function getCustomerReport($customerRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    $byTypeRaw = $customerRepo->getCountByType($companyId);
    $byType = [];
    foreach ($byTypeRaw as $row) {
        $typeName = $row['company_status'] ?? 'Sin tipo';
        $byType[$typeName] = (int)$row['count'];
    }

    return [
        'summary' => [
            'total' => $customerRepo->getCount($companyId),
            'new_customers' => $customerRepo->getNewCustomersCount($companyId, $startDate, $endDate),
            'active_customers' => $customerRepo->getActiveCustomersCount($companyId, $startDate, $endDate, $sellerId)
        ],
        'top_customers' => $customerRepo->getTopCustomers($companyId, $startDate, $endDate, 20, $sellerId),
        'by_type' => $byType,
        'recent_customers' => $customerRepo->getRecent($companyId, 10)
    ];
}

function getProductReport($productRepo, $companyId, $startDate, $endDate, $sellerId = null) {
    return [
        'summary' => [
            'total' => $productRepo->getCount(),
            'with_stock' => $productRepo->getCountWithStock(),
            'low_stock' => $productRepo->getLowStockCount(10)
        ],
        'most_quoted' => $productRepo->getMostQuotedProducts($startDate, $endDate, 20),
        'low_stock_products' => $productRepo->getLowStockProducts(10, 20),
        'by_brand' => $productRepo->getCountByBrand()
    ];
}

// Get report data based on type
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
$activePage = 'reports';

$extraHead = <<<'EOD'
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .chart-container { position: relative; height: 360px; }
    @media print {
        .no-print { display: none !important; }
        .chart-container { height: 260px; }
    }
</style>
EOD;

require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1400px; margin: 0 auto;">

    <!-- =========================================================
         1. BREADCRUMB & HEADER
         ========================================================= -->
    <div class="d-flex flex-column gap-2 mb-3 no-print">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="text-decoration-none text-muted">
                        <i class="fas fa-home me-1"></i>Inicio
                    </a>
                </li>
                <li class="breadcrumb-item text-muted">Operaciones</li>
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Reportes</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Reportes y Estadísticas Comerciales
                    </h1>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Analítica de cotizaciones emitidas, conversión comercial, clientes destacados y rotación de productos.
                </p>
            </div>

            <div class="d-flex align-items-center gap-1.5">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Imprimir
                </button>
                <button type="button" class="btn btn-outline-success btn-sm px-3" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm px-3" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         2. REPORT FILTER BAR (Hero Panel)
         ========================================================= -->
    <div class="card mb-3 border-0 shadow-sm no-print" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
        <div class="card-body p-3">
            <form method="GET" action="<?= BASE_URL ?>/reports/index.php" id="reportForm">
                <div class="row g-2 align-items-end">
                    <!-- Tipo de Reporte -->
                    <div class="col-12 col-md-3">
                        <label for="type" class="form-label small fw-semibold text-muted mb-1">Módulo de Reporte</label>
                        <select class="form-select form-select-sm" id="type" name="type" onchange="this.form.submit()">
                            <option value="dashboard" <?= $reportType === 'dashboard' ? 'selected' : '' ?>>📊 Dashboard General</option>
                            <option value="quotations" <?= $reportType === 'quotations' ? 'selected' : '' ?>>📄 Cotizaciones Emitidas</option>
                            <option value="customers" <?= $reportType === 'customers' ? 'selected' : '' ?>>👥 Análisis de Clientes</option>
                            <option value="products" <?= $reportType === 'products' ? 'selected' : '' ?>>📦 Productos y Stock</option>
                        </select>
                    </div>

                    <!-- Vendedor (Admin only) -->
                    <?php if ($isAdmin): ?>
                    <div class="col-12 col-md-3">
                        <label for="seller_id" class="form-label small fw-semibold text-muted mb-1">Vendedor Asignado</label>
                        <select class="form-select form-select-sm" id="seller_id" name="seller_id" onchange="this.form.submit()">
                            <option value="">-- Todos los vendedores --</option>
                            <?php foreach ($sellers as $seller): ?>
                                <?php
                                $sName = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? ''));
                                if (empty($sName)) $sName = $seller['username'];
                                ?>
                                <option value="<?= $seller['id'] ?>" <?= $sellerId == $seller['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Rango de Fechas -->
                    <div class="col-6 col-md-2">
                        <label for="start_date" class="form-label small fw-semibold text-muted mb-1">Fecha Desde</label>
                        <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="end_date" class="form-label small fw-semibold text-muted mb-1">Fecha Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                    </div>

                    <div class="col-12 col-md-<?= $isAdmin ? '2' : '5' ?> d-flex gap-1.5">
                        <button type="submit" class="btn btn-warning btn-sm fw-bold text-dark w-100">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>

                <!-- Date Range Quick Presets -->
                <div class="d-flex align-items-center gap-1.5 flex-wrap mt-2 pt-2 border-top">
                    <span class="text-muted small me-1" style="font-size: 0.72rem;">Atajos de Fecha:</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5" style="font-size: 0.72rem;" onclick="setRangePreset('this_month')">
                        Este Mes
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5" style="font-size: 0.72rem;" onclick="setRangePreset('last_month')">
                        Mes Anterior
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5" style="font-size: 0.72rem;" onclick="setRangePreset('last_30')">
                        Últimos 30 días
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-2 py-0.5" style="font-size: 0.72rem;" onclick="setRangePreset('this_year')">
                        Este Año
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         3. REPORT CONTENT
         ========================================================= -->
    <div class="report-content">
        <?php if ($reportType === 'dashboard'): ?>
            <?php include __DIR__ . '/dashboard_report.php'; ?>
        <?php elseif ($reportType === 'quotations'): ?>
            <?php include __DIR__ . '/quotations_report.php'; ?>
        <?php elseif ($reportType === 'customers'): ?>
            <?php include __DIR__ . '/customers_report.php'; ?>
        <?php elseif ($reportType === 'products'): ?>
            <?php include __DIR__ . '/products_report.php'; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.location.href = '<?= BASE_URL ?>/reports/export.php?' + params.toString();
}

function setRangePreset(preset) {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const now = new Date();

    if (preset === 'this_month') {
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
        startInput.value = `${y}-${m}-01`;
        endInput.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
    } else if (preset === 'last_month') {
        const prevMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const y = prevMonth.getFullYear();
        const m = String(prevMonth.getMonth() + 1).padStart(2, '0');
        const lastDay = new Date(y, prevMonth.getMonth() + 1, 0).getDate();
        startInput.value = `${y}-${m}-01`;
        endInput.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
    } else if (preset === 'last_30') {
        const prior30 = new Date();
        prior30.setDate(prior30.getDate() - 30);
        startInput.value = prior30.toISOString().slice(0, 10);
        endInput.value = now.toISOString().slice(0, 10);
    } else if (preset === 'this_year') {
        const y = now.getFullYear();
        startInput.value = `${y}-01-01`;
        endInput.value = now.toISOString().slice(0, 10);
    }

    document.getElementById('reportForm').submit();
}
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
