<?php
/**
 * Credit Approval Panel - Pending Requests
 * Shows pending credit approval requests for Créditos y Cobranzas users
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

// Verify access - only Créditos y Cobranzas, System Admin, or Company Admin
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

// Get user roles to check if only has credit role
$userRepo = new User();
$userRoles = $userRepo->getRoles($userId);
$isOnlyCredits = (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Créditos y Cobranzas');

// Get pending requests
$creditManager = new CreditManager();
$pendingRequests = $creditManager->getPendingCreditRequests($companyId);
$stats = $creditManager->getCreditStats($companyId);

$pageTitle = 'Solicitudes de Crédito Pendientes';
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
                        <?php if (!$isOnlyCredits): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <?php endif; ?>
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
            <h1><i class="fas fa-credit-card"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/credits/history.php" class="btn btn-outline-secondary">
                <i class="fas fa-history"></i> Ver Historial
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

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h3><?= $stats['pending'] ?></h3>
                        <p class="mb-0"><i class="fas fa-clock"></i> Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?= $stats['approved'] ?></h3>
                        <p class="mb-0"><i class="fas fa-check"></i> Aprobados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3><?= $stats['rejected'] ?></h3>
                        <p class="mb-0"><i class="fas fa-times"></i> Rechazados</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?= $stats['total'] ?></h3>
                        <p class="mb-0"><i class="fas fa-list"></i> Total</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Requests Table -->
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-hourglass-half"></i> Solicitudes Pendientes de Aprobación</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingRequests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>No hay solicitudes pendientes</h4>
                        <p class="text-muted">Todas las solicitudes de crédito han sido procesadas.</p>
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
                                    <th>Días Crédito</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <?= date('d/m/Y', strtotime($request['requested_at'])) ?>
                                            <br><small class="text-muted"><?= date('H:i', strtotime($request['requested_at'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($request['quotation_number']) ?></strong>
                                            <br><small class="text-muted"><?= date('d/m/Y', strtotime($request['quotation_date'])) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($request['customer_name']) ?>
                                            <?php if (!empty($request['customer_tax_id'])): ?>
                                                <br><small class="text-muted">RUC: <?= htmlspecialchars($request['customer_tax_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($request['seller_name']) ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($request['seller_email']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $request['credit_days'] ?> días</span>
                                        </td>
                                        <td class="text-end">
                                            <strong>
                                                <?= $request['currency'] === 'USD' ? '$' : 'S/' ?>
                                                <?= number_format($request['total'], 2) ?>
                                            </strong>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= BASE_URL ?>/credits/process.php?id=<?= $request['id'] ?>"
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> Revisar
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
