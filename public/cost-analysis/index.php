<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!Permissions::canAccessCostAnalysis($auth)) {
    $_SESSION['error_message'] = 'No tienes acceso al módulo de análisis de costos';
    $auth->redirect(BASE_URL . '/dashboard.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Exchange rate
$companySettings = new CompanySettings();
$exchangeRate = (float)($companySettings->getSetting($companyId, 'exchange_rate_usd_pen') ?? 3.80);

$pageTitle = 'Análisis de Costos y Márgenes';
$activePage = 'cost_analysis';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-4 py-3" style="max-width: 1400px; margin: 0 auto;">

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
                <li class="breadcrumb-item text-muted">Operaciones</li>
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Análisis de Costos</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Análisis de Costos y Márgenes
                    </h1>
                    <span class="badge rounded-pill bg-body-tertiary text-body border px-2 py-1 small fw-semibold">
                        Simulador Financiero
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Calculadora de rentabilidad comercial, costos de reposición y simulador de descuentos en tiempo real.
                </p>
            </div>

            <!-- Currency Toggle & Exchange Rate Controls -->
            <div class="d-flex align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-1 p-1 rounded border" style="background-color: var(--color-panel);">
                    <button type="button" class="btn btn-sm btn-primary fw-semibold px-2.5 py-1" id="btnUSD" onclick="setCurrency('USD')">
                        <i class="fas fa-dollar-sign me-1"></i>USD
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold px-2.5 py-1" id="btnPEN" onclick="setCurrency('PEN')">
                        S/ PEN
                    </button>
                </div>
                <div class="badge bg-body-tertiary text-body border font-monospace px-3 py-2" id="exchangeRateBadge" style="font-size: 0.78rem;">
                    <i class="fas fa-money-bill-transfer text-warning me-1"></i>T.C.: USD 1 = S/ <?= number_format($exchangeRate, 3) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         2. FINANCIAL MARGIN THRESHOLDS BAR (Compact Guide)
         ========================================================= -->
    <div class="card mb-3 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
        <div class="card-body p-2 px-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">
                        <i class="fas fa-info-circle text-primary me-1"></i>Escala de Rentabilidad:
                    </span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;">
                        <i class="fas fa-check-circle me-1"></i>Excelente: ≥ 30%
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.72rem;">
                        <i class="fas fa-arrow-trend-up me-1"></i>Saludable: 20% - 29%
                    </span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.72rem;">
                        <i class="fas fa-circle-exclamation me-1"></i>Moderado: 10% - 19%
                    </span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.72rem;">
                        <i class="fas fa-triangle-exclamation me-1"></i>Crítico / Bajo: < 10%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. PRODUCT SEARCH HERO BAR (Fast Debounce + Auto-complete)
         ========================================================= -->
    <div class="card mb-4 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
        <div class="card-body p-3">
            <div class="search-wrapper position-relative">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-body-tertiary border-end-0 text-muted">
                        <i class="fas fa-barcode"></i>
                    </span>
                    <input type="text"
                           class="form-control border-start-0 ps-0"
                           id="searchInput"
                           placeholder="Buscar por código de producto, marca, medida o descripción (ej: 175/70, Triangle, Westlake, 11R22.5)..."
                           autocomplete="off">
                    <button class="btn btn-outline-secondary d-none" type="button" id="clearSearchBtn" title="Limpiar búsqueda" onclick="clearSearchInput()">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="btn btn-primary px-4 fw-bold" type="button" id="triggerSearchBtn" onclick="triggerSearchNow()">
                        <i class="fas fa-search me-1"></i> Buscar
                    </button>
                </div>
                <div class="spinner-border spinner-border-sm text-primary search-spinner d-none" id="searchSpinner" role="status"></div>
                <div class="search-dropdown d-none" id="searchDropdown"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                <span class="text-muted small" style="font-size: 0.75rem;">
                    <i class="fas fa-lightbulb text-warning me-1"></i>Escriba al menos 2 caracteres o presione <strong>Enter</strong> para buscar.
                </span>
                <span class="text-muted small" id="searchResultSummary" style="font-size: 0.75rem;"></span>
            </div>
        </div>
    </div>

    <!-- =========================================================
         4. DETAILED PRODUCT FINANCIAL SIMULATOR (Dynamic Container)
         ========================================================= -->
    <div id="productDetail" class="d-none mb-4"></div>

    <!-- =========================================================
         5. COMPARATIVE SEARCH RESULTS TABLE (Multi-Product Grid)
         ========================================================= -->
    <div id="comparisonContainer" class="d-none mb-4">
        <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-body-tertiary border-bottom d-flex justify-content-between align-items-center py-2 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-table-list text-warning"></i>
                    <span class="fw-semibold text-body small">Resultados de Búsqueda y Comparativa de Márgenes</span>
                </div>
                <span class="badge bg-body-secondary text-body border" id="comparisonCountBadge">0 productos</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="comparisonTable" style="width: 100%;">
                        <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                            <tr>
                                <th class="ps-3 py-2 text-muted" style="width: 50px;">Img</th>
                                <th class="py-2 text-muted">Código & Descripción</th>
                                <th class="py-2 text-muted">Marca</th>
                                <th class="py-2 text-muted text-center">Stock</th>
                                <th class="py-2 text-muted text-end">Precio Lista</th>
                                <th class="py-2 text-muted text-end">Costo Reposición</th>
                                <th class="py-2 text-muted text-end">Margen Base</th>
                                <th class="pe-3 py-2 text-end text-muted" style="width: 120px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="comparisonTableBody" class="divide-y"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         6. INITIAL EMPTY STATE
         ========================================================= -->
    <div id="emptyState" class="text-center py-5 px-3">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
            <i class="fas fa-calculator fa-2x"></i>
        </div>
        <h5 class="fw-bold text-body mb-1">Simulador de Costos y Márgenes</h5>
        <p class="text-muted small mb-3" style="max-width: 480px; margin: 0 auto;">
            Ingrese un código de producto, marca o medida en la barra superior para analizar costos unitarios, simular descuentos comerciales y proyectar utilidades netas.
        </p>
    </div>
</div>

<!-- =========================================================
     MODAL: ZOOM IMAGEN DE PRODUCTO
     ========================================================= -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <div class="modal-header border-bottom py-2 px-3">
                <h6 class="modal-title fw-bold text-body mb-0" id="imageModalTitle">Vista del Producto</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center p-3">
                <img src="" id="imageModalImg" class="img-fluid rounded" style="max-height: 70vh; object-fit: contain;" alt="Producto">
            </div>
        </div>
    </div>
</div>

<style>
/* Search dropdown */
.search-wrapper { position: relative; }
.search-spinner {
    position: absolute;
    right: 120px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}
.search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1050;
    max-height: 380px;
    overflow-y: auto;
    background: var(--color-panel, #ffffff);
    border: 1px solid var(--color-rule, #dee2e6);
    border-top: none;
    border-radius: 0 0 10px 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.search-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid var(--color-rule, #dee2e6);
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background 0.15s ease;
}
.search-item:hover, .search-item.active {
    background: rgba(245, 158, 11, 0.08);
}
.search-item:last-child { border-bottom: none; }
.search-item-thumb {
    width: 38px;
    height: 38px;
    object-fit: contain;
    border-radius: 6px;
    background: var(--color-canvas, #f8f9fa);
    flex-shrink: 0;
}
.search-item-thumb-placeholder {
    width: 38px;
    height: 38px;
    border-radius: 6px;
    background: var(--color-canvas, #f8f9fa);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-ink-subtle, #6c757d);
    flex-shrink: 0;
    font-size: 0.8rem;
}
.search-item-code {
    font-family: var(--font-mono, monospace);
    font-size: 0.78rem;
    padding: 1px 6px;
    border-radius: 4px;
}

/* Simulator cards */
.sim-card {
    background-color: var(--color-canvas);
    border: 1px solid var(--color-rule);
    border-radius: 10px;
    padding: 14px 16px;
}
.sim-metric-val {
    font-family: var(--font-mono, monospace);
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1.2;
}

/* Margin Gauge Meter */
.margin-meter-container {
    background: var(--color-canvas);
    border: 1px solid var(--color-rule);
    border-radius: 12px;
    padding: 16px;
}
.margin-meter-track {
    height: 24px;
    border-radius: 12px;
    background: var(--color-panel);
    border: 1px solid var(--color-rule);
    overflow: hidden;
    position: relative;
}
.margin-meter-fill {
    height: 100%;
    border-radius: 12px;
    transition: width 0.35s ease, background-color 0.35s ease;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
    font-family: var(--font-mono, monospace);
    font-size: 0.78rem;
    font-weight: 700;
    color: #fff;
}

/* Discount preset pills */
.discount-pill {
    cursor: pointer;
    font-size: 0.75rem;
    padding: 3px 10px;
    border-radius: 20px;
    transition: all 0.15s ease;
}
.discount-pill.active {
    background-color: #F59E0B !important;
    color: #000 !important;
    border-color: #F59E0B !important;
    font-weight: 700;
}

.divide-y tr {
    border-bottom: 1px solid var(--color-rule);
}
</style>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let currentCurrency = 'USD';
let exchangeRate = <?= $exchangeRate ?>;
let searchResults = [];
let selectedProduct = null;
let currentDiscount = 0;
let searchTimeout = null;

const searchInput = document.getElementById('searchInput');
const searchDropdown = document.getElementById('searchDropdown');
const searchSpinner = document.getElementById('searchSpinner');
const productDetail = document.getElementById('productDetail');
const emptyState = document.getElementById('emptyState');
const clearSearchBtn = document.getElementById('clearSearchBtn');
const comparisonContainer = document.getElementById('comparisonContainer');
const comparisonTableBody = document.getElementById('comparisonTableBody');

// =========================================================
// FAST DEBOUNCED SEARCH (350ms instead of 2000ms!)
// =========================================================
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();

    if (query.length > 0) {
        clearSearchBtn.classList.remove('d-none');
    } else {
        clearSearchBtn.classList.add('d-none');
    }

    if (query.length < 2) {
        hideDropdown();
        return;
    }

    searchTimeout = setTimeout(() => searchProducts(query), 350);
});

searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const activeItem = searchDropdown.querySelector('.search-item.active');
        if (activeItem && !searchDropdown.classList.contains('d-none')) {
            activeItem.click();
        } else {
            triggerSearchNow();
        }
        return;
    }

    if (searchDropdown.classList.contains('d-none')) return;
    const items = searchDropdown.querySelectorAll('.search-item');
    let activeIdx = [...items].findIndex(el => el.classList.contains('active'));

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIdx = Math.min(activeIdx + 1, items.length - 1);
        items.forEach(el => el.classList.remove('active'));
        items[activeIdx].classList.add('active');
        items[activeIdx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIdx = Math.max(activeIdx - 1, 0);
        items.forEach(el => el.classList.remove('active'));
        items[activeIdx].classList.add('active');
        items[activeIdx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Escape') {
        hideDropdown();
    }
});

// Close dropdown on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-wrapper')) {
        hideDropdown();
    }
});

function clearSearchInput() {
    searchInput.value = '';
    clearSearchBtn.classList.add('d-none');
    hideDropdown();
    searchInput.focus();
}

function triggerSearchNow() {
    clearTimeout(searchTimeout);
    const query = searchInput.value.trim();
    if (query.length >= 2) {
        searchProducts(query);
    } else {
        searchInput.focus();
    }
}

async function searchProducts(query) {
    searchSpinner.classList.remove('d-none');
    hideDropdown();

    try {
        const resp = await fetch(`${BASE_URL}/api/cost_analysis_search.php?search=${encodeURIComponent(query)}&page=1`);
        const data = await resp.json();

        if (!data.success) {
            showSearchError(data.message || 'Error en la consulta');
            return;
        }

        if (data.exchangeRate) {
            exchangeRate = data.exchangeRate;
            document.getElementById('exchangeRateBadge').innerHTML =
                `<i class="fas fa-money-bill-transfer text-warning me-1"></i>T.C.: USD 1 = S/ ${exchangeRate.toFixed(3)}`;
        }

        searchResults = data.products || [];
        document.getElementById('searchResultSummary').textContent = `${data.total || 0} productos encontrados`;

        renderDropdown(searchResults, data.total || 0);
        renderComparisonTable(searchResults);

    } catch (err) {
        console.error('Search error:', err);
        showSearchError('Error de conexión con el servidor');
    } finally {
        searchSpinner.classList.add('d-none');
    }
}

function showSearchError(msg) {
    searchDropdown.innerHTML = `
        <div class="p-3 text-center text-danger small">
            <i class="fas fa-exclamation-triangle me-1"></i> ${escHtml(msg)}
        </div>`;
    searchDropdown.classList.remove('d-none');
}

function renderDropdown(products, total) {
    if (products.length === 0) {
        searchDropdown.innerHTML = `
            <div class="p-3 text-center text-muted small">
                <i class="fas fa-box-open me-1"></i> No se encontraron productos coincidentes
            </div>`;
        searchDropdown.classList.remove('d-none');
        return;
    }

    let html = '';
    if (total > products.length) {
        html += `<div class="px-3 py-1.5 text-muted small bg-body-tertiary border-bottom" style="font-size:0.75rem;">
                    Mostrando primeros ${products.length} de ${total} productos coincidentes
                 </div>`;
    }

    products.forEach((p, idx) => {
        const hasCosto = (p.costo_dolares > 0 || p.costo_soles > 0);
        const costoWarning = !hasCosto
            ? '<span class="badge bg-danger-subtle text-danger ms-2" style="font-size:0.68rem;">Sin costo</span>'
            : '';

        html += `
        <div class="search-item ${idx === 0 ? 'active' : ''}" data-index="${idx}">
            ${p.imagen
                ? `<img src="${escHtml(p.imagen)}" class="search-item-thumb" alt="">`
                : `<div class="search-item-thumb-placeholder"><i class="fas fa-image"></i></div>`
            }
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                    <span class="search-item-code badge bg-body-secondary text-body border">${escHtml(p.codigo)}</span>
                    <strong class="text-truncate" style="font-size:0.85rem;">${escHtml(p.descripcion)}</strong>
                    ${costoWarning}
                </div>
                <div class="small text-muted" style="font-size:0.75rem;">
                    ${p.marca ? `<span class="me-2"><i class="fas fa-tag text-muted"></i> ${escHtml(p.marca)}</span>` : ''}
                    <span>Lista: ${formatMoneyWithCurrency(p.precio, 'USD')}</span>
                </div>
            </div>
            <div class="text-end text-nowrap">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size:0.72rem;">
                    <i class="fas fa-box me-1"></i>${p.saldo}
                </span>
            </div>
        </div>`;
    });

    searchDropdown.innerHTML = html;
    searchDropdown.classList.remove('d-none');

    searchDropdown.querySelectorAll('.search-item').forEach(item => {
        item.addEventListener('click', function() {
            const idx = parseInt(this.dataset.index);
            selectProduct(searchResults[idx]);
        });
    });
}

function hideDropdown() {
    searchDropdown.classList.add('d-none');
    searchDropdown.innerHTML = '';
}

// =========================================================
// COMPARISON TABLE (All Results at a Glance)
// =========================================================
function renderComparisonTable(products) {
    if (!products || products.length === 0) {
        comparisonContainer.classList.add('d-none');
        return;
    }

    document.getElementById('comparisonCountBadge').textContent = `${products.length} productos`;
    let rowsHtml = '';

    products.forEach((p, idx) => {
        const calc = calculateMargin(p, 0);
        const marginColor = getMarginColor(calc.margenPorcentaje);

        rowsHtml += `
        <tr class="transition-hover">
            <td class="ps-3 py-2 text-center">
                ${p.imagen
                    ? `<img src="${escHtml(p.imagen)}" style="width:34px;height:34px;object-fit:contain;border-radius:4px;cursor:pointer;"
                            onclick="showImage('${escJs(p.imagen)}', '${escJs(p.descripcion)}')">`
                    : `<div style="width:34px;height:34px;background:var(--color-canvas);border-radius:4px;display:flex;align-items:center;justify-content:center;color:var(--color-ink-subtle);font-size:0.75rem;"><i class="fas fa-image"></i></div>`
                }
            </td>
            <td class="py-2">
                <span class="badge font-monospace bg-body-secondary text-body border px-1.5 py-0.5 me-1" style="font-size:0.72rem;">${escHtml(p.codigo)}</span>
                <span class="fw-semibold text-body" style="font-size:0.83rem;">${escHtml(p.descripcion)}</span>
            </td>
            <td class="py-2">
                <span class="badge bg-body-tertiary text-body border" style="font-size:0.72rem;">${escHtml(p.marca || '-')}</span>
            </td>
            <td class="py-2 text-center">
                <span class="badge ${p.saldo > 0 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-body-secondary text-muted'}" style="font-size:0.72rem;">
                    ${p.saldo}
                </span>
            </td>
            <td class="py-2 text-end font-monospace fw-semibold" style="font-size:0.82rem;">
                ${formatMoney(calc.precioVenta)}
            </td>
            <td class="py-2 text-end font-monospace" style="font-size:0.82rem;">
                ${calc.costo > 0 ? formatMoney(calc.costo) : '<span class="text-danger small">Sin costo</span>'}
            </td>
            <td class="py-2 text-end">
                <span class="badge ${calc.margenPorcentaje >= 20 ? 'bg-success-subtle text-success border border-success-subtle' : (calc.margenPorcentaje >= 10 ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle')} font-monospace" style="font-size:0.75rem;">
                    ${calc.margenPorcentaje.toFixed(1)}%
                </span>
            </td>
            <td class="pe-3 py-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold px-2 py-0.5" style="font-size:0.75rem;" onclick="selectProductByIndex(${idx})">
                    <i class="fas fa-calculator me-1"></i>Simular
                </button>
            </td>
        </tr>`;
    });

    comparisonTableBody.innerHTML = rowsHtml;
    comparisonContainer.classList.remove('d-none');
}

function selectProductByIndex(idx) {
    if (searchResults[idx]) {
        selectProduct(searchResults[idx]);
        // Smooth scroll to product detail
        productDetail.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// =========================================================
// SELECT PRODUCT & LOAD REORDERED FINANCIAL SIMULATOR
// =========================================================
function selectProduct(product) {
    selectedProduct = product;
    currentDiscount = 0;
    hideDropdown();

    searchInput.value = `${product.codigo} - ${product.descripcion}`;
    emptyState.classList.add('d-none');
    productDetail.classList.remove('d-none');

    renderDetail();
}

function renderDetail() {
    const p = selectedProduct;
    const calc = calculateMargin(p, currentDiscount);
    const hasCosto = (calc.costo > 0);

    productDetail.innerHTML = `
    <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 14px; overflow: hidden;">
        <!-- Header Strip -->
        <div class="card-header bg-body-tertiary border-bottom d-flex flex-wrap justify-content-between align-items-center py-2.5 px-3 px-md-4 gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge font-monospace bg-warning text-dark fw-bold px-2.5 py-1" style="font-size: 0.85rem;">
                    ${escHtml(p.codigo)}
                </span>
                <h5 class="fw-bold mb-0 text-body" style="font-size: 1.05rem;">
                    ${escHtml(p.descripcion)}
                </h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="${BASE_URL}/quotations/create.php" class="btn btn-sm btn-warning text-dark fw-bold px-3">
                    <i class="fas fa-file-invoice-dollar me-1"></i> Cotizar Producto
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()" title="Cerrar simulador">
                    <i class="fas fa-times me-1"></i> Cerrar
                </button>
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="row g-4">
                <!-- ==========================================
                     COLUMNA IZQUIERDA: PRODUCTO, MULTIMEDIA & STOCK
                     ========================================== -->
                <div class="col-12 col-lg-4">
                    <div class="p-3 rounded h-100 d-flex flex-column" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <!-- Foto Producto -->
                        <div class="text-center mb-3">
                            ${p.imagen
                                ? `<img src="${escHtml(p.imagen)}" class="img-fluid rounded shadow-sm"
                                        style="max-height: 180px; width: 100%; object-fit: contain; cursor: pointer; background: var(--color-panel);"
                                        alt="${escHtml(p.descripcion)}"
                                        onclick="showImage('${escJs(p.imagen)}', '${escJs(p.descripcion)}')">
                                   <div class="text-muted small mt-1" style="font-size: 0.72rem;"><i class="fas fa-search-plus me-1"></i>Clic para ampliar</div>`
                                : `<div class="rounded d-flex flex-column align-items-center justify-content-center text-muted"
                                        style="height: 160px; background: var(--color-panel); border: 1px dashed var(--color-rule);">
                                       <i class="fas fa-image fa-2x mb-1 opacity-50"></i>
                                       <span class="small">Sin imagen registrada</span>
                                   </div>`
                            }
                        </div>

                        <!-- Fichas & Especificaciones -->
                        <div class="d-flex flex-wrap gap-1.5 mb-3">
                            ${p.marca ? `<span class="badge bg-body-tertiary text-body border px-2 py-1"><i class="fas fa-tag text-muted me-1"></i>${escHtml(p.marca)}</span>` : ''}
                            <span class="badge ${p.saldo > 0 ? 'bg-primary-subtle text-primary border border-primary-subtle' : 'bg-body-secondary text-muted'} px-2 py-1">
                                <i class="fas fa-boxes me-1"></i>Stock: ${p.saldo} ${escHtml(p.unidad || 'UND')}
                            </span>
                            ${p.unidad ? `<span class="badge bg-body-tertiary text-body border px-2 py-1">${escHtml(p.unidad)}</span>` : ''}
                            ${p.fecultcos ? `<span class="badge bg-body-tertiary text-body border px-2 py-1"><i class="far fa-calendar-alt text-muted me-1"></i>Costo: ${formatDate(p.fecultcos)}</span>` : ''}
                            ${!hasCosto ? `<span class="badge bg-danger text-white px-2 py-1"><i class="fas fa-triangle-exclamation me-1"></i>SIN COSTO</span>` : ''}
                        </div>

                        <!-- Fichas Técnicas -->
                        ${p.fichas && p.fichas.length > 0 ? `
                            <div class="mt-auto pt-2 border-top">
                                <span class="small fw-semibold text-muted d-block mb-1.5" style="font-size:0.75rem;">Documentación Técnica:</span>
                                ${p.fichas.map(f => `
                                    <a href="${escHtml(f.url)}" target="_blank" class="btn btn-sm btn-outline-info w-100 text-truncate text-start mb-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-file-pdf me-1"></i> ${escHtml(f.nombre || 'Descargar Ficha Técnica')}
                                    </a>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- ==========================================
                     COLUMNA DERECHA: SIMULADOR DE MÁRGENES Y PRECIOS
                     ========================================== -->
                <div class="col-12 col-lg-8">
                    <!-- Control de Descuento Interactivo con Píldoras -->
                    <div class="p-3 rounded mb-3" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <label class="form-label small fw-bold text-body mb-0">
                                <i class="fas fa-percent text-warning me-1"></i>Simulador de Descuento Comercial
                            </label>
                            <!-- Input directo -->
                            <div class="input-group input-group-sm" style="width: 140px;">
                                <input type="number" class="form-control text-end fw-bold font-monospace"
                                       id="discountInput" min="0" max="100" step="0.5"
                                       value="${currentDiscount}" oninput="updateDiscount(this.value)">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <!-- Presets de descuento rápido -->
                        <div class="d-flex flex-wrap align-items-center gap-1.5 pt-1">
                            <span class="text-muted small me-1" style="font-size: 0.75rem;">Atajos:</span>
                            ${[0, 5, 10, 15, 20, 25, 30].map(pct => `
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary discount-pill ${currentDiscount === pct ? 'active' : ''}"
                                        onclick="applyDiscountPreset(${pct})">
                                    ${pct}%
                                </button>
                            `).join('')}
                        </div>
                    </div>

                    <!-- 4 Tarjetas Métricas Financieras -->
                    <div class="row g-2 mb-3">
                        <!-- 1. Precio Lista -->
                        <div class="col-6 col-md-3">
                            <div class="sim-card h-100">
                                <div class="text-muted small text-uppercase fw-semibold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                    Precio de Lista
                                </div>
                                <div class="sim-metric-val text-body" id="detPrecioVenta">
                                    ${formatMoney(calc.precioVenta)}
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Precio base de catálogo</div>
                            </div>
                        </div>

                        <!-- 2. Costo Real -->
                        <div class="col-6 col-md-3">
                            <div class="sim-card h-100">
                                <div class="text-muted small text-uppercase fw-semibold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                    Costo Reposición
                                </div>
                                <div class="sim-metric-val ${hasCosto ? 'text-body' : 'text-danger'}" id="detCosto">
                                    ${hasCosto ? formatMoney(calc.costo) : '0.00'}
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">
                                    ${hasCosto ? `Costo en ${currentCurrency}` : '<span class="text-danger">Sin costo asignado</span>'}
                                </div>
                            </div>
                        </div>

                        <!-- 3. Precio con Descuento -->
                        <div class="col-6 col-md-3">
                            <div class="sim-card h-100 ${currentDiscount > 0 ? 'border-primary' : ''}">
                                <div class="text-muted small text-uppercase fw-semibold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                    Precio Venta Neto
                                </div>
                                <div class="sim-metric-val ${currentDiscount > 0 ? 'text-primary' : 'text-body'}" id="detPrecioDesc">
                                    ${formatMoney(calc.precioConDescuento)}
                                </div>
                                <div class="small text-muted" id="detAhorroDesc" style="font-size: 0.72rem;">
                                    ${currentDiscount > 0 ? `-${formatMoney(calc.montoDescuento)} (${currentDiscount}%)` : 'Sin descuento'}
                                </div>
                            </div>
                        </div>

                        <!-- 4. Utilidad Neta (Dinero) -->
                        <div class="col-6 col-md-3">
                            <div class="sim-card h-100 ${calc.margenMonto < 0 ? 'border-danger' : (calc.margenMonto > 0 ? 'border-success' : '')}">
                                <div class="text-muted small text-uppercase fw-semibold mb-1" style="font-size: 0.68rem; letter-spacing: 0.05em;">
                                    Utilidad por Unidad
                                </div>
                                <div class="sim-metric-val ${calc.margenMonto < 0 ? 'text-danger' : 'text-success'}" id="detMarginMonto">
                                    ${formatMoney(calc.margenMonto)}
                                </div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Ganancia neta x unidad</div>
                            </div>
                        </div>
                    </div>

                    <!-- Medidor Visual de Rentabilidad (Margen %) -->
                    <div class="margin-meter-container">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="small fw-bold text-body text-uppercase" style="font-size: 0.78rem; letter-spacing: 0.05em;">
                                    Margen de Rentabilidad Comercial:
                                </span>
                                <span class="badge ${getMarginBadgeClass(calc.margenPorcentaje)} ms-2" id="detMarginHealthBadge" style="font-size: 0.75rem;">
                                    ${getMarginHealthText(calc.margenPorcentaje)}
                                </span>
                            </div>
                            <div class="fs-4 fw-bold font-monospace text-body" id="detMarginPercentText">
                                ${calc.margenPorcentaje.toFixed(1)}%
                            </div>
                        </div>

                        <!-- Barra Gráfica -->
                        <div class="margin-meter-track mb-2">
                            <div class="margin-meter-fill" id="detMarginBar"
                                 style="width: ${Math.min(Math.max(calc.margenPorcentaje, 0), 100)}%; background-color: ${getMarginColor(calc.margenPorcentaje)};">
                            </div>
                        </div>

                        <!-- Resumen Detallado de Fórmulas -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center text-muted small pt-1" style="font-size: 0.75rem;">
                            <span>Fórmula: <code>(Precio Neto - Costo) / Precio Neto</code></span>
                            <span id="detMarkupText">Markup sobre Costo: <strong>${calc.markupSobreCosto.toFixed(1)}%</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function applyDiscountPreset(pct) {
    document.getElementById('discountInput').value = pct;
    updateDiscount(pct);
}

function updateDiscount(value) {
    currentDiscount = Math.min(Math.max(parseFloat(value) || 0, 0), 100);
    if (!selectedProduct) return;

    const calc = calculateMargin(selectedProduct, currentDiscount);

    // Update Pills Active State
    document.querySelectorAll('.discount-pill').forEach(btn => {
        const p = parseFloat(btn.textContent);
        if (p === currentDiscount) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Update Elements
    const detPrecioVenta = document.getElementById('detPrecioVenta');
    if (detPrecioVenta) detPrecioVenta.textContent = formatMoney(calc.precioVenta);

    const detCosto = document.getElementById('detCosto');
    if (detCosto) {
        detCosto.textContent = (calc.costo > 0) ? formatMoney(calc.costo) : '0.00';
    }

    const detPrecioDesc = document.getElementById('detPrecioDesc');
    if (detPrecioDesc) {
        detPrecioDesc.textContent = formatMoney(calc.precioConDescuento);
        detPrecioDesc.className = `sim-metric-val ${currentDiscount > 0 ? 'text-primary' : 'text-body'}`;
    }

    const detAhorroDesc = document.getElementById('detAhorroDesc');
    if (detAhorroDesc) {
        detAhorroDesc.textContent = (currentDiscount > 0)
            ? `-${formatMoney(calc.montoDescuento)} (${currentDiscount}%)`
            : 'Sin descuento';
    }

    const detMarginMonto = document.getElementById('detMarginMonto');
    if (detMarginMonto) {
        detMarginMonto.textContent = formatMoney(calc.margenMonto);
        detMarginMonto.className = `sim-metric-val ${calc.margenMonto < 0 ? 'text-danger' : 'text-success'}`;
    }

    const bar = document.getElementById('detMarginBar');
    if (bar) {
        bar.style.width = Math.min(Math.max(calc.margenPorcentaje, 0), 100) + '%';
        bar.style.backgroundColor = getMarginColor(calc.margenPorcentaje);
    }

    const detMarginPercentText = document.getElementById('detMarginPercentText');
    if (detMarginPercentText) {
        detMarginPercentText.textContent = calc.margenPorcentaje.toFixed(1) + '%';
    }

    const healthBadge = document.getElementById('detMarginHealthBadge');
    if (healthBadge) {
        healthBadge.className = `badge ${getMarginBadgeClass(calc.margenPorcentaje)} ms-2`;
        healthBadge.textContent = getMarginHealthText(calc.margenPorcentaje);
    }

    const markupText = document.getElementById('detMarkupText');
    if (markupText) {
        markupText.innerHTML = `Markup sobre Costo: <strong>${calc.markupSobreCosto.toFixed(1)}%</strong>`;
    }
}

function clearSelection() {
    selectedProduct = null;
    currentDiscount = 0;
    productDetail.classList.add('d-none');
    productDetail.innerHTML = '';
    emptyState.classList.remove('d-none');
    searchInput.value = '';
    clearSearchBtn.classList.add('d-none');
    searchInput.focus();
}

function calculateMargin(product, discountPct) {
    const precio = product.precio || 0;
    let precioVenta, costo;

    if (currentCurrency === 'USD') {
        precioVenta = precio;
        costo = product.costo_dolares || 0;
    } else {
        precioVenta = precio * exchangeRate;
        costo = product.costo_soles || (product.costo_dolares * exchangeRate) || 0;
    }

    const montoDescuento = precioVenta * (discountPct / 100);
    const precioConDescuento = precioVenta - montoDescuento;
    const margenMonto = precioConDescuento - costo;
    const margenPorcentaje = (precioConDescuento > 0 && costo > 0)
        ? ((margenMonto / precioConDescuento) * 100)
        : (costo === 0 ? 100 : 0);
    const markupSobreCosto = (costo > 0)
        ? ((margenMonto / costo) * 100)
        : 0;

    return { precioVenta, costo, precioConDescuento, montoDescuento, margenMonto, margenPorcentaje, markupSobreCosto };
}

function getMarginColor(pct) {
    if (pct < 0) return '#EF4444';
    if (pct < 10) return '#F97316';
    if (pct < 20) return '#F59E0B';
    if (pct < 30) return '#3B82F6';
    return '#10B981';
}

function getMarginBadgeClass(pct) {
    if (pct < 0) return 'bg-danger text-white';
    if (pct < 10) return 'bg-danger-subtle text-danger border border-danger-subtle';
    if (pct < 20) return 'bg-warning-subtle text-warning border border-warning-subtle';
    if (pct < 30) return 'bg-primary-subtle text-primary border border-primary-subtle';
    return 'bg-success-subtle text-success border border-success-subtle';
}

function getMarginHealthText(pct) {
    if (pct < 0) return 'Pérdida Crítica';
    if (pct < 10) return 'Margen Crítico';
    if (pct < 20) return 'Margen Moderado';
    if (pct < 30) return 'Margen Saludable';
    return 'Margen Excelente';
}

function setCurrency(currency) {
    currentCurrency = currency;
    document.getElementById('btnUSD').className = `btn btn-sm ${currency === 'USD' ? 'btn-primary' : 'btn-outline-secondary'} fw-semibold px-2.5 py-1`;
    document.getElementById('btnPEN').className = `btn btn-sm ${currency === 'PEN' ? 'btn-primary' : 'btn-outline-secondary'} fw-semibold px-2.5 py-1`;

    if (selectedProduct) {
        updateDiscount(currentDiscount);
    }
    if (searchResults && searchResults.length > 0) {
        renderComparisonTable(searchResults);
    }
}

function formatMoney(amount) {
    const symbol = (currentCurrency === 'USD') ? '$' : 'S/';
    return `${symbol} ${Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatMoneyWithCurrency(amount, curr) {
    const symbol = (curr === 'USD') ? '$' : 'S/';
    return `${symbol} ${Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (e) {
        return dateStr;
    }
}

function showImage(url, title) {
    document.getElementById('imageModalImg').src = url;
    document.getElementById('imageModalTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function escJs(str) {
    if (!str) return '';
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

document.addEventListener('DOMContentLoaded', function() {
    searchInput.focus();
});
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
