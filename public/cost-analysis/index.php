<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!Permissions::canAccessCostAnalysis($auth)) {
    $_SESSION['error_message'] = 'No tienes acceso al módulo de análisis de costos';
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$user = $auth->getUser();
$pageTitle = 'Análisis de Costos y Márgenes';
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
        /* Search dropdown */
        .search-wrapper { position: relative; }
        .search-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1050;
            max-height: 400px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .search-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.15s;
        }
        .search-item:hover, .search-item.active {
            background: #e8f0fe;
        }
        .search-item:last-child { border-bottom: none; }
        .search-item-thumb {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 4px;
            background: #f8f9fa;
            flex-shrink: 0;
        }
        .search-item-thumb-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        .search-item-code {
            font-family: monospace;
            font-size: 0.8rem;
            color: #6c757d;
            background: #e9ecef;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .search-item-stock {
            font-size: 0.75rem;
            margin-left: auto;
            white-space: nowrap;
        }

        /* Detail card */
        .detail-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.2s ease;
        }
        .product-image-lg {
            width: 160px;
            height: 160px;
            object-fit: contain;
            border-radius: 8px;
            background: #f8f9fa;
            cursor: pointer;
        }
        .product-image-lg-placeholder {
            width: 160px;
            height: 160px;
            border-radius: 8px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
        .margin-bar {
            height: 28px;
            border-radius: 14px;
            overflow: hidden;
            background: #e9ecef;
        }
        .margin-bar-fill {
            height: 100%;
            border-radius: 14px;
            transition: width 0.4s ease, background-color 0.4s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 50px;
        }
        .margin-negative { background: linear-gradient(90deg, #dc3545, #e74c5f); }
        .margin-low { background: linear-gradient(90deg, #fd7e14, #ffc107); }
        .margin-medium { background: linear-gradient(90deg, #ffc107, #28a745); }
        .margin-good { background: linear-gradient(90deg, #28a745, #20c997); }
        .margin-excellent { background: linear-gradient(90deg, #20c997, #0dcaf0); }

        .currency-toggle .btn { padding: 2px 12px; font-size: 0.8rem; }
        .search-wrapper .spinner-border {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
        }
        .img-modal {
            max-width: 90vw;
            max-height: 80vh;
            object-fit: contain;
        }
        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
        .info-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .discount-input-lg {
            width: 120px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesi&oacute;n</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-calculator text-primary"></i> <?= $pageTitle ?>
            </h1>
            <div class="d-flex align-items-center gap-3">
                <div class="currency-toggle btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-primary active" id="btnUSD" onclick="setCurrency('USD')">
                        <i class="fas fa-dollar-sign"></i> USD
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnPEN" onclick="setCurrency('PEN')">
                        S/ PEN
                    </button>
                </div>
                <span class="badge bg-info" id="exchangeRateBadge">T.C.: --</span>
            </div>
        </div>

        <!-- Search -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="search-wrapper">
                    <input type="text" class="form-control form-control-lg" id="searchInput"
                           placeholder="Buscar por c&oacute;digo o descripci&oacute;n del producto..."
                           autocomplete="off">
                    <div class="spinner-border spinner-border-sm text-primary d-none" id="searchSpinner" role="status"></div>
                    <div class="search-dropdown d-none" id="searchDropdown"></div>
                </div>
            </div>
        </div>

        <!-- Product Detail (hidden initially) -->
        <div id="productDetail" class="d-none"></div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Ingrese un c&oacute;digo o descripci&oacute;n para buscar productos</h5>
            <p class="text-muted">Seleccione un producto de la lista para ver sus costos y m&aacute;rgenes</p>
        </div>
    </main>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Imagen del Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="imageModalImg" class="img-modal" alt="Producto">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    let currentCurrency = 'USD';
    let exchangeRate = 3.80;
    let searchResults = [];
    let selectedProduct = null;
    let currentDiscount = 0;
    let searchTimeout = null;

    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchSpinner = document.getElementById('searchSpinner');
    const productDetail = document.getElementById('productDetail');
    const emptyState = document.getElementById('emptyState');

    // ── Search with 2s debounce ──
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            hideDropdown();
            return;
        }

        searchTimeout = setTimeout(() => searchProducts(query), 2000);
    });

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-wrapper')) {
            hideDropdown();
        }
    });

    // Keyboard navigation in dropdown
    searchInput.addEventListener('keydown', function(e) {
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
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0) {
                items[activeIdx].click();
            }
        } else if (e.key === 'Escape') {
            hideDropdown();
        }
    });

    async function searchProducts(query) {
        searchSpinner.classList.remove('d-none');

        try {
            const resp = await fetch(`${BASE_URL}/api/cost_analysis_search.php?search=${encodeURIComponent(query)}&page=1`);
            const data = await resp.json();

            if (!data.success) {
                hideDropdown();
                return;
            }

            exchangeRate = data.exchangeRate;
            document.getElementById('exchangeRateBadge').textContent = `T.C.: ${exchangeRate.toFixed(3)}`;

            searchResults = data.products;
            renderDropdown(data.products, data.total);

        } catch (err) {
            hideDropdown();
        } finally {
            searchSpinner.classList.add('d-none');
        }
    }

    function renderDropdown(products, total) {
        if (products.length === 0) {
            searchDropdown.innerHTML = `
                <div class="p-3 text-center text-muted">
                    <i class="fas fa-box-open me-1"></i> No se encontraron productos
                </div>`;
            searchDropdown.classList.remove('d-none');
            return;
        }

        let html = '';
        if (total > products.length) {
            html += `<div class="px-3 py-2 text-muted small bg-light border-bottom">
                        Mostrando ${products.length} de ${total} resultados
                     </div>`;
        }

        products.forEach((p, idx) => {
            html += `
            <div class="search-item ${idx === 0 ? 'active' : ''}" data-index="${idx}">
                ${p.imagen
                    ? `<img src="${escHtml(p.imagen)}" class="search-item-thumb" alt="">`
                    : `<div class="search-item-thumb-placeholder"><i class="fas fa-image"></i></div>`
                }
                <div class="flex-grow-1">
                    <span class="search-item-code">${escHtml(p.codigo)}</span>
                    <span class="ms-1">${escHtml(p.descripcion)}</span>
                </div>
                <span class="search-item-stock text-muted">
                    <i class="fas fa-box"></i> ${p.saldo}
                </span>
            </div>`;
        });

        searchDropdown.innerHTML = html;
        searchDropdown.classList.remove('d-none');

        // Click on item
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

    // ── Select product and show detail ──
    function selectProduct(product) {
        selectedProduct = product;
        currentDiscount = 0;
        hideDropdown();

        // Update search input with selected product
        searchInput.value = `${product.codigo} - ${product.descripcion}`;

        emptyState.classList.add('d-none');
        productDetail.classList.remove('d-none');

        renderDetail();
    }

    function renderDetail() {
        const p = selectedProduct;
        const calc = calculateMargin(p, currentDiscount);

        productDetail.innerHTML = `
        <div class="card detail-card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <span class="badge bg-secondary me-2">${escHtml(p.codigo)}</span>
                    ${escHtml(p.descripcion)}
                </h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Image Column -->
                    <div class="col-auto text-center">
                        ${p.imagen
                            ? `<img src="${escHtml(p.imagen)}" class="product-image-lg mb-2" alt="${escHtml(p.descripcion)}"
                                    onclick="showImage('${escJs(p.imagen)}', '${escJs(p.descripcion)}')">`
                            : `<div class="product-image-lg-placeholder mb-2"><i class="fas fa-image fa-3x"></i></div>`
                        }
                        ${p.fichas && p.fichas.length > 0
                            ? `<div class="mt-2">${p.fichas.map(f =>
                                `<a href="${escHtml(f.url)}" target="_blank" class="btn btn-sm btn-outline-info d-block mb-1">
                                    <i class="fas fa-file-pdf"></i> ${escHtml(f.nombre || 'Ficha Técnica')}
                                </a>`).join('')}</div>`
                            : ''}
                    </div>

                    <!-- Info Column -->
                    <div class="col">
                        <!-- Product badges -->
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            ${p.marca ? `<span class="badge bg-secondary"><i class="fas fa-tag"></i> ${escHtml(p.marca)}</span>` : ''}
                            <span class="badge bg-primary"><i class="fas fa-box"></i> Stock: ${p.saldo}</span>
                            ${p.unidad ? `<span class="badge bg-info">${escHtml(p.unidad)}</span>` : ''}
                            ${p.fecultcos
                                ? `<span class="badge bg-warning text-dark"><i class="fas fa-calendar-alt"></i> Ingreso: ${formatDate(p.fecultcos)}</span>`
                                : ''}
                        </div>

                        <!-- Pricing grid -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="info-label">Precio de Venta</div>
                                <div class="info-value text-primary" id="detPrecioVenta">${formatMoney(calc.precioVenta)}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-label">Costo</div>
                                <div class="info-value" id="detCosto">
                                    <span class="badge bg-dark" style="font-size:0.95rem; padding:5px 12px;">${formatMoney(calc.costo)}</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-label">Descuento</div>
                                <div class="input-group discount-input-lg">
                                    <input type="number" class="form-control" id="discountInput"
                                           min="0" max="100" step="0.5" value="${currentDiscount}"
                                           onchange="updateDiscount(this.value)">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-label">Precio con Descuento</div>
                                <div id="detPrecioDesc">
                                    <span class="info-value ${currentDiscount > 0 ? 'text-danger' : ''}">${formatMoney(calc.precioConDescuento)}</span>
                                    ${currentDiscount > 0 ? `<span class="text-danger small d-block">-${formatMoney(calc.montoDescuento)}</span>` : ''}
                                </div>
                            </div>
                        </div>

                        <!-- Margin bar -->
                        <div class="info-label mb-1">Margen de Ganancia</div>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="margin-bar flex-grow-1">
                                <div class="margin-bar-fill ${getMarginClass(calc.margenPorcentaje)}" id="detMarginBar"
                                     style="width: ${Math.min(Math.max(calc.margenPorcentaje, 0), 100)}%">
                                    ${calc.margenPorcentaje.toFixed(1)}%
                                </div>
                            </div>
                            <span class="fw-bold ${calc.margenPorcentaje < 0 ? 'text-danger' : 'text-success'}" id="detMarginMonto" style="min-width:80px; text-align:right;">
                                ${formatMoney(calc.margenMonto)}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    function updateDiscount(value) {
        currentDiscount = Math.min(Math.max(parseFloat(value) || 0, 0), 100);
        if (!selectedProduct) return;

        const calc = calculateMargin(selectedProduct, currentDiscount);

        // Update precio venta (may change with currency)
        document.getElementById('detPrecioVenta').textContent = formatMoney(calc.precioVenta);

        // Update costo
        document.getElementById('detCosto').innerHTML = `<span class="badge bg-dark" style="font-size:0.95rem; padding:5px 12px;">${formatMoney(calc.costo)}</span>`;

        // Update precio con descuento
        const detPrecioDesc = document.getElementById('detPrecioDesc');
        detPrecioDesc.innerHTML = `
            <span class="info-value ${currentDiscount > 0 ? 'text-danger' : ''}">${formatMoney(calc.precioConDescuento)}</span>
            ${currentDiscount > 0 ? `<span class="text-danger small d-block">-${formatMoney(calc.montoDescuento)}</span>` : ''}`;

        // Update margin bar
        const bar = document.getElementById('detMarginBar');
        bar.style.width = Math.min(Math.max(calc.margenPorcentaje, 0), 100) + '%';
        bar.className = `margin-bar-fill ${getMarginClass(calc.margenPorcentaje)}`;
        bar.textContent = calc.margenPorcentaje.toFixed(1) + '%';

        // Update margin amount
        const margenEl = document.getElementById('detMarginMonto');
        margenEl.textContent = formatMoney(calc.margenMonto);
        margenEl.className = `fw-bold ${calc.margenPorcentaje < 0 ? 'text-danger' : 'text-success'}`;
    }

    function clearSelection() {
        selectedProduct = null;
        currentDiscount = 0;
        productDetail.classList.add('d-none');
        productDetail.innerHTML = '';
        emptyState.classList.remove('d-none');
        searchInput.value = '';
        searchInput.focus();
    }

    function calculateMargin(product, discountPct) {
        const precio = product.precio;
        let precioVenta, costo;

        if (currentCurrency === 'USD') {
            precioVenta = precio;
            costo = product.costo_dolares;
        } else {
            precioVenta = precio * exchangeRate;
            costo = product.costo_soles;
        }

        const montoDescuento = precioVenta * (discountPct / 100);
        const precioConDescuento = precioVenta - montoDescuento;
        const margenMonto = precioConDescuento - costo;
        const margenPorcentaje = costo > 0 ? ((margenMonto / costo) * 100) : 0;

        return { precioVenta, costo, precioConDescuento, montoDescuento, margenMonto, margenPorcentaje };
    }

    function getMarginClass(pct) {
        if (pct < 0) return 'margin-negative';
        if (pct < 10) return 'margin-low';
        if (pct < 25) return 'margin-medium';
        if (pct < 40) return 'margin-good';
        return 'margin-excellent';
    }

    function setCurrency(currency) {
        currentCurrency = currency;
        document.getElementById('btnUSD').className = `btn btn-sm ${currency === 'USD' ? 'btn-primary active' : 'btn-outline-primary'}`;
        document.getElementById('btnPEN').className = `btn btn-sm ${currency === 'PEN' ? 'btn-primary active' : 'btn-outline-primary'}`;
        if (selectedProduct) {
            updateDiscount(currentDiscount);
        }
    }

    function formatMoney(amount) {
        const symbol = currentCurrency === 'USD' ? '$' : 'S/';
        return `${symbol} ${amount.toFixed(2)}`;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
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

    searchInput.focus();
    </script>
</body>
</html>
