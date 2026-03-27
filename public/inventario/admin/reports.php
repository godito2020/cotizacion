<?php
/**
 * Reportes de Inventario
 */

require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para ver reportes de inventario';
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
    exit;
}

$companyId = $auth->getCompanyId();

// Obtener sesión (activa o específica)
$sessionId = (int)($_GET['session_id'] ?? 0);
$session = new InventorySession();

if ($sessionId > 0) {
    $currentSession = $session->getById($sessionId);
} else {
    $currentSession = $session->getActiveSession($companyId);
}

if (!$currentSession) {
    $_SESSION['warning_message'] = 'No hay sesión de inventario disponible';
    header('Location: ' . BASE_URL . '/inventario/admin/index.php');
    exit;
}

$sessionId = $currentSession['id'];

// Obtener datos para reportes
$reports = new InventoryReports();
$summary = $reports->getSessionSummary($sessionId);
$userStats = $reports->getAllUserStats($sessionId);
$ranking = $reports->getUserRanking($sessionId);
$discrepancySummary = $reports->getDiscrepancySummary($sessionId);
$multipleEntries = $reports->getAllProductsWithMultipleEntries($sessionId);

$pageTitle = 'Reportes de Inventario';
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
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-chart-bar"></i> Reportes: <?= htmlspecialchars($currentSession['name']) ?>
            </span>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <!-- Resumen General -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Resumen General</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <h2 class="text-primary"><?= (int)($summary['total_entries'] ?? 0) ?></h2>
                            <small class="text-muted">Total Registros</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <h2 class="text-info"><?= (int)($summary['total_products'] ?? 0) ?></h2>
                            <small class="text-muted">Productos Contados</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <h2 class="text-secondary"><?= (int)($summary['total_users'] ?? 0) ?></h2>
                            <small class="text-muted">Usuarios</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="text-center">
                            <h2 class="text-dark"><?= (int)($summary['total_warehouses'] ?? 0) ?></h2>
                            <small class="text-muted">Almacenes</small>
                        </div>
                    </div>
                </div>

                <!-- Distribución -->
                <div class="row mt-3">
                    <div class="col-md-4 text-center">
                        <h3 class="text-success"><?= (int)($summary['matching_count'] ?? 0) ?></h3>
                        <span class="badge bg-success fs-6">Coinciden</span>
                    </div>
                    <div class="col-md-4 text-center">
                        <h3 class="text-danger"><?= (int)($summary['faltantes_count'] ?? 0) ?></h3>
                        <span class="badge bg-danger fs-6">Faltantes</span>
                        <small class="d-block text-muted">
                            Qty: <?= number_format($discrepancySummary['total_faltante_qty'] ?? 0, 2) ?>
                        </small>
                    </div>
                    <div class="col-md-4 text-center">
                        <h3 class="text-warning"><?= (int)($summary['sobrantes_count'] ?? 0) ?></h3>
                        <span class="badge bg-warning text-dark fs-6">Sobrantes</span>
                        <small class="d-block text-muted">
                            Qty: <?= number_format($discrepancySummary['total_sobrante_qty'] ?? 0, 2) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exportar -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-excel"></i> Exportar a Excel</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?session_id=<?= $sessionId ?>&type=complete"
                           class="btn btn-success btn-lg w-100">
                            <i class="fas fa-file-excel"></i> Completo
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?session_id=<?= $sessionId ?>&type=discrepancies"
                           class="btn btn-danger btn-lg w-100">
                            <i class="fas fa-exclamation-triangle"></i> Discrepancias
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?session_id=<?= $sessionId ?>&type=matching"
                           class="btn btn-outline-success btn-lg w-100">
                            <i class="fas fa-check"></i> Coincidentes
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?session_id=<?= $sessionId ?>&type=summary"
                           class="btn btn-info btn-lg w-100">
                            <i class="fas fa-chart-pie"></i> Resumen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos con Múltiples Conteos -->
        <?php if (!empty($multipleEntries)): ?>
        <div class="card mb-4" id="multiple-entries">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-layer-group"></i> Productos con Múltiples Conteos</h5>
                <span class="badge bg-info fs-6"><?= count($multipleEntries) ?> productos</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fas fa-info-circle"></i> Estos productos fueron contados más de una vez (diferentes ubicaciones o usuarios).
                    El total se calcula sumando todos los conteos.
                </p>

                <!-- Buscador -->
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchMultiple" class="form-control"
                               placeholder="Buscar por código o descripción..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch('searchMultiple', 'multipleTable')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="multipleTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="text-center">Almacén</th>
                                <th class="text-center">Zonas</th>
                                <th class="text-center">Stock Sistema</th>
                                <th class="text-center">Conteos</th>
                                <th class="text-center">Total Contado</th>
                                <th class="text-center">Diferencia</th>
                                <th>Usuarios</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multipleEntries as $product): ?>
                                <?php
                                $conteos = implode(' + ', array_column($product['entries'], 'counted_quantity'));
                                $diff = (float)$product['difference'];
                                $diffClass = $diff == 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-warning');
                                $diffIcon = $diff == 0 ? 'check-circle' : ($diff < 0 ? 'arrow-down' : 'arrow-up');
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($product['product_code']) ?></strong></td>
                                    <td><?= htmlspecialchars($product['product_description'] ?? '') ?></td>
                                    <td class="text-center"><?= (int)$product['warehouse_number'] ?></td>
                                    <td class="text-center"><small><?= htmlspecialchars($product['zones'] ?? '-') ?></small></td>
                                    <td class="text-center"><?= number_format($product['system_stock'], 2) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?= $conteos ?></span>
                                        <small class="text-muted">(<?= $product['entry_count'] ?>x)</small>
                                    </td>
                                    <td class="text-center"><strong><?= number_format($product['total_counted'], 2) ?></strong></td>
                                    <td class="text-center <?= $diffClass ?>">
                                        <i class="fas fa-<?= $diffIcon ?>"></i>
                                        <?= number_format($diff, 2) ?>
                                    </td>
                                    <td><small><?= htmlspecialchars($product['users'] ?? '') ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Ranking de Usuarios -->
        <div class="card mb-4" id="ranking">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-trophy"></i> Ranking de Usuarios</h5>
            </div>
            <div class="card-body">
                <?php if (empty($ranking)): ?>
                    <p class="text-muted text-center">No hay datos de usuarios aún</p>
                <?php else: ?>
                    <!-- Buscador -->
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchRanking" class="form-control"
                                   placeholder="Buscar usuario..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch('searchRanking', 'rankingTable')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="rankingTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Usuario</th>
                                    <th class="text-center">Registros</th>
                                    <th class="text-center">Coinciden</th>
                                    <th class="text-center">Precisión</th>
                                    <th class="text-center">Tiempo (min)</th>
                                    <th class="text-center">Vel. (reg/min)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranking as $i => $user): ?>
                                    <?php
                                    $medal = '';
                                    if ($i == 0) $medal = '<i class="fas fa-medal text-warning"></i>';
                                    elseif ($i == 1) $medal = '<i class="fas fa-medal text-secondary"></i>';
                                    elseif ($i == 2) $medal = '<i class="fas fa-medal text-danger"></i>';

                                    $accuracy = (float)($user['accuracy_percentage'] ?? 0);
                                    $accuracyClass = $accuracy >= 90 ? 'text-success' : ($accuracy >= 70 ? 'text-warning' : 'text-danger');
                                    ?>
                                    <tr>
                                        <td><?= $medal ?> <?= $i + 1 ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            <?php if ($user['full_name'] && trim($user['full_name']) !== ''): ?>
                                                <small class="text-muted d-block"><?= htmlspecialchars($user['full_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= (int)$user['total_entries'] ?></td>
                                        <td class="text-center text-success"><?= (int)$user['matching_count'] ?></td>
                                        <td class="text-center <?= $accuracyClass ?>">
                                            <strong><?= number_format($accuracy, 1) ?>%</strong>
                                        </td>
                                        <td class="text-center"><?= (int)($user['active_minutes'] ?? 0) ?></td>
                                        <td class="text-center"><?= number_format($user['entries_per_minute'] ?? 0, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estadísticas por Usuario -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users"></i> Detalle por Usuario</h5>
            </div>
            <div class="card-body">
                <?php if (empty($userStats)): ?>
                    <p class="text-muted text-center">No hay datos de usuarios aún</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($userStats as $user): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        <?php if ($user['full_name'] && trim($user['full_name']) !== ''): ?>
                                            <small class="text-muted"><?= htmlspecialchars($user['full_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <h5 class="text-primary"><?= (int)$user['total_entries'] ?></h5>
                                                <small>Registros</small>
                                            </div>
                                            <div class="col-4">
                                                <h5 class="text-success"><?= (int)$user['matching_count'] ?></h5>
                                                <small>Coinciden</small>
                                            </div>
                                            <div class="col-4">
                                                <h5 class="text-danger"><?= (int)$user['discrepancy_count'] ?></h5>
                                                <small>Diferencias</small>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <span>Precisión:</span>
                                            <strong><?= number_format($user['accuracy_percentage'] ?? 0, 1) ?>%</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Tiempo activo:</span>
                                            <strong><?= (int)($user['active_minutes'] ?? 0) ?> min</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Primera entrada:</span>
                                            <small><?= $user['first_entry_at'] ? date('H:i', strtotime($user['first_entry_at'])) : '-' ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Última entrada:</span>
                                            <small><?= $user['last_entry_at'] ? date('H:i', strtotime($user['last_entry_at'])) : '-' ?></small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?session_id=<?= $sessionId ?>&type=user&user_id=<?= $user['user_id'] ?>"
                                           class="btn btn-sm btn-outline-success w-100">
                                            <i class="fas fa-download"></i> Exportar datos
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función de búsqueda genérica para tablas
        function setupTableSearch(inputId, tableId) {
            const input = document.getElementById(inputId);
            if (!input) return;

            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const table = document.getElementById(tableId);
                if (!table) return;

                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        function clearSearch(inputId, tableId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input'));
                input.focus();
            }
        }

        // Inicializar buscadores
        document.addEventListener('DOMContentLoaded', function() {
            setupTableSearch('searchMultiple', 'multipleTable');
            setupTableSearch('searchRanking', 'rankingTable');
        });
    </script>
</body>
</html>
