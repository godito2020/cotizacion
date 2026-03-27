<?php
/**
 * Pending Billing Requests - Billing User Interface
 * Shows all pending billing requests for processing
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

// Check if user has billing role
$db = getDBConnection();
$roleCheck = $db->prepare("
    SELECT COUNT(*) FROM user_roles ur
    JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = ? AND r.role_name IN ('Facturación', 'Administrador del Sistema', 'Administrador de Empresa')
");
$roleCheck->execute([$userId]);
if ($roleCheck->fetchColumn() == 0) {
    $_SESSION['error_message'] = 'No tiene permisos para acceder a esta página';
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

// Check if user ONLY has Facturación role
$userRepo = new User();
$userRoles = $userRepo->getRoles($userId);
$isOnlyBilling = (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación');

// Get pending billing requests
$billingManager = new BillingManager();
$pendingRequests = $billingManager->getPendingBillingRequests($companyId);

// Get statistics
$stats = $billingManager->getBillingStats($companyId);

$pageTitle = 'Solicitudes Pendientes de Facturación';
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
            <a class="navbar-brand" href="<?= $isOnlyBilling ? BASE_URL . '/billing/pending.php' : BASE_URL . '/dashboard_simple.php' ?>">
                <i class="fas fa-file-invoice"></i> <?= $isOnlyBilling ? 'Panel de Facturación' : 'Sistema de Cotizaciones' ?>
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
                        <?php if (!$isOnlyBilling): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/index.php">Cotizaciones</a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/billing/pending.php">Facturación</a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-file-invoice"></i> <?= $pageTitle ?></h1>
            <?php if (!$isOnlyBilling): ?>
                <a href="<?= BASE_URL ?>/dashboard_simple.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            <?php endif; ?>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-clock"></i> Pendientes
                        </h5>
                        <h2 class="mb-0"><?= $stats['pending'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-spinner"></i> En Proceso
                        </h5>
                        <h2 class="mb-0"><?= $stats['in_process'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-check-circle"></i> Facturadas
                        </h5>
                        <h2 class="mb-0"><?= $stats['invoiced'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-times-circle"></i> Rechazadas
                        </h5>
                        <h2 class="mb-0"><?= $stats['rejected'] ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Requests Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Solicitudes Pendientes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingRequests)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No hay solicitudes pendientes de facturación.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha Solicitud</th>
                                    <th>Cotización</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($request['requested_at'])) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $request['quotation_id'] ?>" target="_blank">
                                                <strong><?= htmlspecialchars($request['quotation_number']) ?></strong>
                                            </a>
                                            <br>
                                            <small class="text-muted"><?= date('d/m/Y', strtotime($request['quotation_date'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($request['customer_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($request['seller_name']) ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($request['seller_email']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= $request['currency'] ?> <?= number_format($request['total'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $statusBadges = [
                                                'Pending' => 'bg-warning',
                                                'In_Process' => 'bg-info'
                                            ];
                                            $statusNames = [
                                                'Pending' => 'Pendiente',
                                                'In_Process' => 'En Proceso'
                                            ];
                                            ?>
                                            <span class="badge <?= $statusBadges[$request['status']] ?>">
                                                <?= $statusNames[$request['status']] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['observations']): ?>
                                                <small><?= htmlspecialchars(substr($request['observations'], 0, 50)) ?><?= strlen($request['observations']) > 50 ? '...' : '' ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">Sin observaciones</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= BASE_URL ?>/billing/process.php?id=<?= $request['id'] ?>"
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-tasks"></i> Procesar
                                            </a>
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
