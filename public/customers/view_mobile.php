<?php
/**
 * public/customers/view_mobile.php
 * Ficha de Cliente Móvil — Sistema de Cotizaciones (Notion / Linear Design)
 * Con perfil completo, acciones directas de WhatsApp/Llamada, KPIs de cotizaciones y navegación fluida.
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = $auth->getUser();
$companyId = (int)$auth->getCompanyId();

$customerId = (int)($_GET['id'] ?? 0);
$isAdmin = $auth->hasRole(['Admin', 'Administrador', 'Administrador del Sistema', 'Administrador de Empresa']);

$customerRepo = new Customer();
if ($isAdmin) {
    $customer = $customerRepo->getByIdGlobal($customerId);
} else {
    $customer = $customerRepo->getById($customerId, $companyId);
}

if (!$customer) {
    $_SESSION['error'] = 'Cliente no encontrado';
    header('Location: ' . BASE_URL . '/customers/index_mobile.php');
    exit;
}

$customerCompanyId = (int)$customer['company_id'];

// Date Filters
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$isAllPeriod = isset($_GET['all']) && $_GET['all'] == '1';
$hasDateFilters = !empty($dateFrom) || !empty($dateTo);

if (!$hasDateFilters && !$isAllPeriod) {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-t');
}

// Fetch Quotations for this customer
$quotationRepo = new Quotation();
$filters = array_filter([
    'customer_id' => $customerId,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo
]);
$quotationsResult = $quotationRepo->getQuotationsWithFilters($customerCompanyId, $filters, 1, 100);
$quotations = $quotationsResult['quotations'] ?? [];
$totalQuotations = (int)($quotationsResult['total'] ?? count($quotations));

// Calculate Statistics
$acceptedCount = 0;
$pendingCount  = 0;
$totalAmountUSD = 0.0;
$totalAmountPEN = 0.0;

foreach ($quotations as $q) {
    $st = $q['status'] ?? '';
    $curr = strtoupper($q['currency'] ?? 'PEN');
    $val = (float)($q['total'] ?? 0);

    if ($st === 'Accepted' || $st === 'Invoiced') {
        $acceptedCount++;
        if ($curr === 'USD') {
            $totalAmountUSD += $val;
        } else {
            $totalAmountPEN += $val;
        }
    } else {
        $pendingCount++;
    }
}

// Clean Customer Data
$taxId = trim($customer['tax_id'] ?? '');
$taxClean = preg_replace('/\D/', '', $taxId);
$isRuc = (strlen($taxClean) === 11);
$isDni = (strlen($taxClean) === 8);

$phone = trim($customer['phone'] ?? '');
$cleanPhone = preg_replace('/\D/', '', $phone);
$waNumber = $cleanPhone;
if (strlen($waNumber) === 9 && str_starts_with($waNumber, '9')) {
    $waNumber = '51' . $waNumber;
}

$email = trim($customer['email'] ?? '');
$emailCc = trim($customer['email_cc'] ?? '');
$address = trim($customer['address'] ?? '');
$contact = trim($customer['contact_person'] ?? '');

$pageTitle = $customer['name'];

// Query builder helper for date filters
function buildCustomerViewQuery($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> — Detalle de Cliente</title>

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
            document.documentElement.setAttribute('data-coti-theme-mode', mode);
        })();
    </script>

    <!-- Typography: Satoshi, DM Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@700,900&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Font Awesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Core App Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/style.css") ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">

    <?php if (file_exists(dirname(__DIR__) . '/../includes/pwa_head.php')) include_once dirname(__DIR__) . '/../includes/pwa_head.php'; ?>

    <style>
        :root {
            --color-canvas: #09090B;
            --color-panel: #18181B;
            --color-panel-card: #202024;
            --color-rule: #27272A;
            --color-rule-strong: #3F3F46;
            --color-ink: #FAFAFA;
            --color-ink-muted: #A1A1AA;
            --color-amber: #F59E0B;
            --color-amber-dim: rgba(245, 158, 11, 0.12);
        }

        [data-bs-theme="light"] {
            --color-canvas: #F4F4F5;
            --color-panel: #FFFFFF;
            --color-panel-card: #FAFAFA;
            --color-rule: #E4E4E7;
            --color-rule-strong: #D4D4D8;
            --color-ink: #09090B;
            --color-ink-muted: #71717A;
            --color-amber: #D97706;
            --color-amber-dim: rgba(217, 119, 6, 0.1);
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--color-canvas);
            color: var(--color-ink);
            padding-bottom: 96px;
            font-size: 14.5px;
            margin: 0;
            overscroll-behavior-y: contain;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Top Sticky Navbar */
        .mobile-top-navbar {
            background-color: var(--color-panel);
            border-bottom: 1px solid var(--color-rule);
            position: sticky;
            top: 0;
            z-index: 1020;
            padding: 12px 16px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Profile Hero Card */
        .profile-card {
            background-color: var(--color-panel);
            border: 1px solid var(--color-rule);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Document Badges */
        .badge-doc {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.74rem;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
        }
        .badge-ruc {
            background: rgba(245, 158, 11, 0.15);
            color: var(--color-amber);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .badge-dni {
            background: rgba(59, 130, 246, 0.15);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .badge-doc-other {
            background: rgba(161, 161, 170, 0.15);
            color: var(--color-ink-muted);
            border: 1px solid rgba(161, 161, 170, 0.3);
        }

        /* Info Item Row */
        .profile-info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 7px 0;
            border-bottom: 1px solid var(--color-rule);
            font-size: 0.86rem;
        }
        .profile-info-row:last-child {
            border-bottom: none;
        }
        .profile-info-label {
            min-width: 90px;
            color: var(--color-ink-muted);
            font-size: 0.78rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .profile-info-val {
            color: var(--color-ink);
            word-break: break-word;
            flex: 1;
        }

        /* Quick Action Communication Pills */
        .comm-pills-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            margin-bottom: 4px;
        }
        .comm-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.15s ease;
        }
        .whatsapp-pill {
            background-color: rgba(37, 211, 102, 0.15);
            color: #25D366;
            border: 1px solid rgba(37, 211, 102, 0.3);
        }
        .call-pill {
            background-color: rgba(59, 130, 246, 0.12);
            color: #3B82F6;
            border: 1px solid rgba(59, 130, 246, 0.25);
        }
        .email-pill {
            background-color: rgba(168, 85, 247, 0.12);
            color: #A855F7;
            border: 1px solid rgba(168, 85, 247, 0.25);
        }

        /* Quotation Item Card */
        .quotation-card {
            background-color: var(--color-panel);
            border: 1px solid var(--color-rule);
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            transition: border-color 0.12s ease;
        }
        .quotation-card:active {
            border-color: var(--color-amber);
        }

        /* Status Badges */
        .badge-status-draft { background: rgba(161, 161, 170, 0.15); color: #A1A1AA; border: 1px solid rgba(161, 161, 170, 0.3); }
        .badge-status-sent { background: rgba(59, 130, 246, 0.15); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-status-accepted { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-status-rejected { background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-status-invoiced { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; border: 1px solid rgba(139, 92, 246, 0.3); }

        .card-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background-color: var(--color-rule);
            border-top: 1px solid var(--color-rule);
        }
        .card-action-btn {
            background-color: var(--color-panel);
            color: var(--color-ink);
            padding: 9px 4px;
            font-size: 0.78rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            transition: background-color 0.12s ease;
        }
        .card-action-btn:hover, .card-action-btn:active {
            background-color: var(--color-panel-card);
            color: var(--color-amber);
        }
    </style>
</head>
<body>

    <!-- =========================================================
         1. MOBILE TOP NAVIGATION BAR
         ========================================================= -->
    <header class="mobile-top-navbar shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none" title="Volver a Clientes">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div style="min-width: 0;">
                    <h1 class="h6 fw-bold mb-0 text-body text-truncate" style="letter-spacing: -0.01em; max-width: 190px;">
                        <?= htmlspecialchars($customer['name']) ?>
                    </h1>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        <?= $isRuc ? 'RUC ' . $taxId : ($isDni ? 'DNI ' . $taxId : 'Cliente') ?> &bull; Historial
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Theme Toggle -->
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 mobile-theme-toggle"
                        data-theme-toggle title="Cambiar tema (Sistema/Claro/Oscuro)">
                    <i class="fas fa-circle-half-stroke"></i>
                </button>

                <!-- Desktop switch -->
                <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customerId ?>&desktop=1" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Vista Escritorio">
                    <i class="fas fa-desktop"></i>
                </a>

                <!-- Edit Button -->
                <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customerId ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Editar Cliente">
                    <i class="fas fa-pen-to-square"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="px-3 pt-3">

        <!-- =========================================================
             2. CUSTOMER PROFILE HERO CARD
             ========================================================= -->
        <article class="profile-card">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1 text-body" style="font-family: var(--font-display, 'Satoshi', sans-serif);">
                            <?= htmlspecialchars($customer['name']) ?>
                        </h2>
                        <?php if ($customer['company_status']): ?>
                            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 py-0.5 px-2" style="font-size: 0.68rem;">
                                <?= htmlspecialchars($customer['company_status']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($isRuc): ?>
                            <span class="badge-doc badge-ruc"><i class="fas fa-building me-1"></i>RUC <?= htmlspecialchars($taxId) ?></span>
                        <?php elseif ($isDni): ?>
                            <span class="badge-doc badge-dni"><i class="fas fa-id-card me-1"></i>DNI <?= htmlspecialchars($taxId) ?></span>
                        <?php elseif (!empty($taxId)): ?>
                            <span class="badge-doc badge-doc-other"><?= htmlspecialchars($taxId) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info rows -->
                <div class="mt-3">
                    <?php if (!empty($contact)): ?>
                        <div class="profile-info-row">
                            <span class="profile-info-label"><i class="fas fa-user-circle"></i> Contacto</span>
                            <span class="profile-info-val fw-semibold"><?= htmlspecialchars($contact) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($address)): ?>
                        <div class="profile-info-row">
                            <span class="profile-info-label"><i class="fas fa-location-dot text-warning"></i> Dirección</span>
                            <span class="profile-info-val"><?= htmlspecialchars($address) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($email)): ?>
                        <div class="profile-info-row">
                            <span class="profile-info-label"><i class="fas fa-envelope"></i> Email</span>
                            <span class="profile-info-val">
                                <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-info text-decoration-none">
                                    <?= htmlspecialchars($email) ?>
                                </a>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($emailCc)): ?>
                        <div class="profile-info-row">
                            <span class="profile-info-label"><i class="fas fa-at"></i> CC</span>
                            <span class="profile-info-val small text-muted"><?= htmlspecialchars($emailCc) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($customer['owner_name'])): ?>
                        <div class="profile-info-row">
                            <span class="profile-info-label"><i class="fas fa-user-tie"></i> Vendedor</span>
                            <span class="profile-info-val"><?= htmlspecialchars($customer['owner_name']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($isAdmin && !empty($customer['company_name'])): ?>
                        <div class="profile-info-row">
                            <span class="profile-info-label"><i class="fas fa-store"></i> Empresa</span>
                            <span class="profile-info-val"><?= htmlspecialchars($customer['company_name']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="profile-info-row">
                        <span class="profile-info-label"><i class="fas fa-calendar"></i> Alta</span>
                        <span class="profile-info-val font-mono small text-muted"><?= date('d/m/Y', strtotime($customer['created_at'])) ?></span>
                    </div>
                </div>

                <!-- One-click communication pills -->
                <?php if (!empty($phone) || !empty($email)): ?>
                    <div class="comm-pills-row">
                        <?php if (!empty($phone)): ?>
                            <a href="https://wa.me/<?= $waNumber ?>" target="_blank" rel="noopener noreferrer" class="comm-pill whatsapp-pill">
                                <i class="fab fa-whatsapp fs-6"></i> WhatsApp
                            </a>
                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="comm-pill call-pill">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($phone) ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($email)): ?>
                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="comm-pill email-pill">
                                <i class="fas fa-envelope"></i> Enviar Correo
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Customer Primary Actions -->
                <div class="d-flex flex-wrap gap-2 pt-3 mt-2 border-top" style="border-color: var(--color-rule) !important;">
                    <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customerId ?>" class="btn btn-warning text-dark fw-bold btn-sm flex-fill d-inline-flex align-items-center justify-content-center gap-1.5 py-2 shadow-sm">
                        <i class="fas fa-plus-circle"></i>
                        <span>Nueva Cotización</span>
                    </a>
                    <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customerId ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center gap-1.5 py-2 px-3">
                        <i class="fas fa-pen-to-square"></i>
                        <span>Editar</span>
                    </a>
                    <?php if ($isAdmin): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center justify-content-center gap-1.5 py-2 px-3"
                                onclick="confirmDeleteThisCustomer()">
                            <i class="fas fa-trash-can"></i>
                            <span>Eliminar</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <!-- =========================================================
             3. STATS STRIP (4 COMPACT TILES)
             ========================================================= -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-sm-3">
                <div class="p-2.5 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Cotizaciones</span>
                    <span class="font-mono fw-bold fs-5 text-body"><?= number_format($totalQuotations) ?></span>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="p-2.5 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Aceptadas</span>
                    <span class="font-mono fw-bold fs-5 text-success"><?= number_format($acceptedCount) ?></span>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="p-2.5 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Pendientes</span>
                    <span class="font-mono fw-bold fs-5 text-warning"><?= number_format($pendingCount) ?></span>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="p-2.5 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Aceptado</span>
                    <span class="font-mono fw-bold fs-6 text-primary">
                        <?= $totalAmountUSD > 0 ? '$ ' . number_format($totalAmountUSD, 2) : 'S/ ' . number_format($totalAmountPEN, 2) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- =========================================================
             4. DATE FILTER ACCORDION
             ========================================================= -->
        <div class="card border-0 mb-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
            <div class="card-body p-2.5">
                <div class="d-flex justify-content-between align-items-center">
                    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1.5 border-0 px-2 text-body"
                            type="button" data-bs-toggle="collapse" data-bs-target="#dateFilterCollapse">
                        <i class="fas fa-calendar-days text-warning"></i>
                        <span class="fw-semibold small">Filtrar por período</span>
                        <i class="fas fa-chevron-down ms-1 text-muted" style="font-size: 0.7rem;"></i>
                    </button>
                    <div>
                        <?php if ($isAllPeriod): ?>
                            <span class="badge bg-secondary bg-opacity-25 text-body small">Histórico completo</span>
                        <?php elseif (!$hasDateFilters): ?>
                            <span class="badge bg-secondary bg-opacity-25 text-body small"><?= date('M Y') ?></span>
                        <?php else: ?>
                            <a href="<?= buildCustomerViewQuery(['all' => null, 'date_from' => null, 'date_to' => null]) ?>" class="text-warning small text-decoration-none">
                                <i class="fas fa-rotate-left me-1"></i>Mes actual
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="collapse <?= $hasDateFilters ? 'show' : '' ?> pt-2 mt-2 border-top" id="dateFilterCollapse" style="border-color: var(--color-rule) !important;">
                    <form method="GET" action="">
                        <input type="hidden" name="id" value="<?= $customerId ?>">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="text-muted small d-block mb-1" style="font-size: 0.72rem;">Desde</label>
                                <input type="date" class="form-control form-control-sm font-mono" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                                       style="background-color: var(--color-canvas); border-color: var(--color-rule); color: var(--color-ink);">
                            </div>
                            <div class="col-6">
                                <label class="text-muted small d-block mb-1" style="font-size: 0.72rem;">Hasta</label>
                                <input type="date" class="form-control form-control-sm font-mono" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                                       style="background-color: var(--color-canvas); border-color: var(--color-rule); color: var(--color-ink);">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary flex-fill fw-semibold">
                                <i class="fas fa-filter me-1"></i> Aplicar
                            </button>
                            <a href="?id=<?= $customerId ?>&all=1" class="btn btn-sm btn-outline-secondary flex-fill">
                                <i class="fas fa-globe me-1"></i> Ver Todas
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- =========================================================
             5. QUOTATIONS SECTION
             ========================================================= -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 fw-bold mb-0 text-body d-flex align-items-center gap-1.5" style="letter-spacing: -0.01em;">
                <i class="fas fa-file-invoice text-warning"></i>
                <span>Cotizaciones</span>
                <span class="badge rounded-pill bg-secondary bg-opacity-25 ms-1"><?= count($quotations) ?></span>
            </h3>
            <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customerId ?>" class="text-warning small text-decoration-none fw-semibold">
                <i class="fas fa-plus me-1"></i>Nueva
            </a>
        </div>

        <?php if (!empty($quotations)): ?>
            <?php foreach ($quotations as $q): ?>
                <?php
                    $qStatus = $q['status'] ?? 'Draft';
                    $badgeClass = match ($qStatus) {
                        'Draft'    => 'badge-status-draft',
                        'Sent'     => 'badge-status-sent',
                        'Accepted' => 'badge-status-accepted',
                        'Rejected' => 'badge-status-rejected',
                        'Invoiced' => 'badge-status-invoiced',
                        default    => 'badge-status-draft'
                    };
                    $labelStatus = match ($qStatus) {
                        'Draft'    => 'Borrador',
                        'Sent'     => 'Enviada',
                        'Accepted' => 'Aceptada',
                        'Rejected' => 'Rechazada',
                        'Invoiced' => 'Facturada',
                        default    => $qStatus
                    };
                    $currencySymbol = ($q['currency'] === 'USD') ? '$' : 'S/';
                ?>
                <article class="quotation-card">
                    <div class="p-3">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <span class="font-mono fw-bold text-body" style="font-size: 0.95rem;">
                                    <?= htmlspecialchars($q['quotation_number']) ?>
                                </span>
                                <div class="text-muted small font-mono" style="font-size: 0.72rem;">
                                    <i class="fas fa-calendar-day me-1"></i><?= date('d/m/Y', strtotime($q['quotation_date'])) ?>
                                    <?php if (!empty($q['valid_until'])): ?>
                                        &bull; Vence: <?= date('d/m/Y', strtotime($q['valid_until'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span class="badge rounded-pill <?= $badgeClass ?> px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
                                    <?= $labelStatus ?>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-baseline pt-1">
                            <span class="text-muted small" style="font-size: 0.76rem;">Monto Total</span>
                            <span class="font-mono fw-bold fs-5 text-body">
                                <?= $currencySymbol ?> <?= number_format((float)($q['total'] ?? 0), 2) ?>
                            </span>
                        </div>
                    </div>

                    <footer class="card-actions-grid">
                        <a href="<?= BASE_URL ?>/quotations/view_mobile.php?id=<?= $q['id'] ?>" class="card-action-btn">
                            <i class="fas fa-eye text-info"></i>
                            <span>Ver Detalles</span>
                        </a>
                        <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $q['id'] ?>" class="card-action-btn" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-file-pdf text-danger"></i>
                            <span>Ver PDF</span>
                        </a>
                    </footer>
                </article>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="text-center py-5 px-3 rounded-3 mt-2" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                <i class="fas fa-file-circle-question fa-3x text-muted opacity-50 mb-3 d-block"></i>
                <h6 class="fw-bold text-body mb-1">Sin cotizaciones en este período</h6>
                <p class="text-muted small mb-3">
                    <?php if ($hasDateFilters || !$isAllPeriod): ?>
                        No hay cotizaciones para el rango de fechas seleccionado.
                    <?php else: ?>
                        Este cliente aún no registra cotizaciones en el sistema.
                    <?php endif; ?>
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <?php if ($hasDateFilters || !$isAllPeriod): ?>
                        <a href="?id=<?= $customerId ?>&all=1" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-globe me-1"></i> Ver Todas
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customerId ?>" class="btn btn-sm btn-warning text-dark fw-bold">
                        <i class="fas fa-plus me-1"></i> Crear Cotización
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Universal Mobile Bottom Navigation Bar -->
    <?php include __DIR__ . '/../../includes/mobile_bottom_nav.php'; ?>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const customerId = <?= $customerId ?>;

        <?php if ($isAdmin): ?>
        function confirmDeleteThisCustomer() {
            if (!confirm('¿Está seguro de eliminar este cliente?\n\nNota: Solo se puede eliminar si no cuenta con cotizaciones asociadas.')) {
                return;
            }

            const formData = new FormData();
            formData.append('id', customerId);

            fetch(`${BASE_URL}/customers/delete.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Cliente eliminado exitosamente');
                    window.location.href = `${BASE_URL}/customers/index_mobile.php`;
                } else {
                    alert('❌ Error: ' + (data.message || 'No se pudo eliminar el cliente'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('❌ Error de conexión al eliminar el cliente');
            });
        }
        <?php endif; ?>
    </script>
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>"></script>
</body>
</html>