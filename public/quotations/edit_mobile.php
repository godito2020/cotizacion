<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Get quotation ID
$quotationId = $_GET['id'] ?? '';
if (empty($quotationId) || !is_numeric($quotationId)) {
    $_SESSION['error'] = 'ID de cotización inválido';
    header('Location: ' . BASE_URL . '/quotations/index_mobile.php');
    exit;
}

// Initialize repositories
$quotationRepo = new Quotation();
$customerRepo = new Customer();
$productRepo = new Product();

// Get quotation data
$quotation = $quotationRepo->getById((int)$quotationId, $companyId);
if (!$quotation) {
    $_SESSION['error'] = 'Cotización no encontrada';
    header('Location: ' . BASE_URL . '/quotations/index_mobile.php');
    exit;
}

// Get quotation items from the quotation data
$quotationItems = $quotation['items'] ?? [];

// Get customers for dropdown
$customers = $customerRepo->getAllByCompany($companyId);

// Get products with warehouse stock information
$db = getDBConnection();
$stmt = $db->prepare("
    SELECT
        p.id,
        p.code,
        p.name,
        p.description,
        p.regular_price,
        p.price_currency,
        p.image_url,
        GROUP_CONCAT(
            CONCAT(pws.warehouse_name, ':', COALESCE(pws.stock_quantity, 0))
            SEPARATOR '|'
        ) as warehouse_stocks
    FROM products p
    LEFT JOIN product_warehouse_stock pws ON p.id = pws.product_id AND pws.company_id = ?
    WHERE p.company_id = ?
    GROUP BY p.id
    ORDER BY p.name
");
$stmt->execute([$companyId, $companyId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process warehouse stocks into array
foreach ($products as &$product) {
    $warehouses = [];
    if (!empty($product['warehouse_stocks'])) {
        $warehouseData = explode('|', $product['warehouse_stocks']);
        foreach ($warehouseData as $data) {
            if (strpos($data, ':') !== false) {
                list($warehouse, $stock) = explode(':', $data, 2);
                $warehouses[$warehouse] = (float)$stock;
            }
        }
    }
    $product['warehouses'] = $warehouses;
    unset($product['warehouse_stocks']);
}

// Get warehouses from desc_almacen (COBOL integration)
$stockRepo = new Stock();
$warehousesList = $stockRepo->getWarehouses();
$warehousesData = array_column($warehousesList, 'nombre');

// Almacén asignado al vendedor: preselecciona el filtro de stock (sin restringir otros).
$assignedWarehouseName = '';
try {
    $awStmt = $db->prepare("SELECT d.nombre FROM users u
                            JOIN desc_almacen d ON d.numero_almacen = u.default_warehouse
                            WHERE u.id = ? AND d.activo = 1");
    $awStmt->execute([$auth->getUserId()]);
    $assignedWarehouseName = (string)($awStmt->fetchColumn() ?: '');
} catch (Exception $e) {
    $assignedWarehouseName = '';
}

// Get company settings
$companySettings = new CompanySettings();
$exchangeRate = $companySettings->getSetting($companyId, 'exchange_rate_usd_pen');
$exchangeRate = !empty($exchangeRate) ? floatval($exchangeRate) : 3.80;

$enableDiscounts = $companySettings->getSetting($companyId, 'enable_discounts');
$enableDiscounts = ($enableDiscounts === '1' || $enableDiscounts === 1 || $enableDiscounts === true);

$pageTitle = 'Editar Cotización #' . $quotation['quotation_number'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#09090B">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <script>
        (function () {
            var mode = 'system';
            try {
                var saved = localStorage.getItem('coti-theme');
                if (saved === 'light' || saved === 'dark') mode = saved;
            } catch (e) {}
            var theme = mode === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    <!-- Typography: Satoshi, DM Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@700,900&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --color-canvas: #09090B;
            --color-panel: #18181B;
            --color-panel-card: #202024;
            --color-rule: #27272A;
            --color-rule-strong: #3F3F46;
            --color-ink: #FAFAFA;
            --color-ink-muted: #A1A1AA;
            --color-ink-subtle: #71717A;
            --color-amber: #F59E0B;
            --color-amber-dim: rgba(245, 158, 11, 0.12);
            --primary-color: #F59E0B;
            --danger-color: #EF4444;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
            --gray-100: #18181B;
            --gray-200: #27272A;
        }

        [data-bs-theme="light"] {
            --color-canvas: #F4F4F5;
            --color-panel: #FFFFFF;
            --color-panel-card: #FAFAFA;
            --color-rule: #E4E4E7;
            --color-rule-strong: #D4D4D8;
            --color-ink: #09090B;
            --color-ink-muted: #71717A;
            --color-ink-subtle: #A1A1AA;
            --color-amber: #D97706;
            --color-amber-dim: rgba(217, 119, 6, 0.1);
            --primary-color: #D97706;
            --danger-color: #DC2626;
            --success-color: #16A34A;
            --warning-color: #D97706;
            --info-color: #2563EB;
            --gray-100: #F4F4F5;
            --gray-200: #E4E4E7;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--color-canvas) !important;
            color: var(--color-ink) !important;
            padding-bottom: calc(140px + env(safe-area-inset-bottom, 0px));
            font-size: 15px;
            overscroll-behavior: contain;
            margin: 0;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace !important;
        }

        .container-mobile {
            padding: 14px;
            background-color: var(--color-canvas);
        }

        /* Modern Sticky Header */
        .mobile-header {
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: var(--color-panel) !important;
            border-bottom: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
            padding: 12px 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .mobile-header h1 {
            font-size: 1.05rem;
            margin: 0;
            font-weight: 700;
            color: var(--color-ink) !important;
            letter-spacing: -0.01em;
        }

        .mobile-header .back-btn {
            color: var(--color-ink) !important;
            text-decoration: none;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--color-rule);
            background-color: var(--color-panel-card);
            margin-right: 12px;
            transition: all 0.15s ease;
        }
        .mobile-header .back-btn:active {
            opacity: 0.7;
            transform: scale(0.95);
        }

        /* Form Sections */
        .form-section {
            background-color: var(--color-panel) !important;
            border: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
            margin: 12px 10px;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            font-family: var(--font-display, 'Satoshi', sans-serif);
            color: var(--color-ink) !important;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--color-rule) !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title i {
            color: var(--color-amber) !important;
        }

        /* Form Controls */
        .form-label {
            font-size: 0.8rem !important;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--color-ink-muted) !important;
            display: block;
        }

        .form-control, .form-select {
            font-size: 15px !important;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--color-rule) !important;
            background-color: var(--color-canvas) !important;
            color: var(--color-ink) !important;
            min-height: 46px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--color-amber) !important;
            box-shadow: 0 0 0 3px var(--color-amber-dim) !important;
            background-color: var(--color-canvas) !important;
            color: var(--color-ink) !important;
        }

        .form-control::placeholder, textarea.form-control::placeholder {
            color: var(--color-ink-subtle) !important;
            opacity: 0.7;
        }

        /* Product Item Card */
        .product-item {
            background-color: var(--color-panel) !important;
            border: 1px solid var(--color-rule) !important;
            border-left: 4px solid var(--color-amber) !important;
            color: var(--color-ink) !important;
            margin: 10px;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .product-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--color-rule) !important;
        }

        .product-number {
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            color: var(--color-amber) !important;
            font-size: 0.95rem;
        }

        .product-item .mb-3 {
            margin-bottom: 12px !important;
        }

        /* Buttons */
        .btn {
            font-size: 15px;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
            border: none !important;
            color: #09090B !important;
            font-weight: 700;
        }

        .btn-warning {
            background-color: var(--color-amber) !important;
            border-color: var(--color-amber) !important;
            color: #09090B !important;
            font-weight: 700;
        }

        .btn-danger {
            padding: 8px 14px;
            min-height: 38px;
        }

        .btn-block-mobile {
            width: 100%;
            margin-bottom: 10px;
        }

        /* Floating Action Buttons */
        .fab-container {
            position: fixed;
            bottom: 95px;
            right: 20px;
            z-index: 1030;
        }

        .fab {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
            color: #09090B !important;
            border: none;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.4);
            font-size: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .fab:active {
            transform: scale(0.92);
            color: #09090B;
        }

        .fab.primary {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
        }

        /* Bottom Fixed Action Bar */
        .bottom-bar {
            position: fixed;
            bottom: calc(60px + env(safe-area-inset-bottom, 0px));
            left: 0;
            right: 0;
            background-color: var(--color-panel) !important;
            border-top: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
            padding: 10px 14px;
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.35);
            z-index: 1025;
            display: flex;
            gap: 10px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .bottom-bar .btn {
            flex: 1;
        }

        /* Totals Display */
        .totals-card {
            background-color: var(--color-panel) !important;
            border: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
            border-radius: 12px;
            padding: 16px;
            margin: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 0.88rem;
            color: var(--color-ink-muted);
        }

        .total-row span:last-child {
            color: var(--color-ink);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
        }

        .total-row.grand-total {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-ink) !important;
            border-top: 1px solid var(--color-rule) !important;
            padding-top: 12px;
            margin-top: 8px;
        }

        .total-row.grand-total span:last-child {
            color: var(--color-amber) !important;
            font-size: 1.35rem;
            font-weight: 800;
        }

        /* Modals */
        .modal-content {
            border-radius: 12px;
            background-color: var(--color-panel) !important;
            border: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
        }

        .modal-header {
            background-color: var(--color-panel-card) !important;
            border-bottom: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
            border-radius: 12px 12px 0 0;
        }

        .modal-header .modal-title {
            color: var(--color-ink) !important;
            font-weight: 700;
        }

        .modal-body {
            background-color: var(--color-panel) !important;
            color: var(--color-ink) !important;
        }

        .modal-footer {
            background-color: var(--color-panel-card) !important;
            border-top: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--color-ink-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.4;
            color: var(--color-amber);
        }

        /* Product Search Results in Modal */
        .product-search-result {
            padding: 12px;
            border-bottom: 1px solid var(--color-rule);
            background-color: var(--color-panel);
            color: var(--color-ink);
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .product-search-result:hover,
        .product-search-result:active {
            background-color: var(--color-panel-card) !important;
        }

        .product-search-result:last-child {
            border-bottom: none;
        }

        .product-search-result strong {
            color: var(--color-amber);
            font-family: 'JetBrains Mono', monospace;
        }

        /* Input Group */
        .input-group-text {
            min-width: 46px;
            justify-content: center;
            background-color: var(--color-panel-card) !important;
            border-color: var(--color-rule) !important;
            color: var(--color-ink-muted) !important;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            background-color: var(--color-panel) !important;
            border: 1px solid var(--color-rule) !important;
            color: var(--color-ink) !important;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .form-section {
                margin: 10px 8px;
                padding: 14px;
            }

            .product-item {
                margin: 10px 8px;
                padding: 14px;
            }
        }
    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-warning" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3 mb-0">Guardando cambios...</p>
        </div>
    </div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <a href="<?= BASE_URL ?>/quotations/view_mobile.php?id=<?= $quotationId ?>" class="back-btn" title="Volver a la Cotización">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="mb-0">Editar #<?= htmlspecialchars($quotation['quotation_number']) ?></h1>
                    <span class="text-muted small" style="font-size: 0.68rem;"><?= htmlspecialchars($quotation['customer_name'] ?? 'Cliente') ?> &bull; <?= htmlspecialchars($quotation['status'] ?? 'Draft') ?></span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 mobile-theme-toggle"
                        data-theme-toggle title="Cambiar tema (Sistema/Claro/Oscuro)">
                     <i class="fas fa-circle-half-stroke"></i>
                </button>
                <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $quotationId ?>&desktop=1" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Vista Escritorio">
                    <i class="fas fa-desktop"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Form -->
    <form id="quotationForm" method="POST" action="<?= BASE_URL ?>/api/save_quotation.php">
        <input type="hidden" name="quotation_id" value="<?= $quotationId ?>">

        <!-- Customer Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-user"></i> Cliente
            </div>
            <div class="mb-3">
                <label class="form-label">Cliente *</label>
                <select class="form-select" id="customer_id" name="customer_id" required>
                    <option value="">Seleccionar cliente...</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['id'] ?>" <?= $customer['id'] == $quotation['customer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['name']) ?> - <?= htmlspecialchars($customer['tax_id']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="customerReferenceContainer" class="mb-3" style="<?= (!empty($quotation['customer_reference']) || (isset($quotation['customer_name']) && stripos($quotation['customer_name'], 'VARIOS') !== false)) ? '' : 'display: none;' ?>">
                <label for="customer_reference" class="form-label">Identificación del cliente *</label>
                <input type="text"
                       class="form-control"
                       id="customer_reference"
                       name="customer_reference"
                       maxlength="100"
                       value="<?= htmlspecialchars($quotation['customer_reference'] ?? '') ?>"
                       placeholder="Ingrese el nombre del cliente">
                <div class="form-text text-muted" style="font-size: 0.75rem;">Máximo 100 caracteres.</div>
            </div>

            <button type="button" class="btn btn-outline-primary btn-block-mobile" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                <i class="fas fa-plus"></i> Nuevo Cliente
            </button>
        </div>

        <!-- Quotation Details -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-file-alt"></i> Datos de Cotización
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Fecha *</label>
                    <input type="date" class="form-control" name="quotation_date" value="<?= htmlspecialchars($quotation['quotation_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Válido Hasta</label>
                    <input type="date" class="form-control" name="valid_until" value="<?= htmlspecialchars($quotation['valid_until'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Moneda *</label>
                    <select class="form-select" id="currency" name="currency" required>
                        <option value="PEN" <?= ($quotation['currency'] ?? 'PEN') === 'PEN' ? 'selected' : '' ?>>PEN (Soles)</option>
                        <option value="USD" <?= ($quotation['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD (Dólares)</option>
                    </select>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">IGV</label>
                    <select class="form-select" id="price_includes_igv" name="price_includes_igv" onchange="updateIgvDisplay()">
                        <option value="included" <?= ($quotation['igv_mode'] ?? 'included') === 'included' ? 'selected' : '' ?>>INCLUIDO IGV</option>
                        <option value="plus_igv" <?= ($quotation['igv_mode'] ?? '') === 'plus_igv' ? 'selected' : '' ?>>SIN IGV</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Condición de Pago *</label>
                    <select class="form-select" id="payment_condition" name="payment_condition" required onchange="toggleCreditDays()">
                        <option value="cash" <?= ($quotation['payment_condition'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Efectivo / Contado</option>
                        <option value="credit" <?= ($quotation['payment_condition'] ?? '') === 'credit' ? 'selected' : '' ?>>Crédito</option>
                    </select>
                </div>
                <div class="col-6 mb-3" id="credit_days_container" style="<?= ($quotation['payment_condition'] ?? 'cash') === 'credit' ? '' : 'display: none;' ?>">
                    <label class="form-label">Plazo de Crédito</label>
                    <select class="form-select" id="credit_days" name="credit_days">
                        <option value="">Seleccionar...</option>
                        <option value="30" <?= ($quotation['credit_days'] ?? '') == 30 ? 'selected' : '' ?>>30 días</option>
                        <option value="60" <?= ($quotation['credit_days'] ?? '') == 60 ? 'selected' : '' ?>>60 días</option>
                        <option value="90" <?= ($quotation['credit_days'] ?? '') == 90 ? 'selected' : '' ?>>90 días</option>
                        <option value="120" <?= ($quotation['credit_days'] ?? '') == 120 ? 'selected' : '' ?>>120 días</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-box"></i> Productos
            </div>
            <div id="productsContainer">
                <div class="empty-state" id="emptyState">
                    <i class="fas fa-inbox"></i>
                    <p>No hay productos agregados</p>
                    <p class="small text-muted">Usa el botón + para agregar productos</p>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="totals-card">
            <div class="total-row">
                <span>Subtotal:</span>
                <span id="subtotalDisplay">S/ 0.00</span>
            </div>
            <?php if ($enableDiscounts): ?>
            <div class="total-row">
                <span>Descuento:</span>
                <span id="discountDisplay">S/ 0.00</span>
            </div>
            <?php endif; ?>
            <div class="total-row" id="igvRow" style="display: none;">
                <span>IGV (18%):</span>
                <span id="igvDisplay">S/ 0.00</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span id="totalDisplay">S/ 0.00</span>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="form-section">
            <div class="section-title" data-bs-toggle="collapse" data-bs-target="#notesCollapse" aria-expanded="false" aria-controls="notesCollapse" style="cursor: pointer;">
                <i class="fas fa-sticky-note"></i> Notas y Observaciones
                <i class="fas fa-chevron-down float-end ms-auto"></i>
            </div>
            <div class="collapse" id="notesCollapse">
                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="Notas adicionales..."><?= htmlspecialchars($quotation['notes'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Términos y Condiciones</label>
                    <textarea class="form-control" name="terms_and_conditions" rows="5" placeholder="Términos y condiciones..."><?= htmlspecialchars($quotation['terms_and_conditions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </form>

    <!-- Floating Action Button -->
    <div class="fab-container">
        <button type="button" class="fab primary" onclick="addProduct()" title="Agregar Producto">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Bottom Bar -->
    <div class="bottom-bar">
        <button type="button" class="btn btn-outline-secondary" onclick="confirmCancel()">
            <i class="fas fa-times"></i> Cancelar
        </button>
        <button type="button" class="btn btn-warning" onclick="generateCotiRapi()">
            <i class="fas fa-bolt"></i> CotiRapi
        </button>
        <button type="submit" form="quotationForm" class="btn btn-primary">
            <i class="fas fa-save"></i> Guardar
        </button>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newCustomerForm">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Documento</label>
                            <select class="form-select" id="document_type">
                                <option value="ruc">RUC</option>
                                <option value="dni">DNI</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Número de Documento *</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="document_number" placeholder="Ingrese RUC o DNI" required>
                                <button type="button" class="btn btn-primary" id="search_document_btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="customer_data" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Nombre/Razón Social *</label>
                                <input type="text" class="form-control" id="customer_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="customer_email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="customer_phone">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" id="customer_address" rows="2"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="save_customer_btn" disabled>Crear Cliente</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Nuevo Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm">
                        <input type="hidden" id="product_index">
                        <div class="mb-3">
                            <label class="form-label">Buscar por Código o Descripción</label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="product_search" placeholder="Código o descripción...">
                                <select class="form-select" id="warehouse_filter" style="max-width: 150px;">
                                    <option value="">All almacenes</option>
                                </select>
                            </div>
                            <div id="searchResults" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción *</label>
                            <textarea class="form-control" id="product_description" rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Cantidad *</label>
                                <input type="number" class="form-control" id="product_quantity" value="1" step="1" min="1" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Precio Unit. *</label>
                                <input type="number" class="form-control" id="product_price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Descuento (%)</label>
                                <input type="number" class="form-control" id="product_discount" value="0" step="0.01" min="0" max="100">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Precio Final</label>
                                <input type="text" class="form-control font-mono" id="product_final_price" readonly style="font-weight: 700; color: var(--color-amber); background-color: var(--color-canvas); border-color: var(--color-rule);">
                            </div>
                        </div>
                        <div class="mb-3" id="product_image_container" style="display: none;">
                            <button type="button" class="btn btn-sm btn-outline-info w-100" onclick="viewProductImage()">
                                <i class="fas fa-image"></i> Ver Imagen del Producto
                            </button>
                        </div>
                        <div class="mb-3" id="product_ficha_container" style="display: none;">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="viewFichasTecnicas(document.getElementById('productForm').dataset.selectedProductId)">
                                <i class="fas fa-file-alt"></i> Ver Ficha Técnica
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveProductBtn">Guardar Item</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="modal fade" id="imageViewerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-image"></i> Imagen del Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="productImageViewer" src="" alt="Imagen del producto" class="img-fluid" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </div>

    <!-- CotiRapi Modal -->
    <div class="modal fade" id="cotiRapiModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning">
                        <i class="fas fa-bolt text-warning"></i> CotiRapi - Cotización Rápida
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Copia este texto</strong> y pégalo directamente en WhatsApp para enviar una cotización rápida a tu cliente.
                    </div>
                    <textarea id="cotiRapiText" class="form-control font-mono" rows="18" style="font-size: 0.85rem;" readonly></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" id="copyCotiRapiBtn" onclick="copyCotiRapi()">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                    <button type="button" class="btn btn-success" onclick="sendCotiRapiWhatsApp()">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const enableDiscounts = <?= $enableDiscounts ? 'true' : 'false' ?>;
        const exchangeRate = <?= $exchangeRate ?>;
        const products = <?= json_encode($products) ?>;
        const warehouses = <?= json_encode($warehousesData) ?>;
        const ASSIGNED_WAREHOUSE = <?= json_encode($assignedWarehouseName) ?>;
        const customers = <?= json_encode($customers) ?>;

        let productItems = <?= json_encode(array_map(function($item) use ($quotation) {
            return [
                'description' => $item['description'],
                'quantity' => (float)$item['quantity'],
                'price' => (float)$item['unit_price'],
                'discount' => (float)($item['discount_percentage'] ?? 0),
                'product_id' => $item['product_id'],
                'image_url' => $item['image_url'] ?? null,
                'originalPrice' => (float)$item['unit_price'],
                'productCurrency' => $quotation['currency'] ?? 'USD'
            ];
        }, $quotationItems)) ?>;

        let currentEditIndex = null;
        let productSearchTimeout;
        let currentQuotationCurrency = document.getElementById('currency')?.value || '<?= $quotation['currency'] ?? 'PEN' ?>';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initCustomerModal();
            initProductSearch();
            initWarehouseFilter();

            // Render existing products
            renderProducts();
            calculateTotals();

            // Customer selection change listener
            const customerSelect = document.getElementById('customer_id');
            if (customerSelect) {
                customerSelect.addEventListener('change', function() {
                    const text = this.options[this.selectedIndex]?.text || '';
                    const refContainer = document.getElementById('customerReferenceContainer');
                    if (refContainer) {
                        if (text.toUpperCase().includes('CLIENTES VARIOS')) {
                            refContainer.style.display = 'block';
                        } else {
                            refContainer.style.display = 'none';
                        }
                    }
                });
            }

            // Form submission
            document.getElementById('quotationForm').addEventListener('submit', handleSubmit);

            // Currency change
            document.getElementById('currency').addEventListener('change', convertAllPrices);

            // Product form - prevent default submission
            document.getElementById('productForm').addEventListener('submit', function(e) {
                e.preventDefault();
                return false;
            });

            // Save product button
            document.getElementById('saveProductBtn').addEventListener('click', function(e) {
                e.preventDefault();
                saveProduct();
            });

            // Calculate final price when price or discount changes
            document.getElementById('product_price').addEventListener('input', calculateFinalPrice);
            document.getElementById('product_discount').addEventListener('input', calculateFinalPrice);
        });

        // Calculate and display final price with discount
        function calculateFinalPrice() {
            const price = parseFloat(document.getElementById('product_price').value) || 0;
            const discount = parseFloat(document.getElementById('product_discount').value) || 0;
            const finalPrice = price - (price * (discount / 100));
            const currency = document.getElementById('currency').value;
            const symbol = currency === 'USD' ? '$' : 'S/';

            document.getElementById('product_final_price').value = `${symbol} ${finalPrice.toFixed(2)}`;
        }

        // Add Product
        function addProduct() {
            currentEditIndex = null;
            document.getElementById('productModalTitle').textContent = 'Nuevo Item';

            const form = document.getElementById('productForm');
            form.reset();

            // Clear product selection from dataset
            form.dataset.selectedProductId = '';
            form.dataset.selectedImageUrl = '';
            form.dataset.originalPrice = '';
            form.dataset.productCurrency = '';

            // Set default values
            document.getElementById('product_quantity').value = '1';
            document.getElementById('product_discount').value = '0';
            document.getElementById('product_final_price').value = '';

            // Hide image and ficha buttons
            document.getElementById('product_image_container').style.display = 'none';
            document.getElementById('product_ficha_container').style.display = 'none';

            document.getElementById('searchResults').innerHTML = '';
            new bootstrap.Modal(document.getElementById('productModal')).show();
        }

        // Edit Product
        function editProduct(index) {
            currentEditIndex = index;
            const item = productItems[index];

            document.getElementById('productModalTitle').textContent = 'Editar Item';
            document.getElementById('product_description').value = item.description;
            document.getElementById('product_quantity').value = item.quantity;
            document.getElementById('product_price').value = item.price;
            document.getElementById('product_discount').value = item.discount || 0;

            const form = document.getElementById('productForm');
            form.dataset.selectedProductId = item.product_id || '';
            form.dataset.selectedImageUrl = item.image_url || '';
            form.dataset.originalPrice = item.originalPrice || '';
            form.dataset.productCurrency = item.productCurrency || '';

            const imageContainer = document.getElementById('product_image_container');
            if (item.image_url) {
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            document.getElementById('product_ficha_container').style.display =
                item.product_id ? 'block' : 'none';

            calculateFinalPrice();
            new bootstrap.Modal(document.getElementById('productModal')).show();
        }

        // Save Product
        function saveProduct() {
            const description = document.getElementById('product_description').value.trim();
            const quantity = parseFloat(document.getElementById('product_quantity').value);
            const price = parseFloat(document.getElementById('product_price').value);

            const discountInput = document.getElementById('product_discount');
            const discount = discountInput ? parseFloat(discountInput.value) || 0 : 0;

            if (!description || !quantity || isNaN(quantity) || isNaN(price)) {
                alert('Por favor complete todos los campos requeridos correctamente');
                return;
            }

            const form = document.getElementById('productForm');
            const productId = form.dataset.selectedProductId || null;
            const imageUrl = form.dataset.selectedImageUrl || null;
            const originalPrice = parseFloat(form.dataset.originalPrice) || price;
            const productCurrency = form.dataset.productCurrency || document.getElementById('currency').value;

            const item = {
                description,
                quantity,
                price,
                discount: discount || 0,
                product_id: productId,
                image_url: imageUrl,
                originalPrice: originalPrice,
                productCurrency: productCurrency
            };

            if (currentEditIndex !== null) {
                productItems[currentEditIndex] = item;
            } else {
                productItems.push(item);
            }

            renderProducts();
            calculateTotals();

            form.dataset.selectedProductId = '';
            form.dataset.selectedImageUrl = '';
            form.dataset.originalPrice = '';
            form.dataset.productCurrency = '';
            currentEditIndex = null;

            const modalEl = document.getElementById('productModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        }

        // Delete Product
        function deleteProduct(index) {
            if (confirm('¿Eliminar este item?')) {
                productItems.splice(index, 1);
                renderProducts();
                calculateTotals();
            }
        }

        // Render Products
        function renderProducts() {
            const container = document.getElementById('productsContainer');
            if (!container) return;

            container.querySelectorAll('.product-item').forEach(el => el.remove());

            let emptyState = document.getElementById('emptyState');
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.id = 'emptyState';
                emptyState.innerHTML = `
                    <i class="fas fa-inbox"></i>
                    <p>No hay productos agregados</p>
                    <p class="small text-muted">Usa el botón + para agregar productos</p>
                `;
                container.insertBefore(emptyState, container.firstChild);
            }

            if (productItems.length === 0) {
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';

            productItems.forEach((item, index) => {
                const subtotal = item.quantity * item.price;
                const discountAmount = subtotal * (item.discount / 100);
                const total = subtotal - discountAmount;
                const currency = document.getElementById('currency').value;
                const symbol = currency === 'USD' ? '$' : 'S/';

                const imageButton = item.image_url ?
                    `<button type="button" class="btn btn-sm btn-outline-info ms-1" onclick="viewItemImage('${item.image_url}')" title="Ver imagen">
                        <i class="fas fa-image"></i>
                    </button>` : '';
                const fichaBtn = item.product_id ?
                    `<button type="button" class="btn btn-sm btn-outline-secondary ms-1" onclick="viewFichasTecnicas('${item.product_id}')" title="Ver Ficha Técnica">
                        <i class="fas fa-file-alt"></i>
                    </button>` : '';

                const html = `
                    <div class="product-item">
                        <div class="product-item-header">
                            <span class="product-number">Item #${index + 1}</span>
                            <div>
                                ${imageButton}
                                ${fichaBtn}
                                <button type="button" class="btn btn-sm btn-outline-primary ms-1" onclick="editProduct(${index})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger ms-1" onclick="deleteProduct(${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-2">
                            <strong style="font-size: 0.82rem; color: var(--color-ink-muted);">Descripción:</strong><br>
                            <span class="text-body fw-semibold">${item.description}</span>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted" style="font-size: 0.75rem;">Cantidad</small><br>
                                <strong class="font-mono">${item.quantity}</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted" style="font-size: 0.75rem;">Precio Unit.</small><br>
                                <strong class="font-mono">${symbol} ${item.price.toFixed(2)}</strong>
                                ${item.discount > 0 ? `<br><small class="text-success font-mono" style="font-size: 0.72rem;">${symbol} ${(item.price - (item.price * item.discount / 100)).toFixed(2)} c/desc.</small>` : ''}
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted" style="font-size: 0.75rem;">Total</small><br>
                                <strong class="text-warning font-mono">${symbol} ${total.toFixed(2)}</strong>
                            </div>
                        </div>
                        ${item.discount > 0 ? `<div class="mt-2"><small class="badge bg-success">Descuento: ${item.discount}%</small></div>` : ''}
                    </div>
                `;

                container.insertAdjacentHTML('beforeend', html);
            });
        }

        // Calculate Totals
        function calculateTotals() {
            const currency = document.getElementById('currency').value;
            const symbol = currency === 'USD' ? '$' : 'S/';
            const igvOption = document.getElementById('price_includes_igv').value;

            let subtotal = 0;
            let totalDiscount = 0;

            productItems.forEach(item => {
                const itemSubtotal = item.quantity * item.price;
                const itemDiscount = itemSubtotal * (item.discount / 100);
                subtotal += itemSubtotal;
                totalDiscount += itemDiscount;
            });

            const baseTotal = subtotal - totalDiscount;
            let igvAmount = 0;
            let finalTotal = baseTotal;

            if (igvOption === 'plus_igv') {
                igvAmount = baseTotal * 0.18;
                finalTotal = baseTotal + igvAmount;
            }

            document.getElementById('subtotalDisplay').textContent = `${symbol} ${subtotal.toFixed(2)}`;
            const discountDisplay = document.getElementById('discountDisplay');
            if (discountDisplay) {
                discountDisplay.textContent = `${symbol} ${totalDiscount.toFixed(2)}`;
            }

            const igvRow = document.getElementById('igvRow');
            const igvDisplay = document.getElementById('igvDisplay');
            if (igvOption === 'plus_igv') {
                if (igvRow) igvRow.style.display = '';
                if (igvDisplay) igvDisplay.textContent = `${symbol} ${igvAmount.toFixed(2)}`;
            } else {
                if (igvRow) igvRow.style.display = 'none';
            }

            document.getElementById('totalDisplay').textContent = `${symbol} ${finalTotal.toFixed(2)}`;
        }

        function updateIgvDisplay() {
            calculateTotals();
        }

        function toggleCreditDays() {
            const paymentCondition = document.getElementById('payment_condition').value;
            const creditContainer = document.getElementById('credit_days_container');
            const creditDaysSelect = document.getElementById('credit_days');

            if (paymentCondition === 'credit') {
                creditContainer.style.display = 'block';
                creditDaysSelect.required = true;
            } else {
                creditContainer.style.display = 'none';
                creditDaysSelect.required = false;
                creditDaysSelect.value = '';
            }
        }

        function convertAllPrices() {
            const newCurrency = document.getElementById('currency').value;
            const previousCurrency = currentQuotationCurrency;

            if (newCurrency === previousCurrency) {
                calculateTotals();
                return;
            }

            productItems.forEach(item => {
                const currentPrice = parseFloat(item.price) || 0;
                if (currentPrice > 0) {
                    let newPrice = currentPrice;
                    if (item.originalPrice && item.productCurrency) {
                        newPrice = item.originalPrice;
                        if (newCurrency !== item.productCurrency) {
                            if (item.productCurrency === 'USD' && newCurrency === 'PEN') {
                                newPrice = item.originalPrice * parseFloat(exchangeRate);
                            } else if (item.productCurrency === 'PEN' && newCurrency === 'USD') {
                                newPrice = item.originalPrice / parseFloat(exchangeRate);
                            }
                        }
                    } else {
                        if (previousCurrency === 'USD' && newCurrency === 'PEN') {
                            newPrice = currentPrice * parseFloat(exchangeRate);
                        } else if (previousCurrency === 'PEN' && newCurrency === 'USD') {
                            newPrice = currentPrice / parseFloat(exchangeRate);
                        }
                    }
                    item.price = parseFloat(newPrice.toFixed(2));
                }
            });

            currentQuotationCurrency = newCurrency;
            renderProducts();
            calculateTotals();
        }

        // Product Search
        function initProductSearch() {
            const searchInput = document.getElementById('product_search');
            const warehouseFilter = document.getElementById('warehouse_filter');

            searchInput.addEventListener('input', function() {
                clearTimeout(productSearchTimeout);
                const term = this.value.trim();

                if (term.length < 2) {
                    document.getElementById('searchResults').innerHTML = '';
                    return;
                }

                productSearchTimeout = setTimeout(() => {
                    searchProducts(term);
                }, 300);
            });

            warehouseFilter.addEventListener('change', function() {
                const term = searchInput.value.trim();
                if (term.length >= 2) {
                    searchProducts(term);
                }
            });
        }

        function initWarehouseFilter() {
            const warehouseFilter = document.getElementById('warehouse_filter');
            warehouses.forEach(warehouse => {
                const option = document.createElement('option');
                option.value = warehouse;
                option.textContent = warehouse;
                warehouseFilter.appendChild(option);
            });

            if (typeof ASSIGNED_WAREHOUSE === 'string' && ASSIGNED_WAREHOUSE !== '') {
                for (let i = 0; i < warehouseFilter.options.length; i++) {
                    if (warehouseFilter.options[i].value === ASSIGNED_WAREHOUSE) {
                        warehouseFilter.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        function searchProducts(term) {
            const selectedWarehouse = document.getElementById('warehouse_filter').value;
            const resultsContainer = document.getElementById('searchResults');

            resultsContainer.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';

            fetch(`${BASE_URL}/api/search_products.php?search=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        resultsContainer.innerHTML = '<div class="text-muted small p-2">Error en la búsqueda</div>';
                        return;
                    }

                    let results = data.products || [];

                    if (selectedWarehouse) {
                        results = results.filter(p => {
                            return p.warehouses &&
                                   p.warehouses.hasOwnProperty(selectedWarehouse) &&
                                   parseFloat(p.warehouses[selectedWarehouse]) > 0;
                        });
                    }

                    results.sort((a, b) => {
                        const priceA = parseFloat(a.precio || a.regular_price) || 0;
                        const priceB = parseFloat(b.precio || b.regular_price) || 0;
                        return priceA - priceB;
                    });

                    if (results.length === 0) {
                        resultsContainer.innerHTML = '<div class="text-muted small p-2">No se encontraron productos con stock</div>';
                        return;
                    }

                    resultsContainer.innerHTML = results.map(p => {
                        let warehouseInfo = '';
                        if (p.warehouses && Object.keys(p.warehouses).length > 0) {
                            const warehousesWithStock = Object.entries(p.warehouses)
                                .filter(([name, stock]) => parseFloat(stock) > 0)
                                .map(([name, stock]) => `${name}: ${Math.floor(stock)}`)
                                .join(' | ');

                            if (warehousesWithStock) {
                                warehouseInfo = `<br><small class="text-success"><i class="fas fa-warehouse"></i> ${warehousesWithStock}</small>`;
                            }
                        }

                        const productJson = JSON.stringify(p).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const imageUrl = p.image_url || p.imagen_url || '';
                        const imageButton = imageUrl ?
                            `<button type="button" class="btn btn-sm btn-info py-0 px-2 ms-2 text-white" onclick="event.stopPropagation(); previewProductImage('${imageUrl}', '${(p.descripcion || p.name || '').replace(/'/g, "\\'")}')">
                                <i class="fas fa-image"></i> Ver
                            </button>` :
                            `<span class="badge bg-secondary ms-2 py-1"><i class="fas fa-image-slash"></i> Sin img</span>`;
                        const fichaButton = `<button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 ms-1" onclick="event.stopPropagation(); viewFichasTecnicas('${(p.codigo || p.code || '').replace(/'/g, "\\'")}');" title="Ver Ficha Técnica"><i class="fas fa-file-alt"></i></button>`;

                        return `
                            <div class="product-search-result" onclick='selectProductFromData(this)' data-product="${productJson}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${p.codigo || p.code}</strong>${imageButton}${fichaButton}<br>
                                        <span class="text-body fw-semibold">${p.descripcion || p.name}</span><br>
                                        <small class="text-warning fw-bold font-mono">$ ${parseFloat(p.precio || p.regular_price).toFixed(2)}</small>
                                        ${warehouseInfo}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultsContainer.innerHTML = '<div class="text-danger small p-2">Error al buscar productos</div>';
                });
        }

        function selectProductFromData(element) {
            const productJson = element.dataset.product.replace(/&quot;/g, '"');
            const product = JSON.parse(productJson);
            selectProduct(product);
        }

        function selectProduct(product) {
            const description = product.descripcion || product.name || product.description || '';
            document.getElementById('product_description').value = description;

            const originalPrice = parseFloat(product.precio || product.regular_price || 0);
            const quotationCurrency = document.getElementById('currency').value;
            const productCurrency = product.price_currency || 'USD';

            let displayPrice = originalPrice;
            if (quotationCurrency !== productCurrency) {
                if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                    displayPrice = originalPrice * exchangeRate;
                } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                    displayPrice = originalPrice / exchangeRate;
                }
            }

            document.getElementById('product_price').value = displayPrice.toFixed(2);

            const form = document.getElementById('productForm');
            form.dataset.selectedProductId = product.codigo || product.id || '';
            form.dataset.selectedImageUrl = product.image_url || product.imagen_url || '';
            form.dataset.originalPrice = originalPrice;
            form.dataset.productCurrency = productCurrency;

            const imageContainer = document.getElementById('product_image_container');
            const imageUrl = product.image_url || product.imagen_url;
            if (imageUrl) {
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }

            document.getElementById('product_ficha_container').style.display =
                (product.codigo || product.id) ? 'block' : 'none';

            calculateFinalPrice();
            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('product_search').value = '';
        }

        function viewProductImage() {
            const form = document.getElementById('productForm');
            const imageUrl = form.dataset.selectedImageUrl;

            if (imageUrl) {
                const finalUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + '/' + imageUrl;
                document.getElementById('productImageViewer').src = finalUrl;
                const imageModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
                imageModal.show();
            } else {
                alert('No hay imagen disponible para este producto');
            }
        }

        function viewItemImage(imageUrl) {
            if (imageUrl) {
                const finalUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + '/' + imageUrl;
                document.getElementById('productImageViewer').src = finalUrl;
                const imageModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
                imageModal.show();
            }
        }

        function previewProductImage(imageUrl, productName) {
            if (!imageUrl) {
                alert('No hay imagen disponible para este producto');
                return;
            }

            const finalUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + '/' + imageUrl;
            document.getElementById('productImageViewer').src = finalUrl;

            const modalTitle = document.querySelector('#imageViewerModal .modal-title');
            if (modalTitle) {
                modalTitle.innerHTML = '<i class="fas fa-image"></i> ' + (productName || 'Imagen del Producto');
            }

            const imageModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
            imageModal.show();
        }

        function viewFichasTecnicas(codigo) {
            if (!codigo) { alert('Selecciona un producto primero'); return; }
            let fichasModal = document.getElementById('fichasViewModal');
            if (!fichasModal) {
                document.body.insertAdjacentHTML('beforeend', `
                    <div class="modal fade" id="fichasViewModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header py-2">
                                    <h6 class="modal-title"><i class="fas fa-file-alt me-1"></i>Fichas Técnicas — <span id="fichasViewCodigo"></span></h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="fichasViewBody"></div>
                            </div>
                        </div>
                    </div>`);
                fichasModal = document.getElementById('fichasViewModal');
            }
            document.getElementById('fichasViewCodigo').textContent = codigo;
            const body = document.getElementById('fichasViewBody');
            body.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';
            new bootstrap.Modal(fichasModal).show();
            fetch(`${BASE_URL}/api/product_fichas.php?codigo=${encodeURIComponent(codigo)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.fichas.length > 0) {
                        body.innerHTML = data.fichas.map(f => {
                            const ext = f.ficha_url.split('.').pop().toLowerCase();
                            const icon = ext === 'pdf' ? 'fa-file-pdf text-danger' : 'fa-file-image text-info';
                            const nombre = f.nombre_archivo || ('Ficha ' + f.id);
                            return `<div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background: var(--color-panel-card);">
                                <i class="fas ${icon} fa-lg"></i>
                                <a href="${f.ficha_url}" target="_blank" class="text-decoration-none flex-grow-1 text-body">${nombre}</a>
                                <a href="${f.ficha_url}" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="fas fa-external-link-alt"></i></a>
                            </div>`;
                        }).join('');
                    } else {
                        body.innerHTML = '<p class="text-muted mb-0">No hay fichas técnicas registradas para este producto.</p>';
                    }
                })
                .catch(() => { body.innerHTML = '<p class="text-danger mb-0">Error al cargar fichas.</p>'; });
        }

        // Customer Modal Functions
        function initCustomerModal() {
            const modal = document.getElementById('newCustomerModal');
            const documentInput = modal.querySelector('#document_number');
            const searchBtn = modal.querySelector('#search_document_btn');
            const saveBtn = modal.querySelector('#save_customer_btn');

            documentInput.addEventListener('input', function() {
                const docLength = this.value.trim().length;
                const documentTypeSelect = modal.querySelector('#document_type');

                if (docLength === 8) {
                    documentTypeSelect.value = 'dni';
                } else if (docLength === 11) {
                    documentTypeSelect.value = 'ruc';
                }
            });

            documentInput.addEventListener('blur', function() {
                const documentValue = this.value.trim();
                if (documentValue.length >= 8) {
                    searchCustomerByDocument(documentValue);
                }
            });

            searchBtn.addEventListener('click', function() {
                const documentValue = documentInput.value.trim();
                if (documentValue.length >= 8) {
                    searchCustomerByDocument(documentValue);
                } else {
                    alert('Ingrese un documento válido (DNI: 8 dígitos, RUC: 11 dígitos)');
                }
            });

            saveBtn.addEventListener('click', saveNewCustomer);
        }

        function searchCustomerByDocument(documentValue) {
            const modal = document.getElementById('newCustomerModal');
            const button = modal.querySelector('#search_document_btn');
            const originalText = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(`${BASE_URL}/api/lookup_document.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                credentials: 'same-origin',
                body: `document=${encodeURIComponent(documentValue)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    modal.querySelector('#document_number').value = documentValue;

                    const docType = documentValue.length === 8 ? 'dni' : 'ruc';
                    if (docType === 'ruc') {
                        modal.querySelector('#customer_name').value = data.data.razon_social || data.data.nombre_o_razon_social || '';
                        let fullAddress = '';
                        if (data.data.direccion) fullAddress += data.data.direccion;
                        if (data.data.distrito) fullAddress += (fullAddress ? ', ' : '') + data.data.distrito;
                        if (data.data.provincia) fullAddress += (fullAddress ? ', ' : '') + data.data.provincia;
                        if (data.data.departamento) fullAddress += (fullAddress ? ', ' : '') + data.data.departamento;
                        modal.querySelector('#customer_address').value = fullAddress;
                    } else {
                        const nombreCompleto = data.data.nombre_completo ||
                            (data.data.nombres && data.data.apellido_paterno ?
                             `${data.data.nombres} ${data.data.apellido_paterno} ${data.data.apellido_materno || ''}`.trim() : '');
                        modal.querySelector('#customer_name').value = nombreCompleto;
                    }

                    modal.querySelector('#customer_data').style.display = 'block';
                    modal.querySelector('#save_customer_btn').disabled = false;
                    alert('✅ Datos encontrados. Complete los campos adicionales.');
                } else {
                    modal.querySelector('#customer_data').style.display = 'block';
                    modal.querySelector('#document_number').value = documentValue;
                    alert('⚠️ No se encontraron datos. Complete manualmente.');
                    modal.querySelector('#save_customer_btn').disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al consultar el documento.');
                modal.querySelector('#customer_data').style.display = 'block';
                modal.querySelector('#document_number').value = documentValue;
                modal.querySelector('#save_customer_btn').disabled = false;
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }

        function saveNewCustomer() {
            const modal = document.getElementById('newCustomerModal');
            const formData = new FormData();
            formData.append('document', modal.querySelector('#document_number').value);
            formData.append('business_name', modal.querySelector('#customer_name').value);
            formData.append('address', modal.querySelector('#customer_address').value);
            formData.append('phone', modal.querySelector('#customer_phone').value);
            formData.append('email', modal.querySelector('#customer_email').value);

            const button = modal.querySelector('#save_customer_btn');
            const originalText = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            fetch(`${BASE_URL}/api/customers.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const customerSelect = document.getElementById('customer_id');
                    const option = new Option(
                        `${data.customer.business_name} - ${data.customer.document}`,
                        data.customer.id
                    );
                    customerSelect.add(option);
                    customerSelect.value = data.customer.id;

                    bootstrap.Modal.getInstance(modal).hide();
                    modal.querySelector('#newCustomerForm').reset();
                    modal.querySelector('#customer_data').style.display = 'none';
                    modal.querySelector('#save_customer_btn').disabled = true;

                    alert('✅ Cliente creado exitosamente');
                } else {
                    alert('❌ Error: ' + (data.message || 'Error al crear el cliente'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al crear el cliente');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }

        // Form Submission
        function handleSubmit(e) {
            e.preventDefault();

            if (productItems.length === 0) {
                alert('Debe agregar al menos un producto');
                return;
            }

            const formData = new FormData(e.target);

            // Add products
            productItems.forEach((item, index) => {
                formData.append(`items[${index}][description]`, item.description);
                formData.append(`items[${index}][quantity]`, item.quantity);
                formData.append(`items[${index}][unit_price]`, item.price);
                formData.append(`items[${index}][discount_percentage]`, item.discount || 0);

                if (item.product_id) {
                    formData.append(`items[${index}][product_id]`, item.product_id);
                }
                if (item.image_url) {
                    formData.append(`items[${index}][image_url]`, item.image_url);
                }
            });

            document.getElementById('loadingOverlay').classList.add('active');

            fetch(`${BASE_URL}/api/save_quotation.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Cotización actualizada exitosamente');
                    const viewUrl = `${BASE_URL}/quotations/view_mobile.php?id=${data.quotation_id || '<?= $quotationId ?>'}`;
                    window.location.href = viewUrl;
                } else {
                    alert('❌ Error: ' + (data.message || 'Error al guardar la cotización'));
                    document.getElementById('loadingOverlay').classList.remove('active');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al guardar la cotización');
                document.getElementById('loadingOverlay').classList.remove('active');
            });
        }

        // Confirm Cancel
        function confirmCancel() {
            if (confirm('¿Descartar cambios en esta cotización?')) {
                window.location.href = `${BASE_URL}/quotations/view_mobile.php?id=<?= $quotationId ?>`;
            }
        }

        // CotiRapi Generator
        async function generateCotiRapi() {
            try {
                const response = await fetch(`${BASE_URL}/api/get_cotirapi_template.php`);
                const data = await response.json();

                if (!data.success || !data.template) {
                    alert('⚠️ Error al cargar la plantilla');
                    return;
                }

                const template = data.template;

                const customerSelect = document.getElementById('customer_id');
                const customerName = customerSelect?.options[customerSelect.selectedIndex]?.text?.split(' - ')[0] || 'Cliente';

                if (!productItems || productItems.length === 0) {
                    alert('⚠️ No hay productos agregados en la cotización');
                    return;
                }

                const currencySelect = document.getElementById('currency');
                const currency = currencySelect ? currencySelect.value : 'USD';
                const currencySymbol = currency === 'PEN' ? 'S/' : '$';

                let subtotal = 0;
                let itemsText = '';

                productItems.forEach((item, index) => {
                    const price = parseFloat(item.price) || 0;
                    const quantity = parseInt(item.quantity) || 1;
                    const discount = parseFloat(item.discount) || 0;
                    const finalPrice = price * (1 - discount / 100);
                    const itemTotal = finalPrice * quantity;

                    subtotal += itemTotal;

                    itemsText += `*${quantity}* ${item.description}\n`;
                    if (discount > 0) {
                        itemsText += `Precio: ~${currencySymbol} ${price.toFixed(2)}~ *${currencySymbol} ${finalPrice.toFixed(2)}* (Desc. ${discount}%)\n`;
                    } else {
                        itemsText += `Precio: *${currencySymbol} ${price.toFixed(2)}*\n`;
                    }
                    itemsText += `Total: *${currencySymbol} ${itemTotal.toFixed(2)}*\n\n`;
                });

                const total = subtotal;

                let cotiRapiContent = template
                    .replace(/{CUSTOMER_NAME}/g, customerName)
                    .replace(/{ITEMS}/g, itemsText.trim())
                    .replace(/{TOTAL}/g, total.toFixed(2))
                    .replace(/{CURRENCY}/g, currency)
                    .replace(/{EXCHANGE_RATE}/g, exchangeRate.toFixed(2))
                    .replace(/{DATE}/g, new Date().toLocaleDateString('es-PE'))
                    .replace(/{VALID_DAYS}/g, '7')
                    .replace(/{SELLER_NAME}/g, '<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>')
                    .replace(/{SELLER_PHONE}/g, '<?= htmlspecialchars($user['phone'] ?? '') ?>')
                    .replace(/{SELLER_EMAIL}/g, '<?= htmlspecialchars($user['email'] ?? '') ?>');

                document.getElementById('cotiRapiText').value = cotiRapiContent;

                const cotiRapiModal = new bootstrap.Modal(document.getElementById('cotiRapiModal'));
                cotiRapiModal.show();
            } catch (error) {
                console.error('Error generating CotiRapi:', error);
                alert('⚠️ Error al generar la cotización rápida');
            }
        }

        function copyCotiRapi() {
            const textArea = document.getElementById('cotiRapiText');
            textArea.select();
            document.execCommand('copy');

            const btn = document.getElementById('copyCotiRapiBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');

            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        }

        function sendCotiRapiWhatsApp() {
            const text = document.getElementById('cotiRapiText').value;
            const encodedText = encodeURIComponent(text);
            const whatsappUrl = `https://wa.me/?text=${encodedText}`;
            window.open(whatsappUrl, '_blank');
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js?v=<?= @filemtime(APP_ROOT . "/public/assets/js/theme-pro.js") ?>"></script>

    <!-- Universal Mobile Bottom Navigation -->
    <?php include __DIR__ . "/../../includes/mobile_bottom_nav.php"; ?>
</body>
</html>
