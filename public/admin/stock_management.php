<?php
// cotizacion/public/admin/stock_management.php
// Stock management page - reads from COBOL vista_almacenes_anual
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    $_SESSION['error_message'] = "No tienes permisos para gestionar el stock.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$user = $auth->getUser();
$stockRepo = new Stock();

// Get warehouse filter
$selectedWarehouse = filter_input(INPUT_GET, 'warehouse', FILTER_VALIDATE_INT);
$searchQuery = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

// Get data based on filters
$warehouseSummary = $stockRepo->getWarehouseStockSummary();
$availableWarehouses = $stockRepo->getWarehouses();
$lowStockProducts = $stockRepo->getLowStockProducts(10, 10);

// Get stock data
if (!empty($searchQuery)) {
    $stockData = $stockRepo->searchProductsWithStock($searchQuery, 100);
} elseif ($selectedWarehouse) {
    $stockData = $stockRepo->getStockByWarehouse($selectedWarehouse);
} else {
    $stockData = [];
}

$pageTitle = 'Gestión de Stock';
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
        .stock-card {
            transition: transform 0.2s;
        }
        .stock-card:hover {
            transform: translateY(-3px);
        }
        .low-stock {
            background-color: #fff3cd;
        }
        .out-of-stock {
            background-color: #f8d7da;
        }
        .warehouse-badge {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>
            <div class="navbar-nav ms-auto">
                <button class="theme-toggle me-3" id="themeToggle" title="Cambiar tema">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">Panel Admin</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-clipboard-list"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Warehouse Summary - Collapsible -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center"
                         style="cursor: pointer;"
                         data-bs-toggle="collapse"
                         data-bs-target="#warehouseSummaryBody">
                        <h5 class="mb-0">
                            <i class="fas fa-warehouse"></i> Resumen por Almacén
                            <span class="badge bg-light text-info ms-2"><?= count($warehouseSummary) ?></span>
                        </h5>
                        <i class="fas fa-chevron-down" id="collapseIcon"></i>
                    </div>
                    <div class="collapse" id="warehouseSummaryBody">
                        <div class="card-body">
                            <?php if (empty($warehouseSummary)): ?>
                                <p class="text-muted mb-0">No hay datos de stock disponibles.</p>
                            <?php else: ?>
                                <!-- Search filter -->
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-filter"></i></span>
                                        <input type="text" class="form-control" id="warehouseFilter"
                                               placeholder="Filtrar almacenes..."
                                               onkeyup="filterWarehouses()">
                                    </div>
                                </div>
                                <div class="row" id="warehouseCards">
                                    <?php foreach ($warehouseSummary as $warehouse): ?>
                                        <div class="col-md-3 col-sm-6 mb-3 warehouse-card-item"
                                             data-name="<?= htmlspecialchars(strtolower($warehouse['nombre_almacen'])) ?>">
                                            <div class="card stock-card h-100 <?= $selectedWarehouse == $warehouse['numero_almacen'] ? 'border-primary border-2' : '' ?>">
                                                <div class="card-body text-center py-3">
                                                    <h6 class="card-title mb-2"><?= htmlspecialchars($warehouse['nombre_almacen']) ?></h6>
                                                    <p class="mb-1">
                                                        <span class="badge bg-primary warehouse-badge">
                                                            <?= number_format($warehouse['total_productos']) ?> productos
                                                        </span>
                                                    </p>
                                                    <p class="mb-2">
                                                        <span class="badge bg-success warehouse-badge">
                                                            <?= number_format($warehouse['stock_total']) ?> unidades
                                                        </span>
                                                    </p>
                                                    <a href="?warehouse=<?= $warehouse['numero_almacen'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i> Ver
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div id="noWarehouseResults" class="text-center text-muted py-3" style="display: none;">
                                    <i class="fas fa-search"></i> No se encontraron almacenes
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Low Stock Alert -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Stock Bajo</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lowStockProducts)): ?>
                            <p class="text-muted mb-0"><i class="fas fa-check-circle text-success"></i> No hay productos con stock bajo.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($lowStockProducts as $product): ?>
                                    <?php
                                    // Handle both lowercase and uppercase column names from COBOL
                                    $codigo = $product['codigo'] ?? $product['CODIGO'] ?? '';
                                    $descripcion = $product['descripcion'] ?? $product['DESCRIPCION'] ?? '';
                                    $stockActual = $product['stock_actual'] ?? $product['STOCK_ACTUAL'] ?? 0;
                                    $nombreAlmacen = $product['nombre_almacen'] ?? $product['NOMBRE_ALMACEN'] ?? '';
                                    ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <div>
                                            <strong><?= htmlspecialchars($codigo) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($descripcion, 0, 30)) ?>...</small>
                                            <br><small class="text-info"><?= htmlspecialchars($nombreAlmacen) ?></small>
                                        </div>
                                        <span class="badge bg-warning text-dark"><?= $stockActual ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stock Search and Details -->
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search"></i> Buscar Stock</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search"
                                           placeholder="Buscar por código o descripción..."
                                           value="<?= htmlspecialchars($searchQuery) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" name="warehouse">
                                    <option value="">-- Todos los almacenes --</option>
                                    <?php foreach ($availableWarehouses as $wh): ?>
                                        <option value="<?= $wh['numero_almacen'] ?>" <?= $selectedWarehouse == $wh['numero_almacen'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($wh['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </form>

                        <?php if (!empty($searchQuery) || $selectedWarehouse): ?>
                            <div class="mb-3">
                                <a href="<?= BASE_URL ?>/admin/stock_management.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times"></i> Limpiar filtros
                                </a>
                                <?php if ($selectedWarehouse): ?>
                                    <?php
                                    $warehouseInfo = $stockRepo->getWarehouseByNumber($selectedWarehouse);
                                    ?>
                                    <span class="badge bg-info ms-2">
                                        Almacén: <?= htmlspecialchars($warehouseInfo['nombre'] ?? 'Almacén ' . $selectedWarehouse) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($searchQuery)): ?>
                                    <span class="badge bg-secondary ms-2">
                                        Búsqueda: "<?= htmlspecialchars($searchQuery) ?>"
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Stock Results Table -->
                        <?php if (!empty($stockData)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Código</th>
                                            <th>Descripción</th>
                                            <th>Almacén</th>
                                            <th class="text-end">Stock Actual</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stockData as $item): ?>
                                            <?php
                                            // Handle both lowercase and uppercase column names from COBOL
                                            $codigo = $item['codigo'] ?? $item['CODIGO'] ?? '';
                                            $descripcion = $item['descripcion'] ?? $item['DESCRIPCION'] ?? '';
                                            $stockActual = $item['stock_actual'] ?? $item['STOCK_ACTUAL'] ?? 0;
                                            $nombreAlmacen = $item['nombre_almacen'] ?? $item['NOMBRE_ALMACEN'] ?? '';
                                            ?>
                                            <tr class="<?= $stockActual <= 10 ? 'low-stock' : '' ?> <?= $stockActual <= 0 ? 'out-of-stock' : '' ?>">
                                                <td><strong><?= htmlspecialchars($codigo) ?></strong></td>
                                                <td><?= htmlspecialchars($descripcion) ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($nombreAlmacen) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <span class="badge <?= $stockActual <= 10 ? 'bg-warning text-dark' : 'bg-success' ?>">
                                                        <?= number_format($stockActual) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted mt-2">
                                <small>Mostrando <?= count($stockData) ?> registros</small>
                            </p>
                        <?php elseif (!empty($searchQuery) || $selectedWarehouse): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No se encontraron productos con los filtros seleccionados.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary">
                                <i class="fas fa-info-circle"></i> Seleccione un almacén o busque un producto para ver el stock.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Card -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <h6><i class="fas fa-info-circle"></i> Información</h6>
                            <ul class="mb-0">
                                <li>Los datos de stock se obtienen en tiempo real desde el sistema COBOL.</li>
                                <li>El stock mostrado corresponde al mes actual.</li>
                                <li>Los productos con <span class="badge bg-warning text-dark">stock bajo</span> tienen menos de 10 unidades.</li>
                                <li>Para actualizar el stock, utilice el sistema COBOL directamente.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <script>
        // Filter warehouses
        function filterWarehouses() {
            const filter = document.getElementById('warehouseFilter').value.toLowerCase();
            const cards = document.querySelectorAll('.warehouse-card-item');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.dataset.name || '';
                if (name.includes(filter)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide no results message
            const noResults = document.getElementById('noWarehouseResults');
            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        // Toggle collapse icon
        const collapseElement = document.getElementById('warehouseSummaryBody');
        if (collapseElement) {
            collapseElement.addEventListener('show.bs.collapse', function () {
                document.getElementById('collapseIcon').classList.replace('fa-chevron-down', 'fa-chevron-up');
            });
            collapseElement.addEventListener('hide.bs.collapse', function () {
                document.getElementById('collapseIcon').classList.replace('fa-chevron-up', 'fa-chevron-down');
            });
        }
    </script>
</body>
</html>
