<?php
/**
 * Vista de Productos y Stock — Sistema de Cotizaciones
 * Carga instantánea de catálogo e inventario en tiempo real vía AJAX desde api/products_search.php
 * Integrado con Master App Shell y diseño Notion / Linear
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$userRepo  = new User();
$userRoles = $userRepo->getRoles($auth->getUserId());
if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

$user      = $auth->getUser();
$stockRepo = new Stock();

// Almacenes desde DB local
$allWarehouses = $stockRepo->getWarehouses();

$search          = trim($_GET['search'] ?? '');
$warehouseFilter = isset($_GET['warehouse']) ? (string)$_GET['warehouse'] : '';
$perPage         = (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 15, 25, 50])) ? (int)$_GET['per_page'] : 15;
$page            = max(1, (int)($_GET['page'] ?? 1));

$meses = [
    1=>'enero', 2=>'febrero', 3=>'marzo', 4=>'abril', 5=>'mayo', 6=>'junio',
    7=>'julio', 8=>'agosto', 9=>'septiembre', 10=>'octubre', 11=>'noviembre', 12=>'diciembre'
];
$mesActual = $meses[(int)date('n')];
$mesCapitalizado = ucfirst($mesActual) . ' ' . date('Y');

$pageTitle = 'Productos y Stock';
$activePage = 'products';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<style>
.product-thumb-box {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background-color: var(--color-canvas, #09090B);
    border: 1px solid var(--color-rule, #27272A);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: pointer;
    flex-shrink: 0;
    transition: transform 0.15s ease, border-color 0.15s ease;
}
.product-thumb-box:hover {
    transform: scale(1.08);
    border-color: var(--color-amber, #F59E0B);
}
.product-thumb-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.stock-chip {
    font-size: 0.68rem;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    background-color: rgba(245, 158, 11, 0.1);
    color: #F59E0B;
    border: 1px solid rgba(245, 158, 11, 0.25);
    white-space: nowrap;
}
.loading-box {
    text-align: center;
    padding: 3.5rem 1rem;
}
</style>

<div class="content-body" style="padding: 0.85rem 1rem 3rem; max-width: 1400px; margin: 0 auto;">

    <!-- =========================================================
         1. HERO HEADER BAR
         ========================================================= -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 600; font-size: 0.75rem;">
                    <i class="fas fa-boxes-stacked me-1"></i> Inventario & Catálogo COBOL
                </span>
                <span class="text-muted small">Mes: <strong><?= htmlspecialchars($mesCapitalizado) ?></strong></span>
            </div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--color-ink, #09090B); letter-spacing: -0.02em;">
                Productos y Stock en Tiempo Real
            </h1>
            <p class="text-muted small mb-0">
                Consulta instantánea de catálogo de llantas, existencias por sede y fichas técnicas.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-warning text-dark fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-plus-circle"></i>
                <span>Nueva Cotización</span>
            </a>
            <a href="<?= BASE_URL ?>/cost-analysis/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-chart-pie"></i>
                <span>Análisis Costos</span>
            </a>
            <a href="<?= BASE_URL ?>/admin/warehouses.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-warehouse"></i>
                <span>Sedes / Almacenes</span>
            </a>
            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                    onclick="exportProductsViewToCSV()" title="Descargar CSV de la vista actual" style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-file-csv text-success"></i>
                <span class="d-none d-sm-inline">Exportar</span>
            </button>
        </div>
    </div>

    <!-- =========================================================
         2. KPI METRICS STRIP (4 SLIM CARDS)
         ========================================================= -->
    <div class="row g-2 g-md-3 mb-3 mb-md-4">
        <!-- KPI 1: Productos con Stock -->
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2.5 p-md-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Líneas con Stock</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                            <i class="fas fa-boxes" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" id="kpi_total_products" style="color: var(--color-ink, #09090B);">-</span>
                        <span class="badge bg-success-subtle text-success small" style="font-size: 0.65rem;">En Catálogo</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Coincidentes con los filtros</div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Período de Stock Activo -->
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2.5 p-md-3">
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

        <!-- KPI 3: Almacenes en Red -->
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2.5 p-md-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Sedes Monitoreadas</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.15); color: #10B981;">
                            <i class="fas fa-warehouse" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" style="color: var(--color-ink, #09090B);"><?= count($allWarehouses) ?></span>
                        <span class="text-muted small" style="font-size: 0.75rem;">almacenes</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Mapeo multicompañía COBOL</div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Tiempo de Respuesta & Estado -->
        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2.5 p-md-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Velocidad de Búsqueda</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(139, 92, 246, 0.15); color: #8B5CF6;">
                            <i class="fas fa-bolt" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace text-success" id="kpi_query_time">-</span>
                        <span class="badge bg-success-subtle text-success small" style="font-size: 0.65rem;">COBOL Live</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;" id="kpi_warehouse_filter_label">Todos los almacenes</div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. SEARCH & FILTERS BAR
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
        <div class="card-body p-2.5 p-md-3">
            <form id="searchForm" class="row g-2 align-items-center justify-content-between" onsubmit="doSearch(1); return false;">
                <!-- Buscador de texto -->
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="searchFilter"
                               placeholder="Buscar por código, descripción, medida de neumático..."
                               value="<?= htmlspecialchars($search) ?>" autocomplete="off" oninput="onSearchInputDebounce()">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearSearchFilter()" title="Limpiar">
                            <i class="fas fa-times"></i>
                        </button>
                        <button type="submit" class="btn btn-primary px-3" id="btnSearch">
                            Buscar
                        </button>
                    </div>
                </div>

                <!-- Filtro por Almacén -->
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary"><i class="fas fa-warehouse text-muted"></i></span>
                        <select class="form-select" id="warehouseFilter" onchange="doSearch(1)">
                            <option value="">-- Todos los almacenes --</option>
                            <?php foreach ($allWarehouses as $w): ?>
                                <option value="<?= $w['numero_almacen'] ?>" <?= $warehouseFilter == $w['numero_almacen'] ? 'selected' : '' ?>>
                                    #<?= $w['numero_almacen'] ?> - <?= htmlspecialchars($w['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Botón Limpiar filtros e información -->
                <div class="col-12 col-md-3 d-flex justify-content-md-end align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetAllFilters()">
                        <i class="fas fa-undo me-1"></i> Limpiar
                    </button>
                    <span class="text-muted small" id="pageInfo">Cargando...</span>
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
                <span class="fw-semibold text-body small">Directorio de Productos</span>
                <span class="text-muted small" id="resultCountBadge"></span>
            </div>

            <!-- Page Size Switcher -->
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Mostrar:</span>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach ([10, 15, 25, 50] as $sz): ?>
                        <button type="button" class="btn <?= ($perPage == $sz) ? 'btn-primary' : 'btn-outline-secondary' ?> px-2 py-0.5 per-page-btn"
                                data-size="<?= $sz ?>" onclick="changePerPage(<?= $sz ?>)" style="font-size: 0.75rem;">
                            <?= $sz ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card-body p-0" id="tableContainer">
            <div class="loading-box" id="loadingOverlay">
                <div class="spinner-border text-warning mb-3"></div>
                <h5 class="text-muted">Cargando catálogo en tiempo real...</h5>
                <p class="text-muted small">Consultando base de datos COBOL</p>
            </div>
        </div>

        <!-- Pagination Bar -->
        <div class="card-footer bg-body-tertiary border-top py-2 px-3 d-none d-flex flex-wrap justify-content-between align-items-center gap-2" id="paginationContainer">
            <div class="text-muted small" id="paginationSummary"></div>
            <nav aria-label="Navegación de páginas">
                <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- =========================================================
     5. MODAL IMAGEN AMPLIADA
     ========================================================= -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-body mb-0" id="imageModalTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center p-3" style="background: var(--color-canvas);">
                <img id="imageModalSrc" src="" alt="Producto" class="img-fluid rounded" style="max-height: 520px; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     6. MODAL FICHAS TÉCNICAS
     ========================================================= -->
<div class="modal fade" id="fichasModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="modal-title fw-bold text-body mb-0"><i class="fas fa-file-pdf me-1.5 text-danger"></i> Ficha Técnica del Producto</h6>
                    <small class="text-muted" id="fichasModalDesc"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3" id="fichasModalBody">
                <div class="text-center py-4"><div class="spinner-border text-warning"></div></div>
            </div>
            <div class="modal-footer border-top py-2 px-3 bg-body-tertiary">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let currentPage = <?= max(1, (int)($_GET['page'] ?? 1)) ?>;
let currentPerPage = <?= $perPage ?>;
let searchAbort = null;
let debounceTimer = null;
let lastLoadedData = null;

function onSearchInputDebounce() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        doSearch(1);
    }, 300);
}

function clearSearchFilter() {
    const input = document.getElementById('searchFilter');
    input.value = '';
    doSearch(1);
    input.focus();
}

function resetAllFilters() {
    document.getElementById('searchFilter').value = '';
    document.getElementById('warehouseFilter').value = '';
    doSearch(1);
}

function changePerPage(size) {
    currentPerPage = size;
    document.querySelectorAll('.per-page-btn').forEach(btn => {
        if (parseInt(btn.dataset.size) === size) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        }
    });
    doSearch(1);
}

function doSearch(page) {
    if (page) currentPage = page;
    else currentPage = 1;

    const search = document.getElementById('searchFilter').value.trim();
    const warehouse = document.getElementById('warehouseFilter').value;

    // Actualizar URL sin recargar
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (warehouse) params.set('warehouse', warehouse);
    if (currentPerPage !== 15) params.set('per_page', currentPerPage);
    if (currentPage > 1) params.set('page', currentPage);
    history.replaceState(null, '', '?' + params.toString());

    // Cancelar búsqueda anterior
    if (searchAbort) searchAbort.abort();
    searchAbort = new AbortController();

    // Mostrar loading
    const container = document.getElementById('tableContainer');
    container.innerHTML = `<div class="loading-box">
        <div class="spinner-border text-warning mb-3"></div>
        <h5 class="text-muted">Consultando catálogo en COBOL...</h5>
    </div>`;
    document.getElementById('paginationContainer').classList.add('d-none');
    document.getElementById('btnSearch').disabled = true;

    const url = `${BASE_URL}/api/products_search.php?search=${encodeURIComponent(search)}&warehouse=${encodeURIComponent(warehouse)}&per_page=${currentPerPage}&page=${currentPage}`;

    fetch(url, { signal: searchAbort.signal })
        .then(r => r.json())
        .then(data => {
            document.getElementById('btnSearch').disabled = false;
            if (!data.success) {
                container.innerHTML = `<div class="text-center py-5"><p class="text-danger fw-bold">${esc(data.message || 'Error al buscar')}</p></div>`;
                return;
            }
            lastLoadedData = data;
            renderResults(data);
        })
        .catch(err => {
            if (err.name === 'AbortError') return;
            document.getElementById('btnSearch').disabled = false;
            container.innerHTML = `<div class="text-center py-5"><p class="text-danger">Error de conexión al consultar el servidor</p></div>`;
        });
}

function renderResults(data) {
    const container = document.getElementById('tableContainer');
    const products = data.products || [];
    const warehouses = data.warehouses || [];
    const warehouseFilter = document.getElementById('warehouseFilter').value;

    // Actualizar KPIs
    document.getElementById('kpi_total_products').textContent = data.total ? data.total.toLocaleString() : '0';
    document.getElementById('kpi_query_time').textContent = `${data.time || '0.05'} s`;

    const selectedWhOption = document.getElementById('warehouseFilter').options[document.getElementById('warehouseFilter').selectedIndex];
    document.getElementById('kpi_warehouse_filter_label').textContent = warehouseFilter ? selectedWhOption.text : 'Todos los almacenes';

    document.getElementById('resultCountBadge').textContent = `(${data.total.toLocaleString()} productos coincidentes)`;
    document.getElementById('pageInfo').innerHTML =
        `Pág. <strong>${data.page}</strong> de <strong>${data.totalPages}</strong> &bull; Mostrando <strong>${products.length}</strong>`;

    if (products.length === 0) {
        container.innerHTML = `<div class="text-center py-5 text-muted">
            <i class="fas fa-boxes fa-3x text-secondary opacity-50 mb-3 d-block"></i>
            <h5 class="text-muted fw-bold">No se encontraron productos con stock</h5>
            <p class="small text-muted">Pruebe ajustando los términos de búsqueda o cambiando el filtro de almacén.</p>
        </div>`;
        return;
    }

    // Construir tabla compacta de 5 columnas
    let html = `<div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="productsTable" style="width: 100%;">
            <thead style="background-color: var(--color-canvas, #09090B); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                <tr>
                    <th class="ps-3 py-2 text-muted" style="width: 170px;">Producto / Código</th>
                    <th class="py-2 text-muted" style="min-width: 280px;">Descripción & Ficha Técnica</th>
                    <th class="py-2 text-end text-muted" style="width: 120px;">Precio Base (USD)</th>
                    <th class="py-2 text-muted" style="min-width: 240px;">Stock Total & Almacenes</th>
                    <th class="pe-3 py-2 text-end text-muted" style="width: 130px;">Acción</th>
                </tr>
            </thead>
            <tbody>`;

    products.forEach(p => {
        const imgThumb = p.imagen
            ? `<img src="${esc(p.imagen)}" alt="Producto" loading="lazy">`
            : '<i class="fas fa-image text-muted opacity-50" style="font-size: 1.1rem;"></i>';

        const totalClass = p.total_stock > 0
            ? 'bg-success-subtle text-success border border-success-subtle'
            : 'bg-danger-subtle text-danger';

        // Construir chips compactos de almacenes
        let warehouseChips = '';
        if (p.warehouses && typeof p.warehouses === 'object') {
            const positiveEntries = Object.entries(p.warehouses).filter(([wNum, stock]) => parseFloat(stock) > 0);
            if (positiveEntries.length > 0) {
                warehouseChips = positiveEntries.map(([wNum, stock]) => {
                    const wObj = warehouses.find(w => String(w.numero_almacen) === String(wNum));
                    const wName = wObj ? wObj.nombre : ('Almacén ' + wNum);
                    return `<span class="stock-chip" title="${esc(wName)}: ${Number(stock).toLocaleString()} unid.">${esc(wName)}: <strong>${Number(stock).toLocaleString()}</strong></span>`;
                }).join(' ');
            } else {
                warehouseChips = '<span class="text-muted small" style="font-size: 0.7rem;">Sin existencias en sedes</span>';
            }
        }

        const fichaBtn = p.fichas > 0
            ? `<button type="button" class="btn btn-outline-danger btn-sm py-0 px-1.5" onclick="showFichas('${esc(p.codigo)}', '${esc(p.descripcion)}')" title="${p.fichas} ficha(s) técnica(s)" style="font-size: 0.7rem;">
                <i class="fas fa-file-pdf me-0.5"></i> Ficha Técnica
               </button>`
            : '';

        html += `<tr>
            <!-- Col 1: Imagen & Código -->
            <td class="ps-3 py-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="product-thumb-box" onclick="showImageModal('${esc(p.imagen || '')}', '${esc(p.descripcion)}')" title="Ver imagen ampliada">
                        ${imgThumb}
                    </div>
                    <div>
                        <div class="font-monospace fw-bold text-body small" style="font-size: 0.82rem;">${esc(p.codigo)}</div>
                        <button type="button" class="btn btn-link btn-sm p-0 text-muted" onclick="navigator.clipboard.writeText('${esc(p.codigo)}')" title="Copiar código" style="font-size: 0.68rem;">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
            </td>

            <!-- Col 2: Descripción & Ficha -->
            <td class="py-2">
                <div class="fw-semibold text-body" style="font-size: 0.88rem; line-height: 1.35;">
                    ${esc(p.descripcion)}
                </div>
                ${fichaBtn ? `<div class="mt-1">${fichaBtn}</div>` : ''}
            </td>

            <!-- Col 3: Precio USD -->
            <td class="py-2 text-end">
                <div class="font-monospace fw-bold fs-6 text-body">
                    $ ${Number(p.precio).toFixed(2)}
                </div>
                <span class="text-muted small" style="font-size: 0.68rem;">Precio de lista</span>
            </td>

            <!-- Col 4: Stock Total & Sedes -->
            <td class="py-2">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge font-monospace px-2 py-0.5 ${totalClass}" style="font-size: 0.78rem; font-weight: 700;">
                        <i class="fas fa-cubes me-1"></i>Stock: ${Number(p.total_stock).toLocaleString()} unid.
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    ${warehouseChips}
                </div>
            </td>

            <!-- Col 5: Acción Cotizar -->
            <td class="pe-3 py-2 text-end">
                <a href="${BASE_URL}/quotations/create.php?add_product=${encodeURIComponent(p.codigo)}"
                   class="btn btn-warning text-dark btn-sm fw-bold px-2.5 py-1.5 shadow-sm d-inline-flex align-items-center gap-1"
                   title="Iniciar cotización con este producto">
                    <i class="fas fa-plus"></i>
                    <span>Cotizar</span>
                </a>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;

    // Paginación
    renderPagination(data.page, data.totalPages, data.total, products.length);
}

function renderPagination(page, totalPages, totalItems, pageItemsCount) {
    const pagContainer = document.getElementById('paginationContainer');
    if (totalPages <= 1) {
        pagContainer.classList.add('d-none');
        return;
    }
    pagContainer.classList.remove('d-none');

    const startIndex = (page - 1) * currentPerPage + 1;
    const endIndex = Math.min(totalItems, startIndex + pageItemsCount - 1);
    document.getElementById('paginationSummary').innerHTML =
        `Mostrando <strong>${startIndex} - ${endIndex}</strong> de <strong>${totalItems.toLocaleString()}</strong> productos`;

    let html = '';
    // Primera y Anterior
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="doSearch(1); return false;" title="Primera página"><i class="fas fa-angle-double-left"></i></a>
    </li>`;
    html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="doSearch(${page - 1}); return false;" title="Anterior"><i class="fas fa-chevron-left"></i></a>
    </li>`;

    const range = 2;
    const startPage = Math.max(1, page - range);
    const endPage = Math.min(totalPages, page + range);

    if (startPage > 1) {
        html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="doSearch(${i}); return false;">${i}</a>
        </li>`;
    }

    if (endPage < totalPages) {
        html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    // Siguiente y Última
    html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="doSearch(${page + 1}); return false;" title="Siguiente"><i class="fas fa-chevron-right"></i></a>
    </li>`;
    html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="doSearch(${totalPages}); return false;" title="Última página"><i class="fas fa-angle-double-right"></i></a>
    </li>`;

    document.getElementById('pagination').innerHTML = html;
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function showImageModal(url, title) {
    if (!url) return;
    document.getElementById('imageModalSrc').src = url;
    document.getElementById('imageModalTitle').textContent = title || 'Imagen del Producto';
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

function showFichas(codigo, descripcion) {
    document.getElementById('fichasModalDesc').textContent = codigo + ' — ' + descripcion;
    document.getElementById('fichasModalBody').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';
    new bootstrap.Modal(document.getElementById('fichasModal')).show();

    fetch(BASE_URL + '/api/product_fichas.php?codigo=' + encodeURIComponent(codigo))
        .then(r => r.json())
        .then(data => {
            if (!data.success || data.fichas.length === 0) {
                document.getElementById('fichasModalBody').innerHTML =
                    '<p class="text-muted text-center py-4"><i class="fas fa-info-circle me-1"></i> No hay fichas técnicas registradas para este producto.</p>';
                return;
            }
            let html = '<div class="list-group list-group-flush">';
            data.fichas.forEach(f => {
                const ext  = (f.ficha_url || '').split('.').pop().toLowerCase();
                const isPdf = ext === 'pdf';
                const icon  = isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                const name  = (f.nombre_archivo || 'Ficha ' + f.id) + '.' + ext;

                html += `<div class="list-group-item d-flex justify-content-between align-items-center py-2.5 px-2" style="background: var(--color-panel);">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas ${icon} fs-4"></i>
                        <div>
                            <div class="fw-semibold text-body small">${esc(name)}</div>
                            <small class="text-muted">${esc(f.created_at || '')}</small>
                        </div>
                    </div>
                    <a href="${esc(f.ficha_url)}" target="_blank" class="btn btn-sm ${isPdf ? 'btn-outline-danger' : 'btn-outline-primary'} py-1 px-2.5">
                        <i class="fas fa-external-link-alt me-1"></i> Abrir Documento
                    </a>
                </div>`;
            });
            html += '</div>';
            document.getElementById('fichasModalBody').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('fichasModalBody').innerHTML =
                '<p class="text-danger text-center py-4">Error de conexión al cargar fichas técnicas.</p>';
        });
}

function exportProductsViewToCSV() {
    if (!lastLoadedData || !lastLoadedData.products || lastLoadedData.products.length === 0) {
        alert('No hay productos cargados para exportar');
        return;
    }
    let csv = '\uFEFF"Codigo","Descripcion","Precio_USD","Stock_Total"\n';
    lastLoadedData.products.forEach(p => {
        csv += `"${(p.codigo || '').replace(/"/g, '""')}","${(p.descripcion || '').replace(/"/g, '""')}","${p.precio}","${p.total_stock}"\n`;
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

// Cargar al iniciar
document.addEventListener('DOMContentLoaded', () => doSearch(<?= max(1, (int)($_GET['page'] ?? 1)) ?>));
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
