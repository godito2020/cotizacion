<?php
/**
 * Gestión de Productos - Admin
 * Lee productos desde vista_productos (COBOL)
 * Permite gestionar imágenes en BD local
 * Optimizado para búsqueda rápida
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
    $_SESSION['error_message'] = "No tiene permisos para gestionar productos.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$loggedInUser = $auth->getUser();
$stockRepo = new Stock();

// Parámetros de búsqueda
$search = trim($_GET['search'] ?? '');
$warehouseFilter = $_GET['warehouse'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Obtener TODOS los almacenes configurados (para el filtro)
$allWarehouses = $stockRepo->getWarehouses();
$warehouseNames = [];
foreach ($allWarehouses as $w) {
    $warehouseNames[$w['numero_almacen']] = $w['nombre'];
}

// Mes actual
$meses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',
    4 => 'abril', 5 => 'mayo', 6 => 'junio',
    7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
    10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$mesActual = $meses[(int)date('n')];

// Búsqueda optimizada
$dbCobol = getCobolConnection();

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $words = preg_split('/\s+/', trim($search));
    $words = array_values(array_filter($words, fn($w) => strlen($w) >= 2));

    $i = 0;
    foreach ($words as $word) {
        $paramCodigo = ":wc{$i}";
        $paramDesc = ":wd{$i}";
        $whereConditions[] = "(LOWER(p.codigo) LIKE LOWER({$paramCodigo}) OR LOWER(p.descripcion) LIKE LOWER({$paramDesc}))";
        $searchTerm = '%' . $word . '%';
        $params[$paramCodigo] = $searchTerm;
        $params[$paramDesc] = $searchTerm;
        $i++;
    }
}

if (!empty($warehouseFilter)) {
    $whereConditions[] = "s.almacen = :warehouse";
    $params[':warehouse'] = $warehouseFilter;
}

$whereConditions[] = "s.{$mesActual} > 0";
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "SELECT
            p.codigo,
            p.descripcion,
            p.precio,
            s.almacen as numero_almacen,
            s.{$mesActual} as stock_actual
        FROM vista_productos p
        INNER JOIN vista_almacenes_anual s ON p.codigo = s.codigo
        {$whereClause}
        ORDER BY p.descripcion ASC, s.almacen ASC
        LIMIT 2000";

$stmt = $dbCobol->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por producto
$groupedProducts = [];
$warehousesWithStock = [];

foreach ($results as $row) {
    $codigo = $row['codigo'];
    $numAlmacen = $row['numero_almacen'];
    $stockActual = (float)$row['stock_actual'];
    $nombreAlmacen = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;

    if (!isset($warehousesWithStock[$numAlmacen])) {
        $warehousesWithStock[$numAlmacen] = [
            'numero_almacen' => $numAlmacen,
            'nombre' => $nombreAlmacen
        ];
    }

    if (!isset($groupedProducts[$codigo])) {
        $groupedProducts[$codigo] = [
            'codigo' => $codigo,
            'descripcion' => $row['descripcion'],
            'precio' => (float)$row['precio'],
            'total_stock' => 0,
            'warehouses' => [] // Stock por almacén
        ];
    }
    $groupedProducts[$codigo]['total_stock'] += $stockActual;
    $groupedProducts[$codigo]['warehouses'][$nombreAlmacen] = $stockActual;
}

// Si hay filtro de almacén, el total_stock ya es solo de ese almacén
// Si NO hay filtro, total_stock es la suma de todos los almacenes

uasort($warehousesWithStock, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

// Paginación
$totalProducts = count($groupedProducts);
$totalPages = ceil($totalProducts / $perPage);
$offset = ($page - 1) * $perPage;
$products = array_slice($groupedProducts, $offset, $perPage, true);

// Obtener imágenes de los productos mostrados
$dbLocal = getDBConnection();
$productCodes = array_keys($products);
$productImages = [];

if (!empty($productCodes)) {
    $placeholders = str_repeat('?,', count($productCodes) - 1) . '?';
    $stmtImg = $dbLocal->prepare("SELECT codigo_producto, imagen_url FROM imagenes WHERE codigo_producto IN ($placeholders) AND imagen_principal = 1");
    $stmtImg->execute($productCodes);
    while ($row = $stmtImg->fetch(PDO::FETCH_ASSOC)) {
        $productImages[$row['codigo_producto']] = $row['imagen_url'];
    }
}

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .product-image { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
        .product-image-placeholder { width: 50px; height: 50px; background-color: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #6c757d; }
        .stock-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
        .table-responsive { max-height: 65vh; overflow-y: auto; }
        .btn-action { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/admin/index.php">
                <i class="fas fa-cogs me-2"></i>Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/index.php"><i class="fas fa-home me-1"></i>Inicio</a></li>
                    <li class="nav-item"><a class="nav-link active" href="<?= BASE_URL ?>/admin/products.php"><i class="fas fa-boxes me-1"></i>Productos</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/warehouses.php"><i class="fas fa-warehouse me-1"></i>Almacenes</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/dashboard_simple.php"><i class="fas fa-chart-line me-1"></i>Dashboard</a></li>
                </ul>
                <div class="navbar-text text-white">
                    <i class="fas fa-user me-1"></i><?= htmlspecialchars($loggedInUser['username'] ?? 'Usuario') ?>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm ms-2"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row mb-3">
            <div class="col">
                <h2><i class="fas fa-boxes me-2"></i>Gestión de Productos</h2>
                <p class="text-muted mb-0">Total: <strong><?= number_format($totalProducts) ?></strong> productos con stock</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-file-excel me-1"></i>Importar Imágenes
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Buscar por código o descripción..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="warehouse" onchange="this.form.submit()">
                            <option value="">Todos los almacenes</option>
                            <?php foreach ($allWarehouses as $w): ?>
                                <option value="<?= $w['numero_almacen'] ?>" <?= $warehouseFilter == $w['numero_almacen'] ? 'selected' : '' ?>><?= htmlspecialchars($w['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Buscar</button>
                        <?php if (!empty($search) || !empty($warehouseFilter)): ?>
                            <a href="products.php" class="btn btn-outline-secondary">Limpiar</a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 text-end text-muted"><?= count($products) ?> de <?= $totalProducts ?></div>
                </form>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 60px;">Imagen</th>
                                <th style="width: 120px;">Código</th>
                                <th>Descripción</th>
                                <th style="width: 100px;" class="text-end">Stock</th>
                                <th style="width: 100px;" class="text-end">Precio USD</th>
                                <th style="width: 120px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-3x mb-3 d-block"></i>No se encontraron productos</td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $codigo => $product): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($productImages[$codigo])): ?>
                                                <img src="<?= htmlspecialchars($productImages[$codigo]) ?>" class="product-image">
                                            <?php else: ?>
                                                <div class="product-image-placeholder"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($codigo) ?></code></td>
                                        <td><?= htmlspecialchars($product['descripcion']) ?></td>
                                        <td class="text-end">
                                            <?php $badgeClass = $product['total_stock'] > 10 ? 'bg-success' : ($product['total_stock'] > 0 ? 'bg-warning' : 'bg-danger'); ?>
                                            <span class="badge <?= $badgeClass ?> stock-badge"><?= number_format($product['total_stock'], 0) ?></span>
                                        </td>
                                        <td class="text-end">$ <?= number_format($product['precio'], 2) ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-action" onclick="openImageModal('<?= htmlspecialchars($codigo) ?>', '<?= htmlspecialchars(addslashes($product['descripcion'])) ?>')" title="Gestionar imágenes"><i class="fas fa-camera"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-info btn-action" onclick="viewStock('<?= htmlspecialchars($codigo) ?>')" title="Ver stock"><i class="fas fa-warehouse"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav><ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&warehouse=<?= urlencode($warehouseFilter) ?>"><i class="fas fa-chevron-left"></i></a></li><?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&warehouse=<?= urlencode($warehouseFilter) ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&warehouse=<?= urlencode($warehouseFilter) ?>"><i class="fas fa-chevron-right"></i></a></li><?php endif; ?>
                    </ul></nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Imágenes -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>Imágenes del Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Código:</strong> <span id="modalProductCode"></span></p>
                    <p><strong>Descripción:</strong> <span id="modalProductDesc"></span></p>

                    <!-- Tabs principales: Imágenes / Ficha Técnica -->
                    <ul class="nav nav-tabs mb-3" id="mainModalTabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabImages"><i class="fas fa-images me-1"></i>Imágenes</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabFicha"><i class="fas fa-file-alt me-1"></i>Ficha Técnica</a></li>
                    </ul>

                    <div class="tab-content">
                        <!-- ===== TAB IMÁGENES ===== -->
                        <div class="tab-pane fade show active" id="tabImages">
                            <div id="currentImages" class="mb-3"></div>
                            <hr>
                            <h6><i class="fas fa-upload me-1"></i>Agregar imagen</h6>
                            <ul class="nav nav-tabs mb-3" id="uploadTabs">
                                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabFile">Subir archivo</a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabUrl">Desde URL</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="tabFile">
                                    <form id="uploadImageForm" enctype="multipart/form-data">
                                        <input type="hidden" name="codigo" id="imageProductCode">
                                        <div class="mb-3">
                                            <input type="file" class="form-control" name="imagen" id="imagenFile" accept="image/*">
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="principal" id="imagenPrincipal">
                                            <label class="form-check-label" for="imagenPrincipal">Imagen principal</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Subir</button>
                                    </form>
                                </div>
                                <div class="tab-pane fade" id="tabUrl">
                                    <form id="uploadUrlForm">
                                        <input type="hidden" name="codigo" id="imageProductCodeUrl">
                                        <div class="mb-3">
                                            <input type="url" class="form-control" name="imagen_url" id="imagenUrl" placeholder="https://ejemplo.com/imagen.jpg" required>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="principal" id="imagenPrincipalUrl">
                                            <label class="form-check-label" for="imagenPrincipalUrl">Imagen principal</label>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-link me-1"></i>Guardar URL</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ===== TAB FICHA TÉCNICA ===== -->
                        <div class="tab-pane fade" id="tabFicha">
                            <div id="currentFichas" class="mb-3"></div>
                            <hr>
                            <h6><i class="fas fa-upload me-1"></i>Subir Ficha Técnica</h6>
                            <form id="uploadFichaForm" enctype="multipart/form-data">
                                <input type="hidden" name="codigo" id="fichaProductCode">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="ficha" id="fichaFile" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                                    <small class="text-muted">Formatos: JPG, PNG, PDF. Máximo 10MB.</small>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i>Subir Ficha</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Stock -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-warehouse me-2"></i>Stock por Almacén</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"><div id="stockContent"></div></div>
            </div>
        </div>
    </div>

    <!-- Modal Importar Excel -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-excel me-2"></i>Importar desde Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="importTabs">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#importImages"><i class="fas fa-images me-1"></i>Imágenes</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#importFichas"><i class="fas fa-file-alt me-1"></i>Fichas Técnicas</a></li>
                    </ul>
                    <div class="tab-content">
                        <!-- Tab Importar Imágenes -->
                        <div class="tab-pane fade show active" id="importImages">
                            <div class="alert alert-info">
                                <strong>Formato del Excel:</strong><br>
                                El archivo debe tener 2 columnas:<br>
                                <code>codigo</code> | <code>imagen_url</code>
                            </div>
                            <form id="importExcelForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Archivo Excel o CSV</label>
                                    <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv" required>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="set_principal" id="setPrincipal" checked>
                                    <label class="form-check-label" for="setPrincipal">Establecer como imagen principal</label>
                                </div>
                                <div id="importProgress" class="d-none mb-3">
                                    <div class="progress" style="height: 25px;">
                                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 0%">0%</div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <small id="progressText" class="text-muted">Iniciando...</small>
                                        <small id="progressStats" class="text-muted"></small>
                                    </div>
                                </div>
                                <div id="importResult" class="d-none"></div>
                                <button type="submit" class="btn btn-success" id="btnImport"><i class="fas fa-upload me-1"></i>Importar</button>
                                <a href="<?= BASE_URL ?>/api/download_image_template.php" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Descargar plantilla</a>
                            </form>
                        </div>
                        <!-- Tab Importar Fichas Técnicas -->
                        <div class="tab-pane fade" id="importFichas">
                            <div class="alert alert-info">
                                <strong>Formato del Excel:</strong><br>
                                El archivo debe tener 2 columnas:<br>
                                <code>codigo</code> | <code>ficha_url</code>
                            </div>
                            <form id="importFichasForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Archivo Excel o CSV</label>
                                    <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv" required>
                                </div>
                                <div id="importFichasProgress" class="d-none mb-3">
                                    <div class="progress" style="height: 25px;">
                                        <div id="progressBarFichas" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 0%">0%</div>
                                    </div>
                                    <div class="mt-2"><small id="progressTextFichas" class="text-muted">Iniciando...</small></div>
                                </div>
                                <div id="importFichasResult" class="d-none"></div>
                                <button type="submit" class="btn btn-success" id="btnImportFichas"><i class="fas fa-upload me-1"></i>Importar Fichas</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        const stockModal = new bootstrap.Modal(document.getElementById('stockModal'));

        function openImageModal(codigo, descripcion) {
            document.getElementById('modalProductCode').textContent = codigo;
            document.getElementById('modalProductDesc').textContent = descripcion;
            document.getElementById('imageProductCode').value = codigo;
            document.getElementById('imageProductCodeUrl').value = codigo;
            document.getElementById('fichaProductCode').value = codigo;
            loadProductImages(codigo);
            loadProductFichas(codigo);
            imageModal.show();
        }

        function loadProductImages(codigo) {
            const container = document.getElementById('currentImages');
            container.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm"></div></div>';
            fetch('<?= BASE_URL ?>/api/product_images.php?codigo=' + encodeURIComponent(codigo))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.images.length > 0) {
                        let html = '<div class="row g-2">';
                        data.images.forEach(img => {
                            html += `<div class="col-3"><div class="position-relative">
                                <img src="${img.imagen_url}" class="img-fluid rounded" style="height: 80px; object-fit: cover; width: 100%;">
                                ${img.imagen_principal == 1 ? '<span class="badge bg-primary position-absolute top-0 start-0" style="font-size:0.6rem">Principal</span>' : ''}
                                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" style="padding:0 4px" onclick="deleteImage(${img.id}, '${codigo}')"><i class="fas fa-times"></i></button>
                            </div></div>`;
                        });
                        container.innerHTML = html + '</div>';
                    } else {
                        container.innerHTML = '<p class="text-muted">No hay imágenes registradas</p>';
                    }
                }).catch(() => container.innerHTML = '<p class="text-danger">Error al cargar</p>');
        }

        function viewStock(codigo) {
            document.getElementById('stockContent').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
            stockModal.show();
            fetch('<?= BASE_URL ?>/api/product_stock.php?codigo=' + encodeURIComponent(codigo))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        let html = `<h6>${data.producto.descripcion}</h6><p><code>${data.producto.codigo}</code></p>
                            <table class="table table-sm"><thead><tr><th>Almacén</th><th class="text-end">Stock</th></tr></thead><tbody>`;
                        if (data.stock.length > 0) {
                            data.stock.forEach(s => html += `<tr><td>${s.nombre_almacen || 'Almacén ' + s.numero_almacen}</td><td class="text-end">${parseFloat(s.stock_actual).toFixed(0)}</td></tr>`);
                        } else {
                            html += '<tr><td colspan="2" class="text-center text-muted">Sin stock</td></tr>';
                        }
                        document.getElementById('stockContent').innerHTML = html + '</tbody></table>';
                    }
                });
        }

        document.getElementById('uploadImageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('<?= BASE_URL ?>/api/upload_product_image.php', { method: 'POST', body: new FormData(this) })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        loadProductImages(document.getElementById('imageProductCode').value);
                        document.getElementById('imagenFile').value = '';
                    } else alert('Error: ' + data.message);
                });
        });

        document.getElementById('uploadUrlForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('<?= BASE_URL ?>/api/save_image_url.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        loadProductImages(document.getElementById('imageProductCodeUrl').value);
                        document.getElementById('imagenUrl').value = '';
                    } else alert('Error: ' + data.message);
                });
        });

        document.getElementById('importExcelForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Mostrar barra de progreso animada
            document.getElementById('importProgress').classList.remove('d-none');
            document.getElementById('importResult').classList.add('d-none');
            document.getElementById('btnImport').disabled = true;

            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            progressBar.style.width = '100%';
            progressBar.textContent = 'Procesando...';
            progressText.textContent = 'Importando imágenes, por favor espere...';

            // Enviar archivo
            fetch('<?= BASE_URL ?>/api/import_images_excel.php', { method: 'POST', body: new FormData(this) })
                .then(r => {
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}: ${r.statusText}`);
                    }
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
                        result.innerHTML = `<div class="alert alert-success"><strong>✓ Importación completada</strong><br>
                            <i class="fas fa-file-alt"></i> Procesados: ${data.processed}<br>
                            <i class="fas fa-check text-success"></i> Exitosos: ${data.success_count}<br>
                            <i class="fas fa-times text-danger"></i> Errores: ${data.error_count}</div>`;
                        if (data.errors && data.errors.length > 0) {
                            const maxErrors = 20;
                            const errorList = data.errors.slice(0, maxErrors).map(e => `<li>${e}</li>`).join('');
                            const moreErrors = data.errors.length > maxErrors ? `<li class="text-muted">... y ${data.errors.length - maxErrors} errores más</li>` : '';
                            result.innerHTML += `<div class="alert alert-warning"><strong>Errores encontrados:</strong><ul style="max-height: 200px; overflow-y: auto;">${errorList}${moreErrors}</ul></div>`;
                        }
                        if (data.success_count > 0) {
                            result.innerHTML += '<div class="text-center mt-2"><button class="btn btn-primary btn-sm" onclick="location.reload()"><i class="fas fa-sync me-1"></i>Actualizar página</button></div>';
                        }
                    } else {
                        result.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error: ${data.message}</div>`;
                    }
                })
                .catch(err => {
                    document.getElementById('importProgress').classList.add('d-none');
                    document.getElementById('btnImport').disabled = false;
                    document.getElementById('importResult').classList.remove('d-none');
                    document.getElementById('importResult').innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error: ${err.message || 'Error de conexión'}</div>`;
                    console.error('Import error:', err);
                });
        });

        function deleteImage(id, codigo) {
            if (!confirm('¿Eliminar esta imagen?')) return;
            fetch('<?= BASE_URL ?>/api/delete_product_image.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id: id})
            }).then(r => r.json()).then(data => { if (data.success) loadProductImages(codigo); });
        }

        // ===== FICHAS TÉCNICAS =====
        function loadProductFichas(codigo) {
            const container = document.getElementById('currentFichas');
            container.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm"></div></div>';
            fetch('<?= BASE_URL ?>/api/product_fichas.php?codigo=' + encodeURIComponent(codigo))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.fichas.length > 0) {
                        let html = '<div class="list-group">';
                        data.fichas.forEach(f => {
                            const ext = f.ficha_url.split('.').pop().toLowerCase();
                            const icon = ext === 'pdf' ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                            const nombre = f.nombre_archivo || 'Ficha ' + f.id;
                            html += `<div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas ${icon} me-2"></i>
                                    <a href="${f.ficha_url}" target="_blank" class="text-decoration-none">${nombre}.${ext}</a>
                                    <small class="text-muted ms-2">${f.created_at}</small>
                                </div>
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteFicha(${f.id}, '${codigo}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`;
                        });
                        container.innerHTML = html + '</div>';
                    } else {
                        container.innerHTML = '<p class="text-muted">No hay fichas técnicas registradas</p>';
                    }
                }).catch(() => container.innerHTML = '<p class="text-danger">Error al cargar fichas</p>');
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
                    btn.innerHTML = '<i class="fas fa-upload me-1"></i>Subir Ficha';
                    if (data.success) {
                        loadProductFichas(document.getElementById('fichaProductCode').value);
                        document.getElementById('fichaFile').value = '';
                    } else {
                        alert('Error: ' + data.message);
                    }
                }).catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload me-1"></i>Subir Ficha';
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
                        result.innerHTML = `<div class="alert alert-success"><strong>✓ Importación completada</strong><br>
                            Procesados: ${data.processed} &nbsp;|&nbsp; Nuevas: ${data.success_count} &nbsp;|&nbsp; Errores: ${data.error_count}</div>`;
                        if (data.errors && data.errors.length > 0) {
                            result.innerHTML += `<div class="alert alert-warning"><strong>Errores:</strong><ul>${data.errors.map(e=>'<li>'+e+'</li>').join('')}</ul></div>`;
                        }
                    } else {
                        result.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-1"></i>${data.message}</div>`;
                    }
                }).catch(err => {
                    document.getElementById('importFichasProgress').classList.add('d-none');
                    document.getElementById('btnImportFichas').disabled = false;
                    document.getElementById('importFichasResult').classList.remove('d-none');
                    document.getElementById('importFichasResult').innerHTML = `<div class="alert alert-danger">${err.message || err}</div>`;
                });
        });
    </script>
</body>
</html>
