<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Redirect to mobile version if on mobile device (unless explicitly disabled)
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // More precise mobile detection - exclude tablets and check for mobile keywords
    $isMobile = preg_match('/(android|webos|iphone|ipod|blackberry|iemobile|opera mini)/i', $userAgent)
                && !preg_match('/(ipad|tablet|kindle)/i', $userAgent)
                && !preg_match('/windows nt/i', $userAgent); // Exclude Windows desktops

    if ($isMobile) {
        header('Location: ' . BASE_URL . '/quotations/create_mobile.php');
        exit;
    }
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

if (!$user || !$companyId) {
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}

// Initialize company settings
$companySettings = new CompanySettings();

// Get company information from settings
$company = $companySettings->getCompanyInfo($companyId);

// Get customers for dropdown
$customerRepo = new Customer();
$customers = $customerRepo->getAllByCompany($companyId);

// Los productos se buscan dinámicamente via API (search_products.php)
// que consulta directamente a la BD COBOL (vista_productos y vista_almacenes_anual)
$db = getDBConnection();
$products = []; // Se cargarán via búsqueda AJAX

// Obtener almacenes desde la tabla desc_almacen
$stmt = $db->query("SELECT nombre FROM desc_almacen WHERE activo = 1 ORDER BY nombre");
$warehousesData = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get exchange rate and quotation settings
$exchangeRate = $companySettings->getSetting($companyId, 'exchange_rate_usd_pen');
$exchangeRate = !empty($exchangeRate) ? floatval($exchangeRate) : 3.80;

// Always enable discounts for creation, hide in print view with CSS
$enableDiscounts = true;

// Pre-select customer if provided
$selectedCustomerId = $_GET['customer_id'] ?? '';

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;

    // Validation
    if (empty($_POST['customer_id']) || !is_numeric($_POST['customer_id']) || (int)$_POST['customer_id'] <= 0) {
        $errors['customer_id'] = 'Debe seleccionar un cliente válido';
    }

    if (empty($_POST['quotation_date'])) {
        $errors['quotation_date'] = 'La fecha es requerida';
    }

    if (empty($_POST['items']) || !is_array($_POST['items'])) {
        $errors['items'] = 'Debe agregar al menos un producto';
    } else {
        $validItems = [];
        foreach ($_POST['items'] as $index => $item) {
            // Solo procesar items que tengan al menos descripción
            if (!empty(trim($item['description']))) {
                // Si no tiene cantidad o precio, usar valores por defecto
                $quantity = isset($item['quantity']) && $item['quantity'] !== '' ? (float)$item['quantity'] : 1;
                $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float)$item['unit_price'] : 0;

                // product_id puede ser código COBOL (string) o ID local (int)
                $productId = $item['product_id'] ?? null;
                $description = trim($item['description']);

                // Si product_id es alfanumérico (código COBOL), no es un ID válido
                if ($productId && !is_numeric($productId)) {
                    if (strpos($description, $productId) === false) {
                        $description = '[' . $productId . '] ' . $description;
                    }
                    $productId = null;
                } else {
                    $productId = $productId ? (int)$productId : null;
                }

                $validItems[] = [
                    'product_id' => $productId,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => (float)($item['discount_percentage'] ?? 0),
                    'image_url' => $item['image_url'] ?? null
                ];
            }
        }

        if (empty($validItems)) {
            $errors['items'] = 'Debe agregar al menos un producto válido con descripción, cantidad y precio';
        }
    }

    if (empty($errors)) {
        $quotationRepo = new Quotation();

        // Get currency from form
        $currency = $_POST['currency'] ?? 'USD';

        // Get payment condition and credit days
        $paymentCondition = $_POST['payment_condition'] ?? 'cash';
        $creditDays = ($paymentCondition === 'credit') ? ($_POST['credit_days'] ?? null) : null;

        // Get IGV mode
        $igvMode = $_POST['price_includes_igv'] ?? 'included';

        $result = $quotationRepo->create(
            $companyId,
            (int)$_POST['customer_id'],
            $user['id'],
            $_POST['quotation_date'],
            $_POST['valid_until'] ?: null,
            $validItems,
            (float)($_POST['global_discount_percentage'] ?? 0),
            $_POST['notes'] ?: null,
            $_POST['terms_and_conditions'] ?: null,
            'Draft',
            $currency,
            $paymentCondition,
            $creditDays,
            $igvMode
        );

        if ($result) {
            $_SESSION['success_message'] = 'Cotización creada exitosamente';
            $auth->redirect(BASE_URL . '/quotations/view.php?id=' . $result);
        } else {
            $errors['general'] = 'Error al crear la cotización';
        }
    }
}

$pageTitle = 'Nueva Cotización';
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

        /* Product search autocomplete styles */
        .product-suggestions {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .product-suggestions .list-group-item {
            border-left: none;
            border-right: none;
            padding: 0.5rem 0.75rem;
        }

        .product-suggestions .list-group-item:first-child {
            border-top: none;
        }

        .product-suggestions .list-group-item:last-child {
            border-bottom: none;
        }

        .product-suggestions .list-group-item:hover,
        .product-suggestions .list-group-item.active {
            background-color: #e7f1ff;
        }

        /* Hide print-only elements on screen */
        .print-only,
        .print-only-gallery {
            display: none !important;
        }

        /* Image preview and attachment styles */
        .image-preview img {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .image-preview img:hover {
            transform: scale(1.05);
        }

        /* Footer notes styling */
        .footer-notes ul li {
            padding-left: 1.5rem;
            position: relative;
        }

        .footer-notes ul li i {
            position: absolute;
            left: 0;
            top: 3px;
        }

        /* Print styles - A4 format and organization */
        @media print {
            /* Hide screen-only elements */
            .no-print,
            .quotation-builder:not(.print-only):not(.print-only-gallery),
            nav, button, .btn, .form-control, .form-select,
            .add-item-btn, .remove-item-btn, .attach-image-btn,
            .modal, .alert, .dropdown, .navbar,
            .last-price-info, .image-preview, .no-print {
                display: none !important;
            }

            /* Show print-only elements */
            .print-only,
            .print-only-gallery {
                display: block !important;
            }

            /* Page settings */
            @page {
                size: A4;
                margin: 15mm 10mm;
            }

            body {
                max-width: 100%;
                margin: 0;
                padding: 0;
                font-size: 10pt;
                line-height: 1.3;
                background: white !important;
                color: black !important;
            }

            /* Force page breaks */
            .print-only-gallery {
                page-break-before: always !important;
                margin-top: 0 !important;
            }

            /* Card styling */
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
                margin-bottom: 10px !important;
            }

            .card-header {
                background-color: #f8f9fa !important;
                border-bottom: 2px solid #dee2e6 !important;
                padding: 8px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .card-body {
                padding: 10px !important;
            }

            /* Table styling */
            .table-responsive {
                page-break-inside: avoid;
                overflow: visible !important;
            }

            .table {
                width: 100% !important;
                font-size: 9pt !important;
                border-collapse: collapse !important;
            }

            .table th, .table td {
                padding: 4px 6px !important;
                border: 1px solid #ddd !important;
                vertical-align: top !important;
            }

            .table thead {
                background-color: #e9ecef !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Typography */
            h1, h2, h3 {
                font-size: 14pt !important;
                margin: 5px 0 !important;
                color: black !important;
            }

            h4, h5, h6 {
                font-size: 11pt !important;
                margin: 5px 0 !important;
                color: black !important;
            }

            p, div, span {
                color: black !important;
            }

            /* Layout adjustments */
            .row {
                margin-bottom: 3px !important;
            }

            .col-md-6, .col-6 {
                width: 50% !important;
                float: left;
            }

            .col-md-4, .col-4 {
                width: 33.33% !important;
                float: left;
            }

            .col-md-3, .col-3 {
                width: 25% !important;
                float: left;
            }

            .col-md-12, .col-12 {
                width: 100% !important;
                clear: both;
            }

            /* Image gallery on page 2 */
            .print-only-gallery img {
                max-width: 45% !important;
                height: auto !important;
                margin: 10px !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }

            /* Totals section */
            .totals-section {
                background-color: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                padding: 10px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
                    <a href="<?= BASE_URL ?>/dashboard_simple.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> Ver Cotizaciones
                    </a>
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
                                        <h2 class="text-primary mb-1">COTIZACIÓN</h2>
                                        <p class="mb-0"><strong>RUC:</strong> <?= htmlspecialchars($company['tax_id'] ?? 'N/A') ?></p>
                                        <p class="text-muted mb-0"><small>(Se generará al guardar)</small></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Header Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Información General</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="customer_dropdown" class="form-label">Cliente *</label>
                                            <div class="dropdown" id="customer_dropdown">
                                                <input type="text" class="form-control <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>"
                                                       id="customer_search"
                                                       placeholder="Buscar cliente por nombre o RUC..."
                                                       autocomplete="off">
                                                <input type="hidden" id="customer_id" name="customer_id" required>
                                                <ul class="dropdown-menu w-100" id="customer_dropdown_menu">
                                                    <li><span class="dropdown-item-text text-muted">Escriba para buscar clientes...</span></li>
                                                </ul>
                                            </div>
                                            <?php if (isset($errors['customer_id'])): ?>
                                                <div class="invalid-feedback"><?= $errors['customer_id'] ?></div>
                                            <?php endif; ?>
                                            <!-- Selected Customer Display -->
                                            <div id="selected_customer_display" class="mt-2" style="display: none;">
                                                <div class="card border-success">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0 text-success">
                                                            <i class="fas fa-user-check"></i> Cliente Seleccionado
                                                            <button type="button" class="btn btn-sm btn-outline-secondary float-end" onclick="clearCustomerSelection()">
                                                                <i class="fas fa-times"></i> Cambiar
                                                            </button>
                                                        </h6>
                                                    </div>
                                                    <div class="card-body" id="customer_details_content">
                                                        <!-- Customer details will be populated here -->
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="customer_actions" class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#customerModal">
                                                    <i class="fas fa-plus"></i> Nuevo Cliente (API)
                                                </button>
                                                <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-plus"></i> Nuevo Manual
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="quotation_date" class="form-label">Fecha de Cotización *</label>
                                            <input type="date" class="form-control <?= isset($errors['quotation_date']) ? 'is-invalid' : '' ?>"
                                                   id="quotation_date" name="quotation_date"
                                                   value="<?= $formData['quotation_date'] ?? date('Y-m-d') ?>" required>
                                            <?php if (isset($errors['quotation_date'])): ?>
                                                <div class="invalid-feedback"><?= $errors['quotation_date'] ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="valid_until" class="form-label">Válida hasta</label>
                                            <input type="date" class="form-control" id="valid_until" name="valid_until"
                                                   value="<?= $formData['valid_until'] ?? date('Y-m-d', strtotime('+7 days')) ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="currency" class="form-label">Moneda *</label>
                                            <select class="form-select" id="currency" name="currency" required>
                                                <option value="USD" <?= ($formData['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                                                <option value="PEN" <?= ($formData['currency'] ?? 'USD') === 'PEN' ? 'selected' : '' ?>>Soles (PEN)</option>
                                            </select>
                                            <small class="text-muted" id="exchange_rate_info">
                                                Tipo de cambio: USD 1 = PEN <?= number_format($exchangeRate, 3) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="price_includes_igv" class="form-label">
                                                IGV
                                                <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip"
                                                   title="INCLUIDO IGV: el precio queda igual. MÁS IGV: se agrega 18% al precio."></i>
                                            </label>
                                            <select class="form-select" id="price_includes_igv" name="price_includes_igv">
                                                <option value="included" <?= ($formData['price_includes_igv'] ?? 'included') === 'included' ? 'selected' : '' ?>>INCLUIDO IGV</option>
                                                <option value="plus_igv" <?= ($formData['price_includes_igv'] ?? 'included') === 'plus_igv' ? 'selected' : '' ?>>SIN IGV </option>
                                            </select>
                                            <small class="text-muted" id="igv_info">
                                                <span id="igv_status_text">Precio de lista sin cambios</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="payment_condition" class="form-label">Condición de Pago *</label>
                                            <select class="form-select" id="payment_condition" name="payment_condition" required onchange="toggleCreditDays()">
                                                <option value="cash" <?= ($formData['payment_condition'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Efectivo / Contado</option>
                                                <option value="credit" <?= ($formData['payment_condition'] ?? '') === 'credit' ? 'selected' : '' ?>>Crédito</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3" id="credit_days_container" style="<?= ($formData['payment_condition'] ?? 'cash') === 'credit' ? '' : 'display: none;' ?>">
                                        <div class="mb-3">
                                            <label for="credit_days" class="form-label">Plazo de Crédito</label>
                                            <select class="form-select" id="credit_days" name="credit_days">
                                                <option value="">Seleccionar...</option>
                                                <option value="30" <?= ($formData['credit_days'] ?? '') == '30' ? 'selected' : '' ?>>30 días</option>
                                                <option value="60" <?= ($formData['credit_days'] ?? '') == '60' ? 'selected' : '' ?>>60 días</option>
                                                <option value="90" <?= ($formData['credit_days'] ?? '') == '90' ? 'selected' : '' ?>>90 días</option>
                                                <option value="120" <?= ($formData['credit_days'] ?? '') == '120' ? 'selected' : '' ?>>120 días</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Search -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-search"></i> Buscar Productos</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" id="product_search"
                                               placeholder="Buscar por código, nombre, descripción o marca...">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-primary" id="search_products_btn" data-bs-toggle="modal" data-bs-target="#productSearchModal">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                        <button type="button" class="btn btn-info ms-2" id="show_date_history_btn" onclick="showProductHistoryByDate()">
                                            <i class="fas fa-calendar-alt"></i> Historial por Fecha
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Items Section -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Productos Seleccionados</h5>
                                <button type="button" class="btn btn-success add-item-btn">
                                    <i class="fas fa-plus"></i> Agregar Producto Manual
                                </button>
                            </div>
                             <div class="card-body">
                                 <div class="items-container">
                                     <!-- Items will be added here dynamically -->
                                     <div id="empty-items-message" class="text-center py-4 text-muted">
                                         <i class="fas fa-plus-circle fa-2x mb-3"></i>
                                         <p>No hay productos agregados</p>
                                         <p class="small">Haz click en "Agregar Producto Manual" para comenzar</p>
                                     </div>
                                 </div>
                                <?php if (isset($errors['items'])): ?>
                                    <div class="alert alert-danger mt-3"><?= $errors['items'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Totals and Notes -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card" id="notesCard">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Notas y Condiciones</h5>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleNotesBtn" onclick="toggleNotesCard()">
                                            <i class="fas fa-chevron-down" id="toggleNotesIcon"></i>
                                        </button>
                                    </div>
                                    <div class="card-body" id="notesCardBody" style="display: none;">
                                        <div class="mb-3">
                                            <label for="notes" class="form-label">Notas</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="terms_and_conditions" class="form-label">Términos y Condiciones</label>
                                            <textarea class="form-control" id="terms_and_conditions" name="terms_and_conditions" rows="8"><?= htmlspecialchars($formData['terms_and_conditions'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="totals-section">
                                    <h5 class="mb-3">Resumen</h5>

                                    <?php if ($enableDiscounts): ?>
                                    <div class="row mb-3 discount-percentage-section">
                                        <div class="col-6">
                                            <label for="global_discount_percentage" class="form-label">Descuento Global (%)</label>
                                            <input type="number" class="form-control global-discount" id="global_discount_percentage"
                                                   name="global_discount_percentage" min="0" max="100" step="0.01"
                                                   value="<?= $formData['global_discount_percentage'] ?? '0' ?>">
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <input type="hidden" name="global_discount_percentage" value="0">
                                    <?php endif; ?>

                                    <table class="table table-sm">
                                        <tr>
                                            <td>Subtotal:</td>
                                            <td class="text-end"><strong><span class="currency-symbol">$</span> <span class="subtotal-amount">0.00</span></strong></td>
                                        </tr>
                                        <?php if ($enableDiscounts): ?>
                                        <tr>
                                            <td>Descuento Global:</td>
                                            <td class="text-end"><strong><span class="currency-symbol">$</span> <span class="global-discount-amount">0.00</span></strong></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr class="table-light">
                                            <td>IGV (18%):</td>
                                            <td class="text-end"><strong><span class="currency-symbol">$</span> <span id="igv_display" class="igv-amount">0.00</span></strong></td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td><strong>Total:</strong></td>
                                            <td class="text-end"><strong><span class="currency-symbol">$</span> <span id="total_display" class="total-amount">0.00</span></strong></td>
                                        </tr>
                                    </table>

                                    <!-- Hidden fields for totals -->
                                    <input type="hidden" id="subtotal_display" value="0.00">

                                    <!-- Botones Desktop -->
                                    <div class="d-none d-md-flex justify-content-end gap-2 mt-4">
                                        <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                        <button type="button" class="btn btn-outline-primary" onclick="downloadPDF()">
                                            <i class="fas fa-file-pdf"></i> Descargar PDF
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="sendByEmail()">
                                            <i class="fas fa-envelope"></i> Enviar por Correo
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="sendByWhatsApp()">
                                            <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="window.print()">
                                            <i class="fas fa-print"></i> Vista Previa / Imprimir
                                        </button>
                                        <button type="button" class="btn btn-warning" onclick="generateCotiRapi()" title="Generar texto plano para WhatsApp">
                                            <i class="fas fa-bolt"></i> CotiRapi
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Guardar Cotización
                                        </button>
                                    </div>

                                    <!-- Botones Móvil -->
                                    <div class="d-md-none mt-4">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-secondary w-100">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </a>
                                            </div>
                                            <div class="col-6">
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-save"></i> Guardar
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" class="btn btn-warning w-100" onclick="generateCotiRapi()">
                                                    <i class="fas fa-bolt"></i> CotiRapi
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" class="btn btn-outline-success w-100" onclick="sendByWhatsApp()">
                                                    <i class="fab fa-whatsapp"></i> WhatsApp
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" class="btn btn-outline-primary w-100" onclick="downloadPDF()">
                                                    <i class="fas fa-file-pdf"></i> PDF
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" class="btn btn-outline-success w-100" onclick="sendByEmail()">
                                                    <i class="fas fa-envelope"></i> Email
                                                </button>
                                            </div>
                                            <div class="col-12">
                                                <button type="button" class="btn btn-info w-100" onclick="window.print()">
                                                    <i class="fas fa-print"></i> Vista Previa / Imprimir
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Notes Section -->
                        <div class="card mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información Importante</h5>
                            </div>
                            <div class="card-body">
                                <div class="footer-notes" style="font-size: 0.9rem;">
                                    <ul class="list-unstyled mb-3">
                                        <li class="mb-2">
                                            <i class="fas fa-check-circle text-primary"></i>
                                            <strong>Sírvase girar su orden de compra a nombre de:</strong> LLANTA SAN MARTIN S.R.LTDA. - RUC: 20381499627.
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-exclamation-triangle text-warning"></i>
                                            La mercadería se encuentra en stock salvo venta previa.
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-dollar-sign text-success"></i>
                                            Consultar el tipo de cambio del día, antes de realizar algún pago.
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-file-invoice text-info"></i>
                                            Los precios no incluyen la Percepción.
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-landmark text-danger"></i>
                                            <strong>Agentes de Percepción:</strong> Según el Decreto Supremo N° 091-2013-EF publicado el 14 de Mayo del 2013, hemos sido designados AGENTES DE PERCEPCIÓN a partir del 01 de Julio del 2013. Considerar para efectos de la cobranza.
                                        </li>
                                    </ul>

                                    <div class="alert alert-primary mb-0">
                                        <h6 class="mb-2"><i class="fas fa-university"></i> Cuentas Bancarias:</h6>
                                        <?php
                                        // Get bank accounts from database
                                        try {
                                            $dbConn = getDBConnection();
                                            $bankQuery = "SELECT bank_name, account_number, account_type, currency
                                                        FROM bank_accounts
                                                        WHERE company_id = ? AND is_active = 1
                                                        ORDER BY currency, bank_name";
                                            $stmt = $dbConn->prepare($bankQuery);
                                            $stmt->execute([$companyId]);
                                            $bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                            if ($bankAccounts):
                                            ?>
                                                <div class="row">
                                                    <?php foreach ($bankAccounts as $account): ?>
                                                        <div class="col-md-6 mb-2">
                                                            <strong><?= htmlspecialchars($account['bank_name']) ?></strong>
                                                            (<?= htmlspecialchars($account['currency']) ?>)<br>
                                                            <small>
                                                                <?= htmlspecialchars($account['account_type']) ?>:
                                                                <code><?= htmlspecialchars($account['account_number']) ?></code>
                                                            </small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">No hay cuentas bancarias registradas.</small>
                                            <?php endif;
                                        } catch (Exception $e) {
                                            echo '<small class="text-muted">Error al cargar las cuentas bancarias.</small>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Print Version - Hidden on screen, shown on print -->
                <div id="print-version" class="print-only" style="display: none;">
                    <!-- This will be populated by JavaScript -->
                </div>

                <!-- Image Gallery Section (for printing) -->
                <div class="quotation-builder mt-5 print-only-gallery" style="page-break-before: always; display: none;">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3 class="mb-0 text-center">
                                <i class="fas fa-images"></i> Galería de Imágenes de Productos
                            </h3>
                        </div>
                        <div class="card-body p-4" id="image-gallery-content">
                            <p class="text-center text-muted py-5">No hay imágenes adjuntas</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="customerForm">
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

    <!-- Product History by Date Modal -->
    <div class="modal fade" id="productHistoryByDateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Historial de Productos Cotizados por Fecha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Date Range Filter -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="history_date_from" class="form-label">Fecha Desde</label>
                            <input type="date" class="form-control" id="history_date_from">
                        </div>
                        <div class="col-md-4">
                            <label for="history_date_to" class="form-label">Fecha Hasta</label>
                            <input type="date" class="form-control" id="history_date_to">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-primary w-100" onclick="loadProductHistoryByDate()">
                                    <i class="fas fa-filter"></i> Filtrar por Fecha
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search Filters -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="history_search_code" class="form-label">Buscar por Código</label>
                            <input type="text" class="form-control" id="history_search_code" placeholder="Ej: 7312">
                        </div>
                        <div class="col-md-4">
                            <label for="history_search_description" class="form-label">Buscar por Descripción</label>
                            <input type="text" class="form-control" id="history_search_description" placeholder="Ej: motor">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary flex-grow-1" onclick="filterHistoryTable()">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearHistoryFilters()">
                                    <i class="fas fa-times"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Export Button -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-success btn-sm" onclick="exportHistoryToExcel()">
                                <i class="fas fa-file-excel"></i> Exportar a Excel
                            </button>
                            <span class="ms-2 text-muted" id="history_results_count"></span>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="history_date_loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando historial...</p>
                    </div>

                    <!-- Results Table -->
                    <div id="history_date_results" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-striped" id="historyTable" style="font-size: 0.85rem;">
                                <thead class="table-dark">
                                    <tr style="font-size: 0.8rem;">
                                        <th style="white-space: nowrap;">Fecha</th>
                                        <th style="white-space: nowrap;">Cotización</th>
                                        <th style="white-space: nowrap;">Cliente</th>
                                        <th style="white-space: nowrap;">RUC/DNI</th>
                                        <th style="white-space: nowrap;">Código</th>
                                        <th style="min-width: 200px;">Descripción</th>
                                        <th style="white-space: nowrap; text-align: right;">Cant.</th>
                                        <th style="white-space: nowrap; text-align: right;">P. Unit.</th>
                                        <th style="white-space: nowrap; text-align: right;">Desc. %</th>
                                        <th style="white-space: nowrap; text-align: right;">Subtotal</th>
                                        <th style="white-space: nowrap;">Mon.</th>
                                        <th style="white-space: nowrap;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="history_date_table_body">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- No Results Message -->
                    <div id="history_date_no_results" class="text-center py-5" style="display: none;">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No se encontraron productos cotizados en el rango de fechas seleccionado.</p>
                    </div>
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
        // Products data for autocomplete
        const products = <?= json_encode($products) ?>;
        const customers = <?= json_encode($customers) ?>;
        const exchangeRate = <?= json_encode($exchangeRate) ?>;
        const enableDiscounts = <?= json_encode($enableDiscounts) ?>;
        const warehouses = <?= json_encode($warehousesData) ?>;
        let itemIndex = 0;

        // Initialize quotation builder
        document.addEventListener('DOMContentLoaded', function() {
            addItem(); // Add first item by default

            // Event listeners para add-item-btn y remove-item-btn están en el otro listener más abajo

            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('item-quantity') ||
                    e.target.classList.contains('item-price') ||
                    e.target.classList.contains('item-discount') ||
                    e.target.classList.contains('global-discount')) {
                    calculateTotals();
                }
            });

            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('product-select')) {
                    onProductChange(e.target);
                }
            });

            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // addItem() function moved to avoid duplication - see line 2812 for the main implementation

        function removeItem(button) {
            const itemRow = button.closest('.item-row');
            if (document.querySelectorAll('.item-row').length > 1) {
                itemRow.remove();
                calculateTotals();
            } else {
                alert('Debe mantener al menos un producto');
            }
        }

        function onProductChange(select) {
            const option = select.options[select.selectedIndex];
            const itemRow = select.closest('.item-row');

            if (option.value) {
                const product = products.find(p => p.id == option.value);
                if (product) {
                    itemRow.querySelector('[name*="[description]"]').value = product.description;

                    // Convert price if necessary
                    let price = parseFloat(product.regular_price) || 0;
                    const quotationCurrency = document.getElementById('currency').value;
                    const productCurrency = product.price_currency || 'USD';

                    // Convert price if currencies don't match
                    if (quotationCurrency !== productCurrency) {
                        if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                            // Convert USD to PEN
                            price = price * parseFloat(exchangeRate);
                        } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                            // Convert PEN to USD
                            price = price / parseFloat(exchangeRate);
                        }
                    }

                    // El precio unitario siempre es el precio de lista (sin modificar por IGV)
                    itemRow.querySelector('[name*="[unit_price]"]').value = price.toFixed(2);

                    // Load product image if available
                    if (product.image_url) {
                        const itemIndex = itemRow.dataset.itemIndex;
                        loadProductImage(itemIndex, product.image_url, product.description);
                    }

                // Get last quoted price for this product and customer
                const customerId = document.getElementById('customer_id').value;
                if (customerId) {
                    getLastQuotedPrice(product.id, customerId, itemRow);
                }

                    calculateTotals();
                }
            }
        }

        function calculateTotals() {
            let subtotal = 0;
            const currencySymbol = getCurrencySymbol();
            const igvOption = document.getElementById('price_includes_igv').value;

            document.querySelectorAll('.item-row').forEach(function(row) {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const discount = parseFloat(row.querySelector('.item-discount').value) || 0;

                const lineSubtotal = quantity * price;
                const discountAmount = (lineSubtotal * discount) / 100;
                const lineTotal = lineSubtotal - discountAmount;

                const itemTotalInput = row.querySelector('.item-total');
                if (itemTotalInput) itemTotalInput.value = lineTotal.toFixed(2);
                subtotal += lineTotal;
            });

            const globalDiscountEl = document.querySelector('.global-discount');
            const globalDiscount = globalDiscountEl ? parseFloat(globalDiscountEl.value) || 0 : 0;
            const globalDiscountAmount = (subtotal * globalDiscount) / 100;
            let baseTotal = subtotal - globalDiscountAmount;

            let subtotalSinIGV, igv, total;

            if (igvOption === 'plus_igv') {
                // MÁS IGV: El precio es sin IGV, se agrega 18%
                subtotalSinIGV = baseTotal;
                igv = baseTotal * 0.18;
                total = baseTotal + igv;
            } else {
                // INCLUIDO IGV: Los precios YA incluyen IGV (18%)
                // Total = Subtotal sin IGV * 1.18
                // Por lo tanto: Subtotal sin IGV = Total / 1.18
                total = baseTotal;
                subtotalSinIGV = total / 1.18;
                igv = total - subtotalSinIGV;
            }

            // Update display
            const subtotalAmountEl = document.querySelector('.subtotal-amount');
            if (subtotalAmountEl) {
                subtotalAmountEl.textContent = subtotalSinIGV.toFixed(2);
            }

            const globalDiscountAmountEl = document.querySelector('.global-discount-amount');
            if (globalDiscountAmountEl) {
                // El descuento según el modo de IGV
                const globalDiscountDisplay = igvOption === 'plus_igv' ? globalDiscountAmount : globalDiscountAmount / 1.18;
                globalDiscountAmountEl.textContent = globalDiscountDisplay.toFixed(2);
            }

            const igvAmountEl = document.querySelector('.igv-amount');
            if (igvAmountEl) {
                igvAmountEl.textContent = igv.toFixed(2);
            }

            const totalAmountEl = document.querySelector('.total-amount');
            if (totalAmountEl) {
                totalAmountEl.textContent = total.toFixed(2);
            }

            // Update hidden fields for print version
            const subtotalDisplayEl = document.getElementById('subtotal_display');
            if (subtotalDisplayEl) {
                subtotalDisplayEl.value = subtotalSinIGV.toFixed(2);
            }
        }

        function getCurrencySymbol() {
            const currency = document.getElementById('currency').value;
            return currency === 'USD' ? '$' : 'S/';
        }

        // Update currency symbols when currency changes
        document.getElementById('currency').addEventListener('change', function() {
            updateCurrencySymbols();
            convertAllPrices();
        });

        // Update IGV status text and recalculate totals when IGV option changes
        document.getElementById('price_includes_igv').addEventListener('change', function() {
            const igvStatusText = document.getElementById('igv_status_text');
            const newValue = this.value;

            if (newValue === 'plus_igv') {
                igvStatusText.innerHTML = '<span class="text-success"><i class="fas fa-plus-circle"></i> Precio + 18% IGV</span>';
            } else {
                igvStatusText.innerHTML = 'Precio de lista sin cambios';
            }

            // Recalculate totals (el precio unitario NO cambia, solo el total)
            calculateTotals();
        });

        function updateCurrencySymbols() {
            const symbol = getCurrencySymbol();

            // Update item total symbols
            document.querySelectorAll('.item-total').forEach(function(input) {
                const inputGroup = input.closest('.input-group');
                const symbolSpan = inputGroup.querySelector('.input-group-text');
                symbolSpan.textContent = symbol;
            });

            // Update totals section symbols
            document.querySelectorAll('.currency-symbol').forEach(function(span) {
                span.textContent = symbol;
            });
        }

        // Toggle credit days visibility based on payment condition
        function toggleCreditDays() {
            const paymentCondition = document.getElementById('payment_condition').value;
            const creditDaysContainer = document.getElementById('credit_days_container');
            const creditDaysSelect = document.getElementById('credit_days');

            if (paymentCondition === 'credit') {
                creditDaysContainer.style.display = 'block';
                creditDaysSelect.required = true;
            } else {
                creditDaysContainer.style.display = 'none';
                creditDaysSelect.required = false;
                creditDaysSelect.value = '';
            }
        }

        // Toggle Notes and Conditions card visibility
        function toggleNotesCard() {
            const cardBody = document.getElementById('notesCardBody');
            const icon = document.getElementById('toggleNotesIcon');

            if (cardBody.style.display === 'none') {
                cardBody.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                cardBody.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        }

        // Track current quotation currency for conversion
        let currentQuotationCurrency = document.getElementById('currency')?.value || 'USD';

        function convertAllPrices() {
            const newCurrency = document.getElementById('currency').value;
            const previousCurrency = currentQuotationCurrency;

            // Convert all item prices
            document.querySelectorAll('.item-row').forEach(function(row) {
                const priceInput = row.querySelector('[name*="[unit_price]"]');
                const currentPrice = parseFloat(priceInput.value) || 0;

                if (currentPrice > 0) {
                    let newPrice = currentPrice;

                    // Check if we have original product data
                    const originalPrice = parseFloat(row.dataset.originalPrice);
                    const productCurrency = row.dataset.productCurrency || 'USD';

                    if (!isNaN(originalPrice) && originalPrice > 0) {
                        // Use original price and convert from product currency
                        newPrice = originalPrice;
                        if (newCurrency !== productCurrency) {
                            if (productCurrency === 'USD' && newCurrency === 'PEN') {
                                newPrice = originalPrice * parseFloat(exchangeRate);
                            } else if (productCurrency === 'PEN' && newCurrency === 'USD') {
                                newPrice = originalPrice / parseFloat(exchangeRate);
                            }
                        }
                    } else {
                        // No original data - convert from previous currency to new currency
                        if (previousCurrency !== newCurrency) {
                            if (previousCurrency === 'USD' && newCurrency === 'PEN') {
                                newPrice = currentPrice * parseFloat(exchangeRate);
                            } else if (previousCurrency === 'PEN' && newCurrency === 'USD') {
                                newPrice = currentPrice / parseFloat(exchangeRate);
                            }
                        }
                    }

                    priceInput.value = newPrice.toFixed(2);
                }
            });

            // Update current currency tracker
            currentQuotationCurrency = newCurrency;

            // Recalculate totals
            calculateTotals();
        }

        // Product search functionality - solo abre el modal
        let searchTimeout;

        function initProductSearch() {
            const productSearchInput = document.getElementById('product_search');
            if (productSearchInput) {
                // Cuando presiona Enter, abrir el modal con el término de búsqueda
                productSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        openProductSearchModal();
                    }
                });
            }
        }

        function openProductSearchModal() {
            const searchTerm = document.getElementById('product_search').value.trim();
            const modal = new bootstrap.Modal(document.getElementById('productSearchModal'));
            modal.show();

            // Si hay término de búsqueda, ejecutar la búsqueda en el modal
            if (searchTerm.length >= 2) {
                setTimeout(() => {
                    document.getElementById('modal_product_search').value = searchTerm;
                    searchProductsModal(searchTerm);
                }, 300);
            }
        }

        function selectProductFromSearch(product) {
            // Add to the current item or create new item
            const activeItemRow = document.querySelector('.item-row:last-child');
            const productSearchInput = activeItemRow.querySelector('.product-search-input');
            const productIdHidden = activeItemRow.querySelector('.product-id-hidden');

            // Calculate converted price
            let price = parseFloat(product.regular_price) || parseFloat(product.precio) || 0;
            const quotationCurrency = document.getElementById('currency').value;
            const productCurrency = product.price_currency || 'USD';

            // Convert price if currencies don't match
            if (quotationCurrency !== productCurrency) {
                if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                    // Convert USD to PEN
                    price = price * parseFloat(exchangeRate);
                } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                    // Convert PEN to USD
                    price = price / parseFloat(exchangeRate);
                }
            }

            // El precio unitario siempre es el precio de lista (sin modificar por IGV)
            // El IGV se aplica solo en el cálculo del total

            // Get product code and description
            const productCode = product.id || product.codigo || product.code || '';
            const description = product.description || product.descripcion || product.name || '';

            // Check if current item is empty, if not add new item
            let targetRow = activeItemRow;
            if (productIdHidden && productIdHidden.value) {
                addItem();
                targetRow = document.querySelector('.item-row:last-child');
            }

            // Set values in target row
            const targetSearchInput = targetRow.querySelector('.product-search-input');
            const targetIdHidden = targetRow.querySelector('.product-id-hidden');
            const targetDescription = targetRow.querySelector('[name*="[description]"]');
            const targetPrice = targetRow.querySelector('[name*="[unit_price]"]');

            // Set product code in visible input and hidden field
            if (targetSearchInput) targetSearchInput.value = productCode;
            if (targetIdHidden) targetIdHidden.value = productCode;
            if (targetDescription) targetDescription.value = description;
            if (targetPrice) {
                targetPrice.value = price.toFixed(2);
                targetPrice.dispatchEvent(new Event('input', { bubbles: true }));
            }

            // Store original product data for currency conversion
            const originalPrice = parseFloat(product.regular_price) || parseFloat(product.precio) || 0;
            targetRow.dataset.originalPrice = originalPrice;
            targetRow.dataset.productCurrency = productCurrency;

            // Load product image if available
            const itemIndex = targetRow.dataset.itemIndex;
            const imageUrl = product.image_url || product.imagen_url;
            if (imageUrl && itemIndex) {
                loadProductImage(itemIndex, imageUrl, description);
            }

            // Clear search
            const searchInput = document.getElementById('product_search');
            if (searchInput) searchInput.value = '';
            clearProductSearchResults();
            calculateTotals();
        }

        // Customer search functionality
        let customerSearchTimeout;

        function initCustomerSearch() {
            const customerSearchInput = document.getElementById('customer_search');
            const customerDropdownMenu = document.getElementById('customer_dropdown_menu');

            if (customerSearchInput && customerDropdownMenu) {
                console.log('Customer search input found, adding listener');

                // Load customers but keep dropdown hidden initially
                showAllCustomers();
                hideDropdown();

                customerSearchInput.addEventListener('input', function() {
                    clearTimeout(customerSearchTimeout);
                    const searchTerm = this.value.trim().toLowerCase();
                    console.log('Customer search input:', searchTerm);

                    if (searchTerm.length >= 1) {
                        customerSearchTimeout = setTimeout(() => {
                            filterCustomers(searchTerm);
                            showDropdown();
                        }, 300);
                    } else {
                        showAllCustomers();
                        showDropdown();
                    }
                });

                // Handle dropdown item clicks
                customerDropdownMenu.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Find the customer-item element (might be the target or a parent)
                    let customerItem = e.target.closest('.customer-item');
                    if (customerItem) {
                        console.log('Customer item clicked:', customerItem);
                        hideDropdown();
                        selectCustomer(customerItem);
                    }
                });

                // Show dropdown when input is focused
                customerSearchInput.addEventListener('focus', function() {
                    if (this.value.trim().length > 0) {
                        filterCustomers(this.value.trim().toLowerCase());
                    } else {
                        showAllCustomers();
                    }
                    showDropdown();
                });

                // Hide dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!customerSearchInput.contains(e.target) && !customerDropdownMenu.contains(e.target)) {
                        hideDropdown();
                    }
                });
            } else {
                console.error('Customer search elements not found');
            }
        }

        function showDropdown() {
            const customerDropdownMenu = document.getElementById('customer_dropdown_menu');
            if (customerDropdownMenu) {
                customerDropdownMenu.classList.add('show');
                customerDropdownMenu.style.display = 'block';
            }
        }

        function hideDropdown() {
            const customerDropdownMenu = document.getElementById('customer_dropdown_menu');
            if (customerDropdownMenu) {
                customerDropdownMenu.classList.remove('show');
                customerDropdownMenu.style.display = 'none';
            }
        }

        function filterCustomers(searchTerm) {
            const customerDropdownMenu = document.getElementById('customer_dropdown_menu');

            if (!customers || customers.length === 0) {
                customerDropdownMenu.innerHTML = '<li><span class="dropdown-item-text text-muted">No hay clientes disponibles</span></li>';
                return;
            }

            const filteredCustomers = customers.filter(customer => {
                const name = (customer.name || '').toLowerCase();
                const taxId = (customer.tax_id || '').toLowerCase();
                const email = (customer.email || '').toLowerCase();

                return name.includes(searchTerm) ||
                       taxId.includes(searchTerm) ||
                       email.includes(searchTerm);
            });

            if (filteredCustomers.length === 0) {
                customerDropdownMenu.innerHTML = '<li><span class="dropdown-item-text text-muted">No se encontraron clientes</span></li>';
            } else {
                let html = '';
                filteredCustomers.forEach(customer => {
                    html += `
                        <li>
                            <a class="dropdown-item customer-item" href="#"
                               data-customer-id="${customer.id}"
                               data-customer-name="${customer.name}"
                               data-customer-tax-id="${customer.tax_id}"
                               data-customer-email="${customer.email || ''}"
                               data-customer-phone="${customer.phone || ''}"
                               data-customer-address="${customer.address || ''}"
                               data-customer-user-id="${customer.user_id || ''}"
                               data-owner-name="${customer.owner_name || 'Desconocido'}">
                                <div>
                                    <strong>${customer.name}</strong>
                                    <br><small class="text-muted">RUC/DNI: ${customer.tax_id}</small>
                                    ${customer.email ? `<br><small class="text-muted">${customer.email}</small>` : ''}
                                    <br><small class="text-info">Vendedor: ${customer.owner_name || 'Desconocido'}</small>
                                </div>
                            </a>
                        </li>
                    `;
                });
                customerDropdownMenu.innerHTML = html;
            }
        }

        function showAllCustomers() {
            const customerDropdownMenu = document.getElementById('customer_dropdown_menu');

            if (!customers || customers.length === 0) {
                customerDropdownMenu.innerHTML = '<li><span class="dropdown-item-text text-muted">No hay clientes disponibles</span></li>';
                return;
            }

            let html = '';
            // Show first 10 customers to avoid overwhelming the dropdown
            const customersToShow = customers.slice(0, 10);
                customersToShow.forEach(customer => {
                html += `
                    <li>
                        <a class="dropdown-item customer-item" href="#"
                            data-customer-id="${customer.id}"
                            data-customer-name="${customer.name}"
                            data-customer-tax-id="${customer.tax_id}"
                            data-customer-email="${customer.email || ''}"
                            data-customer-phone="${customer.phone || ''}"
                            data-customer-address="${customer.address || ''}"
                            data-customer-user-id="${customer.user_id || ''}"
                            data-owner-name="${customer.owner_name || 'Desconocido'}">
                             <div>
                                 <strong>${customer.name}</strong>
                                 <br><small class="text-muted">RUC/DNI: ${customer.tax_id}</small>
                                 ${customer.email ? `<br><small class="text-muted">${customer.email}</small>` : ''}
                                 <br><small class="text-info">Vendedor: ${customer.owner_name || 'Desconocido'}</small>
                             </div>
                         </a>
                     </li>
                 `;
            });

            if (customers.length > 10) {
                html += '<li><hr class="dropdown-divider"></li>';
                html += '<li><span class="dropdown-item-text text-muted"><small>Escriba para buscar más clientes...</small></span></li>';
            }

            customerDropdownMenu.innerHTML = html;
        }

        function selectCustomer(customerElement) {
            console.log('selectCustomer called with:', customerElement);

            const customerId = customerElement.dataset.customerId;
            const customerName = customerElement.dataset.customerName;
            const customerTaxId = customerElement.dataset.customerTaxId;
            const customerEmail = customerElement.dataset.customerEmail;
            const customerPhone = customerElement.dataset.customerPhone;
            const customerAddress = customerElement.dataset.customerAddress;
            const customerUserId = customerElement.dataset.customerUserId;
            const ownerName = customerElement.dataset.ownerName;

            console.log('Customer data:', { customerId, customerName, customerTaxId, customerEmail, customerPhone, customerAddress });

            // Validate we have the required data
            if (!customerId || !customerName) {
                console.error('Missing required customer data');
                return;
            }

            // Update hidden input and clear search input
            const customerIdInput = document.getElementById('customer_id');
            const customerSearchInput = document.getElementById('customer_search');

            if (customerIdInput) {
                customerIdInput.value = customerId;
                console.log('Set customer_id to:', customerId);
            }

            if (customerSearchInput) {
                customerSearchInput.value = '';
            }

            // Hide the dropdown
            hideDropdown();

            // Show customer details
            const customerDetailsContent = document.getElementById('customer_details_content');
            if (customerDetailsContent) {
                // Check if customer belongs to another user
                const currentUserId = <?= json_encode($user['id']) ?>;

                // Add history button
                const historyButton = `<div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showCustomerHistory(${customerId})">
                        <i class="fas fa-history"></i> Ver Historial de Cotizaciones
                    </button>
                </div>`;
                const isOwner = customerUserId == currentUserId;
                const warningHtml = !isOwner ? `
                    <div class="alert alert-warning mb-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Atención:</strong> Este cliente pertenece a otro usuario (${ownerName}).
                        Asegúrate de tener autorización para crear cotizaciones con este cliente.
                    </div>
                ` : '';

                customerDetailsContent.innerHTML = `
                    ${warningHtml}
                    <div class="row">
                        <div class="col-md-12">
                            <h6 class="mb-1 text-primary">
                                <i class="fas fa-user"></i> ${customerName}
                            </h6>
                            <div class="mb-2">
                                <strong><i class="fas fa-id-card text-info"></i> RUC/DNI:</strong> ${customerTaxId}
                            </div>
                            <div class="mb-2">
                                <strong><i class="fas fa-user-tie text-success"></i> Vendedor:</strong> ${ownerName}
                            </div>
                            ${customerEmail ? `
                                <div class="mb-2">
                                    <strong><i class="fas fa-envelope text-info"></i> Email:</strong> ${customerEmail}
                                </div>
                            ` : ''}
                            ${customerPhone ? `
                                <div class="mb-2">
                                    <strong><i class="fas fa-phone text-info"></i> Teléfono:</strong> ${customerPhone}
                                </div>
                            ` : ''}
                             ${customerAddress ? `
                                 <div class="mb-2">
                                     <strong><i class="fas fa-map-marker-alt text-info"></i> Dirección:</strong> ${customerAddress}
                                 </div>
                             ` : ''}
                         </div>
                     </div>
                     <div class="mt-2">
                         <button type="button" class="btn btn-sm btn-outline-info" onclick="showCustomerHistory(${customerId})">
                             <i class="fas fa-history"></i> Ver Historial de Cotizaciones
                         </button>
                     </div>
                 `;
                console.log('Customer details populated');
            } else {
                console.error('customer_details_content element not found');
            }

            // Show the customer display section and hide actions
            const selectedDisplay = document.getElementById('selected_customer_display');
            const customerActions = document.getElementById('customer_actions');
            const customerDropdown = document.getElementById('customer_dropdown');

            if (selectedDisplay) {
                selectedDisplay.style.display = 'block';
                console.log('Showing selected customer display');
            }

            if (customerActions) {
                customerActions.style.display = 'none';
                console.log('Hiding customer actions');
            }

            if (customerDropdown) {
                customerDropdown.style.display = 'none';
                console.log('Hiding customer dropdown');
            }

            console.log('Customer selected successfully:', customerName, customerId);
        }

        function clearCustomerSelection() {
            // Clear the form
            document.getElementById('customer_id').value = '';
            document.getElementById('customer_search').value = '';

            // Hide customer display and show actions
            document.getElementById('selected_customer_display').style.display = 'none';
            document.getElementById('customer_actions').style.display = 'block';
            document.getElementById('customer_dropdown').style.display = 'block';

            // Hide all last price info
            document.querySelectorAll('.last-price-info').forEach(el => {
                el.style.display = 'none';
            });

            // Reset dropdown menu and hide it
            showAllCustomers();
            hideDropdown();

            console.log('Customer selection cleared');
        }

        // Customer API functionality - correct implementation
        function initCustomerModal() {
            const customerModal = document.getElementById('customerModal');
            if (customerModal) {
                console.log('Customer modal found, setting up events');
                const documentInput = customerModal.querySelector('#document_number');
                const searchBtn = customerModal.querySelector('#search_document_btn');
                const saveBtn = customerModal.querySelector('#save_customer_btn');

                if (documentInput) {
                    // Auto-update document type while typing
                    documentInput.addEventListener('input', function() {
                        const docLength = this.value.trim().length;
                        const documentTypeSelect = customerModal.querySelector('#document_type');

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
            const modal = document.getElementById('customerModal');
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
                            showAlert('warning', 'Datos encontrados. ⚠️ ' + data.warning + ' Complete los campos adicionales y guarde el cliente.');
                        } else {
                            showAlert('success', 'Datos encontrados exitosamente. Complete los campos adicionales y guarde el cliente.');
                        }
                    } else {
                        // Show customer data section for manual entry
                        modal.querySelector('#customer_data').style.display = 'block';
                        modal.querySelector('#document_number').value = documentValue;
                        const errorMsg = data.message || 'No se encontraron datos para este documento. Complete los datos manualmente.';
                        showAlert('warning', errorMsg);
                        modal.querySelector('#save_customer_btn').disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error completo:', error);
                    showAlert('error', 'Error al consultar el documento: ' + error.message + '. Complete los datos manualmente.');
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
            const modal = document.getElementById('customerModal');
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
                        const c = data.customer;
                        const currentUser = <?= json_encode($user['first_name'] . ' ' . $user['last_name']) ?>;

                        // Add to customers array so it appears in future searches
                        customers.push({
                            id: c.id,
                            name: c.name || c.business_name,
                            tax_id: c.tax_id || c.document,
                            email: c.email || '',
                            phone: c.phone || '',
                            address: c.address || '',
                            user_id: <?= json_encode($user['id']) ?>,
                            owner_name: currentUser
                        });

                        // Auto-select the new customer
                        const fakeElement = document.createElement('a');
                        fakeElement.dataset.customerId = c.id;
                        fakeElement.dataset.customerName = c.name || c.business_name;
                        fakeElement.dataset.customerTaxId = c.tax_id || c.document;
                        fakeElement.dataset.customerEmail = c.email || '';
                        fakeElement.dataset.customerPhone = c.phone || '';
                        fakeElement.dataset.customerAddress = c.address || '';
                        fakeElement.dataset.customerUserId = <?= json_encode($user['id']) ?>;
                        fakeElement.dataset.ownerName = currentUser;
                        selectCustomer(fakeElement);

                        // Close modal
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        modalInstance.hide();

                        // Reset form
                        modal.querySelector('#customerForm').reset();
                        modal.querySelector('#customer_data').style.display = 'none';
                        modal.querySelector('#save_customer_btn').disabled = true;

                        showAlert('success', 'Cliente "' + (c.name || c.business_name) + '" creado y seleccionado');
                    } else {
                        showAlert('error', data.message || 'Error al crear el cliente');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('error', 'Error al crear el cliente');
                })
                .finally(() => {
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
        }

        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' :
                              type === 'warning' ? 'alert-warning' : 'alert-danger';

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show`;
            alertDiv.style.cssText = 'position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; max-width: 500px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Try multiple containers, fallback to body
            const container = document.querySelector('main .container-fluid') ||
                             document.querySelector('main') ||
                             document.body;

            if (container === document.body) {
                document.body.appendChild(alertDiv);
            } else {
                container.insertBefore(alertDiv, container.firstChild);
            }

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Update valid until date when quotation date changes
        function updateValidUntilDate() {
            const quotationDate = document.getElementById('quotation_date').value;
            if (quotationDate) {
                const date = new Date(quotationDate);
                date.setDate(date.getDate() + 1);
                const validUntil = date.toISOString().split('T')[0];
                document.getElementById('valid_until').value = validUntil;

                // Update terms and conditions with the calculated valid days (1 day)
                const termsTextarea = document.getElementById('terms_and_conditions');
                const currentTerms = termsTextarea.value;

                // Update the terms only if it contains the default text
                if (currentTerms.includes('Precios válidos por') || currentTerms.trim() === '') {
                    termsTextarea.value = `Precios válidos por 1 día.
Tiempo de entrega: 7 días hábiles.`;
                }
            }
        }

        // Modal product search functionality
        function initModalProductSearch() {
            const modalSearchInput = document.getElementById('modal_product_search');
            const modalSearchBtn = document.getElementById('modal_search_btn');
            const warehouseFilter = document.getElementById('modal_warehouse_filter');

            // Populate warehouse filter
            if (warehouseFilter && warehouses) {
                warehouses.forEach(warehouse => {
                    const option = document.createElement('option');
                    option.value = warehouse;
                    option.textContent = warehouse;
                    warehouseFilter.appendChild(option);
                });

                // Filter results visually when warehouse filter changes
                warehouseFilter.addEventListener('change', function() {
                    filterModalResults();
                });
            }

            if (modalSearchInput && modalSearchBtn) {
                modalSearchBtn.addEventListener('click', function() {
                    const searchTerm = modalSearchInput.value.trim();
                    if (searchTerm.length >= 2) {
                        searchProductsModal(searchTerm);
                    } else {
                        alert('Ingrese al menos 2 caracteres para buscar');
                    }
                });

                modalSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        modalSearchBtn.click();
                    }
                });

                // Auto search when modal opens with existing search term
                document.getElementById('productSearchModal').addEventListener('shown.bs.modal', function() {
                    const mainSearchTerm = document.getElementById('product_search').value.trim();
                    if (mainSearchTerm) {
                        modalSearchInput.value = mainSearchTerm;
                        if (mainSearchTerm.length >= 2) {
                            searchProductsModal(mainSearchTerm);
                        }
                    }
                    modalSearchInput.focus();
                });
            }
        }

        function searchProductsModal(searchTerm) {
            const resultsContainer = document.getElementById('modal_search_results');
            const selectedWarehouse = document.getElementById('modal_warehouse_filter').value;

            resultsContainer.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Buscando...</span>
                    </div>
                    <p class="mt-2">Buscando productos en COBOL...</p>
                </div>
            `;

            // Buscar productos via API (COBOL)
            fetch(`<?= BASE_URL ?>/api/search_products.php?search=${encodeURIComponent(searchTerm)}`, {
                credentials: 'same-origin'
            })
            .then(response => {
                // Verificar si la respuesta es OK
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text(); // Primero obtener como texto
            })
            .then(text => {
                // Intentar parsear como JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Respuesta no es JSON válido:', text.substring(0, 500));
                    throw new Error('La respuesta del servidor no es JSON válido');
                }
            })
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    // Display all products, filtering will be done visually
                    displayModalProductResults(data.products);

                    // Apply warehouse filter if selected
                    if (selectedWarehouse) {
                        filterModalResults();
                    }
                } else {
                    resultsContainer.innerHTML = `
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle"></i>
                            ${data.message || 'No se encontraron productos con el término: <strong>' + searchTerm + '</strong>'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error searching products:', error);
                resultsContainer.innerHTML = `
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-circle"></i>
                        Error: ${error.message}
                    </div>
                `;
            });
        }

        function displayModalProductResults(products) {
            const resultsContainer = document.getElementById('modal_search_results');

            if (products.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i>
                        No se encontraron productos
                    </div>
                `;
                return;
            }

            // Sort products by price ascending
            products.sort((a, b) => {
                const priceA = parseFloat(a.regular_price) || 0;
                const priceB = parseFloat(b.regular_price) || 0;
                return priceA - priceB;
            });

            let html = `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Marca</th>
                                <th>Imagen</th>
                                <th>Stock Total</th>
                                <th>Almacenes</th>
                                <th>Precio</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            products.forEach(product => {
                // Format warehouse stocks (only show warehouses with stock > 0)
                let warehouseStocks = '';
                let warehousesWithStock = {};
                if (product.warehouses && Object.keys(product.warehouses).length > 0) {
                    Object.entries(product.warehouses).forEach(([warehouse, stock]) => {
                        if (parseFloat(stock) > 0) {
                            warehousesWithStock[warehouse] = stock;
                        }
                    });

                    if (Object.keys(warehousesWithStock).length > 0) {
                        warehouseStocks = Object.entries(warehousesWithStock)
                            .map(([warehouse, stock]) => `<small class="d-block"><span class="badge bg-info">${warehouse}</span>: <strong>${stock}</strong></small>`)
                            .join('');
                    } else {
                        warehouseStocks = '<small class="text-muted">Sin stock disponible</small>';
                    }
                } else {
                    warehouseStocks = '<small class="text-muted">Sin stock</small>';
                }

                // Image display with clickable modal
                let imageDisplay = '';
                if (product.image_url) {
                    if (product.image_url.startsWith('http') || product.image_url.startsWith('/')) {
                        imageDisplay = `<img src="${product.image_url}" alt="Producto" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; cursor: pointer;" onclick="showImageModal('${product.image_url}', '${product.name || product.description}')" title="Click para ver imagen completa">`;
                    } else {
                        imageDisplay = `<a href="${product.image_url}" target="_blank" class="btn btn-sm btn-outline-info" title="Ver imagen"><i class="fas fa-external-link-alt"></i></a>`;
                    }
                } else {
                    imageDisplay = '<span class="text-muted"><i class="fas fa-image"></i></span>';
                }

                // Calculate converted price
                let price = parseFloat(product.regular_price) || 0;
                const quotationCurrency = document.getElementById('currency').value;
                const productCurrency = product.price_currency || 'USD';

                if (quotationCurrency !== productCurrency) {
                    if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                        price = price * parseFloat(exchangeRate);
                    } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                        price = price / parseFloat(exchangeRate);
                    }
                }

                const currencySymbol = quotationCurrency === 'USD' ? '$' : 'S/';

                // Get warehouse list for filtering (only warehouses with stock > 0)
                const warehousesWithStockList = Object.keys(warehousesWithStock).join(',');
                const brand = product.brand || '';

                html += `
                    <tr data-brand="${brand}" data-warehouses="${warehousesWithStockList}" data-warehouse-stocks='${JSON.stringify(warehousesWithStock)}'>
                        <td><code>${product.code}</code></td>
                        <td>${product.name || product.description}</td>
                        <td>${brand ? `<span class="badge bg-secondary">${brand}</span>` : '-'}</td>
                        <td class="text-center">${imageDisplay}</td>
                        <td class="text-center"><strong class="text-primary">${product.total_stock}</strong></td>
                        <td>${warehouseStocks}</td>
                        <td><strong>${currencySymbol} ${price.toFixed(2)}</strong><br><small class="text-muted">${productCurrency}</small></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <button type="button" class="btn btn-success btn-sm" onclick="selectProductFromModal(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                                    <i class="fas fa-plus"></i> Agregar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="viewFichasTecnicas('${product.code}')" title="Ver Ficha Técnica">
                                    <i class="fas fa-file-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
                <div class="text-muted text-center mt-2">
                    <small>Se encontraron ${products.length} productos</small>
                </div>
            `;

            resultsContainer.innerHTML = html;

            // Update filters with current products
            updateModalFilters(products);
        }

        function updateModalFilters(products) {
            // Extract unique brands
            const brands = [...new Set(products.map(p => p.brand).filter(b => b))].sort();
            const brandSelect = document.getElementById('modal_brand_filter');
            brandSelect.innerHTML = '<option value="">Todas las marcas</option>';
            brands.forEach(brand => {
                brandSelect.innerHTML += `<option value="${brand}">${brand}</option>`;
            });

            // Extract unique warehouses that have stock > 0
            const warehousesWithStock = new Set();
            products.forEach(product => {
                if (product.warehouses) {
                    Object.entries(product.warehouses).forEach(([warehouse, stock]) => {
                        if (parseFloat(stock) > 0) {
                            warehousesWithStock.add(warehouse);
                        }
                    });
                }
            });

            const warehouses = [...warehousesWithStock].sort();
            const warehouseSelect = document.getElementById('modal_warehouse_filter');
            warehouseSelect.innerHTML = '<option value="">Todos los almacenes</option>';
            warehouses.forEach(warehouse => {
                warehouseSelect.innerHTML += `<option value="${warehouse}">${warehouse}</option>`;
            });

            // Remove existing event listeners to avoid duplicates
            brandSelect.removeEventListener('change', filterModalResults);
            warehouseSelect.removeEventListener('change', filterModalResults);

            // Add filter event listeners
            brandSelect.addEventListener('change', filterModalResults);
            warehouseSelect.addEventListener('change', filterModalResults);
        }

        function filterModalResults() {
            const brandFilter = document.getElementById('modal_brand_filter').value;
            const warehouseFilter = document.getElementById('modal_warehouse_filter').value;
            const rows = document.querySelectorAll('#modal_search_results tbody tr');

            let visibleCount = 0;
            rows.forEach(row => {
                const brand = row.dataset.brand || '';
                const warehouses = row.dataset.warehouses || '';
                const warehouseStocks = row.dataset.warehouseStocks || '{}';

                const brandMatch = !brandFilter || brand === brandFilter;

                let warehouseMatch = true;
                if (warehouseFilter) {
                    try {
                        const stockData = JSON.parse(warehouseStocks);
                        // Check if the selected warehouse exists and has stock > 0
                        warehouseMatch = stockData[warehouseFilter] && parseFloat(stockData[warehouseFilter]) > 0;
                    } catch (e) {
                        // Fallback to simple string check if JSON parsing fails
                        warehouseMatch = warehouses.includes(warehouseFilter);
                    }
                }

                if (brandMatch && warehouseMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Update results count
            const resultsCountElement = document.getElementById('results_count');
            const totalProducts = rows.length;

            if (visibleCount === totalProducts) {
                resultsCountElement.textContent = visibleCount;
            } else {
                resultsCountElement.innerHTML = `${visibleCount} <small class="text-muted">de ${totalProducts}</small>`;
            }
        }

        function showImageModal(imageUrl, productName) {
            // Create image modal if it doesn't exist
            let imageModal = document.getElementById('imageModal');
            if (!imageModal) {
                const modalHTML = `
                    <div class="modal fade" id="imageModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Imagen del Producto</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <img id="modalImage" src="" class="img-fluid" style="max-height: 70vh;">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                imageModal = document.getElementById('imageModal');

                // Handle ESC key to close only image modal
                imageModal.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        e.stopPropagation();
                        const modal = bootstrap.Modal.getInstance(imageModal);
                        if (modal) modal.hide();
                    }
                });

                // Click on image or backdrop to close
                imageModal.addEventListener('click', function(e) {
                    if (e.target === imageModal || e.target.id === 'modalImage') {
                        const modal = bootstrap.Modal.getInstance(imageModal);
                        if (modal) modal.hide();
                    }
                });
            }

            // Update modal content
            document.querySelector('#imageModal .modal-title').textContent = productName;
            document.getElementById('modalImage').src = imageUrl;

            // Show modal
            const modal = new bootstrap.Modal(imageModal);
            modal.show();

            // Focus the modal so ESC works
            imageModal.focus();
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
            fetch(`<?= BASE_URL ?>/api/product_fichas.php?codigo=${encodeURIComponent(codigo)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.fichas.length > 0) {
                        body.innerHTML = data.fichas.map(f => {
                            const ext = f.ficha_url.split('.').pop().toLowerCase();
                            const icon = ext === 'pdf' ? 'fa-file-pdf text-danger' : 'fa-file-image text-info';
                            const nombre = f.nombre_archivo || ('Ficha ' + f.id);
                            return `<div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas ${icon}"></i>
                                <a href="${f.ficha_url}" target="_blank" class="text-decoration-none flex-grow-1">${nombre}</a>
                            </div>`;
                        }).join('');
                    } else {
                        body.innerHTML = '<p class="text-muted mb-0">No hay fichas técnicas registradas para este producto.</p>';
                    }
                })
                .catch(() => { body.innerHTML = '<p class="text-danger mb-0">Error al cargar fichas.</p>'; });
        }

        function selectProductFromModal(product) {
            // Close modal first
            const modal = bootstrap.Modal.getInstance(document.getElementById('productSearchModal'));
            modal.hide();

            // Add product to quotation
            selectProductFromSearch(product);
        }

        // Image attachment functions
        function loadProductImage(itemIndex, imageUrl, description) {
            const imageUrlInput = document.querySelector(`.item-row[data-item-index="${itemIndex}"] .item-image-url`);
            const imagePreview = document.querySelector(`.image-preview-${itemIndex}`);
            const imageInfo = document.querySelector(`.image-info-${itemIndex}`);

            if (imageUrlInput && imageUrl) {
                imageUrlInput.value = imageUrl;

                if (imagePreview) {
                    const img = imagePreview.querySelector('img');
                    img.src = imageUrl;
                    img.alt = description || 'Producto';
                    imagePreview.style.display = 'block';
                }

                if (imageInfo) {
                    imageInfo.textContent = 'Imagen del producto';
                }

                updateImageGallery();
            }
        }

        function removeProductImage(itemIndex) {
            const imageUrlInput = document.querySelector(`.item-row[data-item-index="${itemIndex}"] .item-image-url`);
            const imagePreview = document.querySelector(`.image-preview-${itemIndex}`);
            const imageInfo = document.querySelector(`.image-info-${itemIndex}`);

            if (imageUrlInput) {
                imageUrlInput.value = '';
            }

            if (imagePreview) {
                imagePreview.style.display = 'none';
            }

            if (imageInfo) {
                imageInfo.textContent = '';
            }

            updateImageGallery();
        }

        function promptImageUrl(itemIndex) {
            const currentUrl = document.querySelector(`.item-row[data-item-index="${itemIndex}"] .item-image-url`)?.value || '';
            const description = document.querySelector(`.item-row[data-item-index="${itemIndex}"] [name*="[description]"]`)?.value || 'Producto';

            const imageUrl = prompt('Ingrese la URL de la imagen del producto:', currentUrl);

            if (imageUrl !== null && imageUrl.trim() !== '') {
                loadProductImage(itemIndex, imageUrl.trim(), description);
            }
        }

        function updateImageGallery() {
            const gallery = document.getElementById('image-gallery-content');
            if (!gallery) return;

            const items = [];
            document.querySelectorAll('.item-row').forEach(row => {
                const imageUrl = row.querySelector('.item-image-url')?.value;
                const description = row.querySelector('[name*="[description]"]')?.value;

                if (imageUrl && description) {
                    items.push({ imageUrl, description });
                }
            });

            if (items.length === 0) {
                gallery.innerHTML = '<p class="text-center text-muted py-5">No hay imágenes adjuntas</p>';
                return;
            }

            // Sort by description
            items.sort((a, b) => a.description.localeCompare(b.description));

            let html = '<div class="row g-3">';
            items.forEach((item, index) => {
                // 2 images per row
                html += `
                    <div class="col-md-6 col-print-6">
                        <div class="card h-100 border-primary">
                            <div class="card-header bg-primary text-white py-2">
                                <h6 class="mb-0 fw-bold" style="font-size: 0.95rem;">
                                    <i class="fas fa-box me-1"></i> ${item.description}
                                </h6>
                            </div>
                            <div class="card-body text-center p-3" style="background: #f8f9fa;">
                                <img src="${item.imageUrl}"
                                     alt="${item.description}"
                                     class="img-fluid rounded"
                                     style="max-height: 280px; object-fit: contain; border: 1px solid #dee2e6;"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27200%27 height=%27200%27%3E%3Crect fill=%27%23f8f9fa%27 width=%27200%27 height=%27200%27/%3E%3Ctext fill=%27%23999%27 font-family=%27Arial%27 font-size=%2714%27 x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3EImagen no disponible%3C/text%3E%3C/svg%3E'">
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';

            gallery.innerHTML = html;
        }

        // Generate print-friendly HTML
        function generatePrintVersion() {
            console.log('Generating print version...');

            const printVersion = document.getElementById('print-version');
            if (!printVersion) return;

            // Get company info
            const companyName = '<?= htmlspecialchars($company['name'] ?? 'Empresa Demo') ?>';
            const companyAddress = '<?= htmlspecialchars($company['address'] ?? '') ?>';
            const companyPhone = '<?= htmlspecialchars($company['phone'] ?? '') ?>';
            const companyEmail = '<?= htmlspecialchars($company['email'] ?? '') ?>';
            const companyTaxId = '<?= htmlspecialchars($company['tax_id'] ?? 'N/A') ?>';
            const companyLogo = '<?= !empty($company['logo_url']) ? htmlspecialchars(upload_url($company['logo_url'])) : '' ?>';

            // Get user/seller data
            const sellerName = '<?= htmlspecialchars($user['username'] ?? '') ?>';
            const sellerEmail = '<?= htmlspecialchars($user['email'] ?? '') ?>';
            const sellerPhone = '<?= htmlspecialchars($user['phone'] ?? '') ?>';
            const sellerSignature = '<?= !empty($user['signature_url']) ? BASE_URL . '/' . htmlspecialchars($user['signature_url']) : '' ?>';

            // Get customer data from hidden input and customer display
            const customerId = document.getElementById('customer_id')?.value;
            let customerName = 'Cliente no seleccionado';
            let customerTaxId = '';
            let customerEmail = '';
            let customerPhone = '';
            let customerAddress = '';

            if (customerId && document.getElementById('selected_customer_display')) {
                const customerDetails = document.getElementById('customer_details_content');
                if (customerDetails) {
                    // Extract customer name
                    const nameElement = customerDetails.querySelector('h6');
                    if (nameElement) {
                        customerName = nameElement.textContent.replace('👤', '').trim();
                    }

                    // Extract customer details from the display
                    const detailsText = customerDetails.innerText;
                    const taxIdMatch = detailsText.match(/RUC\/DNI:\s*([^\n]+)/);
                    const emailMatch = detailsText.match(/Email:\s*([^\n]+)/);
                    const phoneMatch = detailsText.match(/Teléfono:\s*([^\n]+)/);
                    const addressMatch = detailsText.match(/Dirección:\s*([^\n]+)/);

                    if (taxIdMatch) customerTaxId = taxIdMatch[1].trim();
                    if (emailMatch) customerEmail = emailMatch[1].trim();
                    if (phoneMatch) customerPhone = phoneMatch[1].trim();
                    if (addressMatch) customerAddress = addressMatch[1].trim();
                }
            }

            const quotationDate = document.getElementById('quotation_date')?.value || '';
            const validUntil = document.getElementById('valid_until')?.value || '';
            const currency = document.getElementById('currency')?.value || 'USD';
            const currencySymbol = currency === 'PEN' ? 'S/.' : '$';

            // Get items
            const items = [];
            document.querySelectorAll('.item-row').forEach((row, index) => {
                const description = row.querySelector('[name*="[description]"]')?.value || '';
                const quantity = parseFloat(row.querySelector('[name*="[quantity]"]')?.value || 0);
                const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value || 0);
                const discount = parseFloat(row.querySelector('[name*="[discount_percentage]"]')?.value || 0);

                if (description && quantity > 0) {
                    const subtotal = quantity * unitPrice * (1 - discount / 100);
                    items.push({
                        num: index + 1,
                        description,
                        quantity,
                        unitPrice,
                        discount,
                        subtotal
                    });
                }
            });

            // Calculate totals
            const subtotal = items.reduce((sum, item) => sum + item.subtotal, 0);
            const globalDiscount = parseFloat(document.querySelector('[name="global_discount_percentage"]')?.value || 0);
            const discount = subtotal * (globalDiscount / 100);
            const igv = (subtotal - discount) * 0.18;
            const total = subtotal - discount + igv;

            // Get notes
            const notes = document.querySelector('[name="notes"]')?.value || '';
            const terms = document.querySelector('[name="terms_and_conditions"]')?.value || '';

            // Build HTML
            let html = `
                <div class="quotation-builder print-only" style="padding: 20px;">
                    <!-- Company Header -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                ${companyLogo ? `
                                <div class="col-2">
                                    <img src="${companyLogo}" alt="Logo" style="max-height: 60px; max-width: 100%;">
                                </div>` : ''}
                                <div class="col-${companyLogo ? '5' : '7'}">
                                    <h3 style="color: #0d6efd; margin-bottom: 5px;">${companyName}</h3>
                                    ${companyAddress ? `<p style="margin: 2px 0; font-size: 9pt;"><i class="fas fa-map-marker-alt"></i> ${companyAddress}</p>` : ''}
                                    ${companyPhone ? `<p style="margin: 2px 0; font-size: 9pt;"><i class="fas fa-phone"></i> ${companyPhone}</p>` : ''}
                                    <p style="margin: 2px 0; font-size: 9pt;"><strong>Vendedor:</strong> ${sellerName}</p>
                                    ${sellerEmail ? `<p style="margin: 2px 0; font-size: 9pt;"><i class="fas fa-envelope"></i> ${sellerEmail}</p>` : ''}
                                    ${sellerPhone ? `<p style="margin: 2px 0; font-size: 9pt;"><i class="fas fa-mobile-alt"></i> ${sellerPhone}</p>` : ''}
                                </div>
                                <div class="col-${companyLogo ? '5' : '5'} text-end">
                                    <h2 style="color: #0d6efd; margin-bottom: 5px;">COTIZACIÓN</h2>
                                    <p style="margin: 2px 0;"><strong>RUC:</strong> ${companyTaxId}</p>
                                    <p style="margin: 2px 0; font-size: 9pt;"><strong>Fecha:</strong> ${quotationDate}</p>
                                    ${validUntil ? `<p style="margin: 2px 0; font-size: 9pt;"><strong>Válida hasta:</strong> ${validUntil}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Datos del Cliente</strong>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <p style="margin: 2px 0;"><strong>Razón Social:</strong> ${customerName}</p>
                                    ${customerTaxId ? `<p style="margin: 2px 0;"><strong>RUC/DNI:</strong> ${customerTaxId}</p>` : ''}
                                    ${customerAddress ? `<p style="margin: 2px 0;"><strong>Dirección:</strong> ${customerAddress}</p>` : ''}
                                </div>
                                <div class="col-6">
                                    ${customerEmail ? `<p style="margin: 2px 0;"><strong>Email:</strong> ${customerEmail}</p>` : ''}
                                    ${customerPhone ? `<p style="margin: 2px 0;"><strong>Teléfono:</strong> ${customerPhone}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 45%;">Descripción</th>
                                    <th style="width: 10%; text-align: center;">Cantidad</th>
                                    <th style="width: 15%; text-align: right;">P. Unitario</th>
                                    <th style="width: 10%; text-align: center;">Desc. %</th>
                                    <th style="width: 15%; text-align: right;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.map(item => `
                                    <tr>
                                        <td style="text-align: center;">${item.num}</td>
                                        <td>${escapeHtml(item.description)}</td>
                                        <td style="text-align: center;">${item.quantity.toFixed(2)}</td>
                                        <td style="text-align: right;">${currencySymbol} ${item.unitPrice.toFixed(2)}</td>
                                        <td style="text-align: center;">${item.discount.toFixed(2)}%</td>
                                        <td style="text-align: right;">${currencySymbol} ${item.subtotal.toFixed(2)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="row">
                        <div class="col-6">
                            ${notes ? `
                            <div class="card">
                                <div class="card-header"><strong>Observaciones</strong></div>
                                <div class="card-body">
                                    <p style="margin: 0; font-size: 9pt;">${escapeHtml(notes)}</p>
                                </div>
                            </div>` : ''}
                        </div>
                        <div class="col-6">
                            <div class="totals-section">
                                <table style="width: 100%; border: none;">
                                    <tr>
                                        <td style="border: none; text-align: right; padding: 3px;"><strong>Subtotal:</strong></td>
                                        <td style="border: none; text-align: right; padding: 3px;">${currencySymbol} ${subtotal.toFixed(2)}</td>
                                    </tr>
                                    ${globalDiscount > 0 ? `
                                    <tr>
                                        <td style="border: none; text-align: right; padding: 3px;"><strong>Descuento (${globalDiscount}%):</strong></td>
                                        <td style="border: none; text-align: right; padding: 3px;">-${currencySymbol} ${discount.toFixed(2)}</td>
                                    </tr>` : ''}
                                    <tr>
                                        <td style="border: none; text-align: right; padding: 3px;"><strong>IGV (18%):</strong></td>
                                        <td style="border: none; text-align: right; padding: 3px;">${currencySymbol} ${igv.toFixed(2)}</td>
                                    </tr>
                                    <tr style="border-top: 2px solid #dee2e6;">
                                        <td style="border: none; text-align: right; padding: 5px;"><strong>TOTAL:</strong></td>
                                        <td style="border: none; text-align: right; padding: 5px;"><strong style="font-size: 12pt;">${currencySymbol} ${total.toFixed(2)}</strong></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    ${terms ? `
                    <div class="card mt-3">
                        <div class="card-header"><strong>Términos y Condiciones</strong></div>
                        <div class="card-body">
                            <p style="margin: 0; font-size: 9pt;">${escapeHtml(terms)}</p>
                        </div>
                    </div>` : ''}

                    <!-- Signatures Section -->
                    <div class="row mt-5" style="margin-top: 60px !important;">
                        <div class="col-6 text-center">
                            ${sellerSignature ? `
                                <div style="margin-bottom: 10px;">
                                    <img src="${sellerSignature}" alt="Firma" style="max-width: 200px; max-height: 80px; margin-bottom: -10px;">
                                </div>
                            ` : ''}
                            <div style="border-top: 2px solid #000; padding-top: 10px; margin: 0 40px;">
                                <p style="margin: 5px 0; font-size: 10pt;"><strong>${sellerName}</strong></p>
                                <p style="margin: 2px 0; font-size: 9pt;">Vendedor</p>
                                ${sellerEmail ? `<p style="margin: 2px 0; font-size: 8pt;">${sellerEmail}</p>` : ''}
                                ${sellerPhone ? `<p style="margin: 2px 0; font-size: 8pt;">${sellerPhone}</p>` : ''}
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div style="border-top: 2px solid #000; padding-top: 10px; margin: 0 40px; margin-top: ${sellerSignature ? '90px' : '0'};">
                                <p style="margin: 5px 0; font-size: 10pt;"><strong>${customerName}</strong></p>
                                <p style="margin: 2px 0; font-size: 9pt;">Cliente</p>
                                ${customerTaxId ? `<p style="margin: 2px 0; font-size: 8pt;">RUC/DNI: ${customerTaxId}</p>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            printVersion.innerHTML = html;
            console.log('Print version generated successfully');
        }

        // Update print version before printing
        window.addEventListener('beforeprint', function() {
            generatePrintVersion();
        });

        // Initialize everything on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');

            // Don't initialize with empty item - let user add items manually

            updateCurrencySymbols();
            initProductSearch();
            initCustomerSearch();
            initCustomerModal();
            initModalProductSearch();

            // Setup quotation date change listener
            document.getElementById('quotation_date').addEventListener('change', updateValidUntilDate);

            // Setup image attachment listeners and add item button
            document.addEventListener('click', function(e) {
                if (e.target.closest('.attach-image-btn')) {
                    const btn = e.target.closest('.attach-image-btn');
                    const itemIndex = btn.dataset.index;
                    promptImageUrl(itemIndex);
                } else if (e.target.closest('.remove-image-btn')) {
                    const btn = e.target.closest('.remove-image-btn');
                    const itemIndex = btn.dataset.index;
                    removeProductImage(itemIndex);
                } else if (e.target.closest('.add-item-btn')) {
                    addItem();
                } else if (e.target.closest('.remove-item-btn')) {
                    const btn = e.target.closest('.remove-item-btn');
                    const itemIndex = btn.dataset.index;
                    removeItem(itemIndex);
                }
            });

            // Initial gallery update
            updateImageGallery();
        });

        // Function to add a new item row
        function addItem() {
            const itemsContainer = document.querySelector('.items-container');
            const itemIndex = document.querySelectorAll('.item-row').length;

            const itemHtml = `
                <div class="item-row card mb-3" data-item-index="${itemIndex}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-2">
                                <div class="mb-3 position-relative">
                                    <label class="form-label">Código</label>
                                    <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id-hidden">
                                    <input type="text" class="form-control product-search-input"
                                           placeholder="Buscar código..."
                                           autocomplete="off"
                                           data-item-index="${itemIndex}">
                                    <div class="product-suggestions list-group position-absolute" style="z-index: 1050; max-height: 300px; overflow-y: auto; display: none; min-width: 350px;"></div>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <input type="text" class="form-control" name="items[${itemIndex}][description]" placeholder="Descripción del producto">
                                </div>
                            </div>

                            <div class="col-6 col-md-1">
                                <div class="mb-3">
                                    <label class="form-label">Cant.</label>
                                    <input type="number" class="form-control item-quantity" name="items[${itemIndex}][quantity]" placeholder="Cant." min="0.01" step="0.01" value="1">
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Precio Unit.</label>
                                    <input type="number" class="form-control item-price" name="items[${itemIndex}][unit_price]" placeholder="Precio" min="0.01" step="0.01" value="0">
                                    <div class="last-price-info mt-1" id="last-price-${itemIndex}" style="display: none;">
                                        <div class="alert alert-info py-1 px-2 mb-0" style="font-size: 0.75rem;">
                                            <i class="fas fa-history"></i>
                                            <span class="last-price-amount"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-1">
                                <div class="mb-3">
                                    <label class="form-label">Desc.%</label>
                                    <input type="number" class="form-control item-discount" name="items[${itemIndex}][discount_percentage]" placeholder="%" min="0" max="100" step="0.01" value="20">
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Total</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control item-total" name="items[${itemIndex}][line_total]" placeholder="Total" readonly>
                                        <span class="input-group-text currency-symbol">$</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-2 d-flex align-items-center gap-3 flex-wrap">
                                    <div class="image-preview-${itemIndex}" style="display: none;">
                                        <img src="" class="img-thumbnail" style="max-width: 60px; max-height: 60px; cursor: pointer;" onclick="showImageModal(this.src, 'Producto')" alt="Imagen">
                                    </div>
                                    <input type="hidden" class="item-image-url" name="items[${itemIndex}][image_url]" value="">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="promptImageUrl(${itemIndex})">
                                        <i class="fas fa-link"></i> Imagen
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary view-ficha-btn" title="Ver Ficha Técnica" onclick="viewFichasTecnicas(this.closest('.item-row').querySelector('.product-id-hidden').value)">
                                        <i class="fas fa-file-alt"></i> Ficha
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" data-index="${itemIndex}">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            itemsContainer.insertAdjacentHTML('beforeend', itemHtml);

            // Hide empty message if this is the first item
            const emptyMessage = document.getElementById('empty-items-message');
            if (emptyMessage) {
                emptyMessage.style.display = 'none';
            }

            // Add event listeners for the new item
            const newItemRow = itemsContainer.lastElementChild;
            const productSearchInput = newItemRow.querySelector('.product-search-input');
            const quantityInput = newItemRow.querySelector('.item-quantity');
            const priceInput = newItemRow.querySelector('.item-price');
            const discountInput = newItemRow.querySelector('.item-discount');

            // Setup product search autocomplete
            setupProductSearchAutocomplete(productSearchInput);

            quantityInput.addEventListener('input', calculateTotals);
            priceInput.addEventListener('input', calculateTotals);
            discountInput.addEventListener('input', calculateTotals);

            // Update currency symbols
            updateCurrencySymbols();
        }

        // Product search autocomplete with debounce
        function setupProductSearchAutocomplete(input) {
            const suggestionsContainer = input.nextElementSibling;
            const itemIndex = input.dataset.itemIndex;

            input.addEventListener('input', function() {
                const searchTerm = this.value.trim();

                // Clear previous timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }

                // Hide suggestions if search term is too short
                if (searchTerm.length < 2) {
                    suggestionsContainer.style.display = 'none';
                    return;
                }

                // Debounce: wait 300ms before searching
                searchTimeout = setTimeout(() => {
                    fetchProductSuggestions(searchTerm, suggestionsContainer, input);
                }, 300);
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.style.display = 'none';
                }
            });

            // Navigate suggestions with keyboard
            input.addEventListener('keydown', function(e) {
                const items = suggestionsContainer.querySelectorAll('.list-group-item');
                const activeItem = suggestionsContainer.querySelector('.list-group-item.active');
                let currentIndex = Array.from(items).indexOf(activeItem);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (currentIndex < items.length - 1) {
                        if (activeItem) activeItem.classList.remove('active');
                        items[currentIndex + 1].classList.add('active');
                        items[currentIndex + 1].scrollIntoView({ block: 'nearest' });
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (currentIndex > 0) {
                        if (activeItem) activeItem.classList.remove('active');
                        items[currentIndex - 1].classList.add('active');
                        items[currentIndex - 1].scrollIntoView({ block: 'nearest' });
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeItem) {
                        activeItem.click();
                    }
                } else if (e.key === 'Escape') {
                    suggestionsContainer.style.display = 'none';
                }
            });
        }

        function fetchProductSuggestions(searchTerm, container, input) {
            container.innerHTML = '<div class="list-group-item text-center"><div class="spinner-border spinner-border-sm"></div> Buscando...</div>';
            container.style.display = 'block';

            fetch(`<?= BASE_URL ?>/api/search_products.php?search=${encodeURIComponent(searchTerm)}`, {
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    container.innerHTML = '';
                    data.products.slice(0, 15).forEach(product => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action py-2';
                        item.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-primary">${product.codigo}</strong>
                                    <small class="d-block text-truncate" style="max-width: 200px;">${product.descripcion}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success">$${parseFloat(product.precio).toFixed(2)}</span>
                                    <small class="d-block text-muted">Stock: ${product.total_stock}</small>
                                </div>
                            </div>
                        `;
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectProductFromSuggestion(product, input);
                            container.style.display = 'none';
                        });
                        container.appendChild(item);
                    });

                    if (data.products.length > 15) {
                        const moreItem = document.createElement('div');
                        moreItem.className = 'list-group-item text-center text-muted small';
                        moreItem.textContent = `+${data.products.length - 15} productos más. Refine su búsqueda.`;
                        container.appendChild(moreItem);
                    }
                } else {
                    container.innerHTML = '<div class="list-group-item text-muted">No se encontraron productos</div>';
                }
            })
            .catch(error => {
                container.innerHTML = '<div class="list-group-item text-danger">Error al buscar</div>';
            });
        }

        function selectProductFromSuggestion(product, input) {
            const itemRow = input.closest('.item-row');
            const itemIndex = itemRow.dataset.itemIndex;

            // Set product code in search input
            input.value = product.codigo;

            // Set hidden product_id
            const productIdHidden = itemRow.querySelector('.product-id-hidden');
            if (productIdHidden) {
                productIdHidden.value = product.codigo;
            }

            // Fill description
            const descInput = itemRow.querySelector('[name*="[description]"]');
            if (descInput) {
                descInput.value = product.descripcion;
            }

            // Calculate converted price based on quotation currency
            const originalPrice = parseFloat(product.precio) || 0;
            const productCurrency = product.price_currency || 'USD';
            const quotationCurrency = document.getElementById('currency').value;
            let price = originalPrice;

            // Convert price if currencies don't match
            if (quotationCurrency !== productCurrency) {
                if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                    price = price * parseFloat(exchangeRate);
                } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                    price = price / parseFloat(exchangeRate);
                }
            }

            // El precio unitario siempre es el precio de lista (sin modificar por IGV)
            // El IGV se aplica solo en el cálculo del total

            // Fill price
            const priceInput = itemRow.querySelector('.item-price');
            if (priceInput) {
                priceInput.value = price.toFixed(2);
                priceInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            // Store original product data for currency conversion
            itemRow.dataset.originalPrice = originalPrice;
            itemRow.dataset.productCurrency = productCurrency;

            // Set quantity to 1 if empty
            const quantityInput = itemRow.querySelector('.item-quantity');
            if (quantityInput && (!quantityInput.value || quantityInput.value == '0')) {
                quantityInput.value = 1;
                quantityInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            // Load product image if available
            const imageUrl = product.image_url || product.imagen_url;
            if (imageUrl) {
                loadProductImage(itemIndex, imageUrl, product.descripcion);
            }

            // Calculate totals
            calculateTotals();

            // Get last quoted price if customer is selected
            const customerId = document.querySelector('[name="customer_id"]')?.value;
            if (customerId && product.codigo) {
                getLastQuotedPrice(product.codigo, customerId, itemRow);
            }
        }

        // Function to remove an item row
        function removeItem(itemIndex) {
            const itemRow = document.querySelector(`.item-row[data-item-index="${itemIndex}"]`);
            if (itemRow) {
                itemRow.remove();

                // Reindex remaining items
                const remainingItems = document.querySelectorAll('.item-row');
                if (remainingItems.length === 0) {
                    // Show empty message if no items left
                    const emptyMessage = document.getElementById('empty-items-message');
                    if (emptyMessage) {
                        emptyMessage.style.display = 'block';
                    }
                }

                remainingItems.forEach((row, newIndex) => {
                    row.dataset.itemIndex = newIndex;

                    // Update all input names and IDs
                    const inputs = row.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        if (input.name) {
                            input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
                        }
                        if (input.id) {
                            input.id = input.id.replace(/-\d+$/, `-${newIndex}`);
                        }
                    });

                    // Update button data attributes
                    const buttons = row.querySelectorAll('button[data-index]');
                    buttons.forEach(btn => {
                        btn.dataset.index = newIndex;
                    });

                    // Update last price element ID
                    const lastPriceEl = row.querySelector('.last-price-info');
                    if (lastPriceEl) {
                        lastPriceEl.id = `last-price-${newIndex}`;
                    }
                });

                calculateTotals();
                updateImageGallery();
            }
        }

        // Function to get last quoted price for a product and customer
        function getLastQuotedPrice(productId, customerId, itemRow) {
            if (!productId || !customerId) return;

            const itemIndex = itemRow.dataset.itemIndex;
            const lastPriceElement = document.getElementById(`last-price-${itemIndex}`);

            if (!lastPriceElement) {
                console.warn(`Last price element not found for index ${itemIndex}`);
                return;
            }

            fetch(`<?= BASE_URL ?>/api/get_last_quoted_price.php?product_id=${productId}&customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Last price data received:', data);
                    if (data.success && data.last_price !== null) {
                        // Use the currency from the last quotation
                        const currencySymbol = data.currency_symbol || (data.currency === 'PEN' ? 'S/.' : '$');
                        const priceInfo = `${currencySymbol} ${data.formatted_price}`;

                        // Show quotation info
                        let infoText = `Último precio cotizado: ${priceInfo}`;
                        if (data.quotation_number) {
                            infoText += ` (Cot. ${data.quotation_number})`;
                        }
                        if (data.discount_percentage > 0) {
                            infoText += ` - Desc. ${data.discount_percentage}%`;
                        }

                        lastPriceElement.querySelector('.last-price-amount').textContent = infoText;
                        lastPriceElement.style.display = 'block';
                    } else {
                        console.log('No previous price found');
                        lastPriceElement.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error getting last quoted price:', error);
                    lastPriceElement.style.display = 'none';
                });
        }

        // Function to update last prices for all items when customer changes
        function updateAllLastPrices() {
            const customerId = document.getElementById('customer_id').value;
            if (!customerId) return;

            document.querySelectorAll('.item-row').forEach(row => {
                const productSelect = row.querySelector('.product-select');
                if (productSelect && productSelect.value) {
                    getLastQuotedPrice(productSelect.value, customerId, row);
                }
            });
        }

        // Update last prices when customer changes
        document.getElementById('customer_id').addEventListener('change', updateAllLastPrices);

        // Prepare form data before submission
        document.querySelector('form').addEventListener('submit', function(e) {
            console.log('Form submit triggered');

            // Re-index all items to ensure proper numbering
            document.querySelectorAll('.item-row').forEach((row, newIndex) => {
                row.dataset.itemIndex = newIndex;
                const inputs = row.querySelectorAll('input, select');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
                    }
                });
            });

            console.log('Form submission completed');
        });

        // Function to show customer quotation history
        function showCustomerHistory(customerId) {
            // Create modal if it doesn't exist
            let historyModal = document.getElementById('customerHistoryModal');
            if (!historyModal) {
                const modalHTML = `
                    <div class="modal fade" id="customerHistoryModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Historial de Cotizaciones del Cliente</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="history-loading" class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="sr-only">Cargando...</span>
                                        </div>
                                        <p>Cargando historial...</p>
                                    </div>
                                    <div id="history-content" style="display: none;">
                                        <div id="history-list"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                historyModal = document.getElementById('customerHistoryModal');
            }

            // Show loading
            document.getElementById('history-loading').style.display = 'block';
            document.getElementById('history-content').style.display = 'none';

            // Fetch history
            fetch(`<?= BASE_URL ?>/api/quotation_history.php?action=customer_history&customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('history-loading').style.display = 'none';
                    document.getElementById('history-content').style.display = 'block';

                    if (data.success && data.data.length > 0) {
                        let html = '<div class="list-group">';
                        data.data.forEach(quotation => {
                            const statusClass = getStatusClass(quotation.status);
                            const statusText = translateStatus(quotation.status);
                            const currencySymbol = getCurrencySymbol();
                            html += `
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Cotización ${quotation.quotation_number}</h6>
                                        <small class="text-muted">${quotation.created_at}</small>
                                    </div>
                                    <p class="mb-1">
                                        <span class="badge ${statusClass}">${statusText}</span>
                                        ${quotation.item_count} producto(s)
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Total: ${currencySymbol} ${quotation.total}</small>
                                        <a href="<?= BASE_URL ?>/quotations/view.php?id=${quotation.id}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        document.getElementById('history-list').innerHTML = html;
                    } else {
                        document.getElementById('history-list').innerHTML = '<p class="text-center text-muted">No se encontraron cotizaciones anteriores para este cliente.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading history:', error);
                    document.getElementById('history-loading').style.display = 'none';
                    document.getElementById('history-content').style.display = 'block';
                    document.getElementById('history-list').innerHTML = '<p class="text-center text-danger">Error al cargar el historial.</p>';
                });

            // Show modal
            const modal = new bootstrap.Modal(historyModal);
            modal.show();
        }

        // Helper function for status classes
        function getStatusClass(status) {
            switch (status) {
                case 'Draft': return 'bg-secondary';
                case 'Sent': return 'bg-primary';
                case 'Accepted': return 'bg-success';
                case 'Rejected': return 'bg-danger';
                case 'Expired': return 'bg-warning';
                default: return 'bg-secondary';
            }
        }

        // Helper function to translate status to Spanish
        function translateStatus(status) {
            switch (status) {
                case 'Draft': return 'Borrador';
                case 'Sent': return 'Enviada';
                case 'Accepted': return 'Aceptada';
                case 'Rejected': return 'Rechazada';
                case 'Expired': return 'Vencida';
                default: return status;
            }
        }

        // Global variable to store all history data for filtering
        let allHistoryData = [];

        // Function to show product history by date modal
        function showProductHistoryByDate() {
            // Set default dates (last 30 days)
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);

            document.getElementById('history_date_from').value = thirtyDaysAgo.toISOString().split('T')[0];
            document.getElementById('history_date_to').value = today.toISOString().split('T')[0];

            // Clear search filters
            document.getElementById('history_search_code').value = '';
            document.getElementById('history_search_description').value = '';

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('productHistoryByDateModal'));
            modal.show();

            // Load data automatically
            loadProductHistoryByDate();
        }

        // Function to load product history by date
        function loadProductHistoryByDate() {
            const dateFrom = document.getElementById('history_date_from').value;
            const dateTo = document.getElementById('history_date_to').value;
            const customerId = document.getElementById('customer_id').value || null;

            // Show loading
            document.getElementById('history_date_loading').style.display = 'block';
            document.getElementById('history_date_results').style.display = 'none';
            document.getElementById('history_date_no_results').style.display = 'none';

            // Build URL with parameters
            let url = `<?= BASE_URL ?>/api/quotation_history.php?action=products_by_date`;
            if (dateFrom) url += `&date_from=${dateFrom}`;
            if (dateTo) url += `&date_to=${dateTo}`;
            if (customerId) url += `&customer_id=${customerId}`;

            // Fetch data
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('history_date_loading').style.display = 'none';

                    if (data.success && data.data.length > 0) {
                        // Store all data for filtering
                        allHistoryData = data.data;

                        // Display all data initially
                        displayHistoryData(allHistoryData);
                    } else {
                        allHistoryData = [];
                        document.getElementById('history_date_no_results').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading product history:', error);
                    allHistoryData = [];
                    document.getElementById('history_date_loading').style.display = 'none';
                    document.getElementById('history_date_no_results').style.display = 'block';
                });
        }

        // Function to display history data in the table
        function displayHistoryData(dataToDisplay) {
            const tbody = document.getElementById('history_date_table_body');
            tbody.innerHTML = '';

            if (dataToDisplay.length === 0) {
                document.getElementById('history_date_results').style.display = 'none';
                document.getElementById('history_date_no_results').style.display = 'block';
                document.getElementById('history_results_count').textContent = '';
                return;
            }

            document.getElementById('history_date_results').style.display = 'block';
            document.getElementById('history_date_no_results').style.display = 'none';

            dataToDisplay.forEach(item => {
                const statusClass = getStatusClass(item.status);
                const statusText = translateStatus(item.status);
                const currencySymbol = item.currency === 'PEN' ? 'S/.' : '$';

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="font-size: 0.85rem;">${formatDate(item.quotation_date)}</td>
                    <td style="font-size: 0.85rem;"><strong>${item.quotation_number}</strong></td>
                    <td style="font-size: 0.85rem;">${escapeHtml(item.customer_name)}</td>
                    <td style="font-size: 0.85rem;">${escapeHtml(item.customer_tax_id)}</td>
                    <td style="font-size: 0.85rem;">${escapeHtml(item.product_code || '-')}</td>
                    <td style="font-size: 0.85rem;">${escapeHtml(item.product_description)}</td>
                    <td class="text-end" style="font-size: 0.85rem;">${parseFloat(item.quantity).toFixed(2)}</td>
                    <td class="text-end" style="font-size: 0.85rem;">${currencySymbol} ${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td class="text-end" style="font-size: 0.85rem;">${parseFloat(item.discount_percentage || 0).toFixed(2)}%</td>
                    <td class="text-end" style="font-size: 0.85rem;">${currencySymbol} ${parseFloat(item.subtotal).toFixed(2)}</td>
                    <td style="font-size: 0.85rem;">${item.currency}</td>
                    <td><span class="badge ${statusClass}" style="font-size: 0.75rem;">${statusText}</span></td>
                `;
                tbody.appendChild(row);
            });

            // Update results count
            const totalRecords = allHistoryData.length;
            const displayedRecords = dataToDisplay.length;
            if (displayedRecords < totalRecords) {
                document.getElementById('history_results_count').textContent =
                    `Mostrando ${displayedRecords} de ${totalRecords} registros`;
            } else {
                document.getElementById('history_results_count').textContent =
                    `Total: ${totalRecords} registros`;
            }
        }

        // Function to filter the history table
        function filterHistoryTable() {
            const searchCode = document.getElementById('history_search_code').value.toLowerCase().trim();
            const searchDescription = document.getElementById('history_search_description').value.toLowerCase().trim();

            if (!searchCode && !searchDescription) {
                // No filters, show all data
                displayHistoryData(allHistoryData);
                return;
            }

            // Filter the data
            const filteredData = allHistoryData.filter(item => {
                const code = (item.product_code || '').toLowerCase();
                const description = (item.product_description || '').toLowerCase();

                let matchesCode = true;
                let matchesDescription = true;

                if (searchCode) {
                    matchesCode = code.includes(searchCode);
                }

                if (searchDescription) {
                    matchesDescription = description.includes(searchDescription);
                }

                return matchesCode && matchesDescription;
            });

            displayHistoryData(filteredData);
        }

        // Function to clear search filters
        function clearHistoryFilters() {
            document.getElementById('history_search_code').value = '';
            document.getElementById('history_search_description').value = '';
            displayHistoryData(allHistoryData);
        }

        // Add Enter key support for search fields
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('history_search_code');
            const descInput = document.getElementById('history_search_description');

            if (codeInput) {
                codeInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        filterHistoryTable();
                    }
                });
            }

            if (descInput) {
                descInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        filterHistoryTable();
                    }
                });
            }
        });

        // Function to export history to Excel
        function exportHistoryToExcel() {
            const table = document.getElementById('historyTable');
            if (!table) {
                alert('No hay datos para exportar');
                return;
            }

            // Get table HTML
            let html = '<html><head><meta charset="UTF-8">';
            html += '<style>table { border-collapse: collapse; } th, td { border: 1px solid #000; padding: 5px; }</style>';
            html += '</head><body>';
            html += '<h2>Historial de Productos Cotizados</h2>';
            html += '<p>Fecha de exportación: ' + new Date().toLocaleString('es-PE') + '</p>';
            html += table.outerHTML;
            html += '</body></html>';

            // Create blob and download
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `historial_productos_${new Date().toISOString().split('T')[0]}.xls`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Helper function to format date
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Function to download PDF
        async function downloadPDF() {
            // Validate that there are items
            const items = document.querySelectorAll('.item-row');
            if (items.length === 0) {
                alert('Por favor, agrega al menos un producto antes de descargar el PDF.');
                return;
            }

            const customerId = document.getElementById('customer_id')?.value;
            if (!customerId) {
                alert('Por favor, selecciona un cliente primero.');
                return;
            }

            // Show loading
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                // Step 1: Save quotation
                const formData = new FormData(document.getElementById('quotationForm'));
                const saveResponse = await fetch('<?= BASE_URL ?>/api/save_quotation.php', {
                    method: 'POST',
                    body: formData
                });

                const saveResult = await saveResponse.json();

                if (!saveResult.success) {
                    throw new Error(saveResult.message || 'Error al guardar la cotización');
                }

                const quotationId = saveResult.quotation_id;
                console.log('Cotización guardada con ID:', quotationId);

                // Step 2: Download PDF
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';

                // Create a temporary link to download the PDF
                const pdfUrl = `<?= BASE_URL ?>/quotations/pdf.php?id=${quotationId}&download=1`;
                const link = document.createElement('a');
                link.href = pdfUrl;
                link.download = `Cotizacion_${quotationId}.pdf`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Success message
                setTimeout(() => {
                    alert('✅ Cotización guardada y PDF generado exitosamente');
                    // Redirect to view page
                    window.location.href = `<?= BASE_URL ?>/quotations/view.php?id=${quotationId}`;
                }, 500);

            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Function to send by email
        async function sendByEmail() {
            // Validate that there are items
            const items = document.querySelectorAll('.item-row');
            if (items.length === 0) {
                alert('Por favor, agrega al menos un producto antes de enviar.');
                return;
            }

            const customerId = document.getElementById('customer_id')?.value;
            if (!customerId) {
                alert('Por favor, selecciona un cliente primero.');
                return;
            }

            const customerEmail = document.querySelector('#customer_details_content')?.innerText.match(/Email:\s*([^\n]+)/)?.[1]?.trim();
            if (!customerEmail) {
                alert('El cliente seleccionado no tiene un correo electrónico registrado.');
                return;
            }

            // Show confirmation
            if (!confirm(`¿Deseas enviar esta cotización por correo electrónico a ${customerEmail}?`)) {
                return;
            }

            // Show loading indicator
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                // Step 1: Save quotation
                const formData = new FormData(document.getElementById('quotationForm'));
                const saveResponse = await fetch('<?= BASE_URL ?>/api/save_quotation.php', {
                    method: 'POST',
                    body: formData
                });

                const saveResult = await saveResponse.json();

                if (!saveResult.success) {
                    throw new Error(saveResult.message || 'Error al guardar la cotización');
                }

                const quotationId = saveResult.quotation_id;
                console.log('Cotización guardada con ID:', quotationId);

                // Step 2: Send email with PDF
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando correo...';

                const emailFormData = new FormData();
                emailFormData.append('id', quotationId);

                const emailResponse = await fetch('<?= BASE_URL ?>/quotations/send_email.php', {
                    method: 'POST',
                    body: emailFormData
                });

                const emailResult = await emailResponse.json();

                // Check if needs confirmation (quotation already accepted)
                if (!emailResult.success && emailResult.needs_confirmation) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;

                    if (confirm('⚠️ ' + emailResult.message)) {
                        // User confirmed - resend with force flag
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reenviando...';

                        const forceEmailFormData = new FormData();
                        forceEmailFormData.append('id', quotationId);
                        forceEmailFormData.append('force_resend', '1');

                        const forceEmailResponse = await fetch('<?= BASE_URL ?>/quotations/send_email.php', {
                            method: 'POST',
                            body: forceEmailFormData
                        });

                        const forceEmailResult = await forceEmailResponse.json();

                        if (!forceEmailResult.success) {
                            throw new Error(forceEmailResult.message || 'Error al reenviar el correo');
                        }

                        alert('✅ Cotización reenviada exitosamente a ' + customerEmail);
                        window.location.href = `<?= BASE_URL ?>/quotations/view.php?id=${quotationId}`;
                    }
                    return;
                }

                if (!emailResult.success) {
                    throw new Error(emailResult.message || 'Error al enviar el correo');
                }

                // Success
                alert('✅ Cotización guardada y enviada exitosamente a ' + customerEmail);

                // Redirect to view page
                window.location.href = `<?= BASE_URL ?>/quotations/view.php?id=${quotationId}`;

            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Function to send by WhatsApp
        async function sendByWhatsApp() {
            // Validate that there are items
            const items = document.querySelectorAll('.item-row');
            if (items.length === 0) {
                alert('Por favor, agrega al menos un producto antes de enviar por WhatsApp.');
                return;
            }

            const customerId = document.getElementById('customer_id')?.value;
            if (!customerId) {
                alert('Por favor, selecciona un cliente primero.');
                return;
            }

            const customerPhone = document.querySelector('#customer_details_content')?.innerText.match(/Teléfono:\s*([^\n]+)/)?.[1]?.trim();
            if (!customerPhone) {
                alert('El cliente seleccionado no tiene un número de teléfono registrado.');
                return;
            }

            // Show confirmation
            if (!confirm('¿Deseas enviar esta cotización por WhatsApp?')) {
                return;
            }

            // Show loading
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparando...';

            try {
                // Step 1: Save quotation
                const formData = new FormData(document.getElementById('quotationForm'));
                const saveResponse = await fetch('<?= BASE_URL ?>/api/save_quotation.php', {
                    method: 'POST',
                    body: formData
                });

                const saveResult = await saveResponse.json();

                if (!saveResult.success) {
                    throw new Error(saveResult.message || 'Error al guardar la cotización');
                }

                const quotationId = saveResult.quotation_id;
                console.log('Cotización guardada con ID:', quotationId);

                // Step 2: Prepare WhatsApp message
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Abriendo WhatsApp...';

                const whatsappFormData = new FormData();
                whatsappFormData.append('id', quotationId);

                const whatsappResponse = await fetch('<?= BASE_URL ?>/quotations/send_whatsapp.php', {
                    method: 'POST',
                    body: whatsappFormData
                });

                const whatsappResult = await whatsappResponse.json();

                if (!whatsappResult.success) {
                    throw new Error(whatsappResult.message || 'Error al preparar WhatsApp');
                }

                // Step 3: Show WhatsApp modal
                const msgText = waInjectEmoji(whatsappResult.message_text || '');
                const phone   = whatsappResult.phone || '';
                document.getElementById('waModalText').value = msgText;
                document.getElementById('waOpenWeb').onclick  = () => window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msgText)}`, '_blank');
                document.getElementById('waOpenDesk').onclick = () => { window.location.href = `whatsapp://send?phone=${phone}&text=${encodeURIComponent(msgText)}`; };
                new bootstrap.Modal(document.getElementById('waModal')).show();

                btn.disabled = false;
                btn.innerHTML = originalText;
                // Redirect after modal closes
                document.getElementById('waModal').addEventListener('hidden.bs.modal', () => {
                    window.location.href = `<?= BASE_URL ?>/quotations/view.php?id=${quotationId}`;
                }, { once: true });

            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        // Replace ASCII placeholders with real emoji (JS-side to avoid PHP/IIS encoding issues)
        function waInjectEmoji(text) {
            return text
                .replace(/\[E:CIRCLE\]/g,  '\uD83D\uDD35')
                .replace(/\[E:DOC\]/g,     '\uD83D\uDCC4')
                .replace(/\[E:MAIL\]/g,    '\u2709\uFE0F')
                .replace(/\[E:CHECK\]/g,   '\u2705')
                .replace(/\[E:POINT\]/g,   '\uD83D\uDC49')
                .replace(/\[E:CARD\]/g,    '\uD83D\uDCB3')
                .replace(/\[E:DOLLAR\]/g,  '\uD83D\uDCB5')
                .replace(/\[E:MONEY\]/g,   '\uD83D\uDCB0')
                .replace(/\[E:WARN\]/g,    '\u26A0\uFE0F')
                .replace(/\[E:PIN\]/g,     '\uD83D\uDCCC')
                .replace(/\[E:PHONE\]/g,   '\uD83D\uDCDE');
        }

        // ============================================
        // COTI RAPI - Cotización Rápida para WhatsApp
        // ============================================
        async function generateCotiRapi() {
            try {
                // Fetch template from server
                const response = await fetch('<?= BASE_URL ?>/api/get_cotirapi_template.php');
                const data = await response.json();

                if (!data.success || !data.template) {
                    alert('⚠️ Error al cargar la plantilla');
                    return;
                }

                const template = data.template;

                // Get customer info
                const customerName = document.getElementById('customer_name')?.value || 'Cliente';

                // Get items from items-container
                const itemRows = document.querySelectorAll('.item-row');

                if (itemRows.length === 0) {
                    alert('⚠️ No hay productos agregados en la cotización');
                    return;
                }

                // Get currency symbol
                const currencySelect = document.getElementById('currency');
                const currency = currencySelect ? currencySelect.value : 'USD';
                const currencySymbol = currency === 'PEN' ? 'S/' : '$';

                // Format date
                const today = new Date();
                const formattedDate = today.toLocaleDateString('es-PE');

                // Start with header
                let text = template.template_header || '';
                text = text.replace(/{CUSTOMER_NAME}/g, customerName);
                text = text.replace(/{DATE}/g, formattedDate);
                text = text.replace(/{CURRENCY}/g, currencySymbol);

                let subtotal = 0;
                let itemCount = 0;
                let itemsText = '';

                // Process each item
                itemRows.forEach((row, index) => {
                    // Extract data from inputs in this row
                    const codeInput = row.querySelector('input[name*="[code]"]');
                    const descInput = row.querySelector('textarea[name*="[description]"], input[name*="[description]"]');
                    const qtyInput = row.querySelector('input[name*="[quantity]"]');
                    const priceInput = row.querySelector('input[name*="[unit_price]"]');
                    const discountInput = row.querySelector('input[name*="[discount_percentage]"]');

                    const codigo = codeInput ? codeInput.value.trim() : '';
                    const descripcion = descInput ? descInput.value.trim() : '';
                    const cantidad = qtyInput ? parseFloat(qtyInput.value) || 0 : 0;
                    const precioOriginal = priceInput ? parseFloat(priceInput.value) || 0 : 0;
                    const descuento = discountInput ? parseFloat(discountInput.value) || 0 : 0;

                    // Calculate price with discount
                    let precioUni = precioOriginal;
                    if (descuento > 0) {
                        precioUni = precioOriginal * (1 - descuento / 100);
                    }

                    if (descripcion && cantidad > 0) {
                        itemCount++;
                        const total = cantidad * precioUni;
                        subtotal += total;

                        // Build item text using template
                        let itemText = template.template_item || '';
                        itemText = itemText.replace(/{ITEM_NUMBER}/g, itemCount);
                        itemText = itemText.replace(/{CODE}/g, codigo);

                        // CODE_LINE: show code line only if code exists
                        const codeLine = codigo ? `   🏷️ Código: ${codigo}\n` : '';
                        itemText = itemText.replace(/{CODE_LINE}/g, codeLine);

                        itemText = itemText.replace(/{DESCRIPTION}/g, descripcion);
                        itemText = itemText.replace(/{QUANTITY}/g, cantidad);
                        itemText = itemText.replace(/{UNIT_PRICE}/g, precioUni.toFixed(2));

                        // DISCOUNT_LINE: show discount line only if discount > 0
                        const discountLine = descuento > 0 ? `   🎯 Descuento: ${descuento}%\n` : '';
                        itemText = itemText.replace(/{DISCOUNT_LINE}/g, discountLine);

                        // IMAGE_URL and IMAGE_LINE: get image from hidden input if exists
                        const imageInput = row.querySelector('input[name*="[image_url]"]');
                        const imageUrl = imageInput ? imageInput.value : '';
                        const imageLine = imageUrl ? `   🖼️ Ver imagen: ${imageUrl}\n` : '';
                        itemText = itemText.replace(/{IMAGE_URL}/g, imageUrl);
                        itemText = itemText.replace(/{IMAGE_LINE}/g, imageLine);

                        itemText = itemText.replace(/{TOTAL}/g, total.toFixed(2));
                        itemText = itemText.replace(/{CURRENCY}/g, currencySymbol);

                        itemsText += itemText;
                    }
                });

                if (itemCount === 0) {
                    alert('⚠️ No hay productos válidos en la cotización');
                    return;
                }

                // Add items to text
                text += itemsText;

                // Calculate IGV and grand total
                const igvAmount = subtotal * 0.18;
                const grandTotal = subtotal + igvAmount;

                // Add footer
                let footer = template.template_footer || '';
                footer = footer.replace(/{SUBTOTAL}/g, subtotal.toFixed(2));
                footer = footer.replace(/{IGV}/g, igvAmount.toFixed(2));
                footer = footer.replace(/{GRAND_TOTAL}/g, grandTotal.toFixed(2));
                footer = footer.replace(/{CURRENCY}/g, currencySymbol);

                text += footer;

                // Show modal with text
                document.getElementById('cotiRapiText').value = text;
                const modal = new bootstrap.Modal(document.getElementById('cotiRapiModal'));
                modal.show();

            } catch (error) {
                console.error('Error generating CotiRapi:', error);
                alert('⚠️ Error al generar la cotización rápida');
            }
        }

        function copyCotiRapi() {
            const textArea = document.getElementById('cotiRapiText');
            textArea.select();
            document.execCommand('copy');

            // Change button text temporarily
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

    <!-- CotiRapi Modal -->
    <div class="modal fade" id="cotiRapiModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-bolt"></i> CotiRapi - Cotización Rápida
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Copia este texto</strong> y pégalo directamente en WhatsApp para enviar una cotización rápida a tu cliente.
                    </div>
                    <textarea id="cotiRapiText" class="form-control" rows="20" style="font-family: monospace; font-size: 0.9rem;" readonly></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary" id="copyCotiRapiBtn" onclick="copyCotiRapi()">
                        <i class="fas fa-copy"></i> Copiar al Portapapeles
                    </button>
                    <button type="button" class="btn btn-success" onclick="sendCotiRapiWhatsApp()">
                        <i class="fab fa-whatsapp"></i> Abrir WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Modal -->
    <div class="modal fade" id="waModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#25D366 !important;color:#fff !important;">
                    <h5 class="modal-title"><i class="fab fa-whatsapp me-2"></i>Enviar por WhatsApp</h5>
                    <button type="button" data-bs-dismiss="modal"
                            style="background:none;border:none;color:#fff;font-size:1.6rem;line-height:1;padding:0 4px;cursor:pointer;opacity:.9;">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Revisa el mensaje, cópialo o ábrelo directamente en WhatsApp:</p>
                    <textarea id="waModalText" class="form-control font-monospace" rows="14" readonly
                              style="font-size:13px;resize:none;white-space:pre;"></textarea>
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button id="waCopyBtn" type="button" class="btn btn-outline-secondary"
                            onclick="const ta=document.getElementById('waModalText');ta.select();document.execCommand('copy');this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar',2000);">
                        <i class="fas fa-copy me-1"></i>Copiar
                    </button>
                    <button id="waOpenDesk" type="button" class="btn btn-outline-success">
                        <i class="fab fa-whatsapp me-1"></i>Abrir WhatsApp Escritorio
                    </button>
                    <button id="waOpenWeb" type="button" class="btn btn-success">
                        <i class="fas fa-globe me-1"></i>Abrir WhatsApp Web
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
