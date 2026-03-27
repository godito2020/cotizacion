<?php
/**
 * Vista de Productos y Stock
 * Carga instantánea - datos via AJAX desde api/products_search.php
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$userRepo  = new User();
$userRoles = $userRepo->getRoles($auth->getUserId());
if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

$user      = $auth->getUser();
$stockRepo = new Stock();

// Almacenes desde DB local (rápido)
$allWarehouses = $stockRepo->getWarehouses();

$search          = trim($_GET['search'] ?? '');
$warehouseFilter = $_GET['warehouse'] ?? '';

$meses = [
    1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
];
$mesActual = $meses[(int)date('n')];

$pageTitle = 'Productos y Stock';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; cursor: zoom-in; }
        .img-placeholder { width: 40px; height: 40px; background: #e9ecef; border-radius: 4px;
                           display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: .85rem; }
        .stock-positive { color: #28a745; font-weight: 600; }
        .stock-zero     { color: #dc3545; }
        .table th { white-space: nowrap; }
        .table-responsive { max-height: 72vh; overflow-y: auto; }
        .loading-overlay { text-align: center; padding: 4rem 1rem; }
        .loading-overlay .spinner-border { width: 3rem; height: 3rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard_simple.php">
            <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
        </a>
        <div class="navbar-nav ms-auto">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                    <?php if ($auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">Panel Admin</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><i class="fas fa-boxes me-2"></i><?= $pageTitle ?></h1>
        <span class="badge bg-info" id="resultCount">
            <?= ucfirst($mesActual) ?>
        </span>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form id="searchForm" class="row g-2 align-items-center" onsubmit="doSearch(); return false;">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" id="searchFilter"
                           placeholder="Código o descripción..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="warehouse" id="warehouseFilter" onchange="doSearch()">
                        <option value="">Todos los almacenes</option>
                        <?php foreach ($allWarehouses as $w): ?>
                            <option value="<?= $w['numero_almacen'] ?>"
                                <?= $warehouseFilter == $w['numero_almacen'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($w['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSearch">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
                <div class="col text-end text-muted small" id="pageInfo"></div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body p-0" id="tableContainer">
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner-border text-primary mb-3"></div>
                <h5 class="text-muted">Cargando productos...</h5>
                <p class="text-muted small">Consultando base de datos COBOL</p>
            </div>
        </div>
        <div class="card-footer py-2 d-none" id="paginationContainer">
            <nav><ul class="pagination pagination-sm justify-content-center mb-0" id="pagination"></ul></nav>
        </div>
    </div>
</main>

<!-- Modal imagen ampliada -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="imageModalTitle"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-2">
                <img id="imageModalSrc" src="" alt="" class="img-fluid" style="max-height:520px">
            </div>
        </div>
    </div>
</div>

<!-- Modal fichas técnicas -->
<div class="modal fade" id="fichasModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <div>
                    <h6 class="modal-title mb-0"><i class="fas fa-file-alt me-1 text-danger"></i>Fichas Técnicas</h6>
                    <small class="text-muted" id="fichasModalDesc"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="fichasModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
let currentPage = <?= max(1, (int)($_GET['page'] ?? 1)) ?>;
let searchAbort = null;

function doSearch(page) {
    if (page) currentPage = page;
    else currentPage = 1;

    const search = document.getElementById('searchFilter').value.trim();
    const warehouse = document.getElementById('warehouseFilter').value;

    // Actualizar URL sin recargar
    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (warehouse) params.set('warehouse', warehouse);
    if (currentPage > 1) params.set('page', currentPage);
    history.replaceState(null, '', '?' + params.toString());

    // Cancelar búsqueda anterior
    if (searchAbort) searchAbort.abort();
    searchAbort = new AbortController();

    // Mostrar loading
    const container = document.getElementById('tableContainer');
    container.innerHTML = `<div class="loading-overlay">
        <div class="spinner-border text-primary mb-3"></div>
        <h5 class="text-muted">Buscando productos...</h5>
    </div>`;
    document.getElementById('paginationContainer').classList.add('d-none');
    document.getElementById('btnSearch').disabled = true;

    const url = `${BASE_URL}/api/products_search.php?search=${encodeURIComponent(search)}&warehouse=${encodeURIComponent(warehouse)}&page=${currentPage}`;

    fetch(url, { signal: searchAbort.signal })
        .then(r => r.json())
        .then(data => {
            document.getElementById('btnSearch').disabled = false;
            if (!data.success) {
                container.innerHTML = `<div class="text-center py-5"><p class="text-danger">${data.message || 'Error al buscar'}</p></div>`;
                return;
            }
            renderResults(data);
        })
        .catch(err => {
            if (err.name === 'AbortError') return;
            document.getElementById('btnSearch').disabled = false;
            container.innerHTML = `<div class="text-center py-5"><p class="text-danger">Error de conexión</p></div>`;
        });
}

function renderResults(data) {
    const container = document.getElementById('tableContainer');
    const products = data.products;
    const warehouses = data.warehouses || [];
    const warehouseFilter = document.getElementById('warehouseFilter').value;

    document.getElementById('resultCount').textContent =
        `${data.total.toLocaleString()} productos · ${data.mes} · ${data.time}s`;
    document.getElementById('pageInfo').textContent =
        `Página ${data.page} de ${data.totalPages} · mostrando ${products.length} de ${data.total.toLocaleString()}`;

    if (products.length === 0) {
        container.innerHTML = `<div class="text-center py-5">
            <i class="fas fa-boxes fa-3x text-muted mb-3 d-block"></i>
            <h5 class="text-muted">No se encontraron productos con stock</h5>
        </div>`;
        return;
    }

    // Construir tabla
    let warehouseCols = '';
    if (!warehouseFilter && warehouses.length > 0) {
        warehouseCols = warehouses.map(w =>
            `<th class="text-center" style="min-width:70px">${esc(w.nombre)}</th>`
        ).join('');
    } else if (warehouseFilter) {
        warehouseCols = '<th class="text-center">Stock</th>';
    }

    let html = `<div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead class="table-light sticky-top"><tr>
                <th style="width:46px">IMG</th>
                <th style="width:110px">Código</th>
                <th>Descripción</th>
                <th class="text-end" style="width:95px">Precio USD</th>
                <th class="text-center" style="width:65px">Total</th>
                ${warehouseCols}
                <th class="text-center" style="width:60px">Docs</th>
            </tr></thead><tbody>`;

    products.forEach(p => {
        const imgCell = p.imagen
            ? `<img src="${esc(p.imagen)}" alt="" class="product-img" onclick="showImageModal('${esc(p.imagen)}', '${esc(p.descripcion)}')">`
            : '<div class="img-placeholder"><i class="fas fa-image"></i></div>';

        const totalClass = p.total_stock > 0 ? 'text-success' : 'text-danger';

        let stockCells = '';
        if (!warehouseFilter && warehouses.length > 0) {
            stockCells = warehouses.map(w => {
                const s = p.warehouses[w.numero_almacen] || 0;
                const cls = s > 0 ? 'stock-positive' : 'stock-zero';
                return `<td class="text-center ${cls}">${s > 0 ? Number(s).toLocaleString() : '-'}</td>`;
            }).join('');
        } else if (warehouseFilter) {
            stockCells = `<td class="text-center ${totalClass}">${Number(p.total_stock).toLocaleString()}</td>`;
        }

        const fichaBtn = p.fichas > 0
            ? `<button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="showFichas('${esc(p.codigo)}', '${esc(p.descripcion)}')" title="${p.fichas} ficha(s)"><i class="fas fa-file-pdf"></i></button>`
            : '<span class="text-muted"><i class="fas fa-file-pdf opacity-25"></i></span>';

        html += `<tr>
            <td>${imgCell}</td>
            <td><code class="small">${esc(p.codigo)}</code></td>
            <td>${esc(p.descripcion)}</td>
            <td class="text-end">$ ${Number(p.precio).toFixed(2)}</td>
            <td class="text-center"><strong class="${totalClass}">${Number(p.total_stock).toLocaleString()}</strong></td>
            ${stockCells}
            <td class="text-center">${fichaBtn}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;

    // Paginación
    renderPagination(data.page, data.totalPages);
}

function renderPagination(page, totalPages) {
    const pagContainer = document.getElementById('paginationContainer');
    if (totalPages <= 1) { pagContainer.classList.add('d-none'); return; }
    pagContainer.classList.remove('d-none');

    let html = '';
    if (page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="doSearch(1);return false">«</a></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" onclick="doSearch(${page-1});return false">‹</a></li>`;
    }
    for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
        html += `<li class="page-item ${i === page ? 'active' : ''}"><a class="page-link" href="#" onclick="doSearch(${i});return false">${i}</a></li>`;
    }
    if (page < totalPages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="doSearch(${page+1});return false">›</a></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" onclick="doSearch(${totalPages});return false">»</a></li>`;
    }
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
    document.getElementById('imageModalSrc').src = url;
    document.getElementById('imageModalTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('imageModal')).show();
}

function showFichas(codigo, descripcion) {
    document.getElementById('fichasModalDesc').textContent = codigo + ' — ' + descripcion;
    document.getElementById('fichasModalBody').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    new bootstrap.Modal(document.getElementById('fichasModal')).show();

    fetch(BASE_URL + '/api/product_fichas.php?codigo=' + encodeURIComponent(codigo))
        .then(r => r.json())
        .then(data => {
            if (!data.success || data.fichas.length === 0) {
                document.getElementById('fichasModalBody').innerHTML =
                    '<p class="text-muted text-center py-3">No hay fichas técnicas registradas.</p>';
                return;
            }
            let html = '';
            data.fichas.forEach(f => {
                const ext  = f.ficha_url.split('.').pop().toLowerCase();
                const isPdf = ext === 'pdf';
                const icon  = isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                const name  = (f.nombre_archivo || 'Ficha ' + f.id) + '.' + ext;
                if (isPdf) {
                    html += `<div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span><i class="fas ${icon} me-1"></i><strong>${name}</strong>
                                <small class="text-muted ms-2">${f.created_at || ''}</small></span>
                            <a href="${f.ficha_url}" target="_blank" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-external-link-alt me-1"></i>Abrir PDF</a>
                        </div>
                        <iframe src="${f.ficha_url}" class="w-100 border rounded" style="height:480px"></iframe>
                    </div>`;
                } else {
                    html += `<div class="mb-3 text-center">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span><i class="fas ${icon} me-1"></i><strong>${name}</strong>
                                <small class="text-muted ms-2">${f.created_at || ''}</small></span>
                            <a href="${f.ficha_url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt me-1"></i>Abrir</a>
                        </div>
                        <img src="${f.ficha_url}" class="img-fluid border rounded" style="max-height:520px">
                    </div>`;
                }
            });
            document.getElementById('fichasModalBody').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('fichasModalBody').innerHTML =
                '<p class="text-danger text-center py-3">Error al cargar fichas.</p>';
        });
}

// Cargar al iniciar
document.addEventListener('DOMContentLoaded', () => doSearch(<?= max(1, (int)($_GET['page'] ?? 1)) ?>));
</script>
</body>
</html>
