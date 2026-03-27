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
    $_SESSION['error_message'] = 'ID de cotización inválido';
    $auth->redirect(BASE_URL . '/quotations/index.php');
}

// Redirect to mobile version if on mobile device (unless explicitly disabled)
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        header('Location: ' . BASE_URL . '/quotations/edit_mobile.php?id=' . $quotationId);
        exit;
    }
}

// Initialize repositories
$quotationRepo = new Quotation();
$customerRepo = new Customer();
$productRepo = new Product();

// Get quotation data
$quotation = $quotationRepo->getById((int)$quotationId, $companyId);
if (!$quotation) {
    $_SESSION['error_message'] = 'Cotización no encontrada';
    $auth->redirect(BASE_URL . '/quotations/index.php');
}

// Initialize company settings
$companySettings = new CompanySettings();

// Get company information from settings
$company = $companySettings->getCompanyInfo($companyId);

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

// Get distinct warehouse names (only warehouses with stock > 0)
$stmt = $db->prepare("SELECT DISTINCT warehouse_name FROM product_warehouse_stock WHERE company_id = ? AND stock_quantity > 0 ORDER BY warehouse_name");
$stmt->execute([$companyId]);
$warehousesData = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get exchange rate and quotation settings
$exchangeRate = $companySettings->getSetting($companyId, 'exchange_rate_usd_pen');
$exchangeRate = !empty($exchangeRate) ? floatval($exchangeRate) : 3.80;

// Always enable discounts for creation, hide in print view with CSS
$enableDiscounts = true;

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;

    // Validation
    if (empty($_POST['customer_id'])) {
        $errors['customer_id'] = 'Debe seleccionar un cliente';
    }

    if (empty($_POST['quotation_date'])) {
        $errors['quotation_date'] = 'La fecha es requerida';
    }

    if (empty($_POST['items']) || !is_array($_POST['items'])) {
        $errors['items'] = 'Debe agregar al menos un producto';
    } else {
        $validItems = [];
        foreach ($_POST['items'] as $index => $item) {
            if (!empty($item['description']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
                $validItems[] = [
                    'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                    'description' => $item['description'],
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'discount_percentage' => (float)($item['discount_percentage'] ?? 0)
                ];
            }
        }

        if (empty($validItems)) {
            $errors['items'] = 'Debe agregar al menos un producto válido';
        }
    }

    if (empty($errors)) {
        // Calculate totals
        $totalsData = $quotationRepo->calculateTotals($validItems, (float)($_POST['global_discount_percentage'] ?? 0));

        $updateData = [
            'customer_id' => (int)$_POST['customer_id'],
            'quotation_date' => $_POST['quotation_date'],
            'valid_until' => $_POST['valid_until'] ?: null,
            'currency' => $_POST['currency'] ?? 'PEN',
            'igv_mode' => $_POST['price_includes_igv'] ?? 'included',
            'subtotal' => $totalsData['subtotal'],
            'global_discount_percentage' => (float)($_POST['global_discount_percentage'] ?? 0),
            'global_discount_amount' => $totalsData['global_discount_amount'],
            'total' => $totalsData['total'],
            'notes' => $_POST['notes'] ?: null,
            'terms_and_conditions' => $_POST['terms_and_conditions'] ?: null,
            'items' => $totalsData['items']
        ];

        $result = $quotationRepo->update($quotationId, $companyId, $updateData);

        // Update status separately if provided
        if (isset($_POST['status']) && $_POST['status'] !== $quotation['status']) {
            $quotationRepo->updateStatus($quotationId, $companyId, $_POST['status']);
            Notification::notifyQuotationStatusChange(
                $quotation['user_id'], $companyId, $quotationId,
                $quotation['quotation_number'], $_POST['status']
            );
        }

        if ($result) {
            $_SESSION['success_message'] = 'Cotización actualizada exitosamente';
            $auth->redirect(BASE_URL . '/quotations/view.php?id=' . $quotationId);
        } else {
            $errors['general'] = 'Error al actualizar la cotización';
        }
    }
} else {
    // Pre-fill form with existing data
    $formData = [
        'customer_id' => $quotation['customer_id'],
        'quotation_date' => $quotation['quotation_date'],
        'valid_until' => $quotation['valid_until'],
        'currency' => $quotation['currency'] ?? 'PEN', // Default to PEN if not set
        'global_discount_percentage' => $quotation['global_discount_percentage'],
        'notes' => $quotation['notes'],
        'terms_and_conditions' => $quotation['terms_and_conditions'],
        'status' => $quotation['status']
    ];

    // Get quotation items from the quotation data
    $quotationItems = $quotation['items'] ?? [];
}

$pageTitle = 'Editar Cotización #' . $quotation['quotation_number'];
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
        /* Force light theme for this page */
        body {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .quotation-builder {
            background: white !important;
            border-radius: 12px;
            padding: 2rem;
            color: #212529 !important;
        }

        .item-row {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fafafa !important;
            color: #212529 !important;
        }

        .totals-section {
            background: #f8f9fa !important;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
            color: #212529 !important;
        }

        .product-select { border: 1px solid #ddd; }

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

        /* Print styles - Hide discount percentage columns and form elements */
        @media print {
            /* Hide discount percentage columns */
            .discount-percentage-col {
                display: none !important;
            }

            /* Hide discount percentage input section */
            .discount-percentage-section {
                display: none !important;
            }

            /* Hide form controls and buttons */
            .btn, .form-select, button {
                display: none !important;
            }

            /* Hide navigation and other UI elements */
            nav, .navbar {
                display: none !important;
            }

            /* Adjust column widths when discount column is hidden */
            .item-row .row {
                display: flex !important;
            }

            /* Expand other columns when discount is hidden in print */
            @page {
                margin: 1cm;
            }

            .quotation-builder {
                padding: 1rem !important;
                border-radius: 0 !important;
                background: white !important;
            }

            .item-row {
                border: 1px solid #ddd !important;
                border-radius: 0 !important;
                background: white !important;
                page-break-inside: avoid;
            }

            .totals-section {
                background: white !important;
                border-radius: 0 !important;
            }
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
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-edit"></i> <?= $pageTitle ?></h1>
                    <div class="gap-2">
                        <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                        <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <div class="quotation-builder">
                    <form method="POST" id="quotationForm">
                        <!-- Company Header -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <?php if (!empty($company['logo_url'])): ?>
                                            <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>"
                                                 alt="Logo"
                                                 class="img-fluid"
                                                 style="max-height: 80px; max-width: 100%;">
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <i class="fas fa-building fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h3 class="mb-1 text-primary"><?= htmlspecialchars($company['name'] ?? 'Empresa Demo') ?></h3>
                                        <?php if (!empty($company['address'])): ?>
                                            <p class="mb-1"><i class="fas fa-map-marker-alt text-muted"></i> <?= htmlspecialchars($company['address']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($company['phone'])): ?>
                                            <p class="mb-1"><i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($company['phone']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($company['email'])): ?>
                                            <p class="mb-0"><i class="fas fa-envelope text-muted"></i> <?= htmlspecialchars($company['email']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <h2 class="text-secondary mb-0">COTIZACIÓN</h2>
                                        <h4 class="text-muted">#<?= htmlspecialchars($quotation['quotation_number']) ?></h4>
                                        <p class="text-muted mb-0">Tipo de cambio: USD 1 = PEN <?= number_format($exchangeRate, 3) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle"></i>
                            <strong>Estado actual:</strong>
                            <span class="badge bg-<?= $quotation['status'] === 'Draft' ? 'secondary' : ($quotation['status'] === 'Sent' ? 'primary' : ($quotation['status'] === 'Accepted' ? 'success' : 'danger')) ?>">
                                <?= htmlspecialchars($quotation['status']) ?>
                            </span>
                        </div>

                        <!-- Customer and Date Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label">Cliente *</label>
                                <div class="input-group">
                                    <select class="form-select <?= !empty($errors['customer_id']) ? 'is-invalid' : '' ?>"
                                            id="customer_id" name="customer_id" required>
                                        <option value="">Seleccione un cliente...</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= $customer['id'] ?>"
                                                    <?= isset($formData['customer_id']) && $formData['customer_id'] == $customer['id'] ? 'selected' : '' ?>
                                                    data-tax-id="<?= htmlspecialchars($customer['tax_id']) ?>"
                                                    data-address="<?= htmlspecialchars($customer['address']) ?>"
                                                    data-phone="<?= htmlspecialchars($customer['phone']) ?>"
                                                    data-email="<?= htmlspecialchars($customer['email']) ?>">
                                                <?= htmlspecialchars($customer['name']) ?> - <?= htmlspecialchars($customer['tax_id']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#customerSearchModal">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <?php if (!empty($errors['customer_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['customer_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-3">
                                <label for="quotation_date" class="form-label">Fecha de Cotización *</label>
                                <input type="date" class="form-control <?= !empty($errors['quotation_date']) ? 'is-invalid' : '' ?>"
                                       id="quotation_date" name="quotation_date"
                                       value="<?= htmlspecialchars($formData['quotation_date'] ?? '') ?>" required>
                                <?php if (!empty($errors['quotation_date'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['quotation_date']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-3">
                                <label for="valid_until" class="form-label">Válida Hasta</label>
                                <input type="date" class="form-control" id="valid_until" name="valid_until"
                                       value="<?= htmlspecialchars($formData['valid_until'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- Currency, IGV Mode and Status -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label for="currency" class="form-label">Moneda</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="PEN" <?= isset($formData['currency']) && $formData['currency'] === 'PEN' ? 'selected' : '' ?>>Soles (PEN)</option>
                                    <option value="USD" <?= isset($formData['currency']) && $formData['currency'] === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="price_includes_igv" class="form-label">
                                    IGV
                                    <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip"
                                       title="INCLUIDO IGV: El precio de lista ya incluye el 18% de IGV. MÁS IGV: Se agregará 18% al total."></i>
                                </label>
                                <select class="form-select" id="price_includes_igv" name="price_includes_igv">
                                    <option value="included" <?= ($formData['igv_mode'] ?? 'included') === 'included' ? 'selected' : '' ?>>INCLUIDO IGV</option>
                                    <option value="plus_igv" <?= ($formData['igv_mode'] ?? 'included') === 'plus_igv' ? 'selected' : '' ?>>MÁS IGV (+18%)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Draft" <?= isset($formData['status']) && $formData['status'] === 'Draft' ? 'selected' : '' ?>>Borrador</option>
                                    <option value="Sent" <?= isset($formData['status']) && $formData['status'] === 'Sent' ? 'selected' : '' ?>>Enviada</option>
                                    <option value="Accepted" <?= isset($formData['status']) && $formData['status'] === 'Accepted' ? 'selected' : '' ?>>Aceptada</option>
                                    <option value="Rejected" <?= isset($formData['status']) && $formData['status'] === 'Rejected' ? 'selected' : '' ?>>Rechazada</option>
                                </select>
                            </div>
                        </div>

                        <!-- Items Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Productos y Servicios</h5>
                                <div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#productSearchModal">
                                        <i class="fas fa-search"></i> Buscar Producto
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="addNewItem()">
                                        <i class="fas fa-plus"></i> Agregar Producto
                                    </button>
                                </div>
                            </div>

                            <div id="items-container">
                                <?php if (!empty($quotationItems)): ?>
                                    <?php foreach ($quotationItems as $index => $item): ?>
                                        <div class="item-row" data-item-index="<?= $index ?>">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-4">
                                                    <label class="form-label">Descripción</label>
                                                    <textarea class="form-control item-description"
                                                              name="items[<?= $index ?>][description]"
                                                              rows="2" required><?= htmlspecialchars($item['description']) ?></textarea>
                                                    <input type="hidden" name="items[<?= $index ?>][product_id]"
                                                           value="<?= $item['product_id'] ?>" class="item-product-id">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Cantidad</label>
                                                    <input type="number" class="form-control item-quantity"
                                                           name="items[<?= $index ?>][quantity]"
                                                           value="<?= $item['quantity'] ?>"
                                                           step="0.01" min="0" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Precio Unit.</label>
                                                    <input type="number" class="form-control item-unit-price"
                                                           name="items[<?= $index ?>][unit_price]"
                                                           value="<?= $item['unit_price'] ?>"
                                                           step="0.01" min="0" required>
                                                </div>
                                                <?php if ($enableDiscounts): ?>
                                                <div class="col-md-2 discount-percentage-col discount-percentage-section">
                                                    <label class="form-label">Desc. %</label>
                                                    <input type="number" class="form-control item-discount"
                                                           name="items[<?= $index ?>][discount_percentage]"
                                                           value="<?= $item['discount_percentage'] ?? 0 ?>"
                                                           step="0.01" min="0" max="100">
                                                </div>
                                                <?php endif; ?>
                                                <div class="col-md-1">
                                                    <label class="form-label">Subtotal</label>
                                                    <input type="text" class="form-control item-subtotal" readonly>
                                                </div>
                                                <div class="col-md-1">
                                                    <label class="form-label">&nbsp;</label>
                                                    <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeItem(<?= $index ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        No hay productos agregados. Use los botones de arriba para agregar productos.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($errors['items'])): ?>
                                <div class="alert alert-danger mt-2">
                                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['items']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Totals Section -->
                        <div class="totals-section">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="notes" class="form-label">Observaciones</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="terms_and_conditions" class="form-label">Términos y Condiciones</label>
                                            <textarea class="form-control" id="terms_and_conditions" name="terms_and_conditions" rows="3"><?= htmlspecialchars($formData['terms_and_conditions'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Subtotal:</strong></td>
                                            <td class="text-end" id="subtotal-display">S/ 0.00</td>
                                        </tr>
                                        <?php if ($enableDiscounts): ?>
                                        <tr class="discount-percentage-section">
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Desc. Global %</span>
                                                    <input type="number" class="form-control" id="global_discount_percentage"
                                                           name="global_discount_percentage"
                                                           value="<?= $formData['global_discount_percentage'] ?? 0 ?>"
                                                           step="0.01" min="0" max="100">
                                                </div>
                                            </td>
                                            <td class="text-end" id="global-discount-display">-S/ 0.00</td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td><strong id="igv-label">IGV (18%):</strong></td>
                                            <td class="text-end" id="igv-display">S/ 0.00</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td><strong>TOTAL:</strong></td>
                                            <td class="text-end"><strong id="total-display">S/ 0.00</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancelar
                            </a>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Actualizar Cotización
                                </button>
                                <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>" class="btn btn-outline-info">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Customer Search Modal -->
    <div class="modal fade" id="customerSearchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buscar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="customer_search_input" placeholder="Buscar por nombre o documento...">
                    </div>
                    <div id="customer_search_results">
                        <!-- Results will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newCustomerForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Documento</label>
                                    <select class="form-select" id="document_type">
                                        <option value="ruc">RUC</option>
                                        <option value="dni">DNI</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Número de Documento</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="document_number" placeholder="Ingrese RUC o DNI">
                                        <button type="button" class="btn btn-primary" id="search_document_btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="customer_data" style="display: none;">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre/Razón Social</label>
                                        <input type="text" class="form-control" id="customer_name" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" id="customer_email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="customer_phone">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Dirección</label>
                                        <textarea class="form-control" id="customer_address" rows="2"></textarea>
                                    </div>
                                </div>
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

    <!-- Product Search Modal -->
    <div class="modal fade" id="productSearchModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buscar Productos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Search Form -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_product_search" class="form-label">Buscar Producto</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="modal_product_search"
                                       placeholder="Código, nombre, descripción...">
                                <button type="button" class="btn btn-primary" id="modal_search_btn">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="modal_brand_filter" class="form-label">Filtrar por Marca</label>
                            <select class="form-select" id="modal_brand_filter">
                                <option value="">Todas las marcas</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="modal_warehouse_filter" class="form-label">Filtrar por Almacén</label>
                            <select class="form-select" id="modal_warehouse_filter">
                                <option value="">Todos los almacenes</option>
                            </select>
                        </div>
                    </div>

                    <!-- Results Summary -->
                    <div id="modal_results_summary" class="mb-3" style="display: none;">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i>
                            <span id="results_count">0</span> producto(s) encontrado(s)
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div id="modal_search_results">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <p>Ingrese un término de búsqueda para encontrar productos</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vista Previa de Imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="preview_image" src="" alt="Vista previa" class="img-fluid">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <script>
        // Global variables
        const customers = <?= json_encode($customers) ?>;
        const exchangeRate = <?= json_encode($exchangeRate) ?>;
        const enableDiscounts = <?= json_encode($enableDiscounts) ?>;
        const products = <?= json_encode($products) ?>;
        const warehouses = <?= json_encode($warehousesData) ?>;
        const existingItems = <?= json_encode($quotationItems ?? []) ?>;
        let itemIndex = <?= count($quotationItems ?? []) ?>;
        let allProducts = products;
        let filteredProducts = [];

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate totals on page load
            calculateTotals();

            // Set up event listeners
            setupEventListeners();

            // Set up customer search
            setupCustomerSearch();

            // Set up product search
            setupProductSearch();

            // Set up new customer form
            setupNewCustomerForm();

            // Re-index existing items
            reindexItems();
        });

        function setupEventListeners() {
            // Item calculations
            document.addEventListener('input', function(e) {
                if (e.target.matches('.item-quantity, .item-unit-price, .item-discount, #global_discount_percentage')) {
                    calculateTotals();
                }
            });

            // Currency change
            document.getElementById('currency').addEventListener('change', function() {
                calculateTotals();
            });

            // IGV mode change
            document.getElementById('price_includes_igv').addEventListener('change', function() {
                calculateTotals();
            });
        }

        function addNewItem(productData = null) {
            const container = document.getElementById('items-container');
            const currencySymbol = getCurrencySymbol();

            // Remove no products alert if exists
            const noProductsAlert = container.querySelector('.alert-info');
            if (noProductsAlert) {
                noProductsAlert.remove();
            }

            let discountColumn = '';
            if (enableDiscounts) {
                discountColumn = `
                    <div class="col-md-2 discount-percentage-col discount-percentage-section">
                        <label class="form-label">Desc. %</label>
                        <input type="number" class="form-control item-discount"
                               name="items[${itemIndex}][discount_percentage]"
                               value="0" step="0.01" min="0" max="100">
                    </div>
                `;
            }

            const itemHtml = `
                <div class="item-row" data-item-index="${itemIndex}">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control item-description"
                                      name="items[${itemIndex}][description]"
                                      rows="2" required>${productData ? productData.description : ''}</textarea>
                            <input type="hidden" name="items[${itemIndex}][product_id]"
                                   value="${productData ? productData.id : ''}" class="item-product-id">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cantidad</label>
                            <input type="number" class="form-control item-quantity"
                                   name="items[${itemIndex}][quantity]"
                                   value="1" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Precio Unit.</label>
                            <input type="number" class="form-control item-unit-price"
                                   name="items[${itemIndex}][unit_price]"
                                   value="${productData ? productData.regular_price : ''}"
                                   step="0.01" min="0" required>
                        </div>
                        ${discountColumn}
                        <div class="col-md-1">
                            <label class="form-label">Subtotal</label>
                            <input type="text" class="form-control item-subtotal" readonly>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-sm d-block" onclick="removeItem(${itemIndex})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', itemHtml);
            itemIndex++;
            calculateTotals();
        }

        function removeItem(index) {
            const itemRow = document.querySelector(`[data-item-index="${index}"]`);
            if (itemRow) {
                itemRow.remove();
                reindexItems();
                calculateTotals();

                // Show no products alert if no items remain
                const container = document.getElementById('items-container');
                if (container.querySelectorAll('.item-row').length === 0) {
                    container.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            No hay productos agregados. Use los botones de arriba para agregar productos.
                        </div>
                    `;
                }
            }
        }

        function reindexItems() {
            const items = document.querySelectorAll('.item-row');
            items.forEach((item, newIndex) => {
                item.setAttribute('data-item-index', newIndex);

                // Update form field names
                const inputs = item.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
                    }
                });

                // Update remove button onclick
                const removeBtn = item.querySelector('.btn-danger');
                if (removeBtn) {
                    removeBtn.setAttribute('onclick', `removeItem(${newIndex})`);
                }
            });

            itemIndex = items.length;
        }

        function calculateTotals() {
            let subtotal = 0;
            const currency = document.getElementById('currency').value;
            const symbol = getCurrencySymbol();

            // Calculate each item
            document.querySelectorAll('.item-row').forEach(function(row) {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
                const discountPercentage = enableDiscounts ?
                    (parseFloat(row.querySelector('.item-discount').value) || 0) : 0;

                const itemTotal = quantity * unitPrice;
                const discountAmount = itemTotal * (discountPercentage / 100);
                const itemSubtotal = itemTotal - discountAmount;

                // Update item subtotal display
                const subtotalField = row.querySelector('.item-subtotal');
                if (subtotalField) {
                    subtotalField.value = symbol + ' ' + itemSubtotal.toFixed(2);
                }

                subtotal += itemSubtotal;
            });

            // Apply global discount
            const globalDiscountPercentage = enableDiscounts ?
                (parseFloat(document.getElementById('global_discount_percentage').value) || 0) : 0;
            const globalDiscountAmount = subtotal * (globalDiscountPercentage / 100);
            const subtotalAfterDiscount = subtotal - globalDiscountAmount;

            // Get IGV mode
            const igvOption = document.getElementById('price_includes_igv').value;

            let subtotalSinIGV, igv, total;

            if (igvOption === 'plus_igv') {
                // MÁS IGV: El precio es sin IGV, se agrega 18%
                subtotalSinIGV = subtotalAfterDiscount;
                igv = subtotalAfterDiscount * 0.18;
                total = subtotalAfterDiscount + igv;
            } else {
                // INCLUIDO IGV: Los precios YA incluyen IGV (18%)
                total = subtotalAfterDiscount;
                subtotalSinIGV = total / 1.18;
                igv = total - subtotalSinIGV;
            }

            // Update displays
            document.getElementById('subtotal-display').textContent = symbol + ' ' + subtotalSinIGV.toFixed(2);

            if (enableDiscounts) {
                const globalDiscountDisplay = igvOption === 'plus_igv' ? globalDiscountAmount : globalDiscountAmount / 1.18;
                document.getElementById('global-discount-display').textContent = '-' + symbol + ' ' + globalDiscountDisplay.toFixed(2);
            }

            // Update IGV label based on mode
            const igvLabel = document.getElementById('igv-label');
            if (igvLabel) {
                igvLabel.textContent = igvOption === 'plus_igv' ? 'IGV (18%):' : 'IGV (inc.):';
            }

            document.getElementById('igv-display').textContent = symbol + ' ' + igv.toFixed(2);
            document.getElementById('total-display').textContent = symbol + ' ' + total.toFixed(2);
        }

        function getCurrencySymbol() {
            const currency = document.getElementById('currency').value;
            return currency === 'USD' ? '$' : 'S/';
        }

        function setupCustomerSearch() {
            const searchInput = document.getElementById('customer_search_input');
            const resultsContainer = document.getElementById('customer_search_results');

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();

                if (searchTerm.length < 2) {
                    resultsContainer.innerHTML = '<p class="text-muted">Ingrese al menos 2 caracteres para buscar</p>';
                    return;
                }

                const filteredCustomers = customers.filter(customer =>
                    customer.name.toLowerCase().includes(searchTerm) ||
                    customer.tax_id.toLowerCase().includes(searchTerm)
                );

                if (filteredCustomers.length === 0) {
                    resultsContainer.innerHTML = '<p class="text-muted">No se encontraron clientes</p>';
                    return;
                }

                const resultsHtml = filteredCustomers.map(customer => `
                    <div class="card mb-2 customer-result" data-customer-id="${customer.id}">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>${customer.name}</strong><br>
                                    <small class="text-muted">${customer.tax_id}</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary select-customer-btn">
                                    Seleccionar
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');

                resultsContainer.innerHTML = resultsHtml;

                // Add click handlers
                resultsContainer.querySelectorAll('.select-customer-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const customerId = this.closest('.customer-result').dataset.customerId;
                        const customer = customers.find(c => c.id == customerId);

                        // Select customer in dropdown
                        document.getElementById('customer_id').value = customerId;

                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('customerSearchModal')).hide();
                    });
                });
            });
        }

        function setupProductSearch() {
            const searchBtn = document.getElementById('modal_search_btn');
            const searchInput = document.getElementById('modal_product_search');
            const brandFilter = document.getElementById('modal_brand_filter');
            const warehouseFilter = document.getElementById('modal_warehouse_filter');
            const resultsContainer = document.getElementById('modal_search_results');
            const resultsSummary = document.getElementById('modal_results_summary');

            // Search products
            searchBtn.addEventListener('click', performProductSearch);
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    performProductSearch();
                }
            });

            // Filter products
            brandFilter.addEventListener('change', applyFilters);
            warehouseFilter.addEventListener('change', applyFilters);

            function performProductSearch() {
                const searchTerm = searchInput.value.trim();

                if (searchTerm.length < 2) {
                    alert('Ingrese al menos 2 caracteres para buscar');
                    return;
                }

                // Show loading
                resultsContainer.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Buscando...</span>
                        </div>
                        <p class="mt-2">Buscando productos...</p>
                    </div>
                `;

                // Filter products locally
                setTimeout(() => {
                    const searchLower = searchTerm.toLowerCase();
                    allProducts = products.filter(p =>
                        p.code.toLowerCase().includes(searchLower) ||
                        p.name.toLowerCase().includes(searchLower) ||
                        (p.description && p.description.toLowerCase().includes(searchLower))
                    );

                    if (allProducts.length > 0) {
                        populateFilters();
                        applyFilters();
                    } else {
                        resultsContainer.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                No se encontraron productos con el término: <strong>${searchTerm}</strong>
                            </div>
                        `;
                        resultsSummary.style.display = 'none';
                    }
                }, 100);
            }

            function populateFilters() {
                // Populate brands
                const brands = [...new Set(allProducts.map(p => p.brand).filter(b => b))];
                brandFilter.innerHTML = '<option value="">Todas las marcas</option>';
                brands.forEach(brand => {
                    brandFilter.innerHTML += `<option value="${brand}">${brand}</option>`;
                });

                // Populate warehouses
                const warehouses = new Set();
                allProducts.forEach(product => {
                    if (product.warehouses) {
                        Object.keys(product.warehouses).forEach(warehouse => {
                            if (product.warehouses[warehouse] > 0) {
                                warehouses.add(warehouse);
                            }
                        });
                    }
                });

                warehouseFilter.innerHTML = '<option value="">Todos los almacenes</option>';
                warehouses.forEach(warehouse => {
                    warehouseFilter.innerHTML += `<option value="${warehouse}">${warehouse}</option>`;
                });
            }

            function applyFilters() {
                let filtered = [...allProducts];

                // Filter by brand
                const selectedBrand = brandFilter.value;
                if (selectedBrand) {
                    filtered = filtered.filter(p => p.brand === selectedBrand);
                }

                // Filter by warehouse
                const selectedWarehouse = warehouseFilter.value;
                if (selectedWarehouse) {
                    filtered = filtered.filter(p =>
                        p.warehouses &&
                        p.warehouses[selectedWarehouse] &&
                        p.warehouses[selectedWarehouse] > 0
                    );
                }

                filteredProducts = filtered;
                displayProducts();
            }

            function displayProducts() {
                const count = filteredProducts.length;

                // Update summary
                document.getElementById('results_count').textContent = count;
                resultsSummary.style.display = 'block';

                if (count === 0) {
                    resultsContainer.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <p>No se encontraron productos con los filtros aplicados</p>
                        </div>
                    `;
                    return;
                }

                const productsHtml = filteredProducts.map(product => {
                    const warehouses = Object.entries(product.warehouses || {})
                        .filter(([warehouse, stock]) => stock > 0)
                        .map(([warehouse, stock]) => `${warehouse}: ${stock}`)
                        .join(', ');

                    const imageHtml = product.image_url ?
                        `<img src="<?= BASE_URL ?>/${product.image_url}" alt="${product.name}"
                              class="img-thumbnail product-image" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                              onclick="showImagePreview('<?= BASE_URL ?>/${product.image_url}')">` :
                        '<div class="bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fas fa-image text-muted"></i></div>';

                    return `
                        <div class="card mb-2 product-result">
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        ${imageHtml}
                                    </div>
                                    <div class="col">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-1">${product.code} - ${product.name}</h6>
                                                <p class="text-muted mb-1 small">${product.description || ''}</p>
                                                <div class="d-flex gap-3 small">
                                                    ${product.brand ? `<span><i class="fas fa-tag"></i> ${product.brand}</span>` : ''}
                                                    <span><i class="fas fa-dollar-sign"></i> ${product.price_currency} ${parseFloat(product.regular_price).toFixed(2)}</span>
                                                    ${warehouses ? `<span><i class="fas fa-warehouse"></i> ${warehouses}</span>` : ''}
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <button type="button" class="btn btn-primary btn-sm add-product-btn"
                                                        data-product='${JSON.stringify(product)}'>
                                                    <i class="fas fa-plus"></i> Agregar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                resultsContainer.innerHTML = productsHtml;

                // Add event listeners to add buttons
                resultsContainer.querySelectorAll('.add-product-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const productData = JSON.parse(this.dataset.product);

                        // Build description: only name (without code)
                        let description = productData.name;

                        // Add additional description if it exists and is different from name
                        if (productData.description &&
                            productData.description.trim() !== '' &&
                            productData.description !== productData.name &&
                            !productData.description.includes(productData.name)) {
                            description += ` - ${productData.description}`;
                        }

                        addNewItem({
                            id: productData.id,
                            description: description,
                            regular_price: productData.regular_price
                        });

                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('productSearchModal')).hide();
                    });
                });
            }
        }

        function showImagePreview(imageUrl) {
            document.getElementById('preview_image').src = imageUrl;
            new bootstrap.Modal(document.getElementById('imagePreviewModal')).show();
        }

        function setupNewCustomerForm() {
            initCustomerModal();
        }

        // Customer API functionality
        function initCustomerModal() {
            const customerModal = document.getElementById('newCustomerModal');
            if (customerModal) {
                console.log('Customer modal found, setting up events');
                const documentInput = customerModal.querySelector('#document_number');
                const searchBtn = customerModal.querySelector('#search_document_btn');
                const saveBtn = customerModal.querySelector('#save_customer_btn');

                if (documentInput) {
                    // Auto-update document type while typing
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
                }

                if (searchBtn) {
                    searchBtn.addEventListener('click', function() {
                        const documentValue = documentInput.value.trim();
                        if (documentValue.length >= 8) {
                            searchCustomerByDocument(documentValue);
                        } else {
                            alert('Ingrese un documento válido (DNI: 8 dígitos, RUC: 11 dígitos)');
                        }
                    });
                }

                if (saveBtn) {
                    saveBtn.addEventListener('click', function() {
                        saveNewCustomer();
                    });
                }
            } else {
                console.error('Customer modal not found');
            }
        }

        function searchCustomerByDocument(documentValue) {
            const modal = document.getElementById('newCustomerModal');
            const button = modal.querySelector('#search_document_btn');
            const originalText = button.innerHTML;

            // Auto-detect document type based on length
            let documentType = '';
            const docLength = documentValue.length;

            if (docLength === 8) {
                documentType = 'dni';
                modal.querySelector('#document_type').value = 'dni';
            } else if (docLength === 11) {
                documentType = 'ruc';
                modal.querySelector('#document_type').value = 'ruc';
            } else {
                alert('❌ Documento inválido. DNI debe tener 8 dígitos, RUC debe tener 11 dígitos.');
                return;
            }

            console.log('Searching for document:', documentValue, 'type:', documentType);
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Use lookup_document.php API - no need to send type, it auto-detects
            fetch(`<?= BASE_URL ?>/api/lookup_document.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: `document=${encodeURIComponent(documentValue)}`
            })
                .then(response => {
                    console.log('Search response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search response data:', data);
                    if (data.success && data.data) {
                        // Fill form with API data based on document type
                        modal.querySelector('#document_number').value = documentValue;

                        if (documentType === 'ruc') {
                            modal.querySelector('#customer_name').value = data.data.razon_social || data.data.nombre_o_razon_social || '';

                            // Combine address fields
                            let fullAddress = '';
                            if (data.data.direccion) fullAddress += data.data.direccion;
                            if (data.data.distrito) fullAddress += (fullAddress ? ', ' : '') + data.data.distrito;
                            if (data.data.provincia) fullAddress += (fullAddress ? ', ' : '') + data.data.provincia;
                            if (data.data.departamento) fullAddress += (fullAddress ? ', ' : '') + data.data.departamento;

                            modal.querySelector('#customer_address').value = fullAddress;
                        } else {
                            // DNI
                            const nombreCompleto = data.data.nombre_completo ||
                                                  (data.data.nombres && data.data.apellido_paterno ?
                                                   `${data.data.nombres} ${data.data.apellido_paterno} ${data.data.apellido_materno || ''}`.trim() : '');
                            modal.querySelector('#customer_name').value = nombreCompleto;
                        }

                        // Show customer data section
                        modal.querySelector('#customer_data').style.display = 'block';

                        // Enable save button
                        modal.querySelector('#save_customer_btn').disabled = false;

                        // Show success or warning message
                        if (data.warning) {
                            alert('⚠️ ' + data.warning + ' Complete los campos adicionales y guarde el cliente.');
                        } else {
                            alert('✅ Datos encontrados exitosamente. Complete los campos adicionales y guarde el cliente.');
                        }
                    } else {
                        // Show customer data section for manual entry
                        modal.querySelector('#customer_data').style.display = 'block';
                        modal.querySelector('#document_number').value = documentValue;
                        const errorMsg = data.message || 'No se encontraron datos para este documento. Complete los datos manualmente.';
                        alert('⚠️ ' + errorMsg);
                        modal.querySelector('#save_customer_btn').disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    alert('❌ Error al consultar el documento: ' + error.message + '. Complete los datos manualmente.');
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

            console.log('Saving customer with data:', Object.fromEntries(formData));
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            fetch(`<?= BASE_URL ?>/api/customers.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(response => {
                    console.log('Save response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Save response data:', data);
                    if (data.success) {
                        // Add to customer select
                        const customerSelect = document.getElementById('customer_id');
                        const option = new Option(
                            `${data.customer.business_name} - ${data.customer.document}`,
                            data.customer.id
                        );
                        option.setAttribute('data-document', data.customer.document);
                        option.setAttribute('data-name', data.customer.business_name);
                        customerSelect.add(option);
                        customerSelect.value = data.customer.id;

                        // Close modal
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        modalInstance.hide();

                        // Reset form
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
    </script>
</body>
</html>