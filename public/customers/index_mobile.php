<?php
/**
 * public/customers/index_mobile.php
 * Cartera de Clientes — Vista Móvil Optimizada (gstack Industrial / Linear Design)
 * Con KPIs en tiempo real, búsqueda debounce, filtrado por RUC/DNI, paginación rápida y acciones WhatsApp directas.
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = $auth->getUser();
$userId = (int)$auth->getUserId();
$companyId = (int)$auth->getCompanyId();

// Role and permissions
$isAdmin = $auth->hasRole(['Admin', 'Administrador', 'Administrador del Sistema', 'Administrador de Empresa']);

// Fetch all customers based on permission
$customerRepo = new Customer();
if ($isAdmin) {
    $allCustomers = $customerRepo->getAll();
} else {
    $allCustomers = $customerRepo->getAllByCompany($companyId);
}

// Calculate Global Metrics for the Header Strip
$totalCount = count($allCustomers);
$rucCount   = 0;
$dniCount   = 0;
$phoneCount = 0;
$mineCount  = 0;

foreach ($allCustomers as $c) {
    $taxIdClean = preg_replace('/\D/', '', $c['tax_id'] ?? '');
    if (strlen($taxIdClean) === 11) {
        $rucCount++;
    } elseif (strlen($taxIdClean) === 8) {
        $dniCount++;
    }
    if (!empty($c['phone'])) {
        $phoneCount++;
    }
    if (!empty($c['user_id']) && (int)$c['user_id'] === $userId) {
        $mineCount++;
    }
}

// Filters & Search
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$filteredCustomers = $allCustomers;

// 1. Filter type
if ($filter === 'ruc') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) {
        return strlen(preg_replace('/\D/', '', $c['tax_id'] ?? '')) === 11;
    });
} elseif ($filter === 'dni') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) {
        return strlen(preg_replace('/\D/', '', $c['tax_id'] ?? '')) === 8;
    });
} elseif ($filter === 'phone') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) {
        return !empty($c['phone']);
    });
} elseif ($filter === 'mine') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) use ($userId) {
        return !empty($c['user_id']) && (int)$c['user_id'] === $userId;
    });
}

// 2. Text Search
if ($search !== '') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) use ($search) {
        return stripos($c['name'] ?? '', $search) !== false ||
               stripos($c['tax_id'] ?? '', $search) !== false ||
               stripos($c['email'] ?? '', $search) !== false ||
               stripos($c['phone'] ?? '', $search) !== false ||
               stripos($c['contact_person'] ?? '', $search) !== false ||
               stripos($c['address'] ?? '', $search) !== false;
    });
}

// Re-index array
$filteredCustomers = array_values($filteredCustomers);
$totalFiltered = count($filteredCustomers);
$totalPages    = max(1, (int)ceil($totalFiltered / $perPage));
if ($page > $totalPages) $page = $totalPages;

// Pagination slice
$offset = ($page - 1) * $perPage;
$pagedCustomers = array_slice($filteredCustomers, $offset, $perPage);

$pageTitle = 'Clientes';

// Query builder helper for filter pills and pagination
function buildCustomerQuery($overrides = []) {
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

        /* Horizontal Filter Pills */
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

        /* Modern Customer Card */
        .customer-card {
            background-color: var(--color-panel);
            border: 1px solid var(--color-rule);
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            transition: transform 0.12s ease, border-color 0.12s ease;
        }
        .customer-card:active {
            transform: scale(0.99);
            border-color: var(--color-amber);
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

        /* Info Line */
        .customer-info-line {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.83rem;
            color: var(--color-ink-muted);
            line-height: 1.4;
            margin-bottom: 6px;
        }
        .customer-info-line i {
            width: 14px;
            margin-top: 2px;
            flex-shrink: 0;
            text-align: center;
        }

        /* Direct Communication Quick Buttons */
        .comm-pills-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .comm-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.15s ease;
        }
        .comm-pill:hover, .comm-pill:active {
            opacity: 0.85;
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

        /* Sub-meta tags */
        .meta-tags-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }
        .badge-subtle {
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 4px;
            background-color: var(--color-canvas);
            border: 1px solid var(--color-rule);
            color: var(--color-ink-muted);
            display: inline-flex;
            align-items: center;
        }

        /* Action Buttons Grid inside Card */
        .card-actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background-color: var(--color-rule);
            border-top: 1px solid var(--color-rule);
        }
        .card-actions-grid.no-delete {
            grid-template-columns: repeat(3, 1fr);
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
            border: none;
            cursor: pointer;
            transition: background-color 0.12s ease;
        }
        .card-action-btn:hover, .card-action-btn:active {
            background-color: var(--color-panel-card);
            color: var(--color-amber);
        }
        .card-action-btn.btn-delete:hover, .card-action-btn.btn-delete:active {
            color: #EF4444;
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
                        <?= htmlspecialchars($user['username'] ?? 'Usuario') ?> &bull; <?= number_format($totalCount) ?> registrados
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Theme Toggle Button -->
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 mobile-theme-toggle"
                        data-theme-toggle title="Cambiar tema (Sistema/Claro/Oscuro)">
                    <i class="fas fa-circle-half-stroke"></i>
                </button>

                <!-- Desktop switch -->
                <a href="<?= BASE_URL ?>/customers/index.php?desktop=1" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Vista Escritorio">
                    <i class="fas fa-desktop"></i>
                </a>

                <!-- New Customer Action Button -->
                <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-sm btn-warning text-dark fw-bold px-2 py-1" title="Registrar Nuevo Cliente">
                    <i class="fas fa-plus me-1"></i>Nuevo
                </a>
            </div>
        </div>
    </header>

    <!-- =========================================================
         2. KPI METRICS STRIP (4 TILE SUMMARY)
         ========================================================= -->
    <div class="px-3 pt-3">
        <div class="row g-2">
            <!-- Total Clientes -->
            <div class="col-6 col-sm-3">
                <a href="<?= buildCustomerQuery(['filter' => 'all', 'page' => 1]) ?>" class="text-decoration-none">
                    <div class="p-2 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid <?= $filter === 'all' ? 'var(--color-amber)' : 'var(--color-rule)' ?>;">
                        <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Total Clientes</span>
                        <span class="font-mono fw-bold fs-5 text-body"><?= number_format($totalCount) ?></span>
                    </div>
                </a>
            </div>

            <!-- Empresas RUC -->
            <div class="col-6 col-sm-3">
                <a href="<?= buildCustomerQuery(['filter' => 'ruc', 'page' => 1]) ?>" class="text-decoration-none">
                    <div class="p-2 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid <?= $filter === 'ruc' ? 'var(--color-amber)' : 'var(--color-rule)' ?>;">
                        <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Empresas RUC</span>
                        <span class="font-mono fw-bold fs-5 text-warning"><?= number_format($rucCount) ?></span>
                    </div>
                </a>
            </div>

            <!-- Personas DNI -->
            <div class="col-6 col-sm-3">
                <a href="<?= buildCustomerQuery(['filter' => 'dni', 'page' => 1]) ?>" class="text-decoration-none">
                    <div class="p-2 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid <?= $filter === 'dni' ? 'var(--color-amber)' : 'var(--color-rule)' ?>;">
                        <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Personas DNI</span>
                        <span class="font-mono fw-bold fs-5 text-info"><?= number_format($dniCount) ?></span>
                    </div>
                </a>
            </div>

            <!-- Con Teléfono -->
            <div class="col-6 col-sm-3">
                <a href="<?= buildCustomerQuery(['filter' => 'phone', 'page' => 1]) ?>" class="text-decoration-none">
                    <div class="p-2 rounded-3 text-center" style="background-color: var(--color-panel); border: 1px solid <?= $filter === 'phone' ? 'var(--color-amber)' : 'var(--color-rule)' ?>;">
                        <span class="text-muted small d-block" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em;">Con Teléfono</span>
                        <span class="font-mono fw-bold fs-5 text-success"><?= number_format($phoneCount) ?></span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. SEARCH BAR
         ========================================================= -->
    <div class="px-3 pt-3">
        <form method="GET" action="" class="position-relative">
            <?php if ($filter !== 'all'): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <?php endif; ?>
            <div class="input-group">
                <span class="input-group-text border-end-0" style="background-color: var(--color-panel); border-color: var(--color-rule); color: var(--color-ink-muted);">
                    <i class="fas fa-search"></i>
                </span>
                <input type="search"
                       name="search"
                       class="form-control border-start-0 border-end-0 ps-0"
                       style="background-color: var(--color-panel); border-color: var(--color-rule); color: var(--color-ink);"
                       placeholder="Buscar por RUC, nombre, contacto, teléfono..."
                       value="<?= htmlspecialchars($search) ?>"
                       autocomplete="off">
                <?php if (!empty($search)): ?>
                    <a href="<?= buildCustomerQuery(['search' => null, 'page' => 1]) ?>"
                       class="input-group-text text-decoration-none border-start-0"
                       style="background-color: var(--color-panel); border-color: var(--color-rule); color: var(--color-ink-muted);"
                       title="Limpiar búsqueda">
                        <i class="fas fa-times-circle"></i>
                    </a>
                <?php else: ?>
                    <button type="submit" class="btn btn-outline-secondary border-start-0" style="background-color: var(--color-panel); border-color: var(--color-rule);">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- =========================================================
         4. HORIZONTAL FILTER PILLS
         ========================================================= -->
    <div class="filter-pills-scroll">
        <!-- Todos -->
        <a href="<?= buildCustomerQuery(['filter' => 'all', 'page' => 1]) ?>"
           class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            Todos <span class="badge rounded-pill bg-secondary bg-opacity-25 ms-1"><?= $totalCount ?></span>
        </a>

        <!-- RUC Empresas -->
        <a href="<?= buildCustomerQuery(['filter' => 'ruc', 'page' => 1]) ?>"
           class="filter-pill <?= $filter === 'ruc' ? 'active' : '' ?>">
            <i class="fas fa-building"></i>
            Empresas RUC <span class="badge rounded-pill bg-warning text-dark ms-1"><?= $rucCount ?></span>
        </a>

        <!-- DNI Personas -->
        <a href="<?= buildCustomerQuery(['filter' => 'dni', 'page' => 1]) ?>"
           class="filter-pill <?= $filter === 'dni' ? 'active' : '' ?>">
            <i class="fas fa-id-card"></i>
            Personas DNI <span class="badge rounded-pill bg-info text-dark ms-1"><?= $dniCount ?></span>
        </a>

        <!-- Con Teléfono -->
        <a href="<?= buildCustomerQuery(['filter' => 'phone', 'page' => 1]) ?>"
           class="filter-pill <?= $filter === 'phone' ? 'active' : '' ?>">
            <i class="fas fa-phone"></i>
            Con Teléfono <span class="badge rounded-pill bg-success text-white ms-1"><?= $phoneCount ?></span>
        </a>

        <?php if ($mineCount > 0): ?>
            <!-- Mis Clientes -->
            <a href="<?= buildCustomerQuery(['filter' => 'mine', 'page' => 1]) ?>"
               class="filter-pill <?= $filter === 'mine' ? 'active' : '' ?>">
                <i class="fas fa-user-check"></i>
                Mis Clientes <span class="badge rounded-pill bg-secondary bg-opacity-25 ms-1"><?= $mineCount ?></span>
            </a>
        <?php endif; ?>
    </div>

    <!-- =========================================================
         5. CUSTOMERS LIST CARDS
         ========================================================= -->
    <main class="px-3 pt-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small" style="font-size: 0.76rem;">
                Mostrando <strong><?= number_format(count($pagedCustomers)) ?></strong> de <strong><?= number_format($totalFiltered) ?></strong> resultados
            </span>
            <?php if (!empty($search) || $filter !== 'all'): ?>
                <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="text-decoration-none small text-warning" style="font-size: 0.76rem;">
                    <i class="fas fa-rotate-left me-1"></i>Restablecer
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($pagedCustomers)): ?>
            <?php foreach ($pagedCustomers as $customer): ?>
                <?php
                    $taxId = trim($customer['tax_id'] ?? '');
                    $taxClean = preg_replace('/\D/', '', $taxId);
                    $isRuc = (strlen($taxClean) === 11);
                    $isDni = (strlen($taxClean) === 8);

                    $phone = trim($customer['phone'] ?? '');
                    $cleanPhone = preg_replace('/\D/', '', $phone);
                    // Standardize Peruvian mobile for WhatsApp link
                    $waNumber = $cleanPhone;
                    if (strlen($waNumber) === 9 && str_starts_with($waNumber, '9')) {
                        $waNumber = '51' . $waNumber;
                    }

                    $email = trim($customer['email'] ?? '');
                    $address = trim($customer['address'] ?? '');
                    $contact = trim($customer['contact_person'] ?? '');
                ?>
                <article class="customer-card">
                    <div class="p-3">
                        <!-- Card Header: Name & Document Badge -->
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h2 class="h6 fw-bold mb-0 text-body" style="font-family: var(--font-display, 'Satoshi', sans-serif); line-height: 1.3;">
                                <?= htmlspecialchars($customer['name']) ?>
                            </h2>
                            <div>
                                <?php if ($isRuc): ?>
                                    <span class="badge-doc badge-ruc" title="RUC <?= htmlspecialchars($taxId) ?>">
                                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($taxId) ?>
                                    </span>
                                <?php elseif ($isDni): ?>
                                    <span class="badge-doc badge-dni" title="DNI <?= htmlspecialchars($taxId) ?>">
                                        <i class="fas fa-id-card me-1"></i><?= htmlspecialchars($taxId) ?>
                                    </span>
                                <?php elseif (!empty($taxId)): ?>
                                    <span class="badge-doc badge-doc-other">
                                        <?= htmlspecialchars($taxId) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Address -->
                        <?php if (!empty($address)): ?>
                            <div class="customer-info-line">
                                <i class="fas fa-location-dot text-warning"></i>
                                <span><?= htmlspecialchars($address) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Contact Person -->
                        <?php if (!empty($contact)): ?>
                            <div class="customer-info-line">
                                <i class="fas fa-user-circle text-muted"></i>
                                <span><?= htmlspecialchars($contact) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Direct Communication Quick Pills -->
                        <?php if (!empty($phone) || !empty($email)): ?>
                            <div class="comm-pills-row">
                                <?php if (!empty($phone)): ?>
                                    <a href="https://wa.me/<?= $waNumber ?>" target="_blank" rel="noopener noreferrer" class="comm-pill whatsapp-pill">
                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                    </a>
                                    <a href="tel:<?= htmlspecialchars($phone) ?>" class="comm-pill call-pill">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($phone) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($email)): ?>
                                    <a href="mailto:<?= htmlspecialchars($email) ?>" class="comm-pill email-pill">
                                        <i class="fas fa-envelope"></i> Email
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Metadata row (Seller, Branch/Company, Date) -->
                        <div class="meta-tags-row">
                            <?php if (!empty($customer['owner_name'])): ?>
                                <span class="badge-subtle">
                                    <i class="fas fa-user-tie me-1 text-muted"></i><?= htmlspecialchars($customer['owner_name']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($isAdmin && !empty($customer['company_name'])): ?>
                                <span class="badge-subtle">
                                    <i class="fas fa-store me-1 text-muted"></i><?= htmlspecialchars($customer['company_name']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($customer['created_at'])): ?>
                                <span class="badge-subtle font-mono">
                                    <i class="fas fa-calendar me-1 text-muted"></i><?= date('d/m/Y', strtotime($customer['created_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 4-Button Action Grid -->
                    <footer class="card-actions-grid <?= $isAdmin ? '' : 'no-delete' ?>">
                        <!-- 1. Ver -->
                        <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customer['id'] ?>" class="card-action-btn" title="Ver Detalles">
                            <i class="fas fa-eye text-info"></i>
                            <span>Ver</span>
                        </a>

                        <!-- 2. Editar -->
                        <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>" class="card-action-btn" title="Editar Cliente">
                            <i class="fas fa-pen-to-square text-primary"></i>
                            <span>Editar</span>
                        </a>

                        <!-- 3. Cotizar -->
                        <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customer['id'] ?>" class="card-action-btn" title="Crear Cotización para este Cliente">
                            <i class="fas fa-file-invoice text-warning"></i>
                            <span class="text-warning fw-bold">Cotizar</span>
                        </a>

                        <!-- 4. Eliminar (Solo Admin) -->
                        <?php if ($isAdmin): ?>
                            <button type="button"
                                    class="card-action-btn btn-delete text-danger"
                                    onclick="confirmDeleteCustomer(<?= (int)$customer['id'] ?>, '<?= addslashes($customer['name']) ?>')"
                                    title="Eliminar Cliente">
                                <i class="fas fa-trash-can"></i>
                                <span>Eliminar</span>
                            </button>
                        <?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>

            <!-- =========================================================
                 PAGINATION
                 ========================================================= -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div>
                        <?php if ($page > 1): ?>
                            <a href="<?= buildCustomerQuery(['page' => $page - 1]) ?>" class="btn btn-sm btn-outline-secondary px-3 py-1">
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
                            <a href="<?= buildCustomerQuery(['page' => $page + 1]) ?>" class="btn btn-sm btn-outline-secondary px-3 py-1">
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
            <!-- Empty State -->
            <div class="text-center py-5 px-3 rounded-3 mt-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule);">
                <i class="fas fa-users-slash fa-3x text-muted opacity-50 mb-3 d-block"></i>
                <h6 class="fw-bold text-body mb-1">No se encontraron clientes</h6>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        No hay registros que coincidan con los filtros aplicados.
                    <?php else: ?>
                        Aún no tienes clientes registrados en el sistema.
                    <?php endif; ?>
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <?php if (!empty($search) || $filter !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-rotate-left me-1"></i> Ver Todos
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-sm btn-warning text-dark fw-bold">
                        <i class="fas fa-plus me-1"></i> Nuevo Cliente
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

        <?php if ($isAdmin): ?>
        function confirmDeleteCustomer(customerId, customerName) {
            if (!confirm(`¿Está seguro de eliminar al cliente "${customerName}"?\n\nNota: Solo se puede eliminar si no cuenta con cotizaciones asociadas.`)) {
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
                    window.location.reload();
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