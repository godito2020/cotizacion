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

// Bank accounts
$bankQuery = "SELECT bank_name, account_number, account_type, currency
              FROM bank_accounts
              WHERE company_id = ? AND is_active = 1
              ORDER BY currency, bank_name";
$bankStmt = $db->prepare($bankQuery);
$bankStmt->execute([$companyId]);
$bankAccounts = $bankStmt->fetchAll(PDO::FETCH_ASSOC);

// Always enable discounts for creation/edit
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
            if (!empty(trim($item['description'] ?? ''))) {
                $quantity = isset($item['quantity']) && $item['quantity'] !== '' ? max(1, (int)$item['quantity']) : 1;
                $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float)$item['unit_price'] : 0;

                $validItems[] = [
                    'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                    'description' => trim($item['description']),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
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

        $paymentCondition = $_POST['payment_condition'] ?? ($quotation['payment_condition'] ?? 'cash');
        $creditDays = ($paymentCondition === 'credit') ? ($_POST['credit_days'] ?? null) : null;

        $updateData = [
            'customer_id' => (int)$_POST['customer_id'],
            'quotation_date' => $_POST['quotation_date'],
            'valid_until' => $_POST['valid_until'] ?: null,
            'currency' => $_POST['currency'] ?? 'USD',
            'payment_condition' => $paymentCondition,
            'credit_days' => $creditDays,
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
        'currency' => $quotation['currency'] ?? 'USD',
        'payment_condition' => $quotation['payment_condition'] ?? 'cash',
        'credit_days' => $quotation['credit_days'] ?? '',
        'price_includes_igv' => $quotation['igv_mode'] ?? 'included',
        'global_discount_percentage' => $quotation['global_discount_percentage'],
        'notes' => $quotation['notes'],
        'terms_and_conditions' => $quotation['terms_and_conditions'],
        'status' => $quotation['status']
    ];

    $quotationItems = $quotation['items'] ?? [];
}

$pageTitle = 'Editar Cotización #' . $quotation['quotation_number'];
$activePage = 'quotations';

require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="quotation-builder-container mx-auto" style="max-width: 1100px;">
    <!-- =========================================================
         BARRA SUPERIOR: ACCIONES Y RESUMEN RÁPIDO
         ========================================================= -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="live-pulse-dot" title="Modo Edición"></span>
                <h1 class="h3 mb-0 fw-bold" style="font-family: var(--font-display);">Editar Cotización #<?= htmlspecialchars($quotation['quotation_number']) ?></h1>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                    <i class="fas fa-edit me-1"></i>En Edición
                </span>
            </div>
            <p class="text-muted small mb-0">
                <i class="fas fa-building text-warning me-1"></i><strong><?= htmlspecialchars($company['name'] ?? 'Empresa') ?></strong>
                · RUC: <?= htmlspecialchars($company['tax_id'] ?? 'N/A') ?>
                · TC: <span class="badge bg-light text-dark border">USD 1 = S/ <?= number_format($exchangeRate, 3) ?></span>
            </p>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>" class="btn btn-outline-info btn-sm px-3">
                <i class="fas fa-eye me-1"></i> Ver Cotización
            </a>
            <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>
            <button type="submit" form="quotationForm" class="btn btn-primary btn-sm px-3 shadow-sm fw-bold">
                <i class="fas fa-save me-1"></i> Actualizar Cotización
            </button>
        </div>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($errors['general']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="quotationForm">
        <!-- =========================================================
             1. CUADRO: CLIENTE
             ========================================================= -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-bold"><i class="fas fa-user-tag text-warning me-2"></i> Cliente</span>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-outline-primary btn-sm py-1" data-bs-toggle="modal" data-bs-target="#customerSearchModal">
                        <i class="fas fa-search me-1"></i> Buscar Cliente
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm py-1" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                        <i class="fas fa-plus-circle me-1"></i> Nuevo Cliente (API)
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <label for="customer_id" class="form-label small fw-semibold text-muted mb-1">Seleccionar Cliente *</label>
                    <select class="form-select <?= !empty($errors['customer_id']) ? 'is-invalid' : '' ?>"
                            id="customer_id" name="customer_id" required onchange="updateSelectedCustomerInfo()">
                        <option value="">Seleccione un cliente...</option>
                        <?php foreach ($customers as $cust): ?>
                            <option value="<?= $cust['id'] ?>"
                                    <?= (isset($formData['customer_id']) && $formData['customer_id'] == $cust['id']) ? 'selected' : '' ?>
                                    data-tax-id="<?= htmlspecialchars($cust['tax_id'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars($cust['address'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($cust['phone'] ?? '') ?>"
                                    data-email="<?= htmlspecialchars($cust['email'] ?? '') ?>"
                                    data-contact="<?= htmlspecialchars($cust['contact_person'] ?? '') ?>">
                                <?= htmlspecialchars($cust['name']) ?> - <?= htmlspecialchars($cust['tax_id'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['customer_id'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['customer_id']) ?></div>
                    <?php endif; ?>

                    <!-- Customer Details Preview Box -->
                    <div id="customer_info_box" class="mt-3 p-3 rounded" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <div class="row g-2 small">
                            <div class="col-md-3 col-6">
                                <span class="text-muted d-block">Documento:</span>
                                <strong id="cust_doc" style="color: var(--color-ink);">-</strong>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted d-block">Teléfono:</span>
                                <strong id="cust_phone" style="color: var(--color-ink);">-</strong>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted d-block">Email:</span>
                                <strong id="cust_email" style="color: var(--color-ink);">-</strong>
                            </div>
                            <div class="col-md-3 col-6">
                                <span class="text-muted d-block">Contacto:</span>
                                <strong id="cust_contact" style="color: var(--color-ink);">-</strong>
                            </div>
                            <div class="col-12 mt-1 pt-1 border-top border-secondary-subtle">
                                <span class="text-muted d-block">Dirección Fiscal:</span>
                                <span id="cust_address" class="text-muted">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================
             2. CUADRO: CONDICIONES COMERCIALES
             ========================================================= -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="fas fa-sliders text-warning me-2"></i> Condiciones Comerciales</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <label for="quotation_date" class="form-label small fw-semibold text-muted">Fecha Emisión *</label>
                        <input type="date" class="form-control <?= !empty($errors['quotation_date']) ? 'is-invalid' : '' ?>"
                               id="quotation_date" name="quotation_date"
                               value="<?= htmlspecialchars($formData['quotation_date'] ?? '') ?>" required>
                        <?php if (!empty($errors['quotation_date'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['quotation_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3 col-6">
                        <label for="valid_until" class="form-label small fw-semibold text-muted">Válida Hasta</label>
                        <input type="date" class="form-control" id="valid_until" name="valid_until"
                               value="<?= htmlspecialchars($formData['valid_until'] ?? '') ?>">
                    </div>

                    <div class="col-md-3 col-6">
                        <label for="currency" class="form-label small fw-semibold text-muted">Moneda *</label>
                        <select class="form-select" id="currency" name="currency">
                            <option value="USD" <?= ($formData['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>Dólares (USD)</option>
                            <option value="PEN" <?= ($formData['currency'] ?? 'USD') === 'PEN' ? 'selected' : '' ?>>Soles (PEN)</option>
                        </select>
                        <div class="text-muted small mt-1" id="exchange_rate_info">
                            USD 1 = S/ <?= number_format($exchangeRate, 3) ?>
                        </div>
                    </div>

                    <div class="col-md-3 col-6">
                        <label for="price_includes_igv" class="form-label small fw-semibold text-muted">
                            Régimen IGV
                            <i class="fas fa-info-circle text-muted ms-1" data-bs-toggle="tooltip"
                               title="INCLUIDO IGV: El precio de lista ya incluye el 18%. MÁS IGV: Se agrega 18% al precio."></i>
                        </label>
                        <select class="form-select" id="price_includes_igv" name="price_includes_igv">
                            <option value="included" <?= ($formData['price_includes_igv'] ?? 'included') === 'included' ? 'selected' : '' ?>>INCLUIDO IGV</option>
                            <option value="plus_igv" <?= ($formData['price_includes_igv'] ?? 'included') === 'plus_igv' ? 'selected' : '' ?>>MÁS IGV (+18%)</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-12">
                        <label for="payment_condition" class="form-label small fw-semibold text-muted">Forma de Pago *</label>
                        <select class="form-select" id="payment_condition" name="payment_condition" onchange="toggleCreditDays()">
                            <option value="cash" <?= ($formData['payment_condition'] ?? 'cash') === 'cash' ? 'selected' : '' ?>>Efectivo / Contado</option>
                            <option value="credit" <?= ($formData['payment_condition'] ?? '') === 'credit' ? 'selected' : '' ?>>Crédito</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-12" id="credit_days_container" style="<?= ($formData['payment_condition'] ?? 'cash') === 'credit' ? '' : 'display: none;' ?>">
                        <label for="credit_days" class="form-label small fw-semibold text-muted">Plazo de Crédito</label>
                        <select class="form-select" id="credit_days" name="credit_days">
                            <option value="">Seleccionar plazo...</option>
                            <option value="30" <?= ($formData['credit_days'] ?? '') == '30' ? 'selected' : '' ?>>30 días</option>
                            <option value="60" <?= ($formData['credit_days'] ?? '') == '60' ? 'selected' : '' ?>>60 días</option>
                            <option value="90" <?= ($formData['credit_days'] ?? '') == '90' ? 'selected' : '' ?>>90 días</option>
                            <option value="120" <?= ($formData['credit_days'] ?? '') == '120' ? 'selected' : '' ?>>120 días</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-12">
                        <label for="status" class="form-label small fw-semibold text-muted">Estado de Cotización</label>
                        <select class="form-select" id="status" name="status">
                            <option value="Draft" <?= ($formData['status'] ?? 'Draft') === 'Draft' ? 'selected' : '' ?>>Borrador</option>
                            <option value="Sent" <?= ($formData['status'] ?? '') === 'Sent' ? 'selected' : '' ?>>Enviada</option>
                            <option value="Accepted" <?= ($formData['status'] ?? '') === 'Accepted' ? 'selected' : '' ?>>Aceptada</option>
                            <option value="Rejected" <?= ($formData['status'] ?? '') === 'Rejected' ? 'selected' : '' ?>>Rechazada</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- =========================================================
             3. CUADRO: ÍTEMS DE LA COTIZACIÓN
             ========================================================= -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-bold"><i class="fas fa-boxes-stacked text-warning me-2"></i> Ítems de la Cotización</span>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#productSearchModal">
                        <i class="fas fa-search me-1"></i> Catálogo
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="addNewItem()">
                        <i class="fas fa-plus me-1"></i> + Producto Manual
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
                <div id="items-container">
                    <?php if (!empty($quotationItems)): ?>
                        <?php foreach ($quotationItems as $index => $item): ?>
                            <div class="item-row p-3 mb-3 rounded" data-item-index="<?= $index ?>" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Descripción *</label>
                                        <textarea class="form-control item-description"
                                                  name="items[<?= $index ?>][description]"
                                                  rows="2" required><?= htmlspecialchars($item['description']) ?></textarea>
                                        <input type="hidden" name="items[<?= $index ?>][product_id]"
                                               value="<?= $item['product_id'] ?>" class="item-product-id">
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label small fw-semibold text-muted">Cantidad *</label>
                                        <input type="number" class="form-control item-quantity"
                                               name="items[<?= $index ?>][quantity]"
                                               value="<?= max(1, (int)$item['quantity']) ?>"
                                               step="1" min="1" required>
                                    </div>
                                    <div class="col-6 col-md-2">
                                        <label class="form-label small fw-semibold text-muted">Precio Unit. *</label>
                                        <input type="number" class="form-control item-unit-price"
                                               name="items[<?= $index ?>][unit_price]"
                                               value="<?= $item['unit_price'] ?>"
                                               step="0.01" min="0" required>
                                    </div>
                                    <?php if ($enableDiscounts): ?>
                                    <div class="col-6 col-md-2 discount-percentage-col discount-percentage-section">
                                        <label class="form-label small fw-semibold text-muted">Desc. %</label>
                                        <input type="number" class="form-control item-discount"
                                               name="items[<?= $index ?>][discount_percentage]"
                                               value="<?= $item['discount_percentage'] ?? 0 ?>"
                                               step="0.01" min="0" max="100">
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-6 col-md-1">
                                        <label class="form-label small fw-semibold text-muted">Subtotal</label>
                                        <input type="text" class="form-control item-subtotal font-monospace fw-semibold" readonly>
                                    </div>
                                    <div class="col-12 col-md-1 text-end text-md-center">
                                        <label class="form-label small fw-semibold text-muted d-none d-md-block">&nbsp;</label>
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(<?= $index ?>)" title="Eliminar ítem">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" id="empty-items-alert">
                            <i class="fas fa-info-circle me-1"></i>
                            No hay productos agregados. Usa los botones de arriba para agregar productos.
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($errors['items'])): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($errors['items']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- =========================================================
             4. CUADRO: RESUMEN DE COTIZACIÓN (Directamente debajo de Ítems)
             ========================================================= -->
        <div class="card mb-4 border-0 shadow-sm" style="background-color: var(--color-panel);">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-receipt text-warning me-2"></i> Resumen de Cotización</span>
                <span class="badge bg-light text-muted border">Totales actualizados</span>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <!-- Descuento Global y Acciones -->
                    <div class="col-md-6 mb-3 mb-md-0">
                        <?php if ($enableDiscounts): ?>
                        <div class="p-3 rounded mb-3" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <label for="global_discount_percentage" class="form-label small fw-semibold text-muted mb-1">
                                <i class="fas fa-tag text-warning me-1"></i> Descuento Global (%)
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="global_discount_percentage"
                                       name="global_discount_percentage" min="0" max="100" step="0.01"
                                       value="<?= $formData['global_discount_percentage'] ?? 0 ?>">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text small text-muted">Aplica a todos los ítems de la cotización.</div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" id="global_discount_percentage" name="global_discount_percentage" value="0">
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-eye me-1"></i> Ver Cotización
                            </a>
                            <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-ghost border btn-sm text-muted">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                        </div>
                    </div>

                    <!-- Desglose de Totales -->
                    <div class="col-md-6">
                        <div class="p-3 rounded" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-semibold tabular-nums" style="font-family: var(--font-mono); font-size: 1.05rem;" id="subtotal-display">
                                    $ 0.00
                                </span>
                            </div>

                            <?php if ($enableDiscounts): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 text-danger">
                                <span>Descuento Global:</span>
                                <span class="fw-semibold tabular-nums" style="font-family: var(--font-mono); font-size: 1.05rem;" id="global-discount-display">
                                    - $ 0.00
                                </span>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted" id="igv-label">IGV (18%):</span>
                                <span class="fw-semibold tabular-nums" style="font-family: var(--font-mono); font-size: 1.05rem;" id="igv-display">
                                    $ 0.00
                                </span>
                            </div>

                            <hr class="my-2 border-secondary-subtle">

                            <div class="d-flex justify-content-between align-items-baseline">
                                <span class="h5 mb-0 fw-bold">TOTAL:</span>
                                <span class="h3 mb-0 fw-bold tabular-nums" style="font-family: var(--font-mono); color: var(--color-action);" id="total-display">
                                    $ 0.00
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 pt-3 border-top">
                    <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Volver a Cotizaciones
                    </a>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm" style="font-size: 16px;">
                        <i class="fas fa-save me-2"></i> Actualizar Cotización
                    </button>
                </div>
            </div>
        </div>

        <!-- =========================================================
             5. CUADRO: NOTAS Y TÉRMINOS COMERCIALES
             ========================================================= -->
        <div class="card mb-4" id="notesCard">
            <div class="card-header d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="toggleNotesCard()">
                <span class="fw-bold"><i class="fas fa-pen-to-square text-warning me-2"></i> Notas y Términos Comerciales</span>
                <button type="button" class="btn btn-sm btn-ghost p-1 text-muted" id="toggleNotesBtn">
                    <i class="fas fa-chevron-down" id="toggleNotesIcon"></i>
                </button>
            </div>
            <div class="card-body" id="notesCardBody" style="<?= (!empty($formData['notes']) || !empty($formData['terms_and_conditions'])) ? '' : 'display: none;' ?>">
                <div class="mb-3">
                    <label for="notes" class="form-label small fw-semibold text-muted">Observaciones / Notas para el Cliente</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Comentarios adicionales que aparecerán en la cotización..."><?= htmlspecialchars($formData['notes'] ?? '') ?></textarea>
                </div>
                <div class="mb-0">
                    <label for="terms_and_conditions" class="form-label small fw-semibold text-muted">Términos y Condiciones</label>
                    <textarea class="form-control" id="terms_and_conditions" name="terms_and_conditions" rows="6"><?= htmlspecialchars($formData['terms_and_conditions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- =========================================================
             6. CUADRO: CUENTAS BANCARIAS REGISTRADAS (Al final de todos los cuadros)
             ========================================================= -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="fas fa-university text-warning me-2"></i> Cuentas Bancarias Registradas</span>
            </div>
            <div class="card-body">
                <?php if (!empty($bankAccounts)): ?>
                    <div class="row g-3">
                        <?php foreach ($bankAccounts as $account): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="p-3 rounded h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong style="color: var(--color-ink);"><?= htmlspecialchars($account['bank_name']) ?></strong>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($account['currency']) ?></span>
                                    </div>
                                    <div class="text-muted small mb-1"><?= htmlspecialchars($account['account_type']) ?></div>
                                    <code class="d-block user-select-all p-1 rounded" style="background: var(--color-rule-subtle); color: var(--color-ink);"><?= htmlspecialchars($account['account_number']) ?></code>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-center py-3">No hay cuentas bancarias registradas.</div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- =========================================================
     MODALES
     ========================================================= -->

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
                <div id="customer_search_results" style="max-height: 380px; overflow-y: auto;">
                    <!-- Results will be populated here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- New Customer Modal (API) -->
<div class="modal fade" id="newCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Cliente (API)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCustomerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Tipo de Documento</label>
                                <select class="form-select" id="document_type">
                                    <option value="ruc">RUC (11 dígitos)</option>
                                    <option value="dni">DNI (8 dígitos)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Número de Documento</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="document_number" placeholder="Ingrese RUC o DNI">
                                    <button type="button" class="btn btn-primary" id="search_document_btn">
                                        <i class="fas fa-search me-1"></i> Consultar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="customer_data" style="display: none;">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Nombre / Razón Social</label>
                                    <input type="text" class="form-control" id="customer_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Email</label>
                                    <input type="email" class="form-control" id="customer_email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Teléfono</label>
                                    <input type="text" class="form-control" id="customer_phone">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Dirección</label>
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

<!-- Product Search Modal (Catálogo) -->
<div class="modal fade" id="productSearchModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-boxes-stacked me-2"></i>Catálogo de Productos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Search Form -->
                <div class="row mb-3 g-2">
                    <div class="col-md-6">
                        <label for="modal_product_search" class="form-label small fw-semibold">Buscar Producto</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="modal_product_search"
                                   placeholder="Código, nombre, descripción...">
                            <button type="button" class="btn btn-primary" id="modal_search_btn">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="modal_brand_filter" class="form-label small fw-semibold">Filtrar por Marca</label>
                        <select class="form-select" id="modal_brand_filter">
                            <option value="">Todas las marcas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="modal_warehouse_filter" class="form-label small fw-semibold">Filtrar por Almacén</label>
                        <select class="form-select" id="modal_warehouse_filter">
                            <option value="">Todos los almacenes</option>
                        </select>
                    </div>
                </div>

                <!-- Results Summary -->
                <div id="modal_results_summary" class="mb-3" style="display: none;">
                    <div class="alert alert-info py-2 mb-0 small">
                        <i class="fas fa-info-circle me-1"></i>
                        <span id="results_count">0</span> producto(s) encontrado(s)
                    </div>
                </div>

                <!-- Search Results -->
                <div id="modal_search_results" style="max-height: 420px; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                        <p>Ingrese un término de búsqueda para encontrar productos en catálogo</p>
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
                <img id="preview_image" src="" alt="Vista previa" class="img-fluid rounded">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

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
        calculateTotals();
        setupEventListeners();
        setupCustomerSearch();
        setupProductSearch();
        setupNewCustomerForm();
        reindexItems();
        updateSelectedCustomerInfo();
    });

    function toggleNotesCard() {
        const body = document.getElementById('notesCardBody');
        const icon = document.getElementById('toggleNotesIcon');
        if (body.style.display === 'none') {
            body.style.display = 'block';
            icon.className = 'fas fa-chevron-up';
        } else {
            body.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
        }
    }

    function toggleCreditDays() {
        const condition = document.getElementById('payment_condition').value;
        const container = document.getElementById('credit_days_container');
        if (condition === 'credit') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
            document.getElementById('credit_days').value = '';
        }
    }

    function updateSelectedCustomerInfo() {
        const select = document.getElementById('customer_id');
        const selectedOpt = select.options[select.selectedIndex];
        if (!selectedOpt || !selectedOpt.value) {
            document.getElementById('cust_doc').textContent = '-';
            document.getElementById('cust_phone').textContent = '-';
            document.getElementById('cust_email').textContent = '-';
            document.getElementById('cust_contact').textContent = '-';
            document.getElementById('cust_address').textContent = 'No seleccionado';
            return;
        }

        document.getElementById('cust_doc').textContent = selectedOpt.getAttribute('data-tax-id') || '-';
        document.getElementById('cust_phone').textContent = selectedOpt.getAttribute('data-phone') || '-';
        document.getElementById('cust_email').textContent = selectedOpt.getAttribute('data-email') || '-';
        document.getElementById('cust_contact').textContent = selectedOpt.getAttribute('data-contact') || '-';
        document.getElementById('cust_address').textContent = selectedOpt.getAttribute('data-address') || 'No registrada';
    }

    function setupEventListeners() {
        // Enforce integer quantities: block decimals, commas, e, and negative signs
        document.addEventListener('keydown', function(e) {
            if (e.target && e.target.classList.contains('item-quantity')) {
                if (e.key === '.' || e.key === ',' || e.key === 'e' || e.key === 'E' || e.key === '-') {
                    e.preventDefault();
                }
            }
        });

        document.addEventListener('input', function(e) {
            if (e.target && e.target.classList.contains('item-quantity')) {
                if (e.target.value.includes('.') || e.target.value.includes(',')) {
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                }
            }
            if (e.target.matches('.item-quantity, .item-unit-price, .item-discount, #global_discount_percentage')) {
                calculateTotals();
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('item-quantity')) {
                let val = parseInt(e.target.value, 10);
                if (isNaN(val) || val < 1) val = 1;
                e.target.value = val;
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
        const emptyAlert = document.getElementById('empty-items-alert');
        if (emptyAlert) {
            emptyAlert.remove();
        }

        let discountColumn = '';
        if (enableDiscounts) {
            discountColumn = `
                <div class="col-6 col-md-2 discount-percentage-col discount-percentage-section">
                    <label class="form-label small fw-semibold text-muted">Desc. %</label>
                    <input type="number" class="form-control item-discount"
                           name="items[${itemIndex}][discount_percentage]"
                           value="0" step="0.01" min="0" max="100">
                </div>
            `;
        }

        const itemHtml = `
            <div class="item-row p-3 mb-3 rounded" data-item-index="${itemIndex}" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold text-muted">Descripción *</label>
                        <textarea class="form-control item-description"
                                  name="items[${itemIndex}][description]"
                                  rows="2" required>${productData ? productData.description : ''}</textarea>
                        <input type="hidden" name="items[${itemIndex}][product_id]"
                               value="${productData ? productData.id : ''}" class="item-product-id">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted">Cantidad *</label>
                        <input type="number" class="form-control item-quantity"
                               name="items[${itemIndex}][quantity]"
                               value="1" step="1" min="1" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small fw-semibold text-muted">Precio Unit. *</label>
                        <input type="number" class="form-control item-unit-price"
                               name="items[${itemIndex}][unit_price]"
                               value="${productData ? productData.regular_price : '0'}"
                               step="0.01" min="0" required>
                    </div>
                    ${discountColumn}
                    <div class="col-6 col-md-1">
                        <label class="form-label small fw-semibold text-muted">Subtotal</label>
                        <input type="text" class="form-control item-subtotal font-monospace fw-semibold" readonly>
                    </div>
                    <div class="col-12 col-md-1 text-end text-md-center">
                        <label class="form-label small fw-semibold text-muted d-none d-md-block">&nbsp;</label>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(${itemIndex})" title="Eliminar ítem">
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

            const container = document.getElementById('items-container');
            if (container.querySelectorAll('.item-row').length === 0) {
                container.innerHTML = `
                    <div class="alert alert-info" id="empty-items-alert">
                        <i class="fas fa-info-circle me-1"></i>
                        No hay productos agregados. Usa los botones de arriba para agregar productos.
                    </div>
                `;
            }
        }
    }

    function reindexItems() {
        const items = document.querySelectorAll('.item-row');
        items.forEach((item, newIndex) => {
            item.setAttribute('data-item-index', newIndex);

            const inputs = item.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                if (input.name) {
                    input.name = input.name.replace(/\[\d+\]/, `[${newIndex}]`);
                }
            });

            const removeBtn = item.querySelector('.btn-outline-danger, .btn-danger');
            if (removeBtn) {
                removeBtn.setAttribute('onclick', `removeItem(${newIndex})`);
            }
        });

        itemIndex = items.length;
    }

    function calculateTotals() {
        let subtotal = 0;
        const symbol = getCurrencySymbol();

        document.querySelectorAll('.item-row').forEach(function(row) {
            const quantity = parseInt(row.querySelector('.item-quantity').value, 10) || 0;
            const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
            const discountPercentage = enableDiscounts ?
                (parseFloat(row.querySelector('.item-discount').value) || 0) : 0;

            const itemTotal = quantity * unitPrice;
            const discountAmount = itemTotal * (discountPercentage / 100);
            const itemSubtotal = itemTotal - discountAmount;

            const subtotalField = row.querySelector('.item-subtotal');
            if (subtotalField) {
                subtotalField.value = symbol + ' ' + itemSubtotal.toFixed(2);
            }

            subtotal += itemSubtotal;
        });

        const globalDiscountPercentage = enableDiscounts ?
            (parseFloat(document.getElementById('global_discount_percentage').value) || 0) : 0;
        const globalDiscountAmount = subtotal * (globalDiscountPercentage / 100);
        const subtotalAfterDiscount = subtotal - globalDiscountAmount;

        const igvOption = document.getElementById('price_includes_igv').value;

        let subtotalSinIGV, igv, total;

        if (igvOption === 'plus_igv') {
            subtotalSinIGV = subtotalAfterDiscount;
            igv = subtotalAfterDiscount * 0.18;
            total = subtotalAfterDiscount + igv;
        } else {
            total = subtotalAfterDiscount;
            subtotalSinIGV = total / 1.18;
            igv = total - subtotalSinIGV;
        }

        document.getElementById('subtotal-display').textContent = symbol + ' ' + subtotalSinIGV.toFixed(2);

        if (enableDiscounts) {
            const globalDiscountDisplay = igvOption === 'plus_igv' ? globalDiscountAmount : globalDiscountAmount / 1.18;
            const el = document.getElementById('global-discount-display');
            if (el) el.textContent = '-' + symbol + ' ' + globalDiscountDisplay.toFixed(2);
        }

        const igvLabel = document.getElementById('igv-label');
        if (igvLabel) {
            igvLabel.textContent = igvOption === 'plus_igv' ? 'IGV (18% adicional):' : 'IGV (18% inc.):';
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
                resultsContainer.innerHTML = '<p class="text-muted small p-2">Ingrese al menos 2 caracteres para buscar</p>';
                return;
            }

            const filteredCustomers = customers.filter(customer =>
                (customer.name || '').toLowerCase().includes(searchTerm) ||
                (customer.tax_id || '').toLowerCase().includes(searchTerm)
            );

            if (filteredCustomers.length === 0) {
                resultsContainer.innerHTML = '<p class="text-muted small p-2">No se encontraron clientes</p>';
                return;
            }

            const resultsHtml = filteredCustomers.map(customer => `
                <div class="card mb-2 customer-result" data-customer-id="${customer.id}" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong style="color: var(--color-ink);">${customer.name}</strong><br>
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

            resultsContainer.querySelectorAll('.select-customer-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const customerId = this.closest('.customer-result').dataset.customerId;
                    document.getElementById('customer_id').value = customerId;
                    updateSelectedCustomerInfo();
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

        searchBtn.addEventListener('click', performProductSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performProductSearch();
            }
        });

        brandFilter.addEventListener('change', applyFilters);
        warehouseFilter.addEventListener('change', applyFilters);

        function performProductSearch() {
            const searchTerm = searchInput.value.trim();

            if (searchTerm.length < 2) {
                alert('Ingrese al menos 2 caracteres para buscar');
                return;
            }

            resultsContainer.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Buscando...</span>
                    </div>
                    <p class="mt-2 text-muted">Buscando productos...</p>
                </div>
            `;

            setTimeout(() => {
                const searchLower = searchTerm.toLowerCase();
                allProducts = products.filter(p =>
                    (p.code && p.code.toLowerCase().includes(searchLower)) ||
                    (p.name && p.name.toLowerCase().includes(searchLower)) ||
                    (p.description && p.description.toLowerCase().includes(searchLower))
                );

                if (allProducts.length > 0) {
                    populateFilters();
                    applyFilters();
                } else {
                    resultsContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No se encontraron productos con el término: <strong>${searchTerm}</strong>
                        </div>
                    `;
                    resultsSummary.style.display = 'none';
                }
            }, 100);
        }

        function populateFilters() {
            const brands = [...new Set(allProducts.map(p => p.brand).filter(b => b))];
            brandFilter.innerHTML = '<option value="">Todas las marcas</option>';
            brands.forEach(brand => {
                brandFilter.innerHTML += `<option value="${brand}">${brand}</option>`;
            });

            const warehousesSet = new Set();
            allProducts.forEach(product => {
                if (product.warehouses) {
                    Object.keys(product.warehouses).forEach(warehouse => {
                        if (product.warehouses[warehouse] > 0) {
                            warehousesSet.add(warehouse);
                        }
                    });
                }
            });

            warehouseFilter.innerHTML = '<option value="">Todos los almacenes</option>';
            warehousesSet.forEach(warehouse => {
                warehouseFilter.innerHTML += `<option value="${warehouse}">${warehouse}</option>`;
            });
        }

        function applyFilters() {
            let filtered = [...allProducts];

            const selectedBrand = brandFilter.value;
            if (selectedBrand) {
                filtered = filtered.filter(p => p.brand === selectedBrand);
            }

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

            document.getElementById('results_count').textContent = count;
            resultsSummary.style.display = 'block';

            if (count === 0) {
                resultsContainer.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                        <p>No se encontraron productos con los filtros aplicados</p>
                    </div>
                `;
                return;
            }

            const productsHtml = filteredProducts.map(product => {
                const whList = Object.entries(product.warehouses || {})
                    .filter(([warehouse, stock]) => stock > 0)
                    .map(([warehouse, stock]) => `${warehouse}: ${stock}`)
                    .join(', ');

                const imageHtml = product.image_url ?
                    `<img src="<?= BASE_URL ?>/${product.image_url}" alt="${product.name}"
                          class="img-thumbnail product-image" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                          onclick="showImagePreview('<?= BASE_URL ?>/${product.image_url}')">` :
                    '<div class="bg-body-tertiary border rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;"><i class="fas fa-image text-muted"></i></div>';

                return `
                    <div class="card mb-2 product-result" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <div class="card-body p-3">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    ${imageHtml}
                                </div>
                                <div class="col">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1" style="color: var(--color-ink);">${product.code} - ${product.name}</h6>
                                            <p class="text-muted mb-1 small">${product.description || ''}</p>
                                            <div class="d-flex flex-wrap gap-3 small text-muted">
                                                ${product.brand ? `<span><i class="fas fa-tag text-warning me-1"></i> ${product.brand}</span>` : ''}
                                                <span class="font-monospace fw-bold text-success"><i class="fas fa-dollar-sign me-1"></i> ${product.price_currency} ${parseFloat(product.regular_price).toFixed(2)}</span>
                                                ${whList ? `<span><i class="fas fa-warehouse text-info me-1"></i> ${whList}</span>` : ''}
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <button type="button" class="btn btn-primary btn-sm add-product-btn"
                                                    data-product='${JSON.stringify(product)}'>
                                                <i class="fas fa-plus me-1"></i> Agregar
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

            resultsContainer.querySelectorAll('.add-product-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productData = JSON.parse(this.dataset.product);

                    let description = productData.name;
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

    function initCustomerModal() {
        const customerModal = document.getElementById('newCustomerModal');
        if (customerModal) {
            const documentInput = customerModal.querySelector('#document_number');
            const searchBtn = customerModal.querySelector('#search_document_btn');
            const saveBtn = customerModal.querySelector('#save_customer_btn');

            if (documentInput) {
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
        }
    }

    function searchCustomerByDocument(documentValue) {
        const modal = document.getElementById('newCustomerModal');
        const button = modal.querySelector('#search_document_btn');
        const originalText = button.innerHTML;

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

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch(`<?= BASE_URL ?>/api/lookup_document.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: `document=${encodeURIComponent(documentValue)}`
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.data) {
                    modal.querySelector('#document_number').value = documentValue;

                    if (documentType === 'ruc') {
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
                    alert('✅ Datos encontrados exitosamente. Complete los campos adicionales y guarde el cliente.');
                } else {
                    modal.querySelector('#customer_data').style.display = 'block';
                    modal.querySelector('#document_number').value = documentValue;
                    const errorMsg = data.message || 'No se encontraron datos para este documento. Complete los datos manualmente.';
                    alert('⚠️ ' + errorMsg);
                    modal.querySelector('#save_customer_btn').disabled = false;
                }
            })
            .catch(error => {
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

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        fetch(`<?= BASE_URL ?>/api/customers.php`, {
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
                    option.setAttribute('data-tax-id', data.customer.document);
                    option.setAttribute('data-address', data.customer.address || '');
                    option.setAttribute('data-phone', data.customer.phone || '');
                    option.setAttribute('data-email', data.customer.email || '');
                    customerSelect.add(option);
                    customerSelect.value = data.customer.id;
                    updateSelectedCustomerInfo();

                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    modalInstance.hide();

                    modal.querySelector('#newCustomerForm').reset();
                    modal.querySelector('#customer_data').style.display = 'none';
                    modal.querySelector('#save_customer_btn').disabled = true;

                    alert('✅ Cliente creado exitosamente');
                } else {
                    alert('❌ Error: ' + (data.message || 'Error al crear el cliente'));
                }
            })
            .catch(error => {
                alert('❌ Error al crear el cliente');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
    }
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
