<?php
/**
 * Gestión de Productos - Admin
 * Lee productos desde vista_productos (COBOL)
 * Permite gestionar imágenes y fichas técnicas en BD local
 * Integrado con Master App Shell y diseño Notion / Linear
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
    $_SESSION['error_message'] = "No tiene permisos para gestionar productos.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$loggedInUser = $auth->getUser();
$stockRepo = new Stock();

// Parámetros de búsqueda y paginación
$search = trim($_GET['search'] ?? '');
$warehouseFilter = isset($_GET['warehouse']) && $_GET['warehouse'] !== '' ? (string)$_GET['warehouse'] : '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Per page selector (default 15 for faster load & short views)
$allowedPerPage = [15, 25, 50, 100];
$perPageParam = $_GET['per_page'] ?? '15';
$perPage = in_array((int)$perPageParam, $allowedPerPage) ? (int)$perPageParam : 15;

// Obtener TODOS los almacenes configurados (para el filtro)
$allWarehouses = $stockRepo->getWarehouses();
$warehouseNames = [];
$selectedWarehouseName = 'Todos los Almacenes';
foreach ($allWarehouses as $w) {
    $warehouseNames[$w['numero_almacen']] = $w['nombre'];
    if ((string)$w['numero_almacen'] === (string)$warehouseFilter) {
        $selectedWarehouseName = $w['nombre'];
    }
}

// Mes actual para consulta de stock COBOL
$meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',
    4 => 'abril', 5 => 'mayo', 6 => 'junio',
    7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
    10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$mesNumero = (int)date('n');
$mesActual = $meses[$mesNumero];
$mesNombreCapitalizado = ucfirst($mesActual) . ' ' . date('Y');

// Conexión y filtros COBOL
$dbCobol = getCobolConnection();

$whereConditions = ["s.{$mesActual} > 0"];
$params = [];

if (!empty($search)) {
    $words = preg_split('/\s+/', trim($search));
    $words = array_values(array_filter($words, fn($w) => strlen($w) >= 2));

    foreach ($words as $i => $word) {
        $paramCodigo = ":wc{$i}";
        $paramDesc = ":wd{$i}";
        $whereConditions[] = "(LOWER(p.codigo) LIKE LOWER({$paramCodigo}) OR LOWER(p.descripcion) LIKE LOWER({$paramDesc}))";
        $searchTerm = '%' . $word . '%';
        $params[$paramCodigo] = $searchTerm;
        $params[$paramDesc] = $searchTerm;
    }
}

if ($warehouseFilter !== '') {
    $whereConditions[] = "s.almacen = :warehouse";
    $params[':warehouse'] = (int)$warehouseFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Conteo total de productos distintos que coinciden
$countSql = "SELECT COUNT(DISTINCT p.codigo) as total
             FROM vista_productos p
             INNER JOIN vista_almacenes_anual s ON p.codigo = s.codigo
             {$whereClause}";
$stmtCount = $dbCobol->prepare($countSql);
foreach ($params as $param => $value) {
    $stmtCount->bindValue($param, $value);
}
$stmtCount->execute();
$totalProducts = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Paginación
$totalPages = (int)max(1, ceil($totalProducts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Obtener productos de la página actual
$pageSql = "SELECT DISTINCT p.codigo, p.descripcion, p.precio
            FROM vista_productos p
            INNER JOIN vista_almacenes_anual s ON p.codigo = s.codigo
            {$whereClause}
            ORDER BY p.descripcion ASC
            LIMIT :pgLimit OFFSET :pgOffset";
$stmtPage = $dbCobol->prepare($pageSql);
foreach ($params as $param => $value) {
    $stmtPage->bindValue($param, $value);
}
$stmtPage->bindValue(':pgLimit', $perPage, PDO::PARAM_INT);
$stmtPage->bindValue(':pgOffset', $offset, PDO::PARAM_INT);
$stmtPage->execute();
$pageRows = $stmtPage->fetchAll(PDO::FETCH_ASSOC);

$products = [];
foreach ($pageRows as $row) {
    $products[$row['codigo']] = [
        'codigo' => $row['codigo'],
        'descripcion' => $row['descripcion'],
        'precio' => (float)$row['precio'],
        'total_stock' => 0,
        'warehouses' => []
    ];
}

// Stock por almacén solo para los productos de la página actual
if (!empty($products)) {
    $codigos = array_keys($products);
    $ph = str_repeat('?,', count($codigos) - 1) . '?';
    $stockSql = "SELECT codigo, almacen, {$mesActual} as stock_actual
                 FROM vista_almacenes_anual
                 WHERE codigo IN ($ph) AND {$mesActual} > 0";
    $stockParams = $codigos;
    if ($warehouseFilter !== '') {
        $stockSql .= " AND almacen = ?";
        $stockParams[] = (int)$warehouseFilter;
    }
    $stmtStock = $dbCobol->prepare($stockSql);
    $stmtStock->execute($stockParams);
    while ($row = $stmtStock->fetch(PDO::FETCH_ASSOC)) {
        $codigo = $row['codigo'];
        if (!isset($products[$codigo])) continue;
        $stockActual = (float)$row['stock_actual'];
        $numAlmacen = $row['almacen'];
        $nombreAlmacen = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;
        $products[$codigo]['total_stock'] += $stockActual;
        $products[$codigo]['warehouses'][$nombreAlmacen] = $stockActual;
    }
}

// Obtener imágenes y fichas de la BD local
$dbLocal = getDBConnection();
$productCodes = array_keys($products);
$productImages = [];
$productFichas = [];

if (!empty($productCodes)) {
    $placeholders = str_repeat('?,', count($productCodes) - 1) . '?';

    // Imágenes principales
    try {
        $stmtImg = $dbLocal->prepare("SELECT codigo_producto, imagen_url FROM imagenes WHERE codigo_producto IN ($placeholders) AND imagen_principal = 1");
        $stmtImg->execute($productCodes);
        while ($row = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
            $productImages[$row['codigo_producto']] = $row['imagen_url'];
        }
    } catch (Exception $e) {
        // Fallback silencioso si no está disponible
    }

    // Fichas técnicas existentes
    try {
        $stmtFicha = $dbLocal->prepare("SELECT DISTINCT codigo_producto FROM fichas_tecnicas WHERE codigo_producto IN ($placeholders)");
        $stmtFicha->execute($productCodes);
        while ($row = $stmtFicha->fetch(PDO::FETCH_ASSOC)) {
            $productFichas[$row['codigo_producto']] = true;
        }
    } catch (Exception $e) {
        // Fallback silencioso
    }
}

// Helper para construir URLs preservando filtros
function buildProductQuery($overrides = []) {
    global $search, $warehouseFilter, $perPageParam, $page;
    $params = [
        'search' => $search,
        'warehouse' => $warehouseFilter,
        'per_page' => $perPageParam,
        'page' => $page
    ];
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return '?' . http_build_query($params);
}

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

$pageTitle = 'Catálogo de Productos';
$activePage = 'products';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<style>
.product-thumb-container {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    background-color: var(--color-canvas, #27272A);
    border: 1px solid var(--color-rule, #3F3F46);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}
.product-thumb-container:hover {
    transform: scale(1.08);
}
.product-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.stock-warehouse-chip {
    font-size: 0.7rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background-color: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
    border: 1px solid rgba(245, 158, 11, 0.25);
    white-space: nowrap;
}
</style>

<div class="content-body" style="padding: 1.25rem 1.5rem; max-width: 1400px; margin: 0 auto;">

    <!-- =========================================================
         1. HERO HEADER BAR
         ========================================================= -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 600; font-size: 0.75rem;">
                    <i class="fas fa-boxes me-1"></i> Catálogo COBOL
                </span>
                <span class="text-muted small">Mes: <strong><?= htmlspecialchars($mesNombreCapitalizado) ?></strong></span>
            </div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--color-ink, #09090B); letter-spacing: -0.02em;">
                Gestión de Productos & Stock
            </h1>
            <p class="text-muted small mb-0">
                Inventario consolidado con stock mensual, gestión de imágenes comerciales y fichas técnicas.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-warning text-dark fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                    data-bs-toggle="modal" data-bs-target="#importModal" style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-file-excel"></i>
                <span>Importar Excel</span>
            </button>
            <a href="<?= BASE_URL ?>/admin/warehouses.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-warehouse"></i>
                <span>Almacenes</span>
            </a>
            <a href="<?= BASE_URL ?>/cost-analysis/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-chart-pie"></i>
                <span>Costos & Margen</span>
            </a>
            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                    onclick="exportProductsToCSV()" title="Descargar reporte CSV de la vista actual" style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-file-csv text-success"></i>
                <span class="d-none d-sm-inline">Exportar</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4 shadow-sm" role="alert" style="border-radius: 8px;">
            <i class="fas fa-check-circle fs-5"></i>
            <div><?= htmlspecialchars($message) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4 shadow-sm" role="alert" style="border-radius: 8px;">
            <i class="fas fa-exclamation-circle fs-5"></i>
            <div><?= htmlspecialchars($error_message) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         2. KPI METRICS STRIP (4 SLIM CARDS)
         ========================================================= -->
    <div class="row g-3 mb-4">
        <!-- KPI 1: Productos con Stock -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Productos con Stock</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                            <i class="fas fa-cubes" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" style="color: var(--color-ink, #09090B);"><?= number_format($totalProducts) ?></span>
                        <span class="badge bg-success-subtle text-success small" style="font-size: 0.65rem;">En Catálogo</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Coincidentes con los filtros activos</div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Período de Stock Activo -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Mes COBOL Activo</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                            <i class="fas fa-calendar-alt" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-5 fw-bold" style="color: var(--color-ink, #09090B);"><?= htmlspecialchars(ucfirst($mesActual)) ?></span>
                        <span class="badge bg-primary-subtle text-primary small" style="font-size: 0.65rem;"><?= date('Y') ?></span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Columna: <code>vista_almacenes_anual.<?= $mesActual ?></code></div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Almacenes Monitoreados -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Almacenes en Red</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.15); color: #10B981;">
                            <i class="fas fa-warehouse" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" style="color: var(--color-ink, #09090B);"><?= count($allWarehouses) ?></span>
                        <span class="text-muted small" style="font-size: 0.75rem;">sedes</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Distribución multialmacén</div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Filtro de Sede Actual -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Filtro de Sede</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(139, 92, 246, 0.15); color: #8B5CF6;">
                            <i class="fas fa-filter" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-6 fw-bold text-truncate" style="color: var(--color-ink, #09090B);" title="<?= htmlspecialchars($selectedWarehouseName) ?>">
                            <?= htmlspecialchars($selectedWarehouseName) ?>
                        </span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">
                        <?= $warehouseFilter !== '' ? ('Almacén #' . htmlspecialchars($warehouseFilter)) : 'Consolidado general' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. SEARCH & FILTERS BAR
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
        <div class="card-body p-3">
            <form method="GET" action="<?= BASE_URL ?>/admin/products.php" id="searchProductsForm" class="row g-2 align-items-center justify-content-between">
                <input type="hidden" name="per_page" value="<?= htmlspecialchars((string)$perPage) ?>">

                <!-- Buscador de texto -->
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" id="productSearchInput"
                               placeholder="Buscar por código, descripción o medida..."
                               value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        <?php if (!empty($search)): ?>
                            <a href="<?= buildProductQuery(['search' => null, 'page' => 1]) ?>" class="btn btn-outline-secondary" title="Limpiar búsqueda">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-3">
                            Buscar
                        </button>
                    </div>
                </div>

                <!-- Filtro por Almacén -->
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary"><i class="fas fa-warehouse text-muted"></i></span>
                        <select class="form-select" name="warehouse" onchange="this.form.submit()">
                            <option value="">-- Todos los almacenes --</option>
                            <?php foreach ($allWarehouses as $w): ?>
                                <option value="<?= htmlspecialchars((string)$w['numero_almacen']) ?>" <?= (string)$warehouseFilter === (string)$w['numero_almacen'] ? 'selected' : '' ?>>
                                    #<?= $w['numero_almacen'] ?> - <?= htmlspecialchars($w['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Botón Limpiar filtros si hay alguno -->
                <div class="col-12 col-md-3 d-flex justify-content-md-end align-items-center gap-2">
                    <?php if (!empty($search) || !empty($warehouseFilter)): ?>
                        <a href="<?= BASE_URL ?>/admin/products.php?per_page=<?= $perPage ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Limpiar Filtros
                        </a>
                    <?php endif; ?>
                    <span class="text-muted small">
                        Página <strong><?= $page ?></strong> de <strong><?= $totalPages ?></strong>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         4. COMPACT DATA TABLE (All columns 100% visible, no cut-off)
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px; overflow: hidden;">
        <!-- Card Top Bar with Items-Per-Page Selector -->
        <div class="card-header bg-body-tertiary border-bottom d-flex flex-wrap justify-content-between align-items-center py-2 px-3 gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-boxes text-warning"></i>
                <span class="fw-semibold text-body small">Listado de Productos</span>
                <span class="text-muted small">
                    (Mostrando <?= count($products) ?> de <?= number_format($totalProducts) ?> productos)
                </span>
            </div>

            <!-- Page Size Switcher -->
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Mostrar:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach ($allowedPerPage as $sz): ?>
                        <a href="<?= buildProductQuery(['per_page' => $sz, 'page' => 1]) ?>"
                           class="btn <?= ($perPage == $sz) ? 'btn-primary' : 'btn-outline-secondary' ?> px-2 py-0.5" style="font-size: 0.75rem;">
                            <?= $sz ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($products)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="productsTable" style="width: 100%;">
                        <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                            <tr>
                                <th class="ps-3 py-2 text-muted" style="width: 170px;">Producto / Código</th>
                                <th class="py-2 text-muted" style="min-width: 280px;">Descripción & Ficha Técnica</th>
                                <th class="py-2 text-end text-muted" style="width: 110px;">Precio Base</th>
                                <th class="py-2 text-muted" style="min-width: 240px;">Stock Total & Almacenes</th>
                                <th class="pe-3 py-2 text-end text-muted" style="width: 150px; min-width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <?php
                                $codigo = $p['codigo'];
                                $imgUrl = $productImages[$codigo] ?? null;
                                $hasFicha = !empty($productFichas[$codigo]);
                                $codigoJson = htmlspecialchars(json_encode($codigo), ENT_QUOTES, 'UTF-8');
                                $descJson = htmlspecialchars(json_encode($p['descripcion']), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <!-- Imagen & Código -->
                                    <td class="ps-3 py-2">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="product-thumb-container" onclick="openImageModal(<?= $codigoJson ?>, <?= $descJson ?>)" role="button" title="Clic para gestionar imágenes">
                                                <?php if ($imgUrl): ?>
                                                    <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Producto" class="product-thumb-img" loading="lazy">
                                                <?php else: ?>
                                                    <i class="fas fa-image text-muted opacity-50" style="font-size: 1.1rem;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="font-monospace fw-bold text-body small" style="font-size: 0.8rem;"><?= htmlspecialchars($codigo) ?></span>
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted" onclick="navigator.clipboard.writeText('<?= htmlspecialchars(addslashes($codigo)) ?>')" title="Copiar código">
                                                        <i class="fas fa-copy" style="font-size: 0.68rem;"></i>
                                                    </button>
                                                </div>
                                                <span class="text-muted small" style="font-size: 0.68rem;">ID Catálogo</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Descripción & Ficha Técnica -->
                                    <td class="py-2">
                                        <div class="fw-semibold text-body" style="font-size: 0.88rem; line-height: 1.35;">
                                            <?= htmlspecialchars($p['descripcion']) ?>
                                        </div>
                                        <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                                            <?php if ($hasFicha): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1 py-0.5 px-1.5" style="font-size: 0.68rem;">
                                                    <i class="fas fa-file-pdf"></i> Ficha Técnica
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($imgUrl): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle d-inline-flex align-items-center gap-1 py-0.5 px-1.5" style="font-size: 0.68rem;">
                                                    <i class="fas fa-check"></i> Con Imagen
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-muted py-0.5 px-1.5" style="font-size: 0.68rem;">
                                                    Sin imagen
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Precio Base -->
                                    <td class="py-2 text-end">
                                        <div class="font-monospace fw-bold" style="font-size: 0.9rem; color: var(--color-ink, #09090B);">
                                            <?= number_format($p['precio'], 2) ?>
                                        </div>
                                        <span class="text-muted small" style="font-size: 0.68rem;">USD / Soles</span>
                                    </td>

                                    <!-- Stock Total & Almacenes -->
                                    <td class="py-2">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge font-monospace px-2 py-1 <?= $p['total_stock'] > 0 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger' ?>" style="font-size: 0.8rem; font-weight: 700;">
                                                <i class="fas fa-cubes me-1"></i><?= number_format($p['total_stock'], 0) ?> unid.
                                            </span>
                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small text-warning" onclick="viewStock(<?= $codigoJson ?>)">
                                                <i class="fas fa-eye me-0.5"></i> Ver desglose
                                            </button>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if (!empty($p['warehouses'])): ?>
                                                <?php foreach ($p['warehouses'] as $almNombre => $cant): ?>
                                                    <span class="stock-warehouse-chip" title="<?= htmlspecialchars($almNombre) ?>: <?= number_format($cant, 0) ?> unidades">
                                                        <?= htmlspecialchars($almNombre) ?>: <strong><?= number_format($cant, 0) ?></strong>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted small" style="font-size: 0.7rem;">Sin existencias en este filtro</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="pe-3 py-2 text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- Imágenes y Ficha Técnica -->
                                            <button type="button" class="btn btn-outline-secondary py-1 px-2"
                                                    onclick="openImageModal(<?= $codigoJson ?>, <?= $descJson ?>)"
                                                    title="Gestionar imágenes y ficha técnica">
                                                <i class="fas fa-images text-warning"></i>
                                            </button>

                                            <!-- Ver Stock por Almacén -->
                                            <button type="button" class="btn btn-outline-secondary py-1 px-2"
                                                    onclick="viewStock(<?= $codigoJson ?>)"
                                                    title="Ver stock detallado por almacén">
                                                <i class="fas fa-warehouse text-info"></i>
                                            </button>

                                            <!-- Cotizar Producto -->
                                            <a href="<?= BASE_URL ?>/quotations/create.php?add_product=<?= urlencode($codigo) ?>"
                                               class="btn btn-outline-secondary py-1 px-2"
                                               title="Iniciar cotización con este producto">
                                                <i class="fas fa-file-invoice text-success"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- =========================================================
                     PAGINATION BAR
                     ========================================================= -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-body-tertiary border-top py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="text-muted small">
                            Página <strong><?= $page ?></strong> de <strong><?= $totalPages ?></strong> (<?= number_format($totalProducts) ?> productos en total)
                        </div>

                        <nav aria-label="Navegación de páginas">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Primera página -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildProductQuery(['page' => 1]) ?>" title="Primera página">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <!-- Anterior -->
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildProductQuery(['page' => max(1, $page - 1)]) ?>" title="Anterior">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                $range = 2;
                                $startPage = max(1, $page - $range);
                                $endPage = min($totalPages, $page + $range);
                                if ($startPage > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= buildProductQuery(['page' => $i]) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php
                                endfor;
                                if ($endPage < $totalPages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>

                                <!-- Siguiente -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildProductQuery(['page' => min($totalPages, $page + 1)]) ?>" title="Siguiente">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <!-- Última página -->
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="<?= buildProductQuery(['page' => $totalPages]) ?>" title="Última página">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-box-open fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                    <div class="fw-semibold fs-6">No se encontraron productos con stock</div>
                    <div class="small text-muted mt-1">
                        Intente ajustar los términos de búsqueda o cambiar el filtro de almacén.
                    </div>
                    <?php if (!empty($search) || !empty($warehouseFilter)): ?>
                        <div class="mt-3">
                            <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-sm btn-outline-warning text-dark fw-bold">
                                <i class="fas fa-undo me-1"></i> Ver Todos los Productos
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- =========================================================
     5. MODAL GESTIÓN DE IMÁGENES & FICHA TÉCNICA
     ========================================================= -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h5 class="modal-title fs-6 fw-bold text-body" id="imageModalTitle">
                    <i class="fas fa-photo-video text-warning me-2"></i>
                    Recursos del Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Info del Producto -->
                <div class="p-2.5 rounded-3 mb-3" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark font-monospace fw-bold" id="modalProductCode"></span>
                        <span class="text-muted small">ID Producto</span>
                    </div>
                    <div class="fw-semibold text-body" id="modalProductDesc"></div>
                </div>

                <!-- Tabs principales: Imágenes / Ficha Técnica -->
                <ul class="nav nav-tabs mb-3" id="mainModalTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabImages">
                            <i class="fas fa-images me-1 text-primary"></i> Imágenes Comerciales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabFicha">
                            <i class="fas fa-file-alt me-1 text-danger"></i> Ficha Técnica
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- ===== TAB IMÁGENES ===== -->
                    <div class="tab-pane fade show active" id="tabImages">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-2">Imágenes Actuales Registradas:</label>
                            <div id="currentImages" class="p-2 rounded-3" style="background-color: var(--color-canvas); min-height: 90px;"></div>
                        </div>

                        <hr class="my-3">

                        <h6 class="fs-6 fw-bold mb-2"><i class="fas fa-upload me-1 text-warning"></i> Agregar Nueva Imagen</h6>
                        <ul class="nav nav-pills nav-fill mb-3" id="uploadTabs">
                            <li class="nav-item">
                                <a class="nav-link active py-1" data-bs-toggle="pill" href="#tabFile">Subir Archivo Local</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-1" data-bs-toggle="pill" href="#tabUrl">Guardar desde URL</a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Subir Archivo -->
                            <div class="tab-pane fade show active" id="tabFile">
                                <form id="uploadImageForm" enctype="multipart/form-data">
                                    <input type="hidden" name="codigo" id="imageProductCode">
                                    <div class="mb-3">
                                        <input type="file" class="form-control form-control-sm" name="imagen" id="imagenFile" accept="image/*" required>
                                        <small class="text-muted">Formatos recomendados: JPG, PNG, WEBP. Máximo 5MB.</small>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="principal" id="imagenPrincipal">
                                        <label class="form-check-label small" for="imagenPrincipal">Establecer como imagen principal del producto</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-upload me-1"></i> Subir Imagen
                                    </button>
                                </form>
                            </div>

                            <!-- Desde URL -->
                            <div class="tab-pane fade" id="tabUrl">
                                <form id="uploadUrlForm">
                                    <input type="hidden" name="codigo" id="imageProductCodeUrl">
                                    <div class="mb-3">
                                        <input type="url" class="form-control form-control-sm" name="imagen_url" id="imagenUrl" placeholder="https://ejemplo.com/imagen.jpg" required>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="principal" id="imagenPrincipalUrl">
                                        <label class="form-check-label small" for="imagenPrincipalUrl">Establecer como imagen principal</label>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-link me-1"></i> Guardar URL
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- ===== TAB FICHA TÉCNICA ===== -->
                    <div class="tab-pane fade" id="tabFicha">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted mb-2">Fichas Técnicas Registradas:</label>
                            <div id="currentFichas" class="p-2 rounded-3" style="background-color: var(--color-canvas); min-height: 80px;"></div>
                        </div>

                        <hr class="my-3">

                        <h6 class="fs-6 fw-bold mb-2"><i class="fas fa-file-upload me-1 text-danger"></i> Subir Ficha Técnica</h6>
                        <form id="uploadFichaForm" enctype="multipart/form-data">
                            <input type="hidden" name="codigo" id="fichaProductCode">
                            <div class="mb-3">
                                <input type="file" class="form-control form-control-sm" name="ficha" id="fichaFile" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                                <small class="text-muted">Formatos admitidos: PDF, JPG, PNG. Máximo 10MB.</small>
                            </div>
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-upload me-1"></i> Subir Ficha Técnica
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-3 bg-body-tertiary">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     6. MODAL DESGLOSE DE STOCK POR ALMACÉN
     ========================================================= -->
<div class="modal fade" id="stockModal" tabindex="-1" aria-labelledby="stockModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h5 class="modal-title fs-6 fw-bold text-body" id="stockModalTitle">
                    <i class="fas fa-warehouse text-warning me-2"></i>
                    Stock Detallado por Almacén
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div id="stockContent"></div>
            </div>
            <div class="modal-footer border-top py-2 px-3 bg-body-tertiary">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     7. MODAL IMPORTAR EXCEL (IMÁGENES Y FICHAS)
     ========================================================= -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h5 class="modal-title fs-6 fw-bold text-body" id="importModalTitle">
                    <i class="fas fa-file-excel text-success me-2"></i>
                    Importación Masiva desde Excel / CSV
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <ul class="nav nav-tabs mb-3" id="importTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#importImages">
                            <i class="fas fa-images me-1 text-primary"></i> Importar Imágenes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#importFichas">
                            <i class="fas fa-file-alt me-1 text-danger"></i> Importar Fichas Técnicas
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Importar Imágenes -->
                    <div class="tab-pane fade show active" id="importImages">
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <strong><i class="fas fa-info-circle me-1"></i> Formato de Archivo Requerido:</strong><br>
                            El archivo debe tener 2 columnas: <code>codigo</code> | <code>imagen_url</code>
                        </div>

                        <form id="importExcelForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">Archivo Excel (.xlsx, .xls) o CSV *</label>
                                <input type="file" class="form-control form-control-sm" name="excel_file" accept=".xlsx,.xls,.csv" required>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="set_principal" id="setPrincipal" checked>
                                <label class="form-check-label small" for="setPrincipal">Establecer URLs como imagen principal del producto</label>
                            </div>

                            <div id="importProgress" class="d-none mb-3">
                                <div class="progress" style="height: 22px;">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary font-monospace fw-bold" style="width: 0%">0%</div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small id="progressText" class="text-muted">Iniciando...</small>
                                    <small id="progressStats" class="text-muted"></small>
                                </div>
                            </div>

                            <div id="importResult" class="d-none mb-3"></div>

                            <div class="d-flex align-items-center gap-2">
                                <button type="submit" class="btn btn-sm btn-success px-3" id="btnImport">
                                    <i class="fas fa-upload me-1"></i> Iniciar Importación
                                </button>
                                <a href="<?= BASE_URL ?>/api/download_image_template.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-download me-1"></i> Descargar Plantilla
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Tab Importar Fichas Técnicas -->
                    <div class="tab-pane fade" id="importFichas">
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <strong><i class="fas fa-info-circle me-1"></i> Formato de Archivo Requerido:</strong><br>
                            El archivo debe tener 2 columnas: <code>codigo</code> | <code>ficha_url</code>
                        </div>

                        <form id="importFichasForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-muted mb-1">Archivo Excel (.xlsx, .xls) o CSV *</label>
                                <input type="file" class="form-control form-control-sm" name="excel_file" accept=".xlsx,.xls,.csv" required>
                            </div>

                            <div id="importFichasProgress" class="d-none mb-3">
                                <div class="progress" style="height: 22px;">
                                    <div id="progressBarFichas" class="progress-bar progress-bar-striped progress-bar-animated bg-success font-monospace fw-bold" style="width: 0%">0%</div>
                                </div>
                                <div class="mt-2"><small id="progressTextFichas" class="text-muted">Iniciando...</small></div>
                            </div>

                            <div id="importFichasResult" class="d-none mb-3"></div>

                            <button type="submit" class="btn btn-sm btn-success px-3" id="btnImportFichas">
                                <i class="fas fa-upload me-1"></i> Iniciar Importación Fichas
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-3 bg-body-tertiary">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
let imageModalInstance = null;
let stockModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    imageModalInstance = new bootstrap.Modal(document.getElementById('imageModal'));
    stockModalInstance = new bootstrap.Modal(document.getElementById('stockModal'));
});

function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function openImageModal(codigo, descripcion) {
    document.getElementById('modalProductCode').textContent = codigo;
    document.getElementById('modalProductDesc').textContent = descripcion;
    document.getElementById('imageProductCode').value = codigo;
    document.getElementById('imageProductCodeUrl').value = codigo;
    document.getElementById('fichaProductCode').value = codigo;
    loadProductImages(codigo);
    loadProductFichas(codigo);
    imageModalInstance.show();
}

function loadProductImages(codigo) {
    const container = document.getElementById('currentImages');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-warning"></div></div>';
    fetch('<?= BASE_URL ?>/api/product_images.php?codigo=' + encodeURIComponent(codigo))
        .then(r => r.json())
        .then(data => {
            if (data.success && data.images && data.images.length > 0) {
                let html = '<div class="row g-2">';
                const codigoEsc = encodeURIComponent(codigo).replace(/'/g, '%27');
                data.images.forEach(img => {
                    html += `<div class="col-3 col-sm-2">
                        <div class="position-relative border rounded p-1" style="background: var(--color-panel);">
                            <img src="${escapeHtml(img.imagen_url)}" class="img-fluid rounded" style="height: 70px; object-fit: cover; width: 100%;" alt="">
                            ${img.imagen_principal == 1 ? '<span class="badge bg-primary position-absolute top-0 start-0 m-1" style="font-size:0.55rem">Principal</span>' : ''}
                            <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" style="padding: 0 4px; font-size: 0.65rem;" onclick="deleteImage(${parseInt(img.id, 10)}, decodeURIComponent('${codigoEsc}'))" title="Eliminar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>`;
                });
                container.innerHTML = html + '</div>';
            } else {
                container.innerHTML = '<div class="text-center py-2 text-muted small"><i class="fas fa-image me-1"></i> No hay imágenes registradas para este producto</div>';
            }
        }).catch(() => container.innerHTML = '<div class="text-center py-2 text-danger small">Error al cargar imágenes</div>');
}

function viewStock(codigo) {
    const stockContent = document.getElementById('stockContent');
    stockContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';
    stockModalInstance.show();
    fetch('<?= BASE_URL ?>/api/product_stock.php?codigo=' + encodeURIComponent(codigo))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="p-2.5 rounded-3 mb-3" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-warning text-dark font-monospace fw-bold">${escapeHtml(data.producto.codigo)}</span>
                            <span class="text-muted small">Mes: <?= htmlspecialchars(ucfirst($mesActual)) ?></span>
                        </div>
                        <div class="fw-semibold text-body small">${escapeHtml(data.producto.descripcion)}</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead style="font-size: 0.72rem; text-transform: uppercase;">
                                <tr>
                                    <th>Almacén / Sede</th>
                                    <th class="text-end">Stock Físico</th>
                                </tr>
                            </thead>
                            <tbody>`;
                if (data.stock && data.stock.length > 0) {
                    let totalS = 0;
                    data.stock.forEach(s => {
                        const nombre = s.nombre_almacen || ('Almacén ' + s.numero_almacen);
                        const cant = parseFloat(s.stock_actual);
                        totalS += cant;
                        html += `<tr>
                            <td>
                                <i class="fas fa-warehouse text-warning me-1.5 small"></i>
                                <span class="fw-medium">${escapeHtml(nombre)}</span>
                            </td>
                            <td class="text-end font-monospace fw-bold">${cant.toFixed(0)} unid.</td>
                        </tr>`;
                    });
                    html += `<tr class="table-active fw-bold">
                        <td>TOTAL CONSOLIDADO</td>
                        <td class="text-end font-monospace text-warning">${totalS.toFixed(0)} unid.</td>
                    </tr>`;
                } else {
                    html += '<tr><td colspan="2" class="text-center text-muted py-3">Sin stock registrado en el mes actual</td></tr>';
                }
                stockContent.innerHTML = html + '</tbody></table></div>';
            } else {
                stockContent.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data.message || 'No se pudo cargar el stock')}</div>`;
            }
        })
        .catch(err => {
            stockContent.innerHTML = '<div class="alert alert-danger mb-0">Error de conexión al cargar el stock</div>';
            console.error('viewStock error:', err);
        });
}

async function postFormJson(url, formEl) {
    const res = await fetch(url, { method: 'POST', body: new FormData(formEl) });
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        throw new Error(`HTTP ${res.status}: respuesta no es JSON. ${text.substring(0, 200)}`);
    }
}

document.getElementById('uploadImageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fileInput = document.getElementById('imagenFile');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Seleccione una imagen antes de subir.');
        return;
    }
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Subiendo...';
    postFormJson('<?= BASE_URL ?>/api/upload_product_image.php', this)
        .then(data => {
            if (data.success) {
                loadProductImages(document.getElementById('imageProductCode').value);
                fileInput.value = '';
                document.getElementById('imagenPrincipal').checked = false;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error de conexión: ' + (err.message || err));
            console.error('uploadImage error:', err);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
});

document.getElementById('uploadUrlForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    postFormJson('<?= BASE_URL ?>/api/save_image_url.php', this)
        .then(data => {
            if (data.success) {
                loadProductImages(document.getElementById('imageProductCodeUrl').value);
                document.getElementById('imagenUrl').value = '';
                document.getElementById('imagenPrincipalUrl').checked = false;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error de conexión: ' + (err.message || err));
            console.error('saveImageUrl error:', err);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
});

document.getElementById('importExcelForm').addEventListener('submit', function(e) {
    e.preventDefault();
    document.getElementById('importProgress').classList.remove('d-none');
    document.getElementById('importResult').classList.add('d-none');
    document.getElementById('btnImport').disabled = true;

    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    progressBar.style.width = '100%';
    progressBar.textContent = 'Procesando...';
    progressText.textContent = 'Importando imágenes, por favor espere...';

    fetch('<?= BASE_URL ?>/api/import_images_excel.php', { method: 'POST', body: new FormData(this) })
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            return r.text();
        })
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response text:', text);
                throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 200));
            }
        })
        .then(data => {
            document.getElementById('importProgress').classList.add('d-none');
            document.getElementById('btnImport').disabled = false;
            const result = document.getElementById('importResult');
            result.classList.remove('d-none');

            if (data.success) {
                result.innerHTML = `<div class="alert alert-success py-2 px-3 small"><strong>✓ Importación completada</strong><br>
                    <i class="fas fa-file-alt"></i> Procesados: ${data.processed} &nbsp;|&nbsp;
                    <i class="fas fa-check text-success"></i> Exitosos: ${data.success_count} &nbsp;|&nbsp;
                    <i class="fas fa-times text-danger"></i> Errores: ${data.error_count}</div>`;
                if (data.errors && data.errors.length > 0) {
                    const maxErrors = 15;
                    const errorList = data.errors.slice(0, maxErrors).map(e => `<li>${escapeHtml(e)}</li>`).join('');
                    const moreErrors = data.errors.length > maxErrors ? `<li class="text-muted">... y ${data.errors.length - maxErrors} errores más</li>` : '';
                    result.innerHTML += `<div class="alert alert-warning py-2 px-3 small"><strong>Errores encontrados:</strong><ul class="mb-0 ps-3" style="max-height: 150px; overflow-y: auto;">${errorList}${moreErrors}</ul></div>`;
                }
                if (data.success_count > 0) {
                    result.innerHTML += '<div class="text-center mt-2"><button class="btn btn-primary btn-sm" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Actualizar página</button></div>';
                }
            } else {
                result.innerHTML = `<div class="alert alert-danger py-2 px-3 small"><i class="fas fa-exclamation-triangle me-1"></i>Error: ${escapeHtml(data.message)}</div>`;
            }
        })
        .catch(err => {
            document.getElementById('importProgress').classList.add('d-none');
            document.getElementById('btnImport').disabled = false;
            document.getElementById('importResult').classList.remove('d-none');
            document.getElementById('importResult').innerHTML = `<div class="alert alert-danger py-2 px-3 small"><i class="fas fa-exclamation-triangle me-1"></i>Error: ${escapeHtml(err.message || 'Error de conexión')}</div>`;
            console.error('Import error:', err);
        });
});

function deleteImage(id, codigo) {
    if (!confirm('¿Eliminar esta imagen del producto?')) return;
    fetch('<?= BASE_URL ?>/api/delete_product_image.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: id})
    }).then(r => r.json()).then(data => { if (data.success) loadProductImages(codigo); });
}

// ===== FICHAS TÉCNICAS =====
function loadProductFichas(codigo) {
    const container = document.getElementById('currentFichas');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-danger"></div></div>';
    fetch('<?= BASE_URL ?>/api/product_fichas.php?codigo=' + encodeURIComponent(codigo))
        .then(r => r.json())
        .then(data => {
            if (data.success && data.fichas && data.fichas.length > 0) {
                let html = '<div class="list-group list-group-flush">';
                const codigoEsc = encodeURIComponent(codigo).replace(/'/g, '%27');
                data.fichas.forEach(f => {
                    const ext = String(f.ficha_url || '').split('.').pop().toLowerCase();
                    const icon = ext === 'pdf' ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                    const nombre = f.nombre_archivo || ('Ficha ' + f.id);
                    html += `<div class="list-group-item d-flex justify-content-between align-items-center py-2 px-2" style="background: var(--color-panel);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas ${icon} fs-5"></i>
                            <div>
                                <a href="${escapeHtml(f.ficha_url)}" target="_blank" class="text-decoration-none fw-semibold text-body small">
                                    ${escapeHtml(nombre)}.${escapeHtml(ext)}
                                </a>
                                <div class="text-muted" style="font-size: 0.68rem;">${escapeHtml(f.created_at || '')}</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm py-0.5 px-2" onclick="deleteFicha(${parseInt(f.id, 10)}, decodeURIComponent('${codigoEsc}'))" title="Eliminar ficha">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>`;
                });
                container.innerHTML = html + '</div>';
            } else {
                container.innerHTML = '<div class="text-center py-2 text-muted small"><i class="fas fa-file-alt me-1"></i> No hay fichas técnicas registradas</div>';
            }
        }).catch(() => container.innerHTML = '<div class="text-center py-2 text-danger small">Error al cargar fichas</div>');
}

function deleteFicha(id, codigo) {
    if (!confirm('¿Eliminar esta ficha técnica?')) return;
    fetch('<?= BASE_URL ?>/api/delete_ficha_tecnica.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: id})
    }).then(r => r.json()).then(data => {
        if (data.success) loadProductFichas(codigo);
        else alert('Error: ' + data.message);
    });
}

document.getElementById('uploadFichaForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Subiendo...';
    fetch('<?= BASE_URL ?>/api/upload_ficha_tecnica.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload me-1"></i> Subir Ficha Técnica';
            if (data.success) {
                loadProductFichas(document.getElementById('fichaProductCode').value);
                document.getElementById('fichaFile').value = '';
            } else {
                alert('Error: ' + data.message);
            }
        }).catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload me-1"></i> Subir Ficha Técnica';
            alert('Error de conexión');
        });
});

document.getElementById('importFichasForm').addEventListener('submit', function(e) {
    e.preventDefault();
    document.getElementById('importFichasProgress').classList.remove('d-none');
    document.getElementById('importFichasResult').classList.add('d-none');
    document.getElementById('btnImportFichas').disabled = true;
    const pb = document.getElementById('progressBarFichas');
    pb.style.width = '100%';
    pb.textContent = 'Procesando...';
    document.getElementById('progressTextFichas').textContent = 'Importando fichas técnicas, por favor espere...';

    fetch('<?= BASE_URL ?>/api/import_fichas_excel.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.ok ? r.text() : Promise.reject('HTTP ' + r.status))
        .then(text => { try { return JSON.parse(text); } catch(e) { throw new Error('Respuesta inválida: ' + text.substring(0,200)); } })
        .then(data => {
            document.getElementById('importFichasProgress').classList.add('d-none');
            document.getElementById('btnImportFichas').disabled = false;
            const result = document.getElementById('importFichasResult');
            result.classList.remove('d-none');
            if (data.success) {
                result.innerHTML = `<div class="alert alert-success py-2 px-3 small"><strong>✓ Importación completada</strong><br>
                    Procesados: ${data.processed} &nbsp;|&nbsp; Nuevas: ${data.success_count} &nbsp;|&nbsp; Errores: ${data.error_count}</div>`;
                if (data.errors && data.errors.length > 0) {
                    result.innerHTML += `<div class="alert alert-warning py-2 px-3 small"><strong>Errores:</strong><ul>${data.errors.map(e=>'<li>'+escapeHtml(e)+'</li>').join('')}</ul></div>`;
                }
            } else {
                result.innerHTML = `<div class="alert alert-danger py-2 px-3 small"><i class="fas fa-exclamation-triangle me-1"></i>${escapeHtml(data.message)}</div>`;
            }
        }).catch(err => {
            document.getElementById('importFichasProgress').classList.add('d-none');
            document.getElementById('btnImportFichas').disabled = false;
            document.getElementById('importFichasResult').classList.remove('d-none');
            document.getElementById('importFichasResult').innerHTML = `<div class="alert alert-danger py-2 px-3 small">${escapeHtml(err.message || err)}</div>`;
        });
});

function exportProductsToCSV() {
    const table = document.getElementById('productsTable');
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    let csv = '\uFEFF"Codigo","Descripcion","Precio","Stock_Total"\n';

    rows.forEach(row => {
        const cols = row.querySelectorAll('td');
        if (cols.length >= 4) {
            const codigo = cols[0].querySelector('.font-monospace')?.innerText.trim() || '';
            const desc = cols[1].querySelector('.fw-semibold')?.innerText.trim() || '';
            const precio = cols[2].querySelector('.font-monospace')?.innerText.replace(/[^0-9.]/g, '').trim() || '';
            const stock = cols[3].querySelector('.badge')?.innerText.replace(/[^0-9.]/g, '').trim() || '';

            csv += `"${codigo.replace(/"/g, '""')}","${desc.replace(/"/g, '""')}","${precio}","${stock}"\n`;
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `catalogo_productos_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
