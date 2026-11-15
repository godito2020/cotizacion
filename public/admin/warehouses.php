<?php
/**
 * Gestión de Almacenes - Admin
 * Administra la tabla desc_almacen que mapea números de almacén a nombres en el sistema COBOL
 * Integrado con Master App Shell y diseño Notion / Linear
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
    $_SESSION['error_message'] = "No tiene permisos para gestionar almacenes.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$loggedInUser = $auth->getUser();
$stockRepo = new Stock();

// Procesar formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $numero = (int)($_POST['numero_almacen'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        if ($numero > 0 && !empty($nombre)) {
            if ($stockRepo->saveWarehouse($numero, $nombre, $direccion, $telefono)) {
                $_SESSION['message'] = "Almacén #{$numero} ('" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "') guardado correctamente.";
            } else {
                $_SESSION['error_message'] = "Error al guardar el almacén.";
            }
        } else {
            $_SESSION['error_message'] = "El número y nombre del almacén son requeridos.";
        }
    } elseif ($action === 'delete') {
        $numero = (int)($_POST['numero_almacen'] ?? 0);
        if ($numero > 0) {
            if ($stockRepo->deactivateWarehouse($numero)) {
                $_SESSION['message'] = "Almacén #{$numero} desactivado correctamente.";
            } else {
                $_SESSION['error_message'] = "Error al desactivar el almacén.";
            }
        }
    }

    header('Location: ' . BASE_URL . '/admin/warehouses.php');
    exit;
}

// Obtener almacenes y resumen de stock
$warehouses = $stockRepo->getWarehouses();
$stockSummary = $stockRepo->getWarehouseStockSummary();

// Crear mapa de resumen por número de almacén
$summaryMap = [];
$totalStockGlobal = 0.0;
$totalProductosGlobal = 0;
$maxStock = 0.0;
$topWarehouse = null;

foreach ($stockSummary as $s) {
    $num = (int)$s['numero_almacen'];
    $summaryMap[$num] = $s;
    $stockVal = (float)($s['stock_total'] ?? 0);
    $prodVal = (int)($s['total_productos'] ?? 0);
    $totalStockGlobal += $stockVal;
    $totalProductosGlobal += $prodVal;

    if ($stockVal > $maxStock) {
        $maxStock = $stockVal;
        $topWarehouse = $s;
    }
}

$totalWarehouses = count($warehouses);

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

$pageTitle = 'Gestión de Almacenes';
$activePage = 'warehouses';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="content-body" style="padding: 1.25rem 1.5rem; max-width: 1400px; margin: 0 auto;">

    <!-- =========================================================
         1. HERO HEADER BAR
         ========================================================= -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 600; font-size: 0.75rem;">
                    <i class="fas fa-warehouse me-1"></i> Logística & Almacenes
                </span>
                <span class="text-muted small">Mapeo Sistema COBOL</span>
            </div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--color-ink, #09090B); letter-spacing: -0.02em;">
                Gestión de Almacenes
            </h1>
            <p class="text-muted small mb-0">
                Administración de sedes, centros de distribución y consolidado de existencias en tiempo real.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-warning text-dark fw-semibold d-inline-flex align-items-center gap-1.5 shadow-sm"
                    onclick="openWarehouseModal()" style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-plus-circle"></i>
                <span>Nuevo Almacén</span>
            </button>
            <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-boxes"></i>
                <span>Ver Productos</span>
            </a>
            <a href="<?= BASE_URL ?>/inventario/admin/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
                <i class="fas fa-dolly"></i>
                <span>Panel Inventario</span>
            </a>
            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                    onclick="exportWarehousesToCSV()" title="Descargar reporte CSV" style="font-size: 0.85rem; border-radius: var(--radius-action, 8px);">
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
        <!-- KPI 1: Almacenes Activos -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Almacenes Activos</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                            <i class="fas fa-warehouse" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" style="color: var(--color-ink, #09090B);"><?= number_format($totalWarehouses) ?></span>
                        <span class="badge bg-success-subtle text-success small" style="font-size: 0.65rem;">Configurados</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Mapeados con sistema COBOL</div>
                </div>
            </div>
        </div>

        <!-- KPI 2: Total Productos Únicos -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Líneas en Stock</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.15); color: #3B82F6;">
                            <i class="fas fa-boxes" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" style="color: var(--color-ink, #09090B);"><?= number_format($totalProductosGlobal) ?></span>
                        <span class="badge bg-primary-subtle text-primary small" style="font-size: 0.65rem;">Registros</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Total acumulado entre sedes</div>
                </div>
            </div>
        </div>

        <!-- KPI 3: Stock Físico Total -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Stock Global</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.15); color: #10B981;">
                            <i class="fas fa-cubes" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-4 fw-bold font-monospace" style="color: var(--color-ink, #09090B);"><?= number_format($totalStockGlobal, 0) ?></span>
                        <span class="text-muted small" style="font-size: 0.75rem;">unid.</span>
                    </div>
                    <div class="text-muted mt-1" style="font-size: 0.72rem;">Unidades físicas en red</div>
                </div>
            </div>
        </div>

        <!-- KPI 4: Sede con Mayor Volumen -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-medium text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.05em;">Mayor Capacidad</span>
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px; background: rgba(139, 92, 246, 0.15); color: #8B5CF6;">
                            <i class="fas fa-award" style="font-size: 0.85rem;"></i>
                        </div>
                    </div>
                    <?php if ($topWarehouse): ?>
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fs-5 fw-bold text-truncate" style="color: var(--color-ink, #09090B);" title="<?= htmlspecialchars($topWarehouse['nombre_almacen'] ?? '') ?>">
                                <?= htmlspecialchars($topWarehouse['nombre_almacen'] ?? ('Almacén ' . $topWarehouse['numero_almacen'])) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-1 text-muted" style="font-size: 0.72rem;">
                            <span><?= number_format($maxStock, 0) ?> unid.</span>
                            <span class="text-success fw-semibold">
                                <?= $totalStockGlobal > 0 ? round(($maxStock / $totalStockGlobal) * 100, 1) : 0 ?>% del total
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="fs-6 text-muted mt-1">-</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. SEARCH & ACTIONS TOOLBAR
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center justify-content-between">
                <!-- Search input -->
                <div class="col-12 col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="warehouseSearch" class="form-control border-start-0"
                               placeholder="Filtrar por número, nombre, dirección o teléfono..."
                               onkeyup="filterWarehouseTable()" autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearWarehouseSearch()" title="Limpiar">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Info and Count -->
                <div class="col-12 col-md-7 d-flex justify-content-md-end align-items-center gap-2">
                    <span class="text-muted small" id="warehouseCountLabel">
                        Mostrando <strong><?= $totalWarehouses ?></strong> almacenes
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold" onclick="openWarehouseModal()">
                        <i class="fas fa-plus me-1"></i> Agregar Sede
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         4. WAREHOUSES TABLE (Compact, Clean & Responsive)
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px; overflow: hidden;">
        <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-list text-warning"></i>
                <span class="fw-semibold text-body small">Sedes y Almacenes Registrados</span>
            </div>
            <span class="text-muted small">Mapeo ID COBOL &rarr; Sistema Web</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="warehousesTable" style="width: 100%;">
                    <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                        <tr>
                            <th class="ps-3 py-2 text-muted" style="width: 110px;">N° Almacén</th>
                            <th class="py-2 text-muted" style="min-width: 240px;">Nombre & Contacto</th>
                            <th class="py-2 text-muted" style="min-width: 260px;">Dirección / Ubicación</th>
                            <th class="py-2 text-center text-muted" style="width: 130px;">Líneas en Stock</th>
                            <th class="py-2 text-end text-muted" style="width: 160px;">Stock Total (Unid)</th>
                            <th class="pe-3 py-2 text-end text-muted" style="width: 160px; min-width: 160px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="warehouseTableBody">
                        <?php if (empty($warehouses)): ?>
                            <tr id="noWarehousesRow">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-warehouse fa-3x mb-3 text-secondary opacity-50 d-block"></i>
                                    <div class="fw-semibold">No hay almacenes configurados</div>
                                    <div class="small text-muted mt-1">Haga clic en "+ Nuevo Almacén" para configurar una sede COBOL.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($warehouses as $w): ?>
                                <?php
                                $num = (int)$w['numero_almacen'];
                                $summary = $summaryMap[$num] ?? null;
                                $stockUnits = $summary ? (float)$summary['stock_total'] : 0.0;
                                $prodLines = $summary ? (int)$summary['total_productos'] : 0;
                                $pct = ($totalStockGlobal > 0 && $stockUnits > 0) ? min(100, round(($stockUnits / $totalStockGlobal) * 100, 1)) : 0;
                                $warehouseJson = htmlspecialchars(json_encode($w), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="warehouse-row" data-search="<?= htmlspecialchars(strtolower($w['numero_almacen'] . ' ' . $w['nombre'] . ' ' . ($w['direccion'] ?? '') . ' ' . ($w['telefono'] ?? ''))) ?>">
                                    <!-- N° Almacén -->
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-1.5">
                                            <span class="badge font-monospace px-2 py-1" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.8rem; font-weight: 700;">
                                                #<?= str_pad((string)$num, 2, '0', STR_PAD_LEFT) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Nombre & Contacto -->
                                    <td class="py-2.5">
                                        <div class="fw-bold text-body" style="font-size: 0.88rem;">
                                            <?= htmlspecialchars($w['nombre']) ?>
                                        </div>
                                        <?php if (!empty($w['telefono'])): ?>
                                            <div class="d-flex align-items-center gap-1 text-muted small mt-0.5">
                                                <i class="fas fa-phone-alt text-success" style="font-size: 0.7rem;"></i>
                                                <span><?= htmlspecialchars($w['telefono']) ?></span>
                                                <a href="https://wa.me/51<?= preg_replace('/\D/', '', $w['telefono']) ?>" target="_blank"
                                                   class="btn btn-link btn-sm p-0 text-success text-decoration-none ms-1" title="Contactar por WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small" style="font-size: 0.75rem;">Sin teléfono</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Dirección -->
                                    <td class="py-2.5">
                                        <?php if (!empty($w['direccion'])): ?>
                                            <div class="d-flex align-items-start gap-1 text-body-secondary small">
                                                <i class="fas fa-map-marker-alt text-danger mt-0.5" style="font-size: 0.75rem;"></i>
                                                <span><?= htmlspecialchars($w['direccion']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Líneas de Productos -->
                                    <td class="py-2.5 text-center">
                                        <?php if ($prodLines > 0): ?>
                                            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1" style="font-size: 0.75rem;">
                                                <i class="fas fa-box me-1"></i><?= number_format($prodLines) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary small px-2 py-0.5">0 líneas</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Stock Total (Unidades) & Barra de Capacidad -->
                                    <td class="py-2.5 text-end">
                                        <div class="fw-bold font-monospace" style="font-size: 0.88rem; color: var(--color-ink, #09090B);">
                                            <?= number_format($stockUnits, 0) ?>
                                            <span class="text-muted fw-normal small" style="font-size: 0.7rem;">unid.</span>
                                        </div>
                                        <?php if ($pct > 0): ?>
                                            <div class="d-flex align-items-center justify-content-end gap-1.5 mt-1">
                                                <div class="progress" style="width: 60px; height: 5px; background: var(--color-rule);">
                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                                </div>
                                                <span class="text-muted small" style="font-size: 0.65rem;"><?= $pct ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="pe-3 py-2.5 text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- Ver Productos del Almacén -->
                                            <a href="<?= BASE_URL ?>/admin/products.php?warehouse=<?= $num ?>"
                                               class="btn btn-outline-secondary py-1 px-2" title="Ver productos de esta sede">
                                                <i class="fas fa-boxes text-primary"></i>
                                            </a>

                                            <!-- Editar Almacén -->
                                            <button type="button" class="btn btn-outline-secondary py-1 px-2"
                                                    onclick="editWarehouse(<?= $warehouseJson ?>)" title="Editar almacén">
                                                <i class="fas fa-pencil-alt text-warning"></i>
                                            </button>

                                            <!-- Desactivar Almacén -->
                                            <button type="button" class="btn btn-outline-secondary py-1 px-2"
                                                    onclick="confirmDeleteWarehouse(<?= $num ?>, '<?= htmlspecialchars(addslashes($w['nombre'])) ?>')" title="Desactivar">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     5. MODAL AGREGAR / EDITAR ALMACÉN
     ========================================================= -->
<div class="modal fade" id="warehouseModal" tabindex="-1" aria-labelledby="warehouseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h5 class="modal-title fs-6 fw-bold text-body" id="warehouseModalTitle">
                    <i class="fas fa-warehouse text-warning me-2"></i>
                    <span id="warehouseModalActionText">Nuevo Almacén</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" id="warehouseForm" action="<?= BASE_URL ?>/admin/warehouses.php">
                <input type="hidden" name="action" value="save">
                <div class="modal-body p-3">
                    <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                        <i class="fas fa-info-circle fs-6"></i>
                        <span>Los números de almacén corresponden a los códigos numéricos definidos en el sistema COBOL (ej. 1, 2, 3, etc.).</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Número de Almacén (COBOL) *</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-tertiary font-monospace">#</span>
                            <input type="number" class="form-control font-monospace fw-bold" name="numero_almacen" id="modal_numero_almacen"
                                   required min="1" placeholder="Ej: 1, 2, 3...">
                        </div>
                        <div class="form-text" style="font-size: 0.72rem;">Identificador único del almacén en la base de datos COBOL.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Nombre Comercial de la Sede *</label>
                        <input type="text" class="form-control form-control-sm" name="nombre" id="modal_nombre"
                               required placeholder="Ej: Metro, Productores, Principal, Almacén Central...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Dirección / Ubicación Física</label>
                        <input type="text" class="form-control form-control-sm" name="direccion" id="modal_direccion"
                               placeholder="Ej: Av. Nicolás de Piérola 1234, Lima">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-muted mb-1">Teléfono o WhatsApp de Contacto</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body-tertiary"><i class="fas fa-phone-alt text-muted"></i></span>
                            <input type="text" class="form-control" name="telefono" id="modal_telefono"
                                   placeholder="Ej: 987654321 o (01) 456-7890">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 bg-body-tertiary">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold px-3">
                        <i class="fas fa-save me-1"></i> Guardar Almacén
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     6. MODAL CONFIRMAR DESACTIVACIÓN
     ========================================================= -->
<div class="modal fade" id="deleteWarehouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-0" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i> Desactivar Almacén
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/admin/warehouses.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="numero_almacen" id="delete_modal_numero">
                <div class="modal-body p-3 text-center">
                    <p class="small mb-2 text-body">
                        ¿Está seguro de desactivar el almacén:
                    </p>
                    <p class="fw-bold mb-3 text-warning font-monospace" id="delete_modal_nombre"></p>
                    <p class="text-muted small mb-0" style="font-size: 0.72rem;">
                        El almacén no aparecerá en los selectores del sistema, pero los registros históricos de stock se conservarán.
                    </p>
                </div>
                <div class="modal-footer border-top py-2 px-3 bg-body-tertiary justify-content-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3">
                        <i class="fas fa-trash-alt me-1"></i> Sí, Desactivar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let warehouseModalInstance = null;
let deleteModalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    warehouseModalInstance = new bootstrap.Modal(document.getElementById('warehouseModal'));
    deleteModalInstance = new bootstrap.Modal(document.getElementById('deleteWarehouseModal'));
});

function openWarehouseModal() {
    document.getElementById('warehouseModalActionText').textContent = 'Nuevo Almacén';
    document.getElementById('modal_numero_almacen').value = '';
    document.getElementById('modal_numero_almacen').readOnly = false;
    document.getElementById('modal_nombre').value = '';
    document.getElementById('modal_direccion').value = '';
    document.getElementById('modal_telefono').value = '';
    warehouseModalInstance.show();
    setTimeout(() => document.getElementById('modal_numero_almacen').focus(), 300);
}

function editWarehouse(data) {
    document.getElementById('warehouseModalActionText').textContent = 'Editar Almacén #' + data.numero_almacen;
    document.getElementById('modal_numero_almacen').value = data.numero_almacen;
    document.getElementById('modal_numero_almacen').readOnly = true;
    document.getElementById('modal_nombre').value = data.nombre || '';
    document.getElementById('modal_direccion').value = data.direccion || '';
    document.getElementById('modal_telefono').value = data.telefono || '';
    warehouseModalInstance.show();
    setTimeout(() => document.getElementById('modal_nombre').focus(), 300);
}

function confirmDeleteWarehouse(numero, nombre) {
    document.getElementById('delete_modal_numero').value = numero;
    document.getElementById('delete_modal_nombre').textContent = '#' + numero + ' - ' + nombre;
    deleteModalInstance.show();
}

function filterWarehouseTable() {
    const query = document.getElementById('warehouseSearch').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.warehouse-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (text.includes(query)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const countLabel = document.getElementById('warehouseCountLabel');
    if (countLabel) {
        countLabel.innerHTML = `Mostrando <strong>${visibleCount}</strong> almacenes`;
    }
}

function clearWarehouseSearch() {
    const input = document.getElementById('warehouseSearch');
    input.value = '';
    filterWarehouseTable();
    input.focus();
}

function exportWarehousesToCSV() {
    const rows = document.querySelectorAll('#warehousesTable tbody tr.warehouse-row');
    let csv = '\uFEFF"Numero","Nombre","Direccion","Telefono","Lineas_Productos","Stock_Total_Unidades"\n';

    rows.forEach(row => {
        if (row.style.display === 'none') return;
        const cols = row.querySelectorAll('td');
        if (cols.length >= 5) {
            const num = cols[0].innerText.replace(/[^0-9]/g, '').trim();
            const nombre = cols[1].querySelector('.fw-bold')?.innerText.trim() || '';
            const tel = cols[1].querySelector('.small')?.innerText.replace('Sin teléfono', '').trim() || '';
            const dir = cols[2].innerText.replace('-', '').trim();
            const prods = cols[3].innerText.replace(/[^0-9]/g, '').trim();
            const stock = cols[4].innerText.replace(/[^0-9.]/g, '').trim();

            csv += `"${num}","${nombre.replace(/"/g, '""')}","${dir.replace(/"/g, '""')}","${tel}","${prods}","${stock}"\n`;
        }
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `almacenes_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
