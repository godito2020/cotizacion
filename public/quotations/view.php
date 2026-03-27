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

// Calculate totals for verification
$subtotal = 0;
foreach ($quotation['items'] as $item) {
    $subtotal += $item['line_total'];
}

// Get company information from settings
$db = getDBConnection();
$settingsQuery = "SELECT setting_key, setting_value FROM settings WHERE company_id = ?";
$settingsStmt = $db->prepare($settingsQuery);
$settingsStmt->execute([$companyId]);
$settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

$company = [];
foreach ($settingsRows as $row) {
    $company[$row['setting_key']] = $row['setting_value'];
}

    // Get vendor (user) information
    $vendorQuery = "SELECT id, username, email, phone, first_name, last_name, signature_url FROM users WHERE id = ?";
    $vendorStmt = $db->prepare($vendorQuery);
    $vendorStmt->execute([$quotation['user_id']]);
    $vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);
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
        @media print {
            .no-print { display: none !important; }
            .card { box-shadow: none !important; border: none !important; page-break-inside: avoid; page-break-before: avoid; }
            .card-body { page-break-inside: avoid; padding: 10px !important; margin-top: 0px !important; }
            body { max-width: 210mm; margin: 0 auto; font-size: 11px; line-height: 1.2; }
            @page { size: A4; margin: 0 5mm 5mm 5mm; }
            .table-responsive { page-break-inside: avoid; }
            .row { margin-bottom: 2px !important; }
            h2, h4, h5, h6 { font-size: 14px !important; margin-bottom: 5px !important; }
            .table th, .table td { padding: 4px !important; }
            .mb-1, .mb-2, .mb-3, .mb-4 { margin-bottom: 0rem !important; }
            .footer-notes, .footer-notes * { font-size: 10px !important; }
            .totals-breakdown, .totals-breakdown * { font-size: 10px !important; }
        }
        .quotation-header { background: linear-gradient(135deg, #007bff, #6f42c1); color: white; }
        .company-logo { max-height: 50px; }
        .card { margin-top: 2rem; }
        .card-body { margin-top: 2.25rem; }
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
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h1><i class="fas fa-file-invoice"></i> <?= htmlspecialchars($quotation['quotation_number']) ?></h1>
                    <div class="btn-group">
                        <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <?php if ($quotation['status'] === 'Draft'): ?>
                            <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $quotation['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-info" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                        <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                         <button class="btn btn-success" onclick="duplicateQuotation(<?= $quotation['id'] ?>)">
                             <i class="fas fa-copy"></i> Duplicar
                         </button>
                         <div class="btn-group">
                             <button class="btn btn-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                 <i class="fas fa-share"></i> Enviar
                             </button>
                             <ul class="dropdown-menu">
                                 <li>
                                     <button class="dropdown-item" onclick="sendByEmail(<?= $quotation['id'] ?>)">
                                         <i class="fas fa-envelope"></i> Enviar por Correo
                                     </button>
                                 </li>
                                 <li>
                                     <button class="dropdown-item" onclick="sendByWhatsApp(<?= $quotation['id'] ?>)">
                                         <i class="fab fa-whatsapp"></i> Enviar por WhatsApp
                                     </button>
                                 </li>
                             </ul>
                         </div>
                    </div>
                </div>

                <!-- Quotation Document -->
                <div class="card">
                    <div class="card-body">
                        <!-- Company and Quotation Header -->
                        <table class="table table-borderless mb-1">
                            <tr>
                                <td>
                                    <?php if (!empty($company['company_logo_url'])): ?>
                                        <img src="<?= htmlspecialchars(upload_url($company['company_logo_url'])) ?>" alt="Logo" class="company-logo mb-2">
                                    <?php endif; ?>
                                    <h4 class="text-primary mb-1"><?= htmlspecialchars($company['company_name'] ?? 'N/A') ?></h4>
                                    <?php if (!empty($company['company_address'])): ?>
                                        <p class="mb-0"><i class="fas fa-map-marker-alt text-muted"></i> <?= htmlspecialchars($company['company_address']) ?></p>
                                    <?php endif; ?>

                                    <div class="mt-2">
                                        <?php if (!empty($vendor['email'])): ?>
                                            <p class="mb-0"><i class="fas fa-envelope text-muted"></i> <?= htmlspecialchars($vendor['email']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($vendor['phone'])): ?>
                                            <p class="mb-0"><i class="fas fa-phone text-muted"></i> <?= htmlspecialchars($vendor['phone']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end align-top">
                                    <h2 class="text-primary mb-1" style="margin-top: 3rem;">COTIZACIÓN</h2>
                                    <p class="mb-0"><strong>RUC:</strong> <?= htmlspecialchars($company['company_tax_id'] ?? 'N/A') ?></p>
                                    <p class="mb-0"><strong>N°:</strong> <?= htmlspecialchars($quotation['quotation_number']) ?></p>
                                </td>
                            </tr>
                        </table>

                        <hr>

                        <!-- Header Information -->
                        <table class="table table-borderless mb-1">
                            <tr>
                                <td>
                                    <h5>DATOS DEL CLIENTE</h5>
                                    <address>
                                        <strong><?= htmlspecialchars($quotation['customer_name']) ?></strong><br>
                                        <?php
                                        // Get full customer details
                                        $customerRepo = new Customer();
                                        $customer = $customerRepo->getById($quotation['customer_id'], $companyId);
                                        ?>
                                        <?php if ($customer['contact_person']): ?>
                                            Contacto: <?= htmlspecialchars($customer['contact_person']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($customer['email']): ?>
                                            Email: <?= htmlspecialchars($customer['email']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($customer['phone']): ?>
                                            Teléfono: <?= htmlspecialchars($customer['phone']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($customer['tax_id']): ?>
                                            <?= strlen($customer['tax_id']) == 8 ? 'DNI' : 'RUC' ?>: <?= htmlspecialchars($customer['tax_id']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($customer['address']): ?>
                                            <?= nl2br(htmlspecialchars($customer['address'])) ?>
                                        <?php endif; ?>
                                    </address>
                                </td>
                                <td>
                                    <h5>INFORMACIÓN GENERAL</h5>
                                    <table class="table table-sm" style="margin-bottom: 0; line-height: 1.1;">
                                        <tr>
                                            <th>Fecha:</th>
                                            <td><?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></td>
                                        </tr>
                                        <?php if ($quotation['valid_until']): ?>
                                            <tr>
                                                <th>Válida hasta:</th>
                                                <td>
                                                <?= date('d/m/Y', strtotime($quotation['valid_until'])) ?>
                                                <?php if (strtotime($quotation['valid_until']) < time()): ?>
                                                    <span class="badge bg-danger">Vencida</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Vendedor:</th>
                                            <td><?= htmlspecialchars($quotation['user_first_name'] . ' ' . $quotation['user_last_name']) ?></td>
                                        </tr>
                                        <tr>
                                             <th>Moneda:</th>
                                             <td><?= $quotation['currency'] == 'PEN' ? 'SOLES' : 'DOLARES' ?></td>
                                        </tr>
                                        <tr>
                                            <th>Condición de Pago:</th>
                                            <td>
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
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Estado:</th>
                                            <td>
                                            <?php
                                            $statusClasses = [
                                                'Draft' => 'bg-secondary',
                                                'Sent' => 'bg-info',
                                                'Accepted' => 'bg-success',
                                                'Rejected' => 'bg-danger',
                                                'Invoiced' => 'bg-primary'
                                            ];
                                            $statusNames = [
                                                'Draft' => 'Borrador',
                                                'Sent' => 'Enviada',
                                                'Accepted' => 'Aceptada',
                                                'Rejected' => 'Rechazada',
                                                'Invoiced' => 'Facturada'
                                            ];
                                            $class = $statusClasses[$quotation['status']] ?? 'bg-secondary';
                                            $name = $statusNames[$quotation['status']] ?? $quotation['status'];
                                            ?>
                                            <span class="badge <?= $class ?>"><?= $name ?></span>
                                        </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Items Table -->
                         <div class="table-responsive mb-0">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Descripción</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unit.</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quotation['items'] as $index => $item): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['description']) ?></strong>
                                                <?php if ($item['product_code']): ?>
                                                    <br><small class="text-muted">Código: <?= htmlspecialchars($item['product_code']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?= number_format($item['quantity'], 2) ?></td>
                                             <td class="text-end" style="font-family: monospace;"><?php
                                             $symbol = $quotation['currency'] == 'PEN' ? 'S/' : ($quotation['currency'] == 'USD' ? '$' : ($quotation['currency'] == 'EUR' ? '€' : $quotation['currency']));
                                             echo $symbol . ' ' . number_format($item['unit_price'], 2);
                                             ?></td>
                                             <td class="text-end" style="font-family: monospace;"><strong><?php
                                             $symbol = $quotation['currency'] == 'PEN' ? 'S/' : ($quotation['currency'] == 'USD' ? '$' : ($quotation['currency'] == 'EUR' ? '€' : $quotation['currency']));
                                             echo $symbol . ' ' . number_format($item['line_total'], 2);
                                             ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                         <!-- Totals -->
                         <table class="table table-borderless" style="margin-top: -1rem;">
                            <tr>
                                <td>
                                    <!-- Notes and Terms -->
                                    <?php if ($quotation['notes']): ?>
                                        <h6>Notas:</h6>
                                        <p><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
                                    <?php endif; ?>

                                    <?php if ($quotation['terms_and_conditions']): ?>
                                        <h6>Términos y Condiciones:</h6>
                                        <p><?= nl2br(htmlspecialchars($quotation['terms_and_conditions'])) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    // Calculate IGV breakdown based on igv_mode
                                    $igvMode = $quotation['igv_mode'] ?? 'included';
                                    // Total in DB already includes IGV when igv_mode is 'plus_igv'
                                    $total = $quotation['total'];
                                    $subtotalSinIGV = $total / 1.18;
                                    $igv = $total - $subtotalSinIGV;
                                    ?>
                                     <table class="table table-borderless totals-breakdown" style="margin-bottom: 0;">
                                         <tr>
                                             <td style="border: none; padding: 0;" class="text-end"><strong style="font-size: 16px;">Subtotal (sin IGV):</strong></td>
                                             <td style="border: none; padding: 0; text-align: center;"><strong style="font-size: 16px;"><?php $symbol = $quotation['currency'] == 'PEN' ? 'S/' : ($quotation['currency'] == 'USD' ? '$' : ($quotation['currency'] == 'EUR' ? '€' : $quotation['currency'])); echo $symbol; ?></strong></td>
                                             <td style="border: none; padding: 0;" class="text-end" style="font-family: monospace;"><strong style="font-size: 16px;"><?= number_format($subtotalSinIGV, 2) ?></strong></td>
                                         </tr>
                                         <tr>
                                             <td style="border: none; padding: 0;" class="text-end"><strong style="font-size: 16px;"><?= $igvMode === 'plus_igv' ? 'IGV (18%):' : 'IGV (inc.):' ?></strong></td>
                                             <td style="border: none; padding: 0; text-align: center;"><strong style="font-size: 16px;"><?php $symbol = $quotation['currency'] == 'PEN' ? 'S/' : ($quotation['currency'] == 'USD' ? '$' : ($quotation['currency'] == 'EUR' ? '€' : $quotation['currency'])); echo $symbol; ?></strong></td>
                                             <td style="border: none; padding: 0;" class="text-end" style="font-family: monospace;"><strong style="font-size: 16px;"><?= number_format($igv, 2) ?></strong></td>
                                         </tr>
                                         <?php if ($quotation['global_discount_percentage'] > 0): ?>
                                         <tr>
                                             <td style="border: none; padding: 0;" class="text-end">Descuento Global (<?= number_format($quotation['global_discount_percentage'], 2) ?>%):</td>
                                             <td style="border: none; padding: 0; text-align: center;"><?php $symbol = $quotation['currency'] == 'PEN' ? 'S/' : ($quotation['currency'] == 'USD' ? '$' : ($quotation['currency'] == 'EUR' ? '€' : $quotation['currency'])); echo $symbol; ?></td>
                                             <td style="border: none; padding: 0;" class="text-end" style="font-family: monospace;"><?= number_format($quotation['global_discount_amount'], 2) ?></td>
                                         </tr>
                                         <?php endif; ?>
                                         <tr>
                                             <td style="border: none; padding: 0;" class="text-end"><span style="font-size: 16px; font-weight: bold;">TOTAL:</span></td>
                                             <td style="border: none; padding: 0; text-align: center;"><span style="font-size: 16px; font-weight: bold;"><?php $symbol = $quotation['currency'] == 'PEN' ? 'S/' : ($quotation['currency'] == 'USD' ? '$' : ($quotation['currency'] == 'EUR' ? '€' : $quotation['currency'])); echo $symbol; ?></span></td>
                                             <td style="border: none; padding: 0;" class="text-end" style="font-family: monospace;"><span style="font-size: 16px; font-weight: bold;"><?= number_format($quotation['total'], 2) ?></span></td>
                                         </tr>
                                     </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Footer with Important Notes and Bank Accounts -->
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="card">
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
                                                $dbConn = getDBConnection();
                                                $bankQuery = "SELECT bank_name, account_number, account_type, currency
                                                            FROM bank_accounts
                                                            WHERE company_id = ? AND is_active = 1
                                                            ORDER BY currency, bank_name";
                                                $bankStmt = $dbConn->prepare($bankQuery);
                                                $bankStmt->execute([$companyId]);
                                                $bankAccounts = $bankStmt->fetchAll(PDO::FETCH_ASSOC);

                                                if (!empty($bankAccounts)): ?>
                                                    <table class="table table-sm table-borderless">
                                                        <tr>
                                                            <?php foreach ($bankAccounts as $index => $account): ?>
                                                                <td>
                                                                    <strong><?= htmlspecialchars($account['bank_name']) ?></strong><br>
                                                                     <small>
                                                                         <?= htmlspecialchars($account['account_type']) ?>: <?= htmlspecialchars($account['account_number']) ?><br>
                                                                         Moneda: <?= $account['currency'] == 'PEN' ? 'SOLES' : 'DOLARES' ?>
                                                                     </small>
                                                                </td>
                                                                <?php if (($index + 1) % 2 == 0): ?></tr><tr><?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    </table>
                                                <?php else: ?>
                                                    <small class="text-muted">No hay cuentas bancarias registradas.</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Signature Section -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-6 text-center">
                                        <p><strong>Firma del Vendedor:</strong></p>
                                        <?php if (!empty($vendor['signature_url']) && file_exists(__DIR__ . '/../' . $vendor['signature_url'])): ?>
                                            <div style="height: 60px; display: flex; align-items: center; justify-content: center;">
                                                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($vendor['signature_url']) ?>"
                                                     alt="Firma" style="max-width: 150px; max-height: 50px;">
                                            </div>
                                        <?php else: ?>
                                            <p>_______________________________</p>
                                        <?php endif; ?>
                                        <p><?php echo htmlspecialchars($vendor['first_name'] . ' ' . $vendor['last_name']); ?></p>
                                        <p><?php echo htmlspecialchars($vendor['email']); ?></p>
                                    </div>
                                    <div class="col-6 text-center">
                                        <p><strong>APROBADO:</strong></p>
                                        <p>_______________________________</p>
                                        <p>Cliente</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Generation Info Footer -->
                        <div class="row mt-3 no-print">
                            <div class="col-12 text-center">
                                <p class="text-muted">
                                    <small>
                                        Cotización generada el <?= date('d/m/Y H:i', strtotime($quotation['created_at'])) ?>
                                        por <?= htmlspecialchars($quotation['user_first_name'] . ' ' . $quotation['user_last_name']) ?>
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Credit Status Section (no-print) -->
                <?php
                $paymentCondition = $quotation['payment_condition'] ?? 'cash';
                $creditStatus = $quotation['credit_status'] ?? null;
                if ($paymentCondition === 'credit' && $quotation['status'] === 'Accepted'):
                    $creditManager = new CreditManager();
                    $creditInfo = $creditManager->getCreditStatus($quotation['id']);
                ?>
                    <div class="card mt-4 no-print">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-credit-card"></i> Estado de Aprobación de Crédito</h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong>Condición de Pago:</strong>
                                        <span class="badge bg-warning text-dark">Crédito <?= $quotation['credit_days'] ?? 0 ?> días</span>
                                    </p>
                                    <?php if ($creditInfo): ?>
                                        <p class="mb-2">
                                            <strong>Estado:</strong>
                                            <?php
                                            $creditClasses = [
                                                'Pending' => 'bg-warning text-dark',
                                                'Approved' => 'bg-success',
                                                'Rejected' => 'bg-danger'
                                            ];
                                            $creditNames = [
                                                'Pending' => 'Pendiente de Aprobación',
                                                'Approved' => 'Crédito Aprobado',
                                                'Rejected' => 'Crédito Rechazado'
                                            ];
                                            $creditClass = $creditClasses[$creditInfo['status']] ?? 'bg-secondary';
                                            $creditName = $creditNames[$creditInfo['status']] ?? $creditInfo['status'];
                                            ?>
                                            <span class="badge <?= $creditClass ?>"><?= $creditName ?></span>
                                        </p>
                                        <?php if ($creditInfo['status'] === 'Approved' && $creditInfo['credit_user_name']): ?>
                                            <p class="mb-2">
                                                <strong>Aprobado por:</strong> <?= htmlspecialchars($creditInfo['credit_user_name']) ?>
                                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($creditInfo['processed_at'])) ?></small>
                                            </p>
                                        <?php elseif ($creditInfo['status'] === 'Rejected'): ?>
                                            <p class="mb-2">
                                                <strong>Rechazado por:</strong> <?= htmlspecialchars($creditInfo['credit_user_name']) ?>
                                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($creditInfo['processed_at'])) ?></small>
                                            </p>
                                            <?php if (!empty($creditInfo['rejection_reason'])): ?>
                                                <div class="alert alert-danger mt-2 mb-0">
                                                    <strong>Motivo:</strong> <?= nl2br(htmlspecialchars($creditInfo['rejection_reason'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($creditInfo['status'] === 'Pending'): ?>
                                            <p class="mb-0 text-muted">
                                                <i class="fas fa-clock"></i> Solicitado el <?= date('d/m/Y H:i', strtotime($creditInfo['requested_at'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted">
                                            <i class="fas fa-info-circle"></i> El crédito será evaluado cuando solicite facturación.
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <?php if (!$creditInfo && (empty($quotation['billing_status']) || $quotation['billing_status'] === 'Invoice_Rejected')): ?>
                                        <p class="text-info mb-0">
                                            <i class="fas fa-arrow-right"></i> Al solicitar facturación, se enviará primero a Créditos y Cobranzas para aprobación.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Status Actions (no-print) -->
                <?php if ($quotation['status'] !== 'Draft'): ?>
                    <div class="card mt-4 no-print">
                        <div class="card-header">
                            <h5 class="mb-0">Cambiar Estado</h5>
                        </div>
                        <div class="card-body">
                            <div class="btn-group">
                                <?php if ($quotation['status'] !== 'Sent'): ?>
                                    <button class="btn btn-outline-info" onclick="changeStatus('Sent')">
                                        <i class="fas fa-paper-plane"></i> Marcar como Enviada
                                    </button>
                                <?php endif; ?>
                                <?php if ($quotation['status'] === 'Sent'): ?>
                                    <button class="btn btn-outline-success" onclick="changeStatus('Accepted')">
                                        <i class="fas fa-check"></i> Marcar como Aceptada
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="changeStatus('Rejected')">
                                        <i class="fas fa-times"></i> Marcar como Rechazada
                                    </button>
                                <?php endif; ?>
                                <?php if ($quotation['status'] === 'Accepted'): ?>
                                    <?php
                                    // Determine if can request billing
                                    $canRequestBilling = empty($quotation['billing_status']) || $quotation['billing_status'] === 'Invoice_Rejected';
                                    // For credit quotations, also check if credit was rejected (can retry)
                                    if ($paymentCondition === 'credit' && $creditStatus === 'Credit_Rejected') {
                                        $canRequestBilling = true;
                                    }
                                    ?>
                                    <?php if ($canRequestBilling): ?>
                                        <a href="<?= BASE_URL ?>/billing/request.php?id=<?= $quotation['id'] ?>" class="btn btn-success">
                                            <i class="fas fa-file-invoice"></i> Solicitar Facturación
                                        </a>
                                    <?php elseif ($creditStatus === 'Pending_Credit'): ?>
                                        <button class="btn btn-info" disabled>
                                            <i class="fas fa-clock"></i> Pendiente Aprobación de Crédito
                                        </button>
                                    <?php elseif ($quotation['billing_status'] === 'Pending_Invoice'): ?>
                                        <button class="btn btn-warning" disabled>
                                            <i class="fas fa-clock"></i> Pendiente de Facturación
                                        </button>
                                    <?php elseif ($quotation['billing_status'] === 'Invoiced'): ?>
                                        <span class="badge bg-success fs-6 p-2">
                                            <i class="fas fa-check-circle"></i> Facturado: <?= htmlspecialchars($quotation['invoice_number']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Replace ASCII placeholders with real emoji (inserted in JS to avoid PHP/IIS encoding issues)
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

            // Build request body
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
                // Check if needs confirmation (quotation already accepted)
                if (!data.success && data.needs_confirmation) {
                    if (confirm('⚠️ ' + data.message)) {
                        // User confirmed - resend with force flag
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

        function sendByWhatsApp(quotationId) {
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparando...';

            fetch(`<?= BASE_URL ?>/quotations/send_whatsapp.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${quotationId}`
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp"></i> WhatsApp';
                if (!data.success) {
                    alert('Error: ' + (data.message || 'No se pudo preparar WhatsApp'));
                    return;
                }
                const msgText = waInjectEmoji(data.message_text || '');
                const phone   = data.phone || '';
                const waWeb   = `https://wa.me/${phone}?text=${encodeURIComponent(msgText)}`;
                const waDesk  = `whatsapp://send?phone=${phone}&text=${encodeURIComponent(msgText)}`;

                document.getElementById('waModalText').value = msgText;
                document.getElementById('waOpenWeb').onclick  = () => { window.open(waWeb, '_blank'); };
                document.getElementById('waOpenDesk').onclick = () => { window.location.href = waDesk; };
                new bootstrap.Modal(document.getElementById('waModal')).show();

                // Update status silently
                fetch(`<?= BASE_URL ?>/quotations/change_status.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${quotationId}&status=Sent`
                }).catch(() => {});
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp"></i> WhatsApp';
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
    </script>

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
                    <button id="waCopyBtn" type="button" class="btn btn-outline-secondary" onclick="waCopyText()">
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

    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
