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
    header('Location: ' . BASE_URL . '/quotations/index.php');
    exit;
}

// Initialize repositories
$quotationRepo = new Quotation();
$customerRepo = new Customer();

// Get quotation data
$quotation = $quotationRepo->getById((int)$quotationId, $companyId);
if (!$quotation) {
    $_SESSION['error_message'] = 'Cotización no encontrada';
    header('Location: ' . BASE_URL . '/quotations/index.php');
    exit;
}

// Get customer
$customer = $customerRepo->getById($quotation['customer_id'], $companyId);

// Get quotation items
$db = getDBConnection();
$stmt = $db->prepare("
    SELECT * FROM quotation_items
    WHERE quotation_id = ?
    ORDER BY id ASC
");
$stmt->execute([$quotationId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Cotización ' . $quotation['quotation_number'];
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
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--gray-100);
            padding-bottom: 100px;
            font-size: 16px;
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
            font-size: 18px;
            margin: 0;
            font-weight: 600;
        }

        .mobile-header .back-btn {
            color: white;
            text-decoration: none;
            font-size: 24px;
            margin-right: 10px;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-top: 5px;
        }

        .status-draft { background: var(--gray-200); color: #495057; }
        .status-sent { background: #cfe2ff; color: #084298; }
        .status-accepted { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #842029; }

        /* Info Section */
        .info-section {
            background: white;
            margin: 10px;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 14px;
        }

        .info-value {
            font-weight: 500;
            text-align: right;
            font-size: 14px;
        }

        /* Product Item */
        .product-item {
            background: white;
            margin: 10px;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
        }

        .product-item-header {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            font-size: 15px;
        }

        .product-description {
            color: #495057;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .product-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-200);
        }

        .detail-item {
            text-align: center;
        }

        .detail-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #212529;
        }

        /* Totals Card */
        .totals-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 15px;
            margin: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
        }

        .total-row.grand-total {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
            border-top: 2px solid #adb5bd;
            padding-top: 12px;
            margin-top: 8px;
        }

        /* Action Buttons */
        .action-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            z-index: 999;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 5px;
            border: none;
            background: transparent;
            color: var(--primary-color);
            font-size: 24px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .action-btn:active {
            background: var(--gray-100);
            transform: scale(0.95);
        }

        .action-btn span {
            font-size: 11px;
            margin-top: 4px;
            font-weight: 500;
        }

        .action-btn.success { color: var(--success-color); }
        .action-btn.warning { color: var(--warning-color); }
        .action-btn.danger { color: var(--danger-color); }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
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

        /* Section Title */
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 15px 10px 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--gray-200);
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
            <p class="mt-3 mb-0">Procesando...</p>
        </div>
    </div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <a href="<?= BASE_URL ?>/quotations/index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1><?= htmlspecialchars($quotation['quotation_number']) ?></h1>
                    <span class="status-badge status-<?= strtolower($quotation['status']) ?>">
                        <?= htmlspecialchars($quotation['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Info -->
    <div class="info-section">
        <h6 class="mb-3" style="color: var(--primary-color); font-weight: 600;">
            <i class="fas fa-user"></i> Cliente
        </h6>
        <div class="info-row">
            <span class="info-label">Nombre:</span>
            <span class="info-value"><?= htmlspecialchars($customer['name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">RUC/DNI:</span>
            <span class="info-value"><?= htmlspecialchars($customer['tax_id']) ?></span>
        </div>
        <?php if (!empty($customer['email'])): ?>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value"><?= htmlspecialchars($customer['email']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($customer['phone'])): ?>
        <div class="info-row">
            <span class="info-label">Teléfono:</span>
            <span class="info-value"><?= htmlspecialchars($customer['phone']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quotation Info -->
    <div class="info-section">
        <h6 class="mb-3" style="color: var(--primary-color); font-weight: 600;">
            <i class="fas fa-file-alt"></i> Información
        </h6>
        <div class="info-row">
            <span class="info-label">Fecha:</span>
            <span class="info-value"><?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></span>
        </div>
        <?php if (!empty($quotation['valid_until'])): ?>
        <div class="info-row">
            <span class="info-label">Válido hasta:</span>
            <span class="info-value"><?= date('d/m/Y', strtotime($quotation['valid_until'])) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="info-label">Moneda:</span>
            <span class="info-value"><?= $quotation['currency'] === 'USD' ? 'Dólares (USD)' : 'Soles (PEN)' ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Condición de Pago:</span>
            <span class="info-value">
                <?php
                $paymentCondition = $quotation['payment_condition'] ?? 'cash';
                if ($paymentCondition === 'credit') {
                    echo 'Crédito';
                    if (!empty($quotation['credit_days'])) {
                        echo ' (' . $quotation['credit_days'] . ' días)';
                    }
                } else {
                    echo 'Efectivo / Contado';
                }
                ?>
            </span>
        </div>
    </div>

    <!-- Products -->
    <div class="section-title">
        <i class="fas fa-box"></i> Productos (<?= count($items) ?>)
    </div>

    <?php
    $currencySymbol = $quotation['currency'] === 'USD' ? '$' : 'S/';
    foreach ($items as $index => $item):
        $lineTotal = $item['quantity'] * $item['unit_price'];
        if (!empty($item['discount_percentage'])) {
            $lineTotal -= ($lineTotal * $item['discount_percentage'] / 100);
        }
    ?>
    <div class="product-item">
        <div class="product-item-header">
            Producto #<?= $index + 1 ?>
        </div>
        <div class="product-description">
            <?= htmlspecialchars($item['description']) ?>
        </div>
        <div class="product-details">
            <div class="detail-item">
                <div class="detail-label">Cantidad</div>
                <div class="detail-value"><?= number_format($item['quantity'], 2) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Precio Unit.</div>
                <div class="detail-value"><?= $currencySymbol ?> <?= number_format($item['unit_price'], 2) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Total</div>
                <div class="detail-value" style="color: var(--primary-color);"><?= $currencySymbol ?> <?= number_format($lineTotal, 2) ?></div>
            </div>
        </div>
        <?php if (!empty($item['discount_percentage']) && $item['discount_percentage'] > 0): ?>
        <div class="mt-2">
            <span class="badge bg-success">Descuento: <?= number_format($item['discount_percentage'], 2) ?>%</span>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Totals -->
    <div class="totals-card">
        <div class="total-row">
            <span>Subtotal:</span>
            <span><?= $currencySymbol ?> <?= number_format($quotation['subtotal'], 2) ?></span>
        </div>
        <?php if (!empty($quotation['global_discount_amount']) && $quotation['global_discount_amount'] > 0): ?>
        <div class="total-row">
            <span>Descuento:</span>
            <span>- <?= $currencySymbol ?> <?= number_format($quotation['global_discount_amount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="total-row grand-total">
            <span>TOTAL:</span>
            <span><?= $currencySymbol ?> <?= number_format($quotation['total'], 2) ?></span>
        </div>
    </div>

    <!-- Notes -->
    <?php if (!empty($quotation['notes'])): ?>
    <div class="info-section">
        <h6 class="mb-2" style="color: var(--primary-color); font-weight: 600;">
            <i class="fas fa-sticky-note"></i> Notas
        </h6>
        <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($quotation['notes']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <?php if (strtolower($quotation['status']) === 'accepted'): ?>
    <!-- Botones cuando está ACEPTADA -->
    <div class="action-buttons" style="grid-template-columns: repeat(3, 1fr);">
        <button type="button" class="action-btn" onclick="downloadPDF()">
            <i class="fas fa-file-pdf"></i>
            <span>PDF</span>
        </button>
        <button type="button" class="action-btn success" onclick="sendByWhatsApp()">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
        </button>
        <button type="button" class="action-btn" style="color: #6f42c1;" onclick="requestInvoice()">
            <i class="fas fa-file-invoice"></i>
            <span>Facturar</span>
        </button>
    </div>
    <?php else: ?>
    <!-- Botones normales -->
    <div class="action-buttons">
        <button type="button" class="action-btn" onclick="downloadPDF()">
            <i class="fas fa-file-pdf"></i>
            <span>PDF</span>
        </button>
        <button type="button" class="action-btn success" onclick="sendByEmail()">
            <i class="fas fa-envelope"></i>
            <span>Email</span>
        </button>
        <button type="button" class="action-btn success" onclick="sendByWhatsApp()">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
        </button>
        <button type="button" class="action-btn warning" onclick="editQuotation()">
            <i class="fas fa-edit"></i>
            <span>Editar</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const quotationId = <?= $quotationId ?>;
        const customerEmail = '<?= addslashes($customer['email'] ?? '') ?>';

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

        function showLoading(message = 'Procesando...') {
            const overlay = document.getElementById('loadingOverlay');
            overlay.querySelector('p').textContent = message;
            overlay.classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function downloadPDF() {
            showLoading('Generando PDF...');
            window.location.href = `${BASE_URL}/quotations/pdf.php?id=${quotationId}`;
            setTimeout(hideLoading, 1000);
        }

        function sendByEmail(forceResend = false) {
            if (!customerEmail) {
                alert('❌ El cliente no tiene email registrado');
                return;
            }

            if (!forceResend && !confirm(`¿Enviar cotización a ${customerEmail}?`)) {
                return;
            }

            showLoading('Enviando por email...');

            let body = `id=${quotationId}`;
            if (forceResend) {
                body += '&force_resend=1';
            }

            fetch(`${BASE_URL}/quotations/send_email.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();

                // Check if needs confirmation (quotation already accepted)
                if (!data.success && data.needs_confirmation) {
                    if (confirm('⚠️ ' + data.message)) {
                        sendByEmail(true); // Recursive call with force
                    }
                    return;
                }

                if (data.success) {
                    alert('✅ Cotización enviada por email exitosamente');
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('❌ Error al enviar email');
            });
        }

        function sendByWhatsApp() {
            showLoading('Preparando WhatsApp...');

            const formData = new FormData();
            formData.append('id', quotationId);

            fetch(`${BASE_URL}/quotations/send_whatsapp.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();

                if (!data.success) {
                    alert('❌ Error: ' + (data.message || 'Error al preparar WhatsApp'));
                    return;
                }

                const msgText = waInjectEmoji(data.message_text || '');
                const phone   = data.phone || '';
                // On mobile, redirect directly to wa.me — this opens the WhatsApp app
                window.location.href = `https://wa.me/${phone}?text=${encodeURIComponent(msgText)}`;
            })
            .catch(error => {
                hideLoading();
                alert('❌ Error al preparar WhatsApp');
            });
        }

        function editQuotation() {
            if (confirm('¿Desea editar esta cotización?')) {
                window.location.href = `${BASE_URL}/quotations/edit_mobile.php?id=${quotationId}`;
            }
        }

        function requestInvoice() {
            if (!confirm('¿Solicitar facturación para esta cotización?\n\nSe enviará a facturación para su procesamiento.')) {
                return;
            }

            showLoading('Solicitando facturación...');

            fetch(`${BASE_URL}/api/request_invoice.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `quotation_id=${quotationId}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();

                if (data.success) {
                    alert('✅ ' + (data.message || 'Solicitud de facturación enviada exitosamente'));
                    // Recargar la página para mostrar el nuevo estado
                    window.location.reload();
                } else {
                    alert('❌ Error: ' + (data.message || 'Error al solicitar facturación'));
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('❌ Error al solicitar facturación');
            });
        }
    </script>
</body>
</html>
