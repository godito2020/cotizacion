<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Role and permissions check
$canViewAll = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']) || !empty($user['can_view_all_quotations']);

// Filters
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Period defaults: by default show current month unless explicitly filtered or requested all
$hasExplicitFilters = !empty($status) || !empty($search) || !empty($dateFrom) || !empty($dateTo);
$isAllPeriod = isset($_GET['all']) && $_GET['all'] == '1';

if (!$hasExplicitFilters && !$isAllPeriod) {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-t');
}

$filters = array_filter([
    'status' => $status,
    'search' => $search,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

if (!$canViewAll) {
    $filters['user_id'] = $user['id'];
}

// Fetch quotations
$quotationRepo = new Quotation();
$result = $quotationRepo->getQuotationsWithFilters($companyId, $filters, $page, $perPage);
$quotations = $result['quotations'] ?? [];
$totalQuotations = (int)($result['total'] ?? 0);
$totalPages = (int)($result['total_pages'] ?? 1);

// Quick Metrics Calculation for the current filter/view
$totalAmountUSD = 0.0;
$totalAmountPEN = 0.0;
$acceptedCount = 0;
$draftCount = 0;

foreach ($quotations as $q) {
    $curr = strtoupper($q['currency'] ?? 'PEN');
    $val = (float)($q['total'] ?? 0);
    if ($curr === 'USD') {
        $totalAmountUSD += $val;
    } else {
        $totalAmountPEN += $val;
    }

    if (($q['status'] ?? '') === 'Accepted' || ($q['status'] ?? '') === 'Invoiced') {
        $acceptedCount++;
    } elseif (($q['status'] ?? '') === 'Draft') {
        $draftCount++;
    }
}

$pageTitle = $canViewAll ? 'Todas las Cotizaciones' : 'Mis Cotizaciones';

// Helper for filter URLs
function buildMobileQuery($overrides = []) {
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
    <title><?= htmlspecialchars($pageTitle) ?> — Sistema de Cotizaciones</title>

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
    <?php include __DIR__ . '/../../includes/pwa_head.php'; ?>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..700;1,9..40,400..700&family=JetBrains+Mono:wght@400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">

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
            font-size: 15px;
            margin: 0;
            overscroll-behavior-y: contain;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Top Sticky Bar */
        .mobile-top-navbar {
            background-color: var(--color-panel);
            border-bottom: 1px solid var(--color-rule);
            position: sticky;
            top: 0;
            z-index: 1020;
            padding: 12px 16px;
            backdrop-filter: blur(10px);
        }

        /* Horizontal Filter Pills Container */
        .filter-pills-scroll {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            padding: 8px 16px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .filter-pills-scroll::-webkit-scrollbar {
            display: none;
        }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--color-rule);
            background-color: var(--color-panel);
            color: var(--color-ink-muted);
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .filter-pill:hover, .filter-pill:active {
            border-color: var(--color-rule-strong);
            color: var(--color-ink);
        }
        .filter-pill.active {
            background-color: var(--color-amber-dim);
            color: var(--color-amber);
            border-color: var(--color-amber);
        }

        /* Quotation Card */
        .quotation-card {
            background-color: var(--color-panel);
            border: 1px solid var(--color-rule);
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            transition: transform 0.12s ease, border-color 0.12s ease;
        }
        .quotation-card:active {
            transform: scale(0.99);
            border-color: var(--color-amber);
        }

        /* Status Badges */
        .badge-status-draft { background: rgba(161, 161, 170, 0.15); color: #A1A1AA; border: 1px solid rgba(161, 161, 170, 0.3); }
        .badge-status-sent { background: rgba(59, 130, 246, 0.15); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .badge-status-accepted { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-status-rejected { background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-status-invoiced { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; border: 1px solid rgba(139, 92, 246, 0.3); }

        /* Floating Action Button */
        .fab-btn {
            position: fixed;
            bottom: 24px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 28px;
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: #09090B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.4), 0 2px 6px rgba(0, 0, 0, 0.3);
            border: none;
            z-index: 1040;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .fab-btn:active {
            transform: scale(0.92);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
            color: #09090B;
        }

        /* Action Buttons Grid inside Card */
        .card-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background-color: var(--color-rule);
            border-top: 1px solid var(--color-rule);
        }
        .card-action-btn {
            background-color: var(--color-panel);
            color: var(--color-ink);
            padding: 10px 4px;
            font-size: 0.76rem;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
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
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/dashboard_mobile.php" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none" title="Inicio">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h6 fw-bold mb-0 text-body" style="letter-spacing: -0.01em;">
                        <?= htmlspecialchars($pageTitle) ?>
                    </h1>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        <?= htmlspecialchars($user['username'] ?? 'Usuario') ?> &bull; <?= number_format($totalQuotations) ?> registradas
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Notification Bell -->
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>

                <!-- Desktop switch button -->
                <a href="<?= BASE_URL ?>/quotations/index.php?desktop=1" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Versión de escritorio">
                    <i class="fas fa-desktop"></i>
                </a>

                <!-- Nueva Cotización -->
                <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-sm btn-warning text-dark fw-bold py-1 px-2">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- =========================================================
         2. INSTANT SEARCH BAR
         ========================================================= -->
    <div class="px-3 pt-3">
        <form method="GET" action="<?= BASE_URL ?>/quotations/index_mobile.php" class="m-0" id="searchMobileForm">
            <?php if (!empty($status)): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <?php endif; ?>
            <?php if (!empty($dateFrom)): ?>
                <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            <?php endif; ?>
            <?php if (!empty($dateTo)): ?>
                <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            <?php endif; ?>
            <?php if ($isAllPeriod): ?>
                <input type="hidden" name="all" value="1">
            <?php endif; ?>

            <div class="input-group input-group-sm">
                <span class="input-group-text bg-body-tertiary border-end-0">
                    <i class="fas fa-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 py-2" name="search"
                       placeholder="Buscar N° cotización, cliente o RUC/DNI..."
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <?php if (!empty($search)): ?>
                    <a href="<?= buildMobileQuery(['search' => null, 'page' => 1]) ?>" class="btn btn-outline-secondary" title="Limpiar">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary px-3">
                    Buscar
                </button>
            </div>
        </form>
    </div>

    <!-- =========================================================
         3. HORIZONTAL QUICK-FILTER PILLS
         ========================================================= -->
    <div class="filter-pills-scroll mt-2">
        <a href="<?= buildMobileQuery(['status' => null, 'page' => 1]) ?>"
           class="filter-pill <?= empty($status) ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> Todas
        </a>
        <a href="<?= buildMobileQuery(['status' => 'Draft', 'page' => 1]) ?>"
           class="filter-pill <?= $status === 'Draft' ? 'active' : '' ?>">
            <i class="fas fa-pencil-alt"></i> Borrador
        </a>
        <a href="<?= buildMobileQuery(['status' => 'Sent', 'page' => 1]) ?>"
           class="filter-pill <?= $status === 'Sent' ? 'active' : '' ?>">
            <i class="fas fa-paper-plane"></i> Enviadas
        </a>
        <a href="<?= buildMobileQuery(['status' => 'Accepted', 'page' => 1]) ?>"
           class="filter-pill <?= $status === 'Accepted' ? 'active' : '' ?>">
            <i class="fas fa-check-circle"></i> Aceptadas
        </a>
        <a href="<?= buildMobileQuery(['status' => 'Invoiced', 'page' => 1]) ?>"
           class="filter-pill <?= $status === 'Invoiced' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Facturadas
        </a>
        <a href="<?= buildMobileQuery(['status' => 'Rejected', 'page' => 1]) ?>"
           class="filter-pill <?= $status === 'Rejected' ? 'active' : '' ?>">
            <i class="fas fa-times-circle"></i> Rechazadas
        </a>
    </div>

    <!-- Period Context Alert & Advanced Filter Button -->
    <div class="px-3 mt-2 d-flex align-items-center justify-content-between gap-2">
        <div class="text-muted small" style="font-size: 0.75rem;">
            <?php if (!$hasExplicitFilters && !$isAllPeriod): ?>
                <i class="fas fa-calendar-alt text-warning me-1"></i> Período: <strong><?= date('m/Y') ?></strong> (Mes actual)
            <?php elseif ($isAllPeriod): ?>
                <i class="fas fa-globe text-primary me-1"></i> Histórico: <strong>Todas las fechas</strong>
            <?php else: ?>
                <i class="fas fa-filter text-info me-1"></i> Filtro activo
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-1.5">
            <?php if (!$isAllPeriod && empty($dateFrom) && empty($dateTo)): ?>
                <a href="<?= buildMobileQuery(['all' => 1, 'date_from' => null, 'date_to' => null, 'page' => 1]) ?>" class="btn btn-sm btn-link p-0 text-decoration-none small text-warning" style="font-size: 0.75rem;">
                    Ver histórico
                </a>
            <?php elseif ($isAllPeriod || !empty($dateFrom) || !empty($dateTo)): ?>
                <a href="<?= BASE_URL ?>/quotations/index_mobile.php" class="btn btn-sm btn-link p-0 text-decoration-none small text-muted" style="font-size: 0.75rem;">
                    Solo mes actual
                </a>
            <?php endif; ?>

            <button class="btn btn-sm btn-outline-secondary py-0.5 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilterCollapse" style="font-size: 0.72rem;">
                <i class="fas fa-sliders-h me-1"></i> Fechas
            </button>
        </div>
    </div>

    <!-- Advanced Dates Filter (Collapsible) -->
    <div class="collapse px-3 mt-2 <?= (!empty($dateFrom) && !empty($dateTo) && $hasExplicitFilters) ? 'show' : '' ?>" id="advancedFilterCollapse">
        <div class="p-3 rounded-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
            <form method="GET" action="<?= BASE_URL ?>/quotations/index_mobile.php">
                <?php if (!empty($status)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">Desde:</label>
                        <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold text-muted mb-1" style="font-size: 0.72rem;">Hasta:</label>
                        <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= BASE_URL ?>/quotations/index_mobile.php" class="btn btn-sm btn-outline-secondary" style="font-size: 0.75rem;">Restablecer</a>
                    <button type="submit" class="btn btn-sm btn-primary px-3" style="font-size: 0.75rem;">Aplicar Rango</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         4. SLIM KPI SUMMARY (2-COLUMN COMPACT STRIP)
         ========================================================= -->
    <div class="px-3 mt-3">
        <div class="row g-2">
            <!-- Col 1: Total En Moneda -->
            <div class="col-6">
                <div class="p-2.5 rounded-3 h-100" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <div class="text-muted small text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Monto Vista</div>
                    <div class="d-flex flex-column mt-0.5">
                        <?php if ($totalAmountUSD > 0): ?>
                            <span class="font-mono fw-bold text-warning" style="font-size: 0.88rem;">$ <?= number_format($totalAmountUSD, 2) ?></span>
                        <?php endif; ?>
                        <?php if ($totalAmountPEN > 0): ?>
                            <span class="font-mono fw-bold text-body" style="font-size: 0.88rem;">S/ <?= number_format($totalAmountPEN, 2) ?></span>
                        <?php endif; ?>
                        <?php if ($totalAmountUSD == 0 && $totalAmountPEN == 0): ?>
                            <span class="font-mono fw-bold text-muted" style="font-size: 0.88rem;">$ 0.00</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Col 2: Conteo y Conversión -->
            <div class="col-6">
                <div class="p-2.5 rounded-3 h-100" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <div class="text-muted small text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Estatus Coti</div>
                    <div class="d-flex align-items-baseline gap-2 mt-0.5">
                        <span class="font-mono fw-bold text-success fs-6"><?= $acceptedCount ?></span>
                        <span class="small text-muted" style="font-size: 0.7rem;">aceptadas</span>
                    </div>
                    <div class="text-muted small" style="font-size: 0.68rem;">
                        <?= $draftCount ?> en borrador &bull; <?= count($quotations) ?> en página
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         5. QUOTATIONS CARDS LIST
         ========================================================= -->
    <main class="px-3 mt-3">
        <?php if (!empty($quotations)): ?>
            <?php foreach ($quotations as $q): ?>
                <?php
                $statusMap = [
                    'Draft' => ['name' => 'Borrador', 'cls' => 'badge-status-draft', 'icon' => 'fa-pencil-alt'],
                    'Sent' => ['name' => 'Enviada', 'cls' => 'badge-status-sent', 'icon' => 'fa-paper-plane'],
                    'Accepted' => ['name' => 'Aceptada', 'cls' => 'badge-status-accepted', 'icon' => 'fa-check-circle'],
                    'Rejected' => ['name' => 'Rechazada', 'cls' => 'badge-status-rejected', 'icon' => 'fa-times-circle'],
                    'Invoiced' => ['name' => 'Facturada', 'cls' => 'badge-status-invoiced', 'icon' => 'fa-receipt']
                ];
                $stInfo = $statusMap[$q['status'] ?? 'Draft'] ?? ['name' => $q['status'], 'cls' => 'badge-status-draft', 'icon' => 'fa-info-circle'];

                $isExpired = !empty($q['valid_until']) && strtotime($q['valid_until']) < time() && in_array($q['status'], ['Draft', 'Sent']);
                $currSymbol = ($q['currency'] === 'USD') ? '$' : 'S/';

                // WhatsApp message preview text
                $waText = "Hola! Le comparto la cotización #" . $q['quotation_number'] . " por un total de " . $currSymbol . " " . number_format($q['total'], 2) . ". Puede ver el documento aquí: " . BASE_URL . "/quotations/view.php?id=" . $q['id'];
                $waUrl = "https://wa.me/?text=" . rawurlencode($waText);
                ?>
                <article class="quotation-card">
                    <div class="p-3">
                        <!-- Top Row: Quotation Number + Status Badge -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $q['id'] ?>" class="text-decoration-none">
                                    <span class="font-mono fw-bold fs-6 text-warning" style="letter-spacing: -0.01em;">
                                        #<?= htmlspecialchars($q['quotation_number']) ?>
                                    </span>
                                </a>
                            </div>

                            <div class="d-flex align-items-center gap-1.5">
                                <span class="badge rounded-pill <?= $stInfo['cls'] ?> px-2.5 py-1" style="font-size: 0.72rem; font-weight: 600;">
                                    <i class="fas <?= $stInfo['icon'] ?> me-1"></i><?= $stInfo['name'] ?>
                                </span>
                                <?php if (!empty($q['billing_status']) && $q['status'] === 'Accepted'): ?>
                                    <?php if ($q['billing_status'] === 'Pending_Invoice'): ?>
                                        <span class="badge bg-warning text-dark py-1 px-1.5" style="font-size: 0.65rem;" title="Pendiente de Facturación">Pend. Fact</span>
                                    <?php elseif ($q['billing_status'] === 'Invoiced'): ?>
                                        <span class="badge bg-success py-1 px-1.5" style="font-size: 0.65rem;" title="Facturado">Facturado</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Customer Name -->
                        <div class="mb-2">
                            <div class="fw-bold text-body" style="font-size: 0.95rem; line-height: 1.3;">
                                <i class="fas fa-building text-muted me-1.5" style="font-size: 0.8rem;"></i>
                                <?= htmlspecialchars($q['customer_name'] ?? 'Cliente General') ?>
                            </div>
                            <?php if (!empty($q['customer_reference'])): ?>
                                <div class="text-muted small mt-0.5 ps-4" style="font-size: 0.74rem;">
                                    Ref: <strong><?= htmlspecialchars($q['customer_reference']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Details Row: Date, Valid until, Creator, Total -->
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top border-rule mt-2">
                            <div>
                                <div class="text-muted small" style="font-size: 0.72rem;">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?= date('d/m/Y', strtotime($q['quotation_date'])) ?>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-danger-subtle text-danger ms-1" style="font-size: 0.6rem;">VENCIDA</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small mt-0.5" style="font-size: 0.7rem;">
                                    <i class="far fa-user me-1"></i><?= htmlspecialchars($q['creator_name'] ?? 'Vendedor') ?>
                                </div>
                            </div>

                            <div class="text-end">
                                <div class="text-muted small text-uppercase" style="font-size: 0.65rem;">Total</div>
                                <div class="font-mono fw-bold fs-5 text-body" style="line-height: 1.1;">
                                    <span class="text-warning small fs-6"><?= $currSymbol ?></span> <?= number_format($q['total'], 2) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Quick Actions Strip (4 Buttons) -->
                    <div class="card-actions-grid">
                        <!-- 1. Ver Detalle -->
                        <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $q['id'] ?>" class="card-action-btn">
                            <i class="fas fa-eye text-primary"></i>
                            <span>Ver</span>
                        </a>

                        <!-- 2. Editar o Duplicar -->
                        <?php if ($q['status'] === 'Draft'): ?>
                            <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $q['id'] ?>" class="card-action-btn">
                                <i class="fas fa-edit text-warning"></i>
                                <span>Editar</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/quotations/duplicate.php?id=<?= $q['id'] ?>" class="card-action-btn">
                                <i class="fas fa-copy text-info"></i>
                                <span>Duplicar</span>
                            </a>
                        <?php endif; ?>

                        <!-- 3. WhatsApp Compartir -->
                        <a href="<?= $waUrl ?>" target="_blank" class="card-action-btn" title="Compartir por WhatsApp">
                            <i class="fab fa-whatsapp text-success fs-6"></i>
                            <span>WhatsApp</span>
                        </a>

                        <!-- 4. Descargar PDF -->
                        <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $q['id'] ?>" target="_blank" class="card-action-btn">
                            <i class="fas fa-file-pdf text-danger"></i>
                            <span>PDF</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>

            <!-- =========================================================
                 6. MOBILE PAGINATION BAR
                 ========================================================= -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3 mt-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                    <div>
                        <?php if ($page > 1): ?>
                            <a href="<?= buildMobileQuery(['page' => $page - 1]) ?>" class="btn btn-sm btn-outline-secondary px-3 py-1">
                                <i class="fas fa-chevron-left me-1"></i> Anterior
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary px-3 py-1" disabled>
                                <i class="fas fa-chevron-left me-1"></i> Anterior
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="font-mono small text-muted">
                        Pág. <strong><?= $page ?></strong> / <?= $totalPages ?>
                    </div>

                    <div>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= buildMobileQuery(['page' => $page + 1]) ?>" class="btn btn-sm btn-outline-secondary px-3 py-1">
                                Siguiente <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary px-3 py-1" disabled>
                                Siguiente <i class="fas fa-chevron-right ms-1"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5 px-3 rounded-3 mt-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                <i class="fas fa-inbox fa-3x text-muted opacity-50 mb-3 d-block"></i>
                <h6 class="fw-bold text-body mb-1">No se encontraron cotizaciones</h6>
                <p class="text-muted small mb-3">
                    <?php if (!empty($filters)): ?>
                        No hay registros que coincidan con los filtros aplicados.
                    <?php else: ?>
                        Aún no tienes cotizaciones creadas en este período.
                    <?php endif; ?>
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <?php if (!empty($filters) || !$isAllPeriod): ?>
                        <a href="<?= BASE_URL ?>/quotations/index_mobile.php?all=1" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-globe me-1"></i> Ver Todas
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-sm btn-warning text-dark fw-bold">
                        <i class="fas fa-plus me-1"></i> Nueva Cotización
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Universal Mobile Bottom Navigation Bar -->
    <?php include __DIR__ . '/../../includes/mobile_bottom_nav.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>"></script>

    <!-- Floating PWA Install Button -->
    <button id="pwa-install-btn" onclick="installPWA()" class="btn btn-primary position-fixed d-none align-items-center gap-2"
            style="bottom: 90px; right: 20px; z-index: 1030; border-radius: 50px; padding: 8px 16px; box-shadow: 0 4px 14px rgba(0,0,0,0.3); font-size: 0.8rem;">
        <i class="fas fa-download"></i>
        <span>Instalar App</span>
    </button>
</body>
</html>
