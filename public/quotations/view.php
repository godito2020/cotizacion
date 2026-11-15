<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Redirect to mobile version if on mobile device (unless explicitly disabled)
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        $quotationId = $_GET['id'] ?? 0;
        header('Location: ' . BASE_URL . '/quotations/view_mobile.php?id=' . $quotationId);
        exit;
    }
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$quotationId = $_GET['id'] ?? 0;

$quotationRepo = new Quotation();
$quotation = $quotationRepo->getById($quotationId, $companyId);

if (!$quotation) {
    $_SESSION['error_message'] = 'Cotización no encontrada';
    $auth->redirect(BASE_URL . '/quotations/index.php');
}

$pageTitle = 'Cotización: ' . $quotation['quotation_number'];
$activePage = 'quotations';

// Company information from settings
$db = getDBConnection();
$settingsQuery = "SELECT setting_key, setting_value FROM settings WHERE company_id = ?";
$settingsStmt = $db->prepare($settingsQuery);
$settingsStmt->execute([$companyId]);
$settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

$company = [];
foreach ($settingsRows as $row) {
    $company[$row['setting_key']] = $row['setting_value'];
}

// Exchange rate
$companySettings = new CompanySettings();
$exchangeRate = $companySettings->getSetting($companyId, 'exchange_rate_usd_pen');
$exchangeRate = !empty($exchangeRate) ? floatval($exchangeRate) : 3.80;

// Vendor information
$vendorQuery = "SELECT id, username, email, phone, first_name, last_name, signature_url FROM users WHERE id = ?";
$vendorStmt = $db->prepare($vendorQuery);
$vendorStmt->execute([$quotation['user_id']]);
$vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);

// Full customer details
$customerRepo = new Customer();
$customer = $customerRepo->getById($quotation['customer_id'], $companyId);

// Currency details
$currency = $quotation['currency'] ?? 'USD';
$currencySymbol = $currency === 'PEN' ? 'S/' : ($currency === 'EUR' ? '€' : '$');
$currencyLabel = $currency === 'PEN' ? 'Soles (PEN)' : ($currency === 'EUR' ? 'Euros (EUR)' : 'Dólares (USD)');

// Calculation values
$total = (float)$quotation['total'];
$igvMode = $quotation['igv_mode'] ?? 'included';
$subtotalSinIGV = $total / 1.18;
$igv = $total - $subtotalSinIGV;

// Status details
$statusClasses = [
    'Draft'    => 'bg-secondary-subtle text-secondary border',
    'Sent'     => 'bg-info-subtle text-info border border-info-subtle',
    'Accepted' => 'bg-success-subtle text-success border border-success-subtle',
    'Rejected' => 'bg-danger-subtle text-danger border border-danger-subtle',
    'Invoiced' => 'bg-primary-subtle text-primary border border-primary-subtle'
];
$statusNames = [
    'Draft'    => 'Borrador',
    'Sent'     => 'Enviada',
    'Accepted' => 'Aceptada',
    'Rejected' => 'Rechazada',
    'Invoiced' => 'Facturada'
];
$statusIcons = [
    'Draft'    => 'fa-pen-ruler',
    'Sent'     => 'fa-paper-plane',
    'Accepted' => 'fa-check-circle',
    'Rejected' => 'fa-times-circle',
    'Invoiced' => 'fa-file-invoice-dollar'
];
$currentStatusClass = $statusClasses[$quotation['status']] ?? 'bg-secondary-subtle text-secondary border';
$currentStatusName  = $statusNames[$quotation['status']] ?? $quotation['status'];
$currentStatusIcon  = $statusIcons[$quotation['status']] ?? 'fa-circle';

// Check if any items have discounts
$hasItemDiscounts = false;
foreach ($quotation['items'] as $item) {
    if (!empty($item['discount_percentage']) && (float)$item['discount_percentage'] > 0) {
        $hasItemDiscounts = true;
        break;
    }
}

// Payment & Credit Details
$paymentCondition = $quotation['payment_condition'] ?? 'cash';
$creditStatus = $quotation['credit_status'] ?? null;
$creditInfo = null;
if ($paymentCondition === 'credit' && $quotation['status'] === 'Accepted') {
    $creditManager = new CreditManager();
    $creditInfo = $creditManager->getCreditStatus($quotation['id']);
}

require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="quotation-view-container mx-auto" style="max-width: 1100px;">
    <!-- =========================================================
         BARRA SUPERIOR: ACCIONES Y RESUMEN RÁPIDO
         ========================================================= -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="live-pulse-dot" title="Cotización Activa"></span>
                <h1 class="h3 mb-0 fw-bold" style="font-family: var(--font-display);">Cotización: <?= htmlspecialchars($quotation['quotation_number']) ?></h1>
                <span class="badge <?= $currentStatusClass ?> px-2 py-1">
                    <i class="fas <?= $currentStatusIcon ?> me-1"></i><?= $currentStatusName ?>
                </span>
            </div>
            <p class="text-muted small mb-0">
                <i class="fas fa-building text-warning me-1"></i><strong><?= htmlspecialchars($company['company_name'] ?? 'Empresa') ?></strong>
                · RUC: <?= htmlspecialchars($company['company_tax_id'] ?? 'N/A') ?>
                · TC: <span class="badge bg-light text-dark border">USD 1 = S/ <?= number_format($exchangeRate, 3) ?></span>
                · Vendedor: <strong><?= htmlspecialchars($quotation['user_first_name'] . ' ' . $quotation['user_last_name']) ?></strong>
            </p>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>

            <?php if ($quotation['status'] === 'Draft'): ?>
                <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $quotation['id'] ?>" class="btn btn-primary btn-sm px-3 fw-bold">
                    <i class="fas fa-edit me-1"></i> Editar
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>&print=1" target="_blank" class="btn btn-outline-secondary btn-sm px-3" title="Imprimir formato oficial PDF">
                <i class="fas fa-print me-1"></i> Imprimir
            </a>

            <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>&download=1" class="btn btn-outline-danger btn-sm px-3" title="Descargar PDF">
                <i class="fas fa-file-pdf me-1"></i> PDF
            </a>

            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="duplicateQuotation(<?= $quotation['id'] ?>)">
                <i class="fas fa-copy me-1"></i> Duplicar
            </button>

            <button type="button" class="btn btn-outline-success btn-sm px-3 fw-bold" onclick="sendByWhatsApp(<?= $quotation['id'] ?>)">
                <i class="fab fa-whatsapp me-1"></i> WhatsApp
            </button>

            <button type="button" class="btn btn-outline-info btn-sm px-3" onclick="sendByEmail(<?= $quotation['id'] ?>)">
                <i class="fas fa-envelope me-1"></i> Correo
            </button>
        </div>
    </div>

    <!-- =========================================================
         1. CUADRO: DATOS DEL CLIENTE
         ========================================================= -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-user-tag text-warning me-2"></i> Cliente</span>
            <span class="badge bg-light text-muted border"><?= strlen($customer['tax_id'] ?? '') == 8 ? 'DNI' : 'RUC' ?>: <?= htmlspecialchars($customer['tax_id'] ?? 'S/N') ?></span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5 col-12">
                    <div class="p-3 rounded h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <label class="text-muted small fw-semibold d-block mb-1">Nombre / Razón Social</label>
                        <h6 class="mb-2 fw-bold" style="color: var(--color-ink);"><?= htmlspecialchars($quotation['customer_name']) ?></h6>
                        <?php if (!empty($quotation['customer_reference'])): ?>
                            <div class="badge bg-secondary-subtle text-secondary border">
                                <i class="fas fa-tag me-1"></i> Ref: <?= htmlspecialchars($quotation['customer_reference']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="p-3 rounded h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <label class="text-muted small fw-semibold d-block mb-1">Contacto y Comunicación</label>
                        <div class="small mb-1">
                            <i class="fas fa-user text-muted me-1"></i> <strong>Atención:</strong> <?= htmlspecialchars($customer['contact_person'] ?? $quotation['customer_reference'] ?? '-') ?>
                        </div>
                        <div class="small mb-1">
                            <i class="fas fa-phone text-muted me-1"></i> <strong>Teléfono:</strong> <?= htmlspecialchars($customer['phone'] ?? '-') ?>
                        </div>
                        <div class="small">
                            <i class="fas fa-envelope text-muted me-1"></i> <strong>Email:</strong> <?= htmlspecialchars($customer['email'] ?? '-') ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="p-3 rounded h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <label class="text-muted small fw-semibold d-block mb-1">Dirección Fiscal / Entrega</label>
                        <div class="small text-muted">
                            <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= nl2br(htmlspecialchars($customer['address'] ?? 'No registrada')) ?>
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
                    <label class="form-label small fw-semibold text-muted mb-1">Fecha Emisión</label>
                    <div class="fw-bold"><?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Válida Hasta</label>
                    <div>
                        <?php if ($quotation['valid_until']): ?>
                            <span class="fw-bold"><?= date('d/m/Y', strtotime($quotation['valid_until'])) ?></span>
                            <?php if (strtotime($quotation['valid_until']) < time()): ?>
                                <span class="badge bg-danger ms-1">Vencida</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Sin vencimiento</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Moneda</label>
                    <div class="fw-bold"><?= $currencyLabel ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Régimen IGV</label>
                    <div>
                        <span class="badge bg-light text-dark border">
                            <?= $igvMode === 'plus_igv' ? 'SIN IGV (+18%)' : 'INCLUIDO IGV' ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <label class="form-label small fw-semibold text-muted mb-1">Forma de Pago</label>
                    <div class="fw-bold">
                        <?php if ($paymentCondition === 'credit'): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-credit-card me-1"></i> Crédito (<?= (int)($quotation['credit_days'] ?? 0) ?> días)</span>
                        <?php else: ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-money-bill-wave me-1"></i> Efectivo / Contado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <label class="form-label small fw-semibold text-muted mb-1">Vendedor Asignado</label>
                    <div class="small">
                        <strong><?= htmlspecialchars($quotation['user_first_name'] . ' ' . $quotation['user_last_name']) ?></strong>
                        <?php if (!empty($vendor['email'])): ?>
                            <span class="text-muted">(<?= htmlspecialchars($vendor['email']) ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. CUADRO: ÍTEMS DE LA COTIZACIÓN
         ========================================================= -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-boxes-stacked text-warning me-2"></i> Ítems de la Cotización</span>
            <span class="badge bg-light text-muted border"><?= count($quotation['items']) ?> producto<?= count($quotation['items']) == 1 ? '' : 's' ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 45px;">#</th>
                            <th>Descripción / Producto</th>
                            <th class="text-center" style="width: 100px;">Cant.</th>
                            <th class="text-end" style="width: 140px;">Precio Unit.</th>
                            <?php if ($hasItemDiscounts): ?>
                                <th class="text-center" style="width: 90px;">Desc. %</th>
                            <?php endif; ?>
                            <th class="text-end" style="width: 140px;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotation['items'] as $index => $item): ?>
                            <tr>
                                <td class="text-center text-muted small"><?= $index + 1 ?></td>
                                <td>
                                    <div class="fw-bold" style="color: var(--color-ink);"><?= htmlspecialchars($item['description']) ?></div>
                                    <?php if (!empty($item['product_code'])): ?>
                                        <div class="small text-muted font-monospace"><i class="fas fa-barcode me-1"></i>Código: <?= htmlspecialchars($item['product_code']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace fw-semibold" style="font-size: 14px;">
                                    <?= number_format($item['quantity'], 0) ?>
                                </td>
                                <td class="text-end font-monospace">
                                    <?= $currencySymbol ?> <?= number_format($item['unit_price'], 2) ?>
                                </td>
                                <?php if ($hasItemDiscounts): ?>
                                    <td class="text-center">
                                        <?php if (!empty($item['discount_percentage']) && (float)$item['discount_percentage'] > 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">-<?= number_format($item['discount_percentage'], 0) ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="text-end font-monospace fw-bold" style="color: var(--color-ink); font-size: 14px;">
                                    <?= $currencySymbol ?> <?= number_format($item['line_total'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =========================================================
         4. CUADRO: RESUMEN DE COTIZACIÓN (Directamente debajo de Ítems)
         ========================================================= -->
    <div class="card mb-4 border-0 shadow-sm" style="background-color: var(--color-panel);">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-receipt text-warning me-2"></i> Resumen de Cotización</span>
            <span class="badge bg-light text-muted border">Totales</span>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <!-- Acciones Rápidas -->
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="p-3 rounded h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <label class="form-label small fw-semibold text-muted mb-2">
                            <i class="fas fa-share-nodes text-warning me-1"></i> Acciones con esta Cotización
                        </label>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>&print=1" target="_blank" class="btn btn-outline-secondary btn-sm" title="Imprimir formato oficial PDF">
                                <i class="fas fa-print me-1"></i> Imprimir PDF
                            </a>
                            <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>&download=1" class="btn btn-outline-danger btn-sm" title="Descargar archivo PDF">
                                <i class="fas fa-file-pdf me-1"></i> Descargar
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="sendByWhatsApp(<?= $quotation['id'] ?>)">
                                <i class="fab fa-whatsapp me-1"></i> WhatsApp
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="sendByEmail(<?= $quotation['id'] ?>)">
                                <i class="fas fa-envelope me-1"></i> Correo
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="duplicateQuotation(<?= $quotation['id'] ?>)">
                                <i class="fas fa-copy me-1"></i> Duplicar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desglose de Totales -->
                <div class="col-md-6">
                    <div class="p-3 rounded" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Subtotal (sin IGV):</span>
                            <span class="fw-semibold tabular-nums" style="font-family: var(--font-mono); font-size: 1.05rem;">
                                <?= $currencySymbol ?> <?= number_format($subtotalSinIGV, 2) ?>
                            </span>
                        </div>

                        <?php if ($quotation['global_discount_percentage'] > 0): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 text-danger">
                                <span>Descuento Global (<?= number_format($quotation['global_discount_percentage'], 2) ?>%):</span>
                                <span class="fw-semibold tabular-nums" style="font-family: var(--font-mono); font-size: 1.05rem;">
                                    - <?= $currencySymbol ?> <?= number_format($quotation['global_discount_amount'], 2) ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted"><?= $igvMode === 'plus_igv' ? 'IGV (18% adicional):' : 'IGV (18% incluido):' ?></span>
                            <span class="fw-semibold tabular-nums" style="font-family: var(--font-mono); font-size: 1.05rem;">
                                <?= $currencySymbol ?> <?= number_format($igv, 2) ?>
                            </span>
                        </div>

                        <hr class="my-2 border-secondary-subtle">

                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="h5 mb-0 fw-bold">TOTAL:</span>
                            <span class="h3 mb-0 fw-bold tabular-nums" style="font-family: var(--font-mono); color: var(--color-action);">
                                <?= $currencySymbol ?> <?= number_format($total, 2) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         5. CUADRO: ESTADO DE CRÉDITO Y FACTURACIÓN (Si Aplica)
         ========================================================= -->
    <?php if ($paymentCondition === 'credit' && $quotation['status'] === 'Accepted'): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning-subtle text-dark">
                <span class="fw-bold"><i class="fas fa-credit-card me-2"></i> Estado de Aprobación de Crédito</span>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <p class="mb-2">
                            <strong>Condición:</strong>
                            <span class="badge bg-warning text-dark">Crédito <?= (int)($quotation['credit_days'] ?? 0) ?> días</span>
                        </p>
                        <?php if ($creditInfo): ?>
                            <p class="mb-2">
                                <strong>Estado de Crédito:</strong>
                                <?php
                                $creditClasses = [
                                    'Pending'  => 'bg-warning text-dark',
                                    'Approved' => 'bg-success',
                                    'Rejected' => 'bg-danger'
                                ];
                                $creditNames = [
                                    'Pending'  => 'Pendiente de Aprobación',
                                    'Approved' => 'Crédito Aprobado',
                                    'Rejected' => 'Crédito Rechazado'
                                ];
                                $creditClass = $creditClasses[$creditInfo['status']] ?? 'bg-secondary';
                                $creditName = $creditNames[$creditInfo['status']] ?? $creditInfo['status'];
                                ?>
                                <span class="badge <?= $creditClass ?>"><?= $creditName ?></span>
                            </p>
                            <?php if ($creditInfo['status'] === 'Approved' && !empty($creditInfo['credit_user_name'])): ?>
                                <p class="mb-1 text-muted small">
                                    <i class="fas fa-user-check text-success me-1"></i> Aprobado por <strong><?= htmlspecialchars($creditInfo['credit_user_name']) ?></strong>
                                    el <?= date('d/m/Y H:i', strtotime($creditInfo['processed_at'])) ?>
                                </p>
                            <?php elseif ($creditInfo['status'] === 'Rejected'): ?>
                                <p class="mb-1 text-danger small">
                                    <i class="fas fa-user-times me-1"></i> Rechazado por <strong><?= htmlspecialchars($creditInfo['credit_user_name'] ?? 'Créditos') ?></strong>
                                    el <?= date('d/m/Y H:i', strtotime($creditInfo['processed_at'])) ?>
                                </p>
                                <?php if (!empty($creditInfo['rejection_reason'])): ?>
                                    <div class="alert alert-danger py-2 mt-2 mb-0 small">
                                        <strong>Motivo:</strong> <?= nl2br(htmlspecialchars($creditInfo['rejection_reason'])) ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($creditInfo['status'] === 'Pending'): ?>
                                <p class="mb-0 text-muted small">
                                    <i class="fas fa-clock text-warning me-1"></i> Solicitado el <?= date('d/m/Y H:i', strtotime($creditInfo['requested_at'])) ?>
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mb-0 text-muted small">
                                <i class="fas fa-info-circle text-info me-1"></i> El crédito será evaluado al solicitar facturación.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-5 text-md-end mt-3 mt-md-0">
                        <?php
                        $canRequestBilling = empty($quotation['billing_status']) || $quotation['billing_status'] === 'Invoice_Rejected';
                        if ($paymentCondition === 'credit' && $creditStatus === 'Credit_Rejected') {
                            $canRequestBilling = true;
                        }
                        ?>
                        <?php if ($canRequestBilling): ?>
                            <a href="<?= BASE_URL ?>/billing/request.php?id=<?= $quotation['id'] ?>" class="btn btn-success fw-bold">
                                <i class="fas fa-file-invoice me-1"></i> Solicitar Facturación
                            </a>
                        <?php elseif ($creditStatus === 'Pending_Credit'): ?>
                            <button class="btn btn-outline-info" disabled>
                                <i class="fas fa-clock me-1"></i> Pendiente Aprobación Crédito
                            </button>
                        <?php elseif ($quotation['billing_status'] === 'Pending_Invoice'): ?>
                            <button class="btn btn-outline-warning" disabled>
                                <i class="fas fa-clock me-1"></i> Pendiente de Facturación
                            </button>
                        <?php elseif ($quotation['billing_status'] === 'Invoiced'): ?>
                            <span class="badge bg-success fs-6 p-2">
                                <i class="fas fa-check-circle me-1"></i> Facturado: <?= htmlspecialchars($quotation['invoice_number']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Acciones de Cambio de Estado (si no es Borrador) -->
    <?php if ($quotation['status'] !== 'Draft'): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-code-branch text-warning me-2"></i> Gestión de Estado</span>
                <span class="badge <?= $currentStatusClass ?>"><?= $currentStatusName ?></span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($quotation['status'] !== 'Sent'): ?>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="changeStatus('Sent')">
                            <i class="fas fa-paper-plane me-1"></i> Marcar como Enviada
                        </button>
                    <?php endif; ?>
                    <?php if ($quotation['status'] === 'Sent'): ?>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="changeStatus('Accepted')">
                            <i class="fas fa-check me-1"></i> Marcar como Aceptada
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="changeStatus('Rejected')">
                            <i class="fas fa-times me-1"></i> Marcar como Rechazada
                        </button>
                    <?php endif; ?>
                    <?php if ($quotation['status'] === 'Accepted' && $paymentCondition !== 'credit'): ?>
                        <?php if (empty($quotation['billing_status']) || $quotation['billing_status'] === 'Invoice_Rejected'): ?>
                            <a href="<?= BASE_URL ?>/billing/request.php?id=<?= $quotation['id'] ?>" class="btn btn-success btn-sm fw-bold">
                                <i class="fas fa-file-invoice me-1"></i> Solicitar Facturación
                            </a>
                        <?php elseif ($quotation['billing_status'] === 'Pending_Invoice'): ?>
                            <span class="badge bg-warning text-dark p-2"><i class="fas fa-clock me-1"></i> Facturación Solicitada</span>
                        <?php elseif ($quotation['billing_status'] === 'Invoiced'): ?>
                            <span class="badge bg-success p-2"><i class="fas fa-check-circle me-1"></i> Facturado: <?= htmlspecialchars($quotation['invoice_number']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         6. CUADRO: NOTAS Y TÉRMINOS COMERCIALES
         ========================================================= -->
    <?php if (!empty($quotation['notes']) || !empty($quotation['terms_and_conditions'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <span class="fw-bold"><i class="fas fa-pen-to-square text-warning me-2"></i> Notas y Términos Comerciales</span>
            </div>
            <div class="card-body">
                <?php if (!empty($quotation['notes'])): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Observaciones / Notas para el Cliente</label>
                        <div class="p-3 rounded" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <?= nl2br(htmlspecialchars($quotation['notes'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($quotation['terms_and_conditions'])): ?>
                    <div class="mb-0">
                        <label class="form-label small fw-semibold text-muted">Términos y Condiciones</label>
                        <div class="p-3 rounded small" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule); white-space: pre-wrap;">
                            <?= htmlspecialchars($quotation['terms_and_conditions']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         7. CUADRO: CUENTAS BANCARIAS REGISTRADAS (Al final de todos los cuadros)
         ========================================================= -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="fw-bold"><i class="fas fa-university text-warning me-2"></i> Cuentas Bancarias Registradas</span>
        </div>
        <div class="card-body">
            <?php
            try {
                $bankQuery = "SELECT bank_name, account_number, account_type, currency
                              FROM bank_accounts
                              WHERE company_id = ? AND is_active = 1
                              ORDER BY currency, bank_name";
                $bankStmt = $db->prepare($bankQuery);
                $bankStmt->execute([$companyId]);
                $bankAccounts = $bankStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($bankAccounts)): ?>
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
                <?php endif;
            } catch (Exception $e) {
                echo '<div class="text-muted text-center py-3">Error al cargar cuentas bancarias.</div>';
            }
            ?>
        </div>
    </div>

    <!-- Metadatos de auditoría -->
    <p class="text-muted text-center small mb-4">
        <i class="fas fa-clock me-1"></i> Cotización generada el <?= date('d/m/Y H:i', strtotime($quotation['created_at'])) ?>
        por <strong><?= htmlspecialchars($quotation['user_first_name'] . ' ' . $quotation['user_last_name']) ?></strong>
    </p>
</div>

<!-- =========================================================
     MODAL DE WHATSAPP
     ========================================================= -->
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
                <button id="waCopyBtn" type="button" class="btn btn-outline-secondary" onclick="waCopyText()">
                    <i class="fas fa-copy me-1"></i> Copiar
                </button>
                <button id="waShareBtn" type="button" class="btn btn-outline-success" style="display:none;">
                    <i class="fas fa-share-alt me-1"></i> Compartir con PDF
                </button>
                <button id="waOpenDesk" type="button" class="btn btn-outline-success">
                    <i class="fab fa-whatsapp me-1"></i> Abrir WhatsApp Escritorio
                </button>
                <button id="waOpenWeb" type="button" class="btn btn-success">
                    <i class="fas fa-globe me-1"></i> Abrir WhatsApp Web
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Replace ASCII placeholders with real emoji
    function waInjectEmoji(text) {
        return text
            .replace(/\[E:CIRCLE\]/g,  '\uD83D\uDD35')  // 🔵
            .replace(/\[E:DOC\]/g,     '\uD83D\uDCC4')  // 📄
            .replace(/\[E:MAIL\]/g,    '\u2709\uFE0F')  // ✉️
            .replace(/\[E:CHECK\]/g,   '\u2705')         // ✅
            .replace(/\[E:POINT\]/g,   '\uD83D\uDC49')  // 👉
            .replace(/\[E:CARD\]/g,    '\uD83D\uDCB3')  // 💳
            .replace(/\[E:DOLLAR\]/g,  '\uD83D\uDCB5')  // 💵
            .replace(/\[E:MONEY\]/g,   '\uD83D\uDCB0')  // 💰
            .replace(/\[E:WARN\]/g,    '\u26A0\uFE0F')  // ⚠️
            .replace(/\[E:PIN\]/g,     '\uD83D\uDCCC')  // 📌
            .replace(/\[E:PHONE\]/g,   '\uD83D\uDCDE'); // 📞
    }

    function changeStatus(newStatus) {
        if (confirm(`¿Cambiar estado de la cotización a ${newStatus}?`)) {
            fetch(`<?= BASE_URL ?>/quotations/change_status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=<?= $quotation['id'] ?>&status=${newStatus}`
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

    function sendByEmail(quotationId, forceResend = false) {
        if (!forceResend && !confirm('¿Enviar cotización por correo electrónico?')) {
            return;
        }

        let body = `id=${quotationId}`;
        if (forceResend) {
            body += '&force_resend=1';
        }

        fetch(`<?= BASE_URL ?>/quotations/send_email.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success && data.needs_confirmation) {
                if (confirm('⚠️ ' + data.message)) {
                    sendByEmail(quotationId, true);
                }
                return;
            }

            if (data.success) {
                alert('✅ Cotización enviada por correo exitosamente');
                window.location.reload();
            } else {
                alert('❌ Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Error al enviar: ' + error.message);
        });
    }

    async function tryNativeShare(pdfUrl, pdfFilename, msgText) {
        if (!navigator.canShare || !navigator.share) return false;
        try {
            const resp = await fetch(pdfUrl, { credentials: 'include' });
            if (!resp.ok) return false;
            const blob = await resp.blob();
            const file = new File([blob], pdfFilename || 'cotizacion.pdf', { type: 'application/pdf' });
            const payload = { files: [file], text: msgText, title: pdfFilename || 'Cotización' };
            if (!navigator.canShare(payload)) return false;
            await navigator.share(payload);
            return true;
        } catch (e) {
            if (e && e.name === 'AbortError') return true;
            console.warn('Web Share failed, falling back to wa.me:', e);
            return false;
        }
    }

    function sendByWhatsApp(quotationId) {
        const btn = event ? event.currentTarget : null;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparando...';
        }

        fetch(`<?= BASE_URL ?>/quotations/send_whatsapp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${quotationId}`
        })
        .then(r => r.json())
        .then(async data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp me-1"></i> WhatsApp';
            }
            if (!data.success) {
                alert('Error: ' + (data.message || 'No se pudo preparar WhatsApp'));
                return;
            }
            const msgText = waInjectEmoji(data.message_text || '');
            const phone   = data.phone || '';
            const pdfUrl  = data.pdf_url || '';
            const pdfName = data.pdf_filename || 'cotizacion.pdf';
            const waWeb   = `https://wa.me/${phone}?text=${encodeURIComponent(msgText)}`;
            const waDesk  = `whatsapp://send?phone=${phone}&text=${encodeURIComponent(msgText)}`;

            fetch(`<?= BASE_URL ?>/quotations/change_status.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${quotationId}&status=Sent`
            }).catch(() => {});

            if (pdfUrl && await tryNativeShare(pdfUrl, pdfName, msgText)) {
                return;
            }

            document.getElementById('waModalText').value = msgText;
            document.getElementById('waOpenWeb').onclick  = () => { window.open(waWeb, '_blank'); };
            document.getElementById('waOpenDesk').onclick = () => { window.location.href = waDesk; };
            const waShareBtn = document.getElementById('waShareBtn');
            if (waShareBtn) {
                if (navigator.canShare && pdfUrl) {
                    waShareBtn.style.display = '';
                    waShareBtn.onclick = () => tryNativeShare(pdfUrl, pdfName, msgText);
                } else {
                    waShareBtn.style.display = 'none';
                }
            }
            new bootstrap.Modal(document.getElementById('waModal')).show();
        })
        .catch(err => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp me-1"></i> WhatsApp';
            }
            alert('Error: ' + err.message);
        });
    }

    function waCopyText() {
        const ta = document.getElementById('waModalText');
        ta.select();
        document.execCommand('copy');
        const btn = document.getElementById('waCopyBtn');
        btn.textContent = 'Copiado!';
        setTimeout(() => btn.textContent = 'Copiar', 2000);
    }

    // Intercept Ctrl+P to print official PDF format instead of active window
    window.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
            e.preventDefault();
            window.open('<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>&print=1', '_blank');
        }
    });
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
