<?php
/**
 * Credit History Page
 * Shows history of all credit requests
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

// Verify access
$db = getDBConnection();
$roleCheck = $db->prepare("
    SELECT COUNT(*) FROM user_roles ur
    JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = ? AND r.role_name IN ('Créditos y Cobranzas', 'Administrador del Sistema', 'Administrador de Empresa')
");
$roleCheck->execute([$userId]);

if ($roleCheck->fetchColumn() == 0) {
    $_SESSION['error_message'] = 'No tiene permisos para acceder a esta página';
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

// Filter parameters
$statusFilter = $_GET['status'] ?? '';

$creditManager = new CreditManager();
$allRequests = $creditManager->getAllCreditRequests($companyId, $statusFilter ?: null);
$stats = $creditManager->getCreditStats($companyId);

$pageTitle = 'Historial de Créditos';
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
                        <i class="fas fa-user"></i> <?= htmlspecialchars($auth->getUser()['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/credits/pending.php">Solicitudes Pendientes</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/credits/history.php">Historial</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-history"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/credits/pending.php" class="btn btn-primary">
                <i class="fas fa-clock"></i> Ver Pendientes
                <?php if ($stats['pending'] > 0): ?>
                    <span class="badge bg-danger"><?= $stats['pending'] ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label class="col-form-label"><strong>Filtrar por estado:</strong></label>
                    </div>
                    <div class="col-auto">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Aprobados</option>
                            <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rechazados</option>
                        </select>
                    </div>
                    <?php if ($statusFilter): ?>
                        <div class="col-auto">
                            <a href="<?= BASE_URL ?>/credits/history.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times"></i> Limpiar filtro
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- History Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Todas las Solicitudes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($allRequests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h4>No hay solicitudes</h4>
                        <p class="text-muted">No se encontraron solicitudes de crédito con los filtros seleccionados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cotización</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Días</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Estado</th>
                                    <th>Procesado por</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <?= date('d/m/Y', strtotime($request['requested_at'])) ?>
                                            <br><small class="text-muted"><?= date('H:i', strtotime($request['requested_at'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($request['quotation_number']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($request['customer_name']) ?></td>
                                        <td><?= htmlspecialchars($request['seller_name']) ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $request['credit_days'] ?>d</span>
                                        </td>
                                        <td class="text-end">
                                            <?= $request['currency'] === 'USD' ? '$' : 'S/' ?>
                                            <?= number_format($request['total_amount'], 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $statusClass = match($request['status']) {
                                                'Approved' => 'bg-success',
                                                'Rejected' => 'bg-danger',
                                                'Pending' => 'bg-warning text-dark',
                                                default => 'bg-secondary'
                                            };
                                            $statusText = match($request['status']) {
                                                'Approved' => 'Aprobado',
                                                'Rejected' => 'Rechazado',
                                                'Pending' => 'Pendiente',
                                                default => $request['status']
                                            };
                                            ?>
                                            <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        <td>
                                            <?php if ($request['credit_user_name']): ?>
                                                <?= htmlspecialchars($request['credit_user_name']) ?>
                                                <br><small class="text-muted"><?= date('d/m/Y H:i', strtotime($request['processed_at'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= BASE_URL ?>/credits/process.php?id=<?= $request['id'] ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!empty($request['rejection_reason'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#reasonModal<?= $request['id'] ?>">
                                                    <i class="fas fa-comment"></i>
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="reasonModal<?= $request['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title">Motivo del Rechazo</h5>
                                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><strong>Cotización:</strong> <?= htmlspecialchars($request['quotation_number']) ?></p>
                                                                <p><strong>Cliente:</strong> <?= htmlspecialchars($request['customer_name']) ?></p>
                                                                <hr>
                                                                <p><strong>Motivo:</strong></p>
                                                                <p><?= nl2br(htmlspecialchars($request['rejection_reason'])) ?></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
