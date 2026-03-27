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
            background-color: var(--gray-100);
            padding-bottom: 80px;
            font-size: 16px;
            overscroll-behavior: contain;
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
            bottom: 20px;
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
            <a href="<?= BASE_URL ?>/quotations/view_mobile.php?id=<?= $quotationId ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h1>Editar #<?= $quotation['quotation_number'] ?></h1>
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
                    <input type="date" class="form-control" name="quotation_date" value="<?= $quotation['quotation_date'] ?>" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Válido Hasta</label>
                    <input type="date" class="form-control" name="valid_until" value="<?= $quotation['valid_until'] ?? '' ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Moneda *</label>
                <select class="form-select" id="currency" name="currency" required>
                    <option value="PEN" <?= $quotation['currency'] === 'PEN' ? 'selected' : '' ?>>PEN (Soles)</option>
                    <option value="USD" <?= $quotation['currency'] === 'USD' ? 'selected' : '' ?>>USD (Dólares)</option>
                </select>
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
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span id="totalDisplay">S/ 0.00</span>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-sticky-note"></i> Notas y Observaciones
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea class="form-control" name="notes" rows="3" placeholder="Notas adicionales..."><?= htmlspecialchars($quotation['notes'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Términos y Condiciones</label>
                <textarea class="form-control" name="terms_and_conditions" rows="3" placeholder="Términos y condiciones..."><?= htmlspecialchars($quotation['terms_and_conditions'] ?? '') ?></textarea>
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
                    <h5 class="modal-title" id="productModalTitle">Nuevo Producto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="productForm">
                        <input type="hidden" id="product_index">
                        <div class="mb-3">
                            <label class="form-label">Buscar Producto</label>
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" id="product_search" placeholder="Buscar por código o nombre...">
                                <select class="form-select" id="warehouse_filter" style="max-width: 150px;">
                                    <option value="">Todos los almacenes</option>
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
                        <div class="mb-3">
                            <label class="form-label">Descuento (%)</label>
                            <input type="number" class="form-control" id="product_discount" value="0" step="0.01" min="0" max="100">
                        </div>
                        <div class="mb-3" id="product_image_container" style="display: none;">
                            <button type="button" class="btn btn-sm btn-outline-info w-100" onclick="viewProductImage()">
                                <i class="fas fa-image"></i> Ver Imagen del Producto
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveProductBtn">Guardar Producto</button>
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

        let productItems = <?= json_encode(array_map(function($item) {
            return [
                'description' => $item['description'],
                'quantity' => (float)$item['quantity'],
                'price' => (float)$item['unit_price'],
                'discount' => (float)($item['discount_percentage'] ?? 0),
                'product_id' => $item['product_id'],
                'image_url' => $item['image_url'] ?? null
            ];
        }, $quotationItems)) ?>;
        let currentEditIndex = null;
        let productSearchTimeout;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initCustomerModal();
            initProductSearch();
            initWarehouseFilter();

            // Render existing products
            renderProducts();
            calculateTotals();

            // Form submission
            document.getElementById('quotationForm').addEventListener('submit', handleSubmit);

            // Currency change
            document.getElementById('currency').addEventListener('change', calculateTotals);

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
        });

        // Add Product
        function addProduct() {
            currentEditIndex = null;
            document.getElementById('productModalTitle').textContent = 'Nuevo Producto';

            const form = document.getElementById('productForm');
            form.reset();

            // Clear product selection from dataset
            form.dataset.selectedProductId = '';
            form.dataset.selectedImageUrl = '';

            // Set default values
            document.getElementById('product_quantity').value = '1';
            document.getElementById('product_discount').value = '20';

            // Hide image button
            document.getElementById('product_image_container').style.display = 'none';

            document.getElementById('searchResults').innerHTML = '';
            new bootstrap.Modal(document.getElementById('productModal')).show();
        }

        // Edit Product
        function editProduct(index) {
            currentEditIndex = index;
            const item = productItems[index];

            document.getElementById('productModalTitle').textContent = 'Editar Producto';
            document.getElementById('product_description').value = item.description;
            document.getElementById('product_quantity').value = item.quantity;
            document.getElementById('product_price').value = item.price;

            // Always set discount value since field is always visible
            document.getElementById('product_discount').value = item.discount || 0;

            // Preserve product_id and image_url in form dataset
            const form = document.getElementById('productForm');
            form.dataset.selectedProductId = item.product_id || '';
            form.dataset.selectedImageUrl = item.image_url || '';

            // Show/hide image button based on whether product has image
            const imageContainer = document.getElementById('product_image_container');
            if (item.image_url) {
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }

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

            // Get product_id and image_url from form dataset
            const form = document.getElementById('productForm');
            const productId = form.dataset.selectedProductId || null;
            const imageUrl = form.dataset.selectedImageUrl || null;

            const item = {
                description,
                quantity,
                price,
                discount: discount || 0,
                product_id: productId,
                image_url: imageUrl
            };

            if (currentEditIndex !== null) {
                productItems[currentEditIndex] = item;
            } else {
                productItems.push(item);
            }

            renderProducts();
            calculateTotals();

            // Clear product selection
            form.dataset.selectedProductId = '';
            form.dataset.selectedImageUrl = '';

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

                const html = `
                    <div class="product-item">
                        <div class="product-item-header">
                            <span class="product-number">Producto #${index + 1}</span>
                            <div>
                                ${imageButton}
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
                                <small class="text-muted">Precio</small><br>
                                <strong>${symbol} ${item.price.toFixed(2)}</strong>
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

            let subtotal = 0;
            let totalDiscount = 0;

            productItems.forEach(item => {
                const itemSubtotal = item.quantity * item.price;
                const itemDiscount = itemSubtotal * (item.discount / 100);
                subtotal += itemSubtotal;
                totalDiscount += itemDiscount;
            });

            const total = subtotal - totalDiscount;

            document.getElementById('subtotalDisplay').textContent = `${symbol} ${subtotal.toFixed(2)}`;
            if (enableDiscounts) {
                document.getElementById('discountDisplay').textContent = `${symbol} ${totalDiscount.toFixed(2)}`;
            }
            document.getElementById('totalDisplay').textContent = `${symbol} ${total.toFixed(2)}`;
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

                    // Limitar a 10 resultados
                    results = results.slice(0, 10);

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

                        return `
                            <div class="product-search-result" onclick='selectProductFromData(this)' data-product="${productJson}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${p.codigo || p.code}</strong>${imageButton}<br>
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

        function selectProduct(product) {
            // Usar campos de COBOL o los mapeados
            const description = product.descripcion || product.name || product.description || '';
            document.getElementById('product_description').value = description;

            // Precio: usar campo de COBOL o el mapeado
            let price = parseFloat(product.precio || product.regular_price || 0);
            const quotationCurrency = document.getElementById('currency').value;
            const productCurrency = product.price_currency || 'USD';

            // Convertir precio si es necesario
            if (quotationCurrency !== productCurrency) {
                if (productCurrency === 'USD' && quotationCurrency === 'PEN') {
                    price = price * exchangeRate;
                } else if (productCurrency === 'PEN' && quotationCurrency === 'USD') {
                    price = price / exchangeRate;
                }
            }

            document.getElementById('product_price').value = price.toFixed(2);

            // Store product_id (código COBOL) and image_url for later use
            const form = document.getElementById('productForm');
            form.dataset.selectedProductId = product.codigo || product.id || '';
            form.dataset.selectedImageUrl = product.image_url || product.imagen_url || '';

            // Show/hide image button based on whether product has image
            const imageContainer = document.getElementById('product_image_container');
            const imageUrl = product.image_url || product.imagen_url;
            if (imageUrl) {
                imageContainer.style.display = 'block';
            } else {
                imageContainer.style.display = 'none';
            }

            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('product_search').value = '';
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

        // Preview product image before selecting (from search results)
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
    </script>
</body>
</html>
