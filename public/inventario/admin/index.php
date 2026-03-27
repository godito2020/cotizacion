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
$username = $auth->getUser()['username'];

// Obtener sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);
$stats = $activeSession ? $session->getSessionStats($activeSession['id']) : null;

// Obtener almacenes disponibles
$stock = new Stock();
$warehouses = $stock->getWarehouses();

$pageTitle = 'Panel de Inventario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/inventario/admin/index.php">
                <i class="fas fa-boxes"></i> Inventario - Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?= BASE_URL ?>/inventario/admin/sessions.php">
                    <i class="fas fa-history"></i> Historial
                </a>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($username) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">
                            <i class="fas fa-home"></i> Dashboard Principal
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if ($activeSession): ?>
            <!-- Sesión Activa -->
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-play-circle"></i> Sesión Activa: <?= htmlspecialchars($activeSession['name']) ?>
                    </h5>
                    <span class="badge bg-light text-dark">
                        Desde: <?= date('d/m/Y H:i', strtotime($activeSession['opened_at'])) ?>
                    </span>
                </div>
                <div class="card-body">
                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-center">
                                <div class="card-body py-3">
                                    <h2 class="mb-0 text-primary"><?= (int)($stats['total_entries'] ?? 0) ?></h2>
                                    <small class="text-muted">Registros</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-center">
                                <div class="card-body py-3">
                                    <h2 class="mb-0 text-info"><?= (int)($stats['total_users'] ?? 0) ?></h2>
                                    <small class="text-muted">Usuarios</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-center">
                                <div class="card-body py-3">
                                    <h2 class="mb-0 text-secondary"><?= (int)($stats['total_products'] ?? 0) ?></h2>
                                    <small class="text-muted">Productos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-center border-success">
                                <div class="card-body py-3">
                                    <h2 class="mb-0 text-success"><?= (int)($stats['matching_count'] ?? 0) ?></h2>
                                    <small class="text-muted">Coinciden</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-center border-danger">
                                <div class="card-body py-3">
                                    <h2 class="mb-0 text-danger"><?= (int)($stats['faltantes_count'] ?? 0) ?></h2>
                                    <small class="text-muted">Faltantes</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 col-6 mb-3">
                            <div class="card text-center border-warning">
                                <div class="card-body py-3">
                                    <h2 class="mb-0 text-warning"><?= (int)($stats['sobrantes_count'] ?? 0) ?></h2>
                                    <small class="text-muted">Sobrantes</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?= BASE_URL ?>/inventario/admin/realtime_monitor.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-desktop"></i> Monitor en Tiempo Real
                        </a>
                        <a href="<?= BASE_URL ?>/inventario/admin/reports.php" class="btn btn-info btn-lg">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                        <button class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#closeSessionModal">
                            <i class="fas fa-stop-circle"></i> Cerrar Sesión
                        </button>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Sin Sesión Activa -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-pause-circle"></i> No hay sesión activa</h5>
                </div>
                <div class="card-body text-center py-5">
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h4>Inicia una nueva sesión de inventario</h4>
                    <p class="text-muted">Los usuarios podrán comenzar a registrar una vez que inicies la sesión.</p>
                    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                        <i class="fas fa-plus-circle"></i> Crear Nueva Sesión
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Accesos Rápidos -->
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-history fa-3x text-secondary mb-3"></i>
                        <h5>Historial de Sesiones</h5>
                        <p class="text-muted">Ver sesiones anteriores y sus resultados</p>
                        <a href="<?= BASE_URL ?>/inventario/admin/sessions.php" class="btn btn-outline-secondary">
                            Ver Historial
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-map-marker-alt fa-3x text-primary mb-3"></i>
                        <h5>Gestión de Zonas</h5>
                        <p class="text-muted">Configurar zonas por almacén</p>
                        <a href="<?= BASE_URL ?>/inventario/admin/zones.php" class="btn btn-outline-primary">
                            Administrar Zonas
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                        <h5>Exportar Datos</h5>
                        <p class="text-muted">Descargar reportes en Excel</p>
                        <?php if ($activeSession): ?>
                            <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?type=complete" class="btn btn-outline-success">
                                Exportar
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>Sin sesión activa</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-3x text-info mb-3"></i>
                        <h5>Ranking de Usuarios</h5>
                        <p class="text-muted">Ver productividad y precisión</p>
                        <?php if ($activeSession): ?>
                            <a href="<?= BASE_URL ?>/inventario/admin/reports.php#ranking" class="btn btn-outline-info">
                                Ver Ranking
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>Sin sesión activa</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuración -->
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-mobile-alt fa-3x text-warning mb-3"></i>
                        <h5>Icono PWA</h5>
                        <p class="text-muted">Personalizar icono de la app</p>
                        <a href="<?= BASE_URL ?>/inventario/admin/pwa_icon.php" class="btn btn-outline-warning">
                            Configurar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Crear Sesión -->
    <div class="modal fade" id="createSessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Nueva Sesión de Inventario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="createSessionForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="sessionName" class="form-label fw-bold">Nombre de la sesión: *</label>
                            <input type="text" id="sessionName" name="name" class="form-control"
                                   placeholder="Ej: Inventario Enero 2026" required>
                        </div>

                        <div class="mb-3">
                            <label for="sessionDesc" class="form-label">Descripción:</label>
                            <textarea id="sessionDesc" name="description" class="form-control" rows="2"
                                      placeholder="Descripción opcional..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Almacenes a inventariar: *</label>
                            <div class="row">
                                <?php foreach ($warehouses as $wh): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input warehouse-check"
                                                   name="warehouses[]" value="<?= $wh['numero_almacen'] ?>"
                                                   id="wh_<?= $wh['numero_almacen'] ?>">
                                            <label class="form-check-label" for="wh_<?= $wh['numero_almacen'] ?>">
                                                <?= htmlspecialchars($wh['nombre']) ?>
                                                <small class="text-muted">(#<?= $wh['numero_almacen'] ?>)</small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Selecciona al menos un almacén</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-play"></i> Iniciar Sesión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cerrar Sesión -->
    <?php if ($activeSession): ?>
    <div class="modal fade" id="closeSessionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-stop-circle"></i> Cerrar Sesión de Inventario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="closeSessionForm">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Atención:</strong> Al cerrar la sesión, los usuarios ya no podrán registrar más datos.
                        </div>

                        <p><strong>Sesión:</strong> <?= htmlspecialchars($activeSession['name']) ?></p>
                        <p><strong>Registros:</strong> <?= (int)($stats['total_entries'] ?? 0) ?></p>

                        <div class="mb-3">
                            <label for="closeNotes" class="form-label">Notas del cierre:</label>
                            <textarea id="closeNotes" name="notes" class="form-control" rows="3"
                                      placeholder="Observaciones sobre el cierre..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-stop"></i> Cerrar Sesión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const activeSessionId = <?= $activeSession ? $activeSession['id'] : 'null' ?>;

        // Crear sesión
        document.getElementById('createSessionForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const warehouses = [];
            document.querySelectorAll('.warehouse-check:checked').forEach(cb => {
                warehouses.push(parseInt(cb.value));
            });

            if (warehouses.length === 0) {
                alert('Selecciona al menos un almacén');
                return;
            }

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
                    alert('Sesión creada correctamente');
                    location.reload();
                } else {
                    alert(data.message || 'Error al crear sesión');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        });

        // Cerrar sesión
        document.getElementById('closeSessionForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!confirm('¿Estás seguro de cerrar esta sesión de inventario?')) {
                return;
            }

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
                    alert('Sesión cerrada correctamente');
                    location.reload();
                } else {
                    alert(data.message || 'Error al cerrar sesión');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        });
    </script>
</body>
</html>
