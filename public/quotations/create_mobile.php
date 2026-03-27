<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Initialize repositories
$customerRepo = new Customer();
$productRepo = new Product();

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

// Get company settings
$companySettings = new CompanySettings();
$exchangeRate = $companySettings->getSetting($companyId, 'exchange_rate_usd_pen');
$exchangeRate = !empty($exchangeRate) ? floatval($exchangeRate) : 3.80;

$enableDiscounts = $companySettings->getSetting($companyId, 'enable_discounts');
$enableDiscounts = ($enableDiscounts === '1' || $enableDiscounts === 1 || $enableDiscounts === true);

$pageTitle = 'Nueva Cotización (Móvil)';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d6efd">
    <title><?= $pageTitle ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0d6efd;
            --danger-color: #dc3545;
            --success-color: #198754;
            --warning-color: #ffc107;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #cfcfcf;
            padding-bottom: 80px;
            font-size: 16px;
            overscroll-behavior: contain;
            margin: 0;
        }

        .container-mobile {
            padding: 16px;
            background: #ebebeb;
        }

        /* Mobile Header */
        .mobile-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: linear-gradient(135deg, var(--primary-color) 0%, #0b5ed7 100%);
            color: white;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .mobile-header h1 {
            font-size: 20px;
            margin: 0;
            font-weight: 600;
        }

        .mobile-header .back-btn {
            color: white;
            text-decoration: none;
            font-size: 24px;
            margin-right: 15px;
        }

        /* Form Sections */
        .form-section {
            background: white;
            margin: 10px;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-200);
        }

        /* Form Controls */
        .form-label {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #495057;
        }

        .form-control, .form-select {
            font-size: 16px !important; /* Prevent zoom on iOS */
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            min-height: 48px; /* Touch-friendly */
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        /* Product Item Card */
        .product-item {
            background: white;
            margin: 10px;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
        }

        .product-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }

        .product-number {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 16px;
        }

        .product-item .mb-3 {
            margin-bottom: 12px !important;
        }

        /* Buttons */
        .btn {
            font-size: 16px;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0b5ed7 100%);
            border: none;
        }

        .btn-danger {
            padding: 8px 16px;
            min-height: 40px;
        }

        .btn-block-mobile {
            width: 100%;
            margin-bottom: 10px;
        }

        /* Floating Action Buttons */
        .fab-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            z-index: 999;
        }

        .fab {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--success-color);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            transition: all 0.3s;
        }

        .fab:active {
            transform: scale(0.9);
        }

        .fab.primary {
            background: var(--primary-color);
        }

        /* Bottom Fixed Bar */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 15px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 998;
            display: flex;
            gap: 10px;
        }

        .bottom-bar .btn {
            flex: 1;
        }

        /* Totals Display */
        .totals-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 15px;
            margin: 10px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 15px;
        }

        .total-row.grand-total {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            border-top: 2px solid var(--gray-200);
            padding-top: 12px;
            margin-top: 8px;
        }

        /* Customer Modal */
        .modal-content {
            border-radius: 12px;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Product Search */
        .product-search-result {
            padding: 12px;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: background 0.2s;
        }

        .product-search-result:active {
            background: var(--gray-100);
        }

        .product-search-result:last-child {
            border-bottom: none;
        }

        /* Customer Search Autocomplete */
        .customer-search-container {
            position: relative;
        }

        .customer-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            margin-top: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .customer-search-results.active {
            display: block;
        }

        .customer-search-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: background 0.2s;
        }

        .customer-search-item:last-child {
            border-bottom: none;
        }

        .customer-search-item:active,
        .customer-search-item:hover {
            background: var(--gray-100);
        }

        .customer-search-item .customer-name {
            font-weight: 600;
            font-size: 15px;
            color: #212529;
            margin-bottom: 2px;
        }

        .customer-search-item .customer-details {
            font-size: 13px;
            color: #6c757d;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }

        .selected-customer {
            display: none;
            background: var(--gray-100);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .selected-customer.active {
            display: block;
        }

        .selected-customer .customer-name {
            font-weight: 600;
            font-size: 16px;
            color: #212529;
            margin-bottom: 4px;
        }

        .selected-customer .customer-details {
            font-size: 14px;
            color: #6c757d;
        }

        .change-customer-btn {
            float: right;
            font-size: 14px;
        }

        /* Input Group */
        .input-group-text {
            min-width: 48px;
            justify-content: center;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .form-section {
                margin: 8px;
                padding: 12px;
            }

            .product-item {
                margin: 8px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3 mb-0">Guardando cotización...</p>
        </div>
    </div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL ?>/quotations/index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1>Nueva Cotización</h1>
        </div>
    </div>

    <!-- Form -->
    <form id="quotationForm" method="POST" action="<?= BASE_URL ?>/api/save_quotation.php">
        <!-- Customer Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-user"></i> Cliente
            </div>

            <!-- Selected Customer Display -->
            <div id="selectedCustomerDisplay" class="selected-customer">
                <button type="button" class="btn btn-sm btn-outline-secondary change-customer-btn" onclick="changeCustomer()">
                    <i class="fas fa-edit"></i> Cambiar
                </button>
                <div class="customer-name" id="selectedCustomerName"></div>
                <div class="customer-details" id="selectedCustomerDetails"></div>
            </div>

            <!-- Customer Search -->
            <div id="customerSearchContainer" class="customer-search-container mb-3">
                <label class="form-label">Buscar Cliente *</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control"
                        id="customerSearchInput"
                        placeholder="Escribir nombre, RUC o email..."
                        autocomplete="off">
                </div>
                <div class="customer-search-results" id="customerSearchResults"></div>
            </div>

            <!-- Hidden input for selected customer ID -->
            <input type="hidden" id="customer_id" name="customer_id" required>

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
                    <input type="date" class="form-control" name="quotation_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Válido Hasta</label>
                    <input type="date" class="form-control" name="valid_until" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Moneda *</label>
                    <select class="form-select" id="currency" name="currency" required>
                        <option value="PEN">PEN (Soles)</option>
                        <option value="USD">USD (Dólares)</option>
                    </select>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">IGV</label>
                    <select class="form-select" id="price_includes_igv" name="price_includes_igv" onchange="updateIgvDisplay()">
                        <option value="included">INCLUIDO IGV</option>
                        <option value="plus_igv">SIN IGV</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Condición de Pago *</label>
                    <select class="form-select" id="payment_condition" name="payment_condition" required onchange="toggleCreditDays()">
                        <option value="cash">Efectivo / Contado</option>
                        <option value="credit">Crédito</option>
                    </select>
                </div>
                <div class="col-6 mb-3" id="credit_days_container" style="display: none;">
                    <label class="form-label">Plazo de Crédito</label>
                    <select class="form-select" id="credit_days" name="credit_days">
                        <option value="">Seleccionar...</option>
                        <option value="30">30 días</option>
                        <option value="60">60 días</option>
                        <option value="90">90 días</option>
                        <option value="120">120 días</option>
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
                <i class="fas fa-chevron-down float-end"></i>
            </div>
            <div class="collapse" id="notesCollapse">
                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="Notas adicionales..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Términos y Condiciones</label>
                    <textarea class="form-control" name="terms_and_conditions" rows="8"></textarea>
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
        <button type="submit" form="quotationForm" class="btn btn-success">
            <i class="fas fa-save"></i> Guardar
        </button>
    </div>

    <!-- New Customer Modal -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm">
                        <input type="hidden" id="product_index">
                        <div class="mb-3">
                            <label class="form-label">Buscar por Código</label>
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
                                <input type="number" class="form-control" id="product_quantity" value="1" step="0.01" min="0.01" required>
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
                                <input type="text" class="form-control bg-light" id="product_final_price" readonly style="font-weight: 600; color: #0d6efd;">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const enableDiscounts = <?= $enableDiscounts ? 'true' : 'false' ?>;
        const exchangeRate = <?= $exchangeRate ?>;
        const products = <?= json_encode($products) ?>;
        const warehouses = <?= json_encode($warehousesData) ?>;
        const customers = <?= json_encode($customers) ?>;

        let productItems = [];
        let currentEditIndex = null;
        let productSearchTimeout;
        let selectedCustomer = null;

        // Track current quotation currency for conversion
        let currentQuotationCurrency = document.getElementById('currency')?.value || 'PEN';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initCustomerSearch();
            initCustomerModal();
            initProductSearch();
            initWarehouseFilter();

            // Form submission
            document.getElementById('quotationForm').addEventListener('submit', handleSubmit);

            // Currency change - convert prices when currency changes
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

        // View product image (from modal)
        function viewProductImage() {
            const form = document.getElementById('productForm');
            const imageUrl = form.dataset.selectedImageUrl;

            if (imageUrl) {
                // Check if URL is external (starts with http:// or https://)
                const finalUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + '/' + imageUrl;
                document.getElementById('productImageViewer').src = finalUrl;
                const imageModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
                imageModal.show();
            } else {
                alert('No hay imagen disponible para este producto');
            }
        }

        // View item image (from product list)
        function viewItemImage(imageUrl) {
            if (imageUrl) {
                // Check if URL is external (starts with http:// or https://)
                const finalUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + '/' + imageUrl;
                document.getElementById('productImageViewer').src = finalUrl;
                const imageModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
                imageModal.show();
            }
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
            document.getElementById('product_discount').value = '20';
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

            // Always set discount value since field is always visible
            document.getElementById('product_discount').value = item.discount || 0;

            // Preserve product_id, image_url, original price and currency in form dataset
            const form = document.getElementById('productForm');
            form.dataset.selectedProductId = item.product_id || '';
            form.dataset.selectedImageUrl = item.image_url || '';
            form.dataset.originalPrice = item.originalPrice || '';
            form.dataset.productCurrency = item.productCurrency || '';

            // Show/hide image button based on whether product has image
            const imageContainer = document.getElementById('product_image_container');
            if (item.image_url) {
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            document.getElementById('product_ficha_container').style.display =
                item.product_id ? 'block' : 'none';

            // Calculate and show final price
            calculateFinalPrice();

            new bootstrap.Modal(document.getElementById('productModal')).show();
        }

        // Save Product
        function saveProduct() {
            const description = document.getElementById('product_description').value.trim();
            const quantity = parseFloat(document.getElementById('product_quantity').value);
            const price = parseFloat(document.getElementById('product_price').value);

            // Always get discount value since field is always visible
            const discountInput = document.getElementById('product_discount');
            const discount = discountInput ? parseFloat(discountInput.value) || 0 : 0;

            if (!description || !quantity || isNaN(quantity) || !price || isNaN(price)) {
                alert('Por favor complete todos los campos requeridos correctamente');
                return;
            }

            // Get product_id, image_url, and original price/currency from form dataset
            const form = document.getElementById('productForm');
            const productId = form.dataset.selectedProductId || null;
            const imageUrl = form.dataset.selectedImageUrl || null;
            const originalPrice = parseFloat(form.dataset.originalPrice) || null;
            const productCurrency = form.dataset.productCurrency || null;

            const item = {
                description,
                quantity,
                price,
                discount: discount || 0,
                product_id: productId,
                code: productId, // Add code field for CotiRapi
                image_url: imageUrl,
                originalPrice: originalPrice,
                productCurrency: productCurrency
            };

            if (currentEditIndex !== null) {
                // Preserve original price/currency when editing if not set
                if (!item.originalPrice && productItems[currentEditIndex].originalPrice) {
                    item.originalPrice = productItems[currentEditIndex].originalPrice;
                    item.productCurrency = productItems[currentEditIndex].productCurrency;
                }
                productItems[currentEditIndex] = item;
            } else {
                productItems.push(item);
            }

            renderProducts();
            calculateTotals();

            // Clear product selection
            form.dataset.selectedProductId = '';
            form.dataset.selectedImageUrl = '';
            form.dataset.originalPrice = '';
            form.dataset.productCurrency = '';

            // Reset current edit index
            currentEditIndex = null;

            const modalEl = document.getElementById('productModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }
        }

        // Delete Product
        function deleteProduct(index) {
            if (confirm('¿Eliminar este producto?')) {
                productItems.splice(index, 1);
                renderProducts();
                calculateTotals();
            }
        }

        // Render Products
        function renderProducts() {
            const container = document.getElementById('productsContainer');

            if (!container) {
                return;
            }

            // Remove only product-item elements, keep emptyState
            container.querySelectorAll('.product-item').forEach(el => el.remove());

            let emptyState = document.getElementById('emptyState');

            // If emptyState doesn't exist, create it
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
                            <strong>Descripción:</strong><br>
                            <span>${item.description}</span>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <small class="text-muted">Cantidad</small><br>
                                <strong>${item.quantity}</strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Precio Unit.</small><br>
                                <strong>${symbol} ${item.price.toFixed(2)}</strong>
                                ${item.discount > 0 ? `<br><small class="text-success">${symbol} ${(item.price - (item.price * item.discount / 100)).toFixed(2)} c/desc.</small>` : ''}
                            </div>
                            <div class="col-4 text-end">
                                <small class="text-muted">Total</small><br>
                                <strong class="text-primary">${symbol} ${total.toFixed(2)}</strong>
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

            // Si es MÁS IGV, agregar 18%
            if (igvOption === 'plus_igv') {
                igvAmount = baseTotal * 0.18;
                finalTotal = baseTotal + igvAmount;
            }

            document.getElementById('subtotalDisplay').textContent = `${symbol} ${subtotal.toFixed(2)}`;
            if (enableDiscounts) {
                document.getElementById('discountDisplay').textContent = `${symbol} ${totalDiscount.toFixed(2)}`;
            }

            // Mostrar u ocultar la fila de IGV
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

        // Update IGV display when option changes
        function updateIgvDisplay() {
            calculateTotals();
        }

        // Convert all product prices when currency changes
        function convertAllPrices() {
            const newCurrency = document.getElementById('currency').value;
            const previousCurrency = currentQuotationCurrency;

            // If same currency, just recalculate totals
            if (newCurrency === previousCurrency) {
                calculateTotals();
                return;
            }

            // Convert each product's price
            productItems.forEach(item => {
                const currentPrice = parseFloat(item.price) || 0;

                if (currentPrice > 0) {
                    let newPrice = currentPrice;

                    // Check if we have original price/currency stored
                    if (item.originalPrice && item.productCurrency) {
                        // Convert from original product currency to new quotation currency
                        newPrice = item.originalPrice;
                        if (newCurrency !== item.productCurrency) {
                            if (item.productCurrency === 'USD' && newCurrency === 'PEN') {
                                newPrice = item.originalPrice * parseFloat(exchangeRate);
                            } else if (item.productCurrency === 'PEN' && newCurrency === 'USD') {
                                newPrice = item.originalPrice / parseFloat(exchangeRate);
                            }
                        }
                    } else {
                        // No original data, convert from previous currency to new currency
                        if (previousCurrency === 'USD' && newCurrency === 'PEN') {
                            newPrice = currentPrice * parseFloat(exchangeRate);
                        } else if (previousCurrency === 'PEN' && newCurrency === 'USD') {
                            newPrice = currentPrice / parseFloat(exchangeRate);
                        }
                    }

                    item.price = parseFloat(newPrice.toFixed(2));
                }
            });

            // Update the current quotation currency tracker
            currentQuotationCurrency = newCurrency;

            // Re-render products and recalculate totals
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

            // Trigger search when warehouse filter changes
            warehouseFilter.addEventListener('change', function() {
                const term = searchInput.value.trim();
                if (term.length >= 2) {
                    searchProducts(term);
                }
            });
        }

        function initWarehouseFilter() {
            const warehouseFilter = document.getElementById('warehouse_filter');

            // Populate warehouse options
            warehouses.forEach(warehouse => {
                const option = document.createElement('option');
                option.value = warehouse;
                option.textContent = warehouse;
                warehouseFilter.appendChild(option);
            });
        }

        function searchProducts(term) {
            const selectedWarehouse = document.getElementById('warehouse_filter').value;
            const resultsContainer = document.getElementById('searchResults');

            // Mostrar indicador de carga
            resultsContainer.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';

            // Buscar en la API de COBOL
            fetch(`${BASE_URL}/api/search_products.php?search=${encodeURIComponent(term)}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        resultsContainer.innerHTML = '<div class="text-muted small p-2">Error en la búsqueda</div>';
                        return;
                    }

                    let results = data.products || [];

                    // Filtrar por almacén si está seleccionado
                    if (selectedWarehouse) {
                        results = results.filter(p => {
                            return p.warehouses &&
                                   p.warehouses.hasOwnProperty(selectedWarehouse) &&
                                   parseFloat(p.warehouses[selectedWarehouse]) > 0;
                        });
                    }

                    // Ordenar por precio ascendente
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
                        // Build warehouse stock display - only show warehouses with stock > 0
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

                        // Escapar el JSON para evitar problemas con comillas
                        const productJson = JSON.stringify(p).replace(/'/g, "\\'").replace(/"/g, '&quot;');

                        // Botón para ver imagen - siempre visible, pero con estilo diferente si hay imagen
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
                                        <span class="text-dark">${p.descripcion || p.name}</span><br>
                                        <small class="text-primary fw-bold">$ ${parseFloat(p.precio || p.regular_price).toFixed(2)}</small>
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

        // Helper function to select product from data attribute
        function selectProductFromData(element) {
            const productJson = element.dataset.product.replace(/&quot;/g, '"');
            const product = JSON.parse(productJson);
            selectProduct(product);
        }

        // Preview product image before selecting
        function previewProductImage(imageUrl, productName) {
            if (!imageUrl) {
                alert('No hay imagen disponible para este producto');
                return;
            }

            // Check if URL is external or relative
            const finalUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + '/' + imageUrl;
            document.getElementById('productImageViewer').src = finalUrl;

            // Update modal title with product name
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

        function selectProduct(product) {
            // Usar campos de COBOL o los mapeados
            const description = product.descripcion || product.name || product.description || '';
            document.getElementById('product_description').value = description;

            // Precio: usar campo de COBOL o el mapeado
            const originalPrice = parseFloat(product.precio || product.regular_price || 0);
            const quotationCurrency = document.getElementById('currency').value;
            const productCurrency = product.price_currency || 'USD';

            // Convertir precio si es necesario
            let displayPrice = originalPrice;
            if (quotationCurrency !== productCurrency) {
                if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                    displayPrice = originalPrice * exchangeRate;
                } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                    displayPrice = originalPrice / exchangeRate;
                }
            }

            document.getElementById('product_price').value = displayPrice.toFixed(2);

            // Store product_id (código COBOL), image_url, original price, and currency for later use
            const form = document.getElementById('productForm');
            form.dataset.selectedProductId = product.codigo || product.id || '';
            form.dataset.selectedImageUrl = product.image_url || product.imagen_url || '';
            form.dataset.originalPrice = originalPrice;
            form.dataset.productCurrency = productCurrency;

            // Show/hide image button based on whether product has image
            const imageContainer = document.getElementById('product_image_container');
            const imageUrl = product.image_url || product.imagen_url;
            if (imageUrl) {
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }
            // Show ficha button whenever a product is selected
            document.getElementById('product_ficha_container').style.display =
                (product.codigo || product.id) ? 'block' : 'none';

            // Calculate and show final price
            calculateFinalPrice();

            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('product_search').value = '';
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
                    // Add new customer to customers array
                    const newCustomer = {
                        id: data.customer.id,
                        name: data.customer.business_name,
                        tax_id: data.customer.document,
                        email: data.customer.email || '',
                        phone: data.customer.phone || ''
                    };
                    customers.push(newCustomer);

                    // Select the new customer
                    selectCustomer(newCustomer.id);

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

                // Add product_id and image_url if available
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
                    alert('✅ Cotización guardada exitosamente');
                    // Redirect to mobile view if on mobile, otherwise desktop view
                    const viewUrl = /mobile/i.test(window.location.href) ?
                        `${BASE_URL}/quotations/view_mobile.php?id=${data.quotation_id}` :
                        `${BASE_URL}/quotations/view.php?id=${data.quotation_id}`;
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
            if (productItems.length > 0) {
                if (confirm('¿Descartar esta cotización? Se perderán los cambios no guardados.')) {
                    window.location.href = `${BASE_URL}/quotations/index.php`;
                }
            } else {
                window.location.href = `${BASE_URL}/quotations/index.php`;
            }
        }

        // ======= CUSTOMER SEARCH AUTOCOMPLETE =======

        function initCustomerSearch() {
            const searchInput = document.getElementById('customerSearchInput');
            const searchResults = document.getElementById('customerSearchResults');

            // Search on input
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.trim().toLowerCase();

                if (query.length === 0) {
                    searchResults.classList.remove('active');
                    searchResults.innerHTML = '';
                    return;
                }

                // Filter customers
                const filteredCustomers = customers.filter(customer => {
                    const name = (customer.name || '').toLowerCase();
                    const taxId = (customer.tax_id || '').toLowerCase();
                    const email = (customer.email || '').toLowerCase();
                    const phone = (customer.phone || '').toLowerCase();

                    return name.includes(query) ||
                           taxId.includes(query) ||
                           email.includes(query) ||
                           phone.includes(query);
                });

                displayCustomerResults(filteredCustomers);
            });

            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.customer-search-container')) {
                    searchResults.classList.remove('active');
                }
            });
        }

        function displayCustomerResults(filteredCustomers) {
            const searchResults = document.getElementById('customerSearchResults');

            if (filteredCustomers.length === 0) {
                searchResults.innerHTML = '<div class="no-results"><i class="fas fa-search"></i><br>No se encontraron clientes</div>';
                searchResults.classList.add('active');
                return;
            }

            let html = '';
            filteredCustomers.slice(0, 10).forEach(customer => {
                const details = [];
                if (customer.tax_id) details.push(`RUC: ${customer.tax_id}`);
                if (customer.email) details.push(customer.email);
                if (customer.phone) details.push(customer.phone);

                html += `
                    <div class="customer-search-item" onclick="selectCustomer(${customer.id})">
                        <div class="customer-name">${escapeHtml(customer.name)}</div>
                        <div class="customer-details">${details.join(' • ')}</div>
                    </div>
                `;
            });

            searchResults.innerHTML = html;
            searchResults.classList.add('active');
        }

        function selectCustomer(customerId) {
            const customer = customers.find(c => c.id === customerId);
            if (!customer) return;

            selectedCustomer = customer;

            // Set hidden input value
            document.getElementById('customer_id').value = customer.id;

            // Update selected customer display
            const details = [];
            if (customer.tax_id) details.push(`RUC: ${customer.tax_id}`);
            if (customer.email) details.push(customer.email);
            if (customer.phone) details.push(customer.phone);

            document.getElementById('selectedCustomerName').textContent = customer.name;
            document.getElementById('selectedCustomerDetails').textContent = details.join(' • ');

            // Show selected, hide search
            document.getElementById('selectedCustomerDisplay').classList.add('active');
            document.getElementById('customerSearchContainer').style.display = 'none';

            // Clear and hide search results
            document.getElementById('customerSearchInput').value = '';
            document.getElementById('customerSearchResults').classList.remove('active');
            document.getElementById('customerSearchResults').innerHTML = '';
        }

        function changeCustomer() {
            // Hide selected, show search
            document.getElementById('selectedCustomerDisplay').classList.remove('active');
            document.getElementById('customerSearchContainer').style.display = 'block';

            // Clear selection
            document.getElementById('customer_id').value = '';
            selectedCustomer = null;

            // Focus on search input
            setTimeout(() => {
                document.getElementById('customerSearchInput').focus();
            }, 100);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

        // Toggle chevron icon on collapse
        document.addEventListener('DOMContentLoaded', function() {
            const notesCollapse = document.getElementById('notesCollapse');
            if (notesCollapse) {
                notesCollapse.addEventListener('show.bs.collapse', function () {
                    const icon = document.querySelector('[data-bs-target="#notesCollapse"] .fa-chevron-down');
                    if (icon) {
                        icon.classList.remove('fa-chevron-down');
                        icon.classList.add('fa-chevron-up');
                    }
                });
                notesCollapse.addEventListener('hide.bs.collapse', function () {
                    const icon = document.querySelector('[data-bs-target="#notesCollapse"] .fa-chevron-up');
                    if (icon) {
                        icon.classList.remove('fa-chevron-up');
                        icon.classList.add('fa-chevron-down');
                    }
                });
            }
        });

        // ============================================
        // COTI RAPI - Cotización Rápida para WhatsApp
        // ============================================
        async function generateCotiRapi() {
            try {
                console.log('CotiRapi: Starting generation');
                console.log('CotiRapi: productItems =', productItems);

                // Fetch template from server
                const response = await fetch('<?= BASE_URL ?>/api/get_cotirapi_template.php');
                const data = await response.json();

                console.log('CotiRapi: Template response =', data);

                if (!data.success || !data.template) {
                    alert('⚠️ Error al cargar la plantilla');
                    return;
                }

                const template = data.template;

                // Get customer info (mobile uses selectedCustomer object)
                const customerName = selectedCustomer?.name || 'Cliente';

                console.log('CotiRapi: Customer name =', customerName);
                console.log('CotiRapi: Number of items =', productItems.length);

                // Get items from the productItems array
                if (!productItems || productItems.length === 0) {
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
                productItems.forEach((product, index) => {
                    console.log(`CotiRapi: Processing item ${index}:`, product);

                    // Get code directly from product
                    const codigo = product.code || product.product_id || '';
                    const descripcion = product.description || '';

                    const cantidad = parseFloat(product.quantity) || 0;
                    const precioBase = parseFloat(product.price) || 0;
                    const descuento = parseFloat(product.discount) || 0;

                    console.log(`CotiRapi: Item ${index} - cantidad: ${cantidad}, precio: ${precioBase}, descuento: ${descuento}`);

                    // Calculate price with discount already applied
                    const subtotalItem = cantidad * precioBase;
                    const descuentoMonto = subtotalItem * (descuento / 100);
                    const total = subtotalItem - descuentoMonto;
                    const precioUni = cantidad > 0 ? total / cantidad : precioBase;

                    if (descripcion && cantidad > 0) {
                        itemCount++;
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

                        // IMAGE_URL and IMAGE_LINE: get image from product object
                        const imageUrl = product.image_url || '';
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
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
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
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                    <button type="button" class="btn btn-success" onclick="sendCotiRapiWhatsApp()">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
