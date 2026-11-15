<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Verificar si el usuario SOLO tiene rol de Facturación (no debe acceder a clientes)
$userRepo = new User();
$userRoles = $userRepo->getRoles($auth->getUserId());

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

// Redirect to mobile version if on mobile device (unless explicitly disabled)
if (!isset($_GET['desktop'])) {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

    if ($isMobile) {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: ' . BASE_URL . '/customers/index_mobile.php' . ($queryString ? '?' . $queryString : ''));
        exit;
    }
}

// Get customers
$customerRepo = new Customer();
$isAdmin = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']);

if ($isAdmin) {
    $allCustomers = $customerRepo->getAll();
} else {
    $allCustomers = $customerRepo->getAllByCompany($companyId);
}

// Calculate metrics on complete dataset
$totalCount = count($allCustomers);
$rucCount = 0;
$dniCount = 0;
$mineCount = 0;

foreach ($allCustomers as $c) {
    $docDigits = preg_replace('/\D/', '', $c['tax_id'] ?? '');
    if (strlen($docDigits) === 11) {
        $rucCount++;
    } elseif (strlen($docDigits) === 8) {
        $dniCount++;
    }
    if (!empty($c['user_id']) && (int)$c['user_id'] === (int)$userId) {
        $mineCount++;
    }
}

// Filters handling
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$filteredCustomers = $allCustomers;

// Apply quick filter
if ($filter === 'ruc') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) {
        $digits = preg_replace('/\D/', '', $c['tax_id'] ?? '');
        return strlen($digits) === 11;
    });
} elseif ($filter === 'dni') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) {
        $digits = preg_replace('/\D/', '', $c['tax_id'] ?? '');
        return strlen($digits) === 8;
    });
} elseif ($filter === 'mine') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) use ($userId) {
        return !empty($c['user_id']) && (int)$c['user_id'] === (int)$userId;
    });
}

// Apply text search
if ($search !== '') {
    $filteredCustomers = array_filter($filteredCustomers, function($c) use ($search) {
        return stripos($c['name'] ?? '', $search) !== false ||
               stripos($c['tax_id'] ?? '', $search) !== false ||
               stripos($c['email'] ?? '', $search) !== false ||
               stripos($c['phone'] ?? '', $search) !== false ||
               stripos($c['contact_person'] ?? '', $search) !== false ||
               stripos($c['owner_name'] ?? '', $search) !== false;
    });
}

// Re-index array
$filteredCustomers = array_values($filteredCustomers);
$filteredTotal = count($filteredCustomers);

// Pagination handling
$perPageParam = $_GET['per_page'] ?? '15';
$isAll = ($perPageParam === 'all');
$perPage = $isAll ? max(1, $filteredTotal) : (int)$perPageParam;
if ($perPage <= 0) $perPage = 15;

$totalPages = max(1, (int)ceil($filteredTotal / $perPage));
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $perPage;
$customers = array_slice($filteredCustomers, $offset, $perPage);

// Helper for pagination query string
function buildCustomerQuery(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}

$pageTitle = 'Gestión de Clientes';
$activePage = 'customers';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 100%; margin: 0 auto;">

    <!-- =========================================================
         1. BREADCRUMB & MASTER ACTION HEADER
         ========================================================= -->
    <div class="d-flex flex-column gap-2 mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="text-decoration-none text-muted">
                        <i class="fas fa-home me-1"></i>Inicio
                    </a>
                </li>
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Clientes</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">Gestión de Clientes</h1>
                    <span class="badge rounded-pill bg-body-tertiary text-body border px-2 py-1 small fw-semibold">
                        <?= $filteredTotal ?> <?= $filteredTotal === 1 ? 'cliente' : 'clientes' ?>
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.8rem;">
                    Directorio unificado para emisión de cotizaciones y facturación rápida.
                </p>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                    <i class="fas fa-magic me-1"></i> + Nuevo Cliente (API)
                </button>
                <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-outline-secondary btn-sm fw-medium px-3">
                    <i class="fas fa-user-plus me-1"></i> Manual
                </a>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportToCSV()" title="Exportar lista visible a CSV">
                    <i class="fas fa-file-csv me-1"></i> Exportar
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================
         2. KPI SUMMARY METRIC STRIP (Compact & Slim)
         ========================================================= -->
    <div class="row g-2 mb-3">
        <!-- Total Clientes -->
        <div class="col-6 col-md-3">
            <a href="?filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover <?= $filter === 'all' ? 'border-start border-3 border-warning' : '' ?>" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                    <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Total Cartera</div>
                            <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= number_format($totalCount) ?></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59, 130, 246, 0.12); color: #3B82F6;">
                            <i class="fas fa-users" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Empresas (RUC) -->
        <div class="col-6 col-md-3">
            <a href="?filter=ruc<?= $search ? '&search=' . urlencode($search) : '' ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover <?= $filter === 'ruc' ? 'border-start border-3 border-primary' : '' ?>" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                    <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Empresas (RUC)</div>
                            <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= number_format($rucCount) ?></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(245, 158, 11, 0.12); color: #F59E0B;">
                            <i class="fas fa-building" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Personas (DNI) -->
        <div class="col-6 col-md-3">
            <a href="?filter=dni<?= $search ? '&search=' . urlencode($search) : '' ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover <?= $filter === 'dni' ? 'border-start border-3 border-info' : '' ?>" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                    <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Personas (DNI)</div>
                            <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= number_format($dniCount) ?></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(16, 185, 129, 0.12); color: #10B981;">
                            <i class="fas fa-id-card" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Mis Asignados -->
        <div class="col-6 col-md-3">
            <a href="?filter=mine<?= $search ? '&search=' . urlencode($search) : '' ?>" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover <?= $filter === 'mine' ? 'border-start border-3 border-success' : '' ?>" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                    <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Mis Clientes</div>
                            <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= number_format($mineCount) ?></div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(139, 92, 246, 0.12); color: #8B5CF6;">
                            <i class="fas fa-user-check" style="font-size: 0.75rem;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- =========================================================
         3. SEARCH & FILTER TOOLBAR
         ========================================================= -->
    <div class="card mb-3 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
        <div class="card-body p-2 px-3">
            <div class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-md-5">
                    <form method="GET" action="<?= BASE_URL ?>/customers/index.php" id="searchForm" class="m-0">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="per_page" value="<?= htmlspecialchars($perPageParam) ?>">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-tertiary border-end-0 text-muted">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text"
                                   class="form-control border-start-0 ps-0"
                                   id="customerSearchInput"
                                   name="search"
                                   placeholder="Buscar por Razón Social, RUC, DNI, teléfono o email..."
                                   value="<?= htmlspecialchars($search) ?>"
                                   autocomplete="off">
                            <?php if (!empty($search)): ?>
                                <a href="?filter=<?= htmlspecialchars($filter) ?>&per_page=<?= htmlspecialchars($perPageParam) ?>" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary px-3 fw-semibold">
                                Buscar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Filter Pills -->
                <div class="col-12 col-md-7">
                    <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-1">
                        <a href="?filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>&per_page=<?= htmlspecialchars($perPageParam) ?>"
                           class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-2.5 py-0.5" style="font-size: 0.75rem;">
                            Todos <span class="badge <?= $filter === 'all' ? 'bg-light text-primary' : 'bg-body-secondary text-body' ?> ms-1"><?= $totalCount ?></span>
                        </a>
                        <a href="?filter=ruc<?= $search ? '&search=' . urlencode($search) : '' ?>&per_page=<?= htmlspecialchars($perPageParam) ?>"
                           class="btn btn-sm <?= $filter === 'ruc' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?> rounded-pill px-2.5 py-0.5" style="font-size: 0.75rem;">
                            <i class="fas fa-building me-1"></i> RUC <span class="badge <?= $filter === 'ruc' ? 'bg-dark text-warning' : 'bg-body-secondary text-body' ?> ms-1"><?= $rucCount ?></span>
                        </a>
                        <a href="?filter=dni<?= $search ? '&search=' . urlencode($search) : '' ?>&per_page=<?= htmlspecialchars($perPageParam) ?>"
                           class="btn btn-sm <?= $filter === 'dni' ? 'btn-info text-white' : 'btn-outline-secondary' ?> rounded-pill px-2.5 py-0.5" style="font-size: 0.75rem;">
                            <i class="fas fa-id-card me-1"></i> DNI <span class="badge <?= $filter === 'dni' ? 'bg-light text-info' : 'bg-body-secondary text-body' ?> ms-1"><?= $dniCount ?></span>
                        </a>
                        <a href="?filter=mine<?= $search ? '&search=' . urlencode($search) : '' ?>&per_page=<?= htmlspecialchars($perPageParam) ?>"
                           class="btn btn-sm <?= $filter === 'mine' ? 'btn-success' : 'btn-outline-secondary' ?> rounded-pill px-2.5 py-0.5" style="font-size: 0.75rem;">
                            <i class="fas fa-user-check me-1"></i> Mis Clientes <span class="badge <?= $filter === 'mine' ? 'bg-light text-success' : 'bg-body-secondary text-body' ?> ms-1"><?= $mineCount ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         4. COMPACT DATA TABLE (All columns 100% visible, no cut-off)
         ========================================================= -->
    <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px; overflow: hidden;">
        <!-- Card Top Bar with Items-Per-Page Selector -->
        <div class="card-header bg-body-tertiary border-bottom d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-address-book text-warning"></i>
                <span class="fw-semibold text-body small">Directorio de Clientes</span>
                <span class="text-muted small">
                    (Página <?= $page ?> de <?= $totalPages ?>)
                </span>
            </div>

            <!-- Page Size Switcher -->
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Mostrar:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach ([10, 15, 25, 50] as $sz): ?>
                        <a href="<?= buildCustomerQuery(['per_page' => $sz, 'page' => 1]) ?>"
                           class="btn <?= ($perPageParam == $sz) ? 'btn-primary' : 'btn-outline-secondary' ?> px-2 py-0.5" style="font-size: 0.75rem;">
                            <?= $sz ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?= buildCustomerQuery(['per_page' => 'all', 'page' => 1]) ?>"
                       class="btn <?= $isAll ? 'btn-primary' : 'btn-outline-secondary' ?> px-2 py-0.5" style="font-size: 0.75rem;">
                        Todos
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($customers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="customersTable" style="width: 100%; table-layout: auto;">
                        <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                            <tr>
                                <th class="ps-3 py-2 text-muted" style="min-width: 280px;">Cliente y Documento</th>
                                <th class="py-2 text-muted" style="min-width: 220px;">Contacto & Comunicación</th>
                                <th class="py-2 text-muted" style="min-width: 170px;">Vendedor <?= $isAdmin ? '/ Empresa' : '' ?></th>
                                <th class="pe-3 py-2 text-end text-muted" style="width: 170px; min-width: 170px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($customers as $customer): ?>
                                <?php
                                $docRaw = trim($customer['tax_id'] ?? '');
                                $docDigits = preg_replace('/\D/', '', $docRaw);
                                $isRuc = (strlen($docDigits) === 11);
                                $isDni = (strlen($docDigits) === 8);

                                // Avatar initials
                                $words = explode(' ', trim($customer['name'] ?? 'C'));
                                $initials = '';
                                if (count($words) >= 2) {
                                    $initials = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
                                } else {
                                    $initials = mb_substr($customer['name'] ?? 'C', 0, 2);
                                }
                                $initials = strtoupper($initials);

                                // Location snippet
                                $locationParts = array_filter([
                                    $customer['address'] ?? '',
                                    $customer['district'] ?? '',
                                    $customer['province'] ?? '',
                                ]);
                                $locationStr = implode(', ', $locationParts);

                                // Clean phone for WhatsApp
                                $cleanPhone = preg_replace('/\D/', '', $customer['phone'] ?? '');
                                $whatsappUrl = '';
                                if (strlen($cleanPhone) >= 9) {
                                    if (substr($cleanPhone, 0, 2) !== '51' && strlen($cleanPhone) === 9) {
                                        $cleanPhone = '51' . $cleanPhone;
                                    }
                                    $whatsappUrl = 'https://wa.me/' . $cleanPhone;
                                }
                                ?>
                                <tr class="customer-row" data-search-text="<?= htmlspecialchars(strtolower(($customer['name'] ?? '') . ' ' . ($customer['tax_id'] ?? '') . ' ' . ($customer['email'] ?? '') . ' ' . ($customer['phone'] ?? '') . ' ' . ($customer['contact_person'] ?? '') . ' ' . ($customer['owner_name'] ?? ''))) ?>">

                                    <!-- 1. Cliente y Documento (Compact 2-Line) -->
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                                 style="width: 34px; height: 34px; font-size: 0.75rem; background: <?= $isRuc ? 'linear-gradient(135deg, #F59E0B, #D97706)' : ($isDni ? 'linear-gradient(135deg, #3B82F6, #1D4ED8)' : 'linear-gradient(135deg, #6B7280, #4B5563)') ?>;">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                            <div class="min-w-0 flex-grow-1">
                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                    <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customer['id'] ?>" class="text-decoration-none fw-bold text-body text-truncate d-inline-block" style="max-width: 320px;" title="<?= htmlspecialchars($customer['name']) ?>">
                                                        <?= htmlspecialchars($customer['name']) ?>
                                                    </a>
                                                    <?php if (!empty($docRaw)): ?>
                                                        <?php if ($isRuc): ?>
                                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-1.5 py-0.5" style="font-size: 0.7rem;">
                                                                <i class="fas fa-building me-1"></i>RUC: <?= htmlspecialchars($docRaw) ?>
                                                            </span>
                                                        <?php elseif ($isDni): ?>
                                                            <span class="badge bg-info-subtle text-info border border-info-subtle font-monospace px-1.5 py-0.5" style="font-size: 0.7rem;">
                                                                <i class="fas fa-id-card me-1"></i>DNI: <?= htmlspecialchars($docRaw) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-body-secondary text-body border font-monospace px-1.5 py-0.5" style="font-size: 0.7rem;">
                                                                <?= htmlspecialchars($docRaw) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Subtext: Dirección / Ubicación -->
                                                <?php if (!empty($locationStr)): ?>
                                                    <div class="small text-muted text-truncate mt-0.5" style="font-size: 0.75rem; max-width: 360px;" title="<?= htmlspecialchars($locationStr) ?>">
                                                        <i class="fas fa-map-marker-alt me-1 text-muted"></i><?= htmlspecialchars($locationStr) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. Contacto & Canales de Comunicación -->
                                    <td class="py-2.5">
                                        <div class="d-flex flex-column gap-1">
                                            <?php if (!empty($customer['contact_person'])): ?>
                                                <div class="small fw-semibold text-body text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($customer['contact_person']) ?>">
                                                    <i class="fas fa-user-circle text-muted me-1"></i><?= htmlspecialchars($customer['contact_person']) ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                <?php if (!empty($customer['phone'])): ?>
                                                    <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="badge bg-body-tertiary text-body border text-decoration-none px-1.5 py-0.5" style="font-size: 0.7rem;" title="Llamar">
                                                        <i class="fas fa-phone-alt text-success me-1"></i><?= htmlspecialchars($customer['phone']) ?>
                                                    </a>
                                                    <?php if ($whatsappUrl): ?>
                                                        <a href="<?= $whatsappUrl ?>" target="_blank" rel="noopener" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none px-1.5 py-0.5" style="font-size: 0.7rem;" title="WhatsApp">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if (!empty($customer['email'])): ?>
                                                    <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" class="badge bg-body-tertiary text-body border text-decoration-none text-truncate px-1.5 py-0.5" style="font-size: 0.7rem; max-width: 140px;" title="<?= htmlspecialchars($customer['email']) ?>">
                                                        <i class="fas fa-envelope text-primary me-1"></i><?= htmlspecialchars($customer['email']) ?>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (empty($customer['phone']) && empty($customer['email']) && empty($customer['contact_person'])): ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 3. Vendedor, Empresa y Fecha -->
                                    <td class="py-2.5">
                                        <div class="d-flex flex-column gap-0.5">
                                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                                <?php if (!empty($customer['owner_name'])): ?>
                                                    <span class="badge bg-body-tertiary text-body border px-1.5 py-0.5" style="font-size: 0.7rem;">
                                                        <i class="fas fa-user-tie text-warning me-1"></i><?= htmlspecialchars($customer['owner_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small" style="font-size: 0.75rem;">Sin asignar</span>
                                                <?php endif; ?>

                                                <?php if ($isAdmin && !empty($customer['company_name'])): ?>
                                                    <span class="badge bg-secondary-subtle text-body border px-1.5 py-0.5" style="font-size: 0.7rem;" title="Empresa">
                                                        <?= htmlspecialchars($customer['company_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="small text-muted font-monospace mt-0.5" style="font-size: 0.72rem;">
                                                <i class="far fa-calendar-alt me-1 text-muted"></i><?= !empty($customer['created_at']) ? date('d/m/Y', strtotime($customer['created_at'])) : '-' ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 4. Acciones (Compactas y visibles) -->
                                    <td class="pe-3 py-2.5 text-end text-nowrap">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <!-- Botón Cotizar Rápido -->
                                            <a href="<?= BASE_URL ?>/quotations/create.php?customer_id=<?= $customer['id'] ?>"
                                               class="btn btn-sm btn-warning text-dark fw-bold px-2 py-1"
                                               style="font-size: 0.75rem;"
                                               title="Crear Nueva Cotización">
                                                <i class="fas fa-file-invoice-dollar me-1"></i>Cotizar
                                            </a>

                                            <!-- Ver Perfil -->
                                            <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customer['id'] ?>"
                                               class="btn btn-sm btn-outline-secondary px-2 py-1"
                                               style="font-size: 0.75rem;"
                                               title="Ver Perfil Completo">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <!-- Editar -->
                                            <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>"
                                               class="btn btn-sm btn-outline-primary px-2 py-1"
                                               style="font-size: 0.75rem;"
                                               title="Editar Cliente">
                                                <i class="fas fa-pen"></i>
                                            </a>

                                            <!-- Eliminar -->
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger px-2 py-1"
                                                    style="font-size: 0.75rem;"
                                                    onclick="openDeleteCustomerModal(<?= $customer['id'] ?>, '<?= addslashes(htmlspecialchars($customer['name'])) ?>')"
                                                    title="Eliminar Cliente">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- =========================================================
                     5. PAGINATION CONTROLS BAR (Footer)
                     ========================================================= -->
                <div class="card-footer bg-body-tertiary border-top d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2">
                    <div class="small text-muted">
                        Mostrando <strong><?= $offset + 1 ?></strong> a <strong><?= min($offset + $perPage, $filteredTotal) ?></strong> de <strong><?= $filteredTotal ?></strong> clientes
                        <?php if ($search): ?>
                            (filtrados de <?= $totalCount ?>)
                        <?php endif; ?>
                    </div>

                    <?php if ($totalPages > 1 && !$isAll): ?>
                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Anterior -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildCustomerQuery(['page' => $page - 1]) ?>" aria-label="Anterior">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>

                                <!-- Números de página inteligentes -->
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);

                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="' . buildCustomerQuery(['page' => 1]) . '">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    }
                                }

                                for ($p = $startPage; $p <= $endPage; $p++) {
                                    $active = ($p === $page) ? 'active' : '';
                                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . buildCustomerQuery(['page' => $p]) . '">' . $p . '</a></li>';
                                }

                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="' . buildCustomerQuery(['page' => $totalPages]) . '">' . $totalPages . '</a></li>';
                                }
                                ?>

                                <!-- Siguiente -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildCustomerQuery(['page' => $page + 1]) ?>" aria-label="Siguiente">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-5 px-3">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <i class="fas fa-users-slash fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-body mb-1" style="font-size: 1.05rem;">No se encontraron clientes</h5>
                    <?php if (empty($search)): ?>
                        <p class="text-muted small mb-3">Aún no se han registrado clientes en esta categoría o empresa.</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                                <i class="fas fa-magic me-1"></i> Nuevo Cliente (API)
                            </button>
                            <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-user-plus me-1"></i> Registro Manual
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-3">No hay clientes que coincidan con la búsqueda "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                        <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Ver todos los clientes
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: CREAR CLIENTE RÁPIDO CON SUNAT / RENIEC API
     ========================================================= -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-labelledby="newCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <div class="modal-header border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                        <i class="fas fa-magic"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-body mb-0" id="newCustomerModalLabel" style="font-size: 1.05rem;">Nuevo Cliente (Consulta API)</h5>
                        <div class="text-muted small">Consulta automática en SUNAT / RENIEC en tiempo real</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Search bar row -->
                <div class="p-3 rounded mb-3" style="background: var(--color-canvas); border: 1px solid var(--color-rule);">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">Tipo de Documento</label>
                            <select class="form-select form-select-sm" id="modal_document_type">
                                <option value="ruc">RUC (11 dígitos - Empresa)</option>
                                <option value="dni">DNI (8 dígitos - Persona)</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted mb-1">Número de Documento *</label>
                            <input type="text"
                                   class="form-control form-control-sm font-monospace"
                                   id="modal_document_number"
                                   placeholder="Ej: 20601234567 o 72345678"
                                   maxlength="11"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-warning btn-sm w-100 text-dark fw-bold" id="modal_search_btn">
                                <i class="fas fa-search me-1"></i> Consultar API
                            </button>
                        </div>
                    </div>
                    <div class="form-text small text-muted mt-1" id="modal_search_hint">
                        Ingrese 11 dígitos para RUC o 8 dígitos para DNI. Detección automática al escribir.
                    </div>
                </div>

                <!-- Form fields -->
                <form id="quickCustomerForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Razón Social / Nombre Completo *</label>
                            <input type="text" class="form-control form-control-sm" id="modal_customer_name" required placeholder="Se autocompleta con la consulta API">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold text-muted mb-1">Dirección Fiscal / Domicilio</label>
                            <input type="text" class="form-control form-control-sm" id="modal_customer_address" placeholder="Dirección completa, distrito, provincia">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Teléfono / WhatsApp</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                <input type="text" class="form-control" id="modal_customer_phone" placeholder="Ej: 999 888 777">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-muted mb-1">Correo Electrónico</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="modal_customer_email" placeholder="cliente@empresa.com">
                            </div>
                        </div>
                    </div>

                    <div id="modal_alert_container" class="mt-3" style="display: none;"></div>
                </form>
            </div>

            <div class="modal-footer border-top py-2 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary btn-sm px-4 fw-semibold" id="modal_save_customer_btn">
                    <i class="fas fa-save me-1"></i> Guardar Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: CONFIRMACIÓN DE ELIMINACIÓN
     ========================================================= -->
<div class="modal fade" id="deleteCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <div class="modal-body text-center p-4">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.12); color: #EF4444;">
                    <i class="fas fa-trash-alt fa-lg"></i>
                </div>
                <h6 class="fw-bold text-body mb-2">¿Eliminar Cliente?</h6>
                <p class="small text-muted mb-3" id="deleteCustomerModalText">
                    Esta acción no se puede deshacer. Los clientes con cotizaciones asociadas no podrán ser eliminados.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-sm btn-danger px-3 fw-semibold" id="confirmDeleteCustomerBtn">
                        <i class="fas fa-trash me-1"></i> Sí, Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Smooth micro transitions */
.transition-hover {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.transition-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}
.divide-y tr {
    border-bottom: 1px solid var(--color-rule);
}
.customer-row:hover {
    background-color: rgba(245, 158, 11, 0.03) !important;
}
.pagination .page-link {
    color: var(--color-ink, inherit);
    background-color: var(--color-panel, #fff);
    border-color: var(--color-rule, #dee2e6);
}
.pagination .page-item.active .page-link {
    background-color: #F59E0B;
    border-color: #F59E0B;
    color: #000;
    font-weight: 700;
}
</style>

<script>
// =========================================================
// CLIENT-SIDE INSTANT ROW FILTERING (Current Page)
// =========================================================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customerSearchInput');
    const table = document.getElementById('customersTable');

    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr.customer-row');

            rows.forEach(row => {
                const text = row.getAttribute('data-search-text') || '';
                if (query === '' || text.indexOf(query) !== -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Modal Document Type Auto-Detection
    const docInput = document.getElementById('modal_document_number');
    const docType = document.getElementById('modal_document_type');
    const searchBtn = document.getElementById('modal_search_btn');

    if (docInput && docType) {
        docInput.addEventListener('input', function() {
            const val = this.value.trim().replace(/\D/g, '');
            this.value = val;
            if (val.length === 8) {
                docType.value = 'dni';
            } else if (val.length === 11) {
                docType.value = 'ruc';
            }
        });

        docInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchBtn.click();
            }
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            lookupCustomerDocument();
        });
    }

    const saveCustomerBtn = document.getElementById('modal_save_customer_btn');
    if (saveCustomerBtn) {
        saveCustomerBtn.addEventListener('click', function() {
            saveCustomerFromModal();
        });
    }
});

// =========================================================
// DOCUMENT LOOKUP VIA SUNAT / RENIEC API
// =========================================================
function lookupCustomerDocument() {
    const docInput = document.getElementById('modal_document_number');
    const docType = document.getElementById('modal_document_type');
    const searchBtn = document.getElementById('modal_search_btn');

    const documentValue = docInput.value.trim();
    if (documentValue.length !== 8 && documentValue.length !== 11) {
        showModalAlert('warning', 'Ingrese un documento válido: 8 dígitos para DNI o 11 dígitos para RUC.');
        docInput.focus();
        return;
    }

    const originalBtnHtml = searchBtn.innerHTML;
    searchBtn.disabled = true;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Consultando...';
    hideModalAlert();

    fetch('<?= BASE_URL ?>/api/lookup_document.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: 'document=' + encodeURIComponent(documentValue)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data) {
            const d = data.data;
            if (documentValue.length === 11) {
                // RUC
                document.getElementById('modal_customer_name').value = d.razon_social || d.nombre_o_razon_social || '';

                let addressParts = [];
                if (d.direccion) addressParts.push(d.direccion);
                if (d.distrito) addressParts.push(d.distrito);
                if (d.provincia) addressParts.push(d.provincia);
                if (d.departamento) addressParts.push(d.departamento);
                document.getElementById('modal_customer_address').value = addressParts.join(', ');
            } else {
                // DNI
                const fullDniName = d.nombre_completo || [d.nombres, d.apellido_paterno, d.apellido_materno].filter(Boolean).join(' ');
                document.getElementById('modal_customer_name').value = fullDniName;
            }

            showModalAlert('success', 'Datos encontrados en ' + (documentValue.length === 11 ? 'SUNAT' : 'RENIEC') + '. Complete los campos adicionales si lo desea.');
        } else {
            showModalAlert('warning', data.message || 'No se encontraron datos automáticos. Ingrese la información manualmente.');
        }
    })
    .catch(err => {
        console.error(err);
        showModalAlert('danger', 'Error de conexión con el servicio de consulta. Complete los datos manualmente.');
    })
    .finally(() => {
        searchBtn.disabled = false;
        searchBtn.innerHTML = originalBtnHtml;
    });
}

// =========================================================
// SAVE CUSTOMER FROM MODAL
// =========================================================
function saveCustomerFromModal() {
    const docInput = document.getElementById('modal_document_number');
    const nameInput = document.getElementById('modal_customer_name');
    const addressInput = document.getElementById('modal_customer_address');
    const phoneInput = document.getElementById('modal_customer_phone');
    const emailInput = document.getElementById('modal_customer_email');
    const saveBtn = document.getElementById('modal_save_customer_btn');

    const documentValue = docInput.value.trim();
    const nameValue = nameInput.value.trim();

    if (!documentValue || !nameValue) {
        showModalAlert('danger', 'El documento y la razón social/nombre son obligatorios.');
        return;
    }

    const formData = new FormData();
    formData.append('document', documentValue);
    formData.append('business_name', nameValue);
    formData.append('address', addressInput.value.trim());
    formData.append('phone', phoneInput.value.trim());
    formData.append('email', emailInput.value.trim());

    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';

    fetch('<?= BASE_URL ?>/api/customers.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showModalAlert('success', 'Cliente guardado exitosamente.');
            setTimeout(() => {
                // Reload with search for the new customer so user sees it right away
                window.location.href = '<?= BASE_URL ?>/customers/index.php?search=' + encodeURIComponent(documentValue);
            }, 600);
        } else {
            showModalAlert('danger', data.message || 'Error al guardar el cliente');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        showModalAlert('danger', 'Error de comunicación con el servidor');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    });
}

function showModalAlert(type, message) {
    const alertBox = document.getElementById('modal_alert_container');
    if (!alertBox) return;
    alertBox.style.display = 'block';
    alertBox.innerHTML = `
        <div class="alert alert-${type} py-2 px-3 small mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle')}"></i>
            <div>${message}</div>
        </div>
    `;
}

function hideModalAlert() {
    const alertBox = document.getElementById('modal_alert_container');
    if (alertBox) alertBox.style.display = 'none';
}

// =========================================================
// DELETE CUSTOMER MODAL
// =========================================================
let customerIdToDelete = null;

function openDeleteCustomerModal(id, name) {
    customerIdToDelete = id;
    const modalText = document.getElementById('deleteCustomerModalText');
    if (modalText) {
        modalText.innerHTML = `¿Estás seguro de que deseas eliminar al cliente <strong>${name}</strong>?<br><small class="text-muted">Si tiene cotizaciones asociadas, el sistema bloqueará la eliminación.</small>`;
    }
    const modal = new bootstrap.Modal(document.getElementById('deleteCustomerModal'));
    modal.show();
}

document.getElementById('confirmDeleteCustomerBtn').addEventListener('click', function() {
    if (!customerIdToDelete) return;

    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Eliminando...';

    fetch('<?= BASE_URL ?>/customers/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(customerIdToDelete)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('No se pudo eliminar: ' + (data.message || 'Error desconocido'));
            btn.disabled = false;
            btn.innerHTML = originalText;
            bootstrap.Modal.getInstance(document.getElementById('deleteCustomerModal')).hide();
        }
    })
    .catch(err => {
        alert('Error de conexión al eliminar el cliente');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// =========================================================
// EXPORT TO CSV
// =========================================================
function exportToCSV() {
    const rows = document.querySelectorAll('#customersTable tbody tr.customer-row');
    if (!rows.length) {
        alert('No hay clientes para exportar');
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,﻿";
    csvContent += "Cliente / Razon Social,Documento,Telefono,Email
";

    rows.forEach(row => {
        const nameEl = row.querySelector('td:nth-child(1) a');
        const docEl = row.querySelector('td:nth-child(1) span.badge');
        const phoneEl = row.querySelector('td:nth-child(2) a[href^="tel:"]');
        const emailEl = row.querySelector('td:nth-child(2) a[href^="mailto:"]');

        const name = nameEl ? `"${nameEl.textContent.trim().replace(/"/g, '""')}"` : '""';
        const doc = docEl ? `"${docEl.textContent.trim().replace(/[^0-9]/g, '')}"` : '""';
        const phone = phoneEl ? `"${phoneEl.textContent.trim().replace(/"/g, '""')}"` : '""';
        const email = emailEl ? `"${emailEl.textContent.trim().replace(/"/g, '""')}"` : '""';

        csvContent += `${name},${doc},${phone},${email}
`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `directorio_clientes_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
