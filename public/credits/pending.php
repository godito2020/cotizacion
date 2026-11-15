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
$pageTitle = 'Solicitudes de Crédito Pendientes';
$activePage = 'credits';
require_once __DIR__ . '/../../includes/app_header.php';
?>
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

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
