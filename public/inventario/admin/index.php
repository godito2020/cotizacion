<?php
/**
 * Panel de Administración de Inventario
 */
require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para administrar inventarios';
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
    exit;
}

$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();
$user = $auth->getUser();

// Obtener sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);
$stats = $activeSession ? $session->getSessionStats($activeSession['id']) : null;

// Obtener almacenes disponibles
$stock = new Stock();
$warehouses = $stock->getWarehouses();

$pageTitle = 'Panel de Inventario';
$activePage = 'inventario';
require_once __DIR__ . '/../../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1300px; margin: 0 auto;">

    <!-- =========================================================
         1. BREADCRUMB & HEADER
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
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Panel de Inventario</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:<?= $activeSession ? '#10B981' : '#F59E0B' ?>; box-shadow:0 0 8px <?= $activeSession ? 'rgba(16, 185, 129, 0.6)' : 'rgba(245, 158, 11, 0.6)' ?>;"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Administración de Toma de Inventario
                    </h1>
                    <?php if ($activeSession): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small fw-semibold">
                            <i class="fas fa-play-circle me-1"></i>Sesión en Curso
                        </span>
                    <?php else: ?>
                        <span class="badge bg-body-secondary text-muted border px-2 py-1 small">
                            Sin sesión activa
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Control de conteo físico, conciliación de almacenes, monitoreo en tiempo real y cierre de inventarios.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/inventario/admin/sessions.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fas fa-history me-1"></i> Historial
                </a>
                <a href="<?= BASE_URL ?>/inventario/admin/zones.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fas fa-map-marker-alt me-1"></i> Zonas
                </a>
            </div>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 small mb-3" role="alert">
            <i class="fas fa-check-circle"></i>
            <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 small mb-3" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- =========================================================
         2. ACTIVE SESSION / NO ACTIVE SESSION HERO
         ========================================================= -->
    <?php if ($activeSession): ?>
        <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 14px; overflow: hidden;">
            <div class="card-header bg-success-subtle border-bottom border-success-subtle py-2.5 px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle d-flex align-items-center justify-content-center text-white bg-success" style="width:24px;height:24px;font-size:0.7rem;">
                        <i class="fas fa-play"></i>
                    </span>
                    <strong class="text-success" style="font-size: 0.95rem;">
                        <?= htmlspecialchars($activeSession['name']) ?>
                    </strong>
                    <?php if (!empty($activeSession['description'])): ?>
                        <span class="text-muted small d-none d-md-inline">— <?= htmlspecialchars($activeSession['description']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-body-tertiary text-body border font-monospace small">
                        <i class="far fa-clock me-1 text-muted"></i>Iniciada: <?= date('d/m/Y H:i', strtotime($activeSession['opened_at'])) ?>
                    </span>
                </div>
            </div>

            <div class="card-body p-3 p-md-4">
                <!-- 6 KPI Metrics in a row -->
                <div class="row g-2 mb-4">
                    <div class="col-4 col-md-2">
                        <div class="p-2.5 rounded text-center h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Registros</div>
                            <div class="fs-4 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;">
                                <?= (int)($stats['total_entries'] ?? 0) ?>
                            </div>
                            <div class="small text-muted" style="font-size: 0.7rem;">Conteos</div>
                        </div>
                    </div>

                    <div class="col-4 col-md-2">
                        <div class="p-2.5 rounded text-center h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Operadores</div>
                            <div class="fs-4 fw-bold text-info" style="font-family: var(--font-mono); line-height: 1.2;">
                                <?= (int)($stats['total_users'] ?? 0) ?>
                            </div>
                            <div class="small text-muted" style="font-size: 0.7rem;">Activos</div>
                        </div>
                    </div>

                    <div class="col-4 col-md-2">
                        <div class="p-2.5 rounded text-center h-100" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Productos</div>
                            <div class="fs-4 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;">
                                <?= (int)($stats['total_products'] ?? 0) ?>
                            </div>
                            <div class="small text-muted" style="font-size: 0.7rem;">Distintos</div>
                        </div>
                    </div>

                    <div class="col-4 col-md-2">
                        <div class="p-2.5 rounded text-center h-100 border-start border-3 border-success" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Coinciden</div>
                            <div class="fs-4 fw-bold text-success" style="font-family: var(--font-mono); line-height: 1.2;">
                                <?= (int)($stats['matching_count'] ?? 0) ?>
                            </div>
                            <div class="small text-success" style="font-size: 0.7rem;">Exactos</div>
                        </div>
                    </div>

                    <div class="col-4 col-md-2">
                        <div class="p-2.5 rounded text-center h-100 border-start border-3 border-danger" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Faltantes</div>
                            <div class="fs-4 fw-bold text-danger" style="font-family: var(--font-mono); line-height: 1.2;">
                                <?= (int)($stats['faltantes_count'] ?? 0) ?>
                            </div>
                            <div class="small text-danger" style="font-size: 0.7rem;">Diferencias</div>
                        </div>
                    </div>

                    <div class="col-4 col-md-2">
                        <div class="p-2.5 rounded text-center h-100 border-start border-3 border-warning" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.05em;">Sobrantes</div>
                            <div class="fs-4 fw-bold text-warning" style="font-family: var(--font-mono); line-height: 1.2;">
                                <?= (int)($stats['sobrantes_count'] ?? 0) ?>
                            </div>
                            <div class="small text-warning" style="font-size: 0.7rem;">Excedentes</div>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <a href="<?= BASE_URL ?>/inventario/admin/realtime_monitor.php" class="btn btn-primary fw-semibold px-3">
                        <i class="fas fa-desktop me-1.5"></i> Monitor en Tiempo Real
                    </a>
                    <a href="<?= BASE_URL ?>/inventario/admin/reports.php" class="btn btn-outline-secondary fw-semibold px-3">
                        <i class="fas fa-chart-bar me-1.5"></i> Reportes y Discrepancias
                    </a>
                    <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?type=complete" class="btn btn-outline-success fw-semibold px-3">
                        <i class="fas fa-file-excel me-1.5"></i> Exportar a Excel
                    </a>
                    <button type="button" class="btn btn-outline-danger ms-auto fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#closeSessionModal">
                        <i class="fas fa-stop-circle me-1.5"></i> Cerrar Sesión
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 14px;">
            <div class="card-body text-center py-5 px-3">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                    <i class="fas fa-clipboard-list fa-2x"></i>
                </div>
                <h5 class="fw-bold text-body mb-1">No hay ninguna sesión de inventario activa</h5>
                <p class="text-muted small mb-3" style="max-width: 480px; margin: 0 auto;">
                    Inicie una nueva sesión para habilitar a los operadores de almacén a escanear y registrar productos desde sus dispositivos móviles.
                </p>
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                    <i class="fas fa-play me-1.5"></i> Iniciar Nueva Sesión de Inventario
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         3. QUICK ACCESS TOOLS GRID
         ========================================================= -->
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="<?= BASE_URL ?>/inventario/admin/sessions.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                    <div class="card-body p-3 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-secondary mb-2" style="width: 44px; height: 44px; background: rgba(107, 114, 128, 0.12);">
                            <i class="fas fa-history fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1" style="font-size: 0.9rem;">Historial de Sesiones</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.75rem;">Consulte conteos cerrados y auditorías previas</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-3">
            <a href="<?= BASE_URL ?>/inventario/admin/zones.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                    <div class="card-body p-3 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-primary mb-2" style="width: 44px; height: 44px; background: rgba(59, 130, 246, 0.12);">
                            <i class="fas fa-map-marker-alt fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1" style="font-size: 0.9rem;">Gestión de Zonas</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.75rem;">Configuración de racks y pasillos por almacén</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-3">
            <a href="<?= BASE_URL ?>/inventario/admin/reports.php#ranking" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                    <div class="card-body p-3 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-info mb-2" style="width: 44px; height: 44px; background: rgba(6, 182, 212, 0.12);">
                            <i class="fas fa-ranking-star fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1" style="font-size: 0.9rem;">Ranking y Productividad</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.75rem;">Velocidad y precisión de conteo por operador</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-3">
            <a href="<?= BASE_URL ?>/inventario/admin/pwa_icon.php" class="text-decoration-none">
                <div class="card h-100 border-0 shadow-sm transition-hover" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                    <div class="card-body p-3 text-center">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-warning mb-2" style="width: 44px; height: 44px; background: rgba(245, 158, 11, 0.12);">
                            <i class="fas fa-mobile-screen fa-lg"></i>
                        </div>
                        <h6 class="fw-bold text-body mb-1" style="font-size: 0.9rem;">Ícono PWA Móvil</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.75rem;">Personalizar ícono de la app en smartphones</p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: CREAR SESIÓN
     ========================================================= -->
<div class="modal fade" id="createSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-body mb-0">
                    <i class="fas fa-play text-success me-2"></i>Nueva Sesión de Inventario
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="createSessionForm">
                <div class="modal-body p-3 p-md-4">
                    <div class="mb-3">
                        <label for="sessionName" class="form-label small fw-semibold text-body mb-1">Nombre de la Sesión *</label>
                        <input type="text" id="sessionName" name="name" class="form-control form-control-sm"
                               placeholder="Ej: Inventario General <?= date('F Y') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="sessionDesc" class="form-label small fw-semibold text-body mb-1">Descripción / Observaciones</label>
                        <textarea id="sessionDesc" name="description" class="form-control form-control-sm" rows="2"
                                  placeholder="Detalle o alcance opcional del conteo..."></textarea>
                    </div>

                    <div class="p-3 rounded mb-2" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <label class="form-label small fw-bold text-body mb-2">
                            <i class="fas fa-warehouse text-warning me-1"></i>Seleccionar Almacenes a Inventariar *
                        </label>
                        <div class="row g-2">
                            <?php foreach ($warehouses as $wh): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input warehouse-check"
                                               name="warehouses[]" value="<?= $wh['numero_almacen'] ?>"
                                               id="wh_<?= $wh['numero_almacen'] ?>">
                                        <label class="form-check-label small text-body" for="wh_<?= $wh['numero_almacen'] ?>">
                                            <strong><?= htmlspecialchars($wh['nombre']) ?></strong>
                                            <span class="text-muted">(#<?= $wh['numero_almacen'] ?>)</span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm px-4 fw-semibold" id="btnSubmitCreateSession">
                        <i class="fas fa-play me-1"></i> Iniciar Sesión Ahora
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: CERRAR SESIÓN
     ========================================================= -->
<?php if ($activeSession): ?>
<div class="modal fade" id="closeSessionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-danger mb-0">
                    <i class="fas fa-stop-circle me-2"></i>Cerrar Sesión de Inventario
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="closeSessionForm">
                <div class="modal-body p-3 p-md-4">
                    <div class="alert alert-warning py-2 px-3 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>Atención:</strong> Al cerrar la sesión, los operadores ya no podrán registrar conteos y se generará el balance final.
                    </div>

                    <div class="mb-2 text-body small">
                        <strong>Sesión:</strong> <?= htmlspecialchars($activeSession['name']) ?>
                    </div>
                    <div class="mb-3 text-body small">
                        <strong>Total Conteos Registrados:</strong> <?= (int)($stats['total_entries'] ?? 0) ?>
                    </div>

                    <div class="mb-2">
                        <label for="closeNotes" class="form-label small fw-semibold text-body mb-1">Notas de Cierre y Conclusiones</label>
                        <textarea id="closeNotes" name="notes" class="form-control form-control-sm" rows="3"
                                  placeholder="Observaciones finales del inventario..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 fw-semibold" id="btnSubmitCloseSession">
                        <i class="fas fa-stop me-1"></i> Confirmar Cierre
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const activeSessionId = <?= $activeSession ? (int)$activeSession['id'] : 'null' ?>;

// Crear sesión
document.getElementById('createSessionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const warehouses = [];
    document.querySelectorAll('.warehouse-check:checked').forEach(cb => {
        warehouses.push(parseInt(cb.value));
    });

    if (warehouses.length === 0) {
        alert('Seleccione al menos un almacén para inventariar');
        return;
    }

    const btn = document.getElementById('btnSubmitCreateSession');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Iniciando...';

    const formData = {
        name: document.getElementById('sessionName').value,
        description: document.getElementById('sessionDesc').value,
        warehouses: warehouses
    };

    try {
        const response = await fetch(`${BASE_URL}/inventario/api/open_session.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al crear la sesión');
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión con el servidor');
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
});

// Cerrar sesión
document.getElementById('closeSessionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    if (!confirm('¿Confirma que desea cerrar esta sesión de inventario?')) {
        return;
    }

    const btn = document.getElementById('btnSubmitCloseSession');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Cerrando...';

    const formData = {
        session_id: activeSessionId,
        notes: document.getElementById('closeNotes').value
    };

    try {
        const response = await fetch(`${BASE_URL}/inventario/api/close_session.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al cerrar la sesión');
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión con el servidor');
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
});
</script>

<?php
require_once __DIR__ . '/../../../includes/app_footer.php';
?>
