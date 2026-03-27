<?php
/**
 * Billing History - Vendor Interface
 * Shows vendor's own billing request history
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

// Get vendor's billing history
$billingManager = new BillingManager();
$history = $billingManager->getSellerBillingHistory($userId, $companyId);

// Get statistics
$stats = $billingManager->getBillingStats($companyId, $userId, 'seller');

$pageTitle = 'Mis Solicitudes de Facturación';
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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/index.php">Cotizaciones</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/billing/history.php">Mi Historial</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-history"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Cotizaciones
            </a>
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

        <!-- History Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list"></i> Historial de Solicitudes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No tiene solicitudes de facturación registradas.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cotización</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>N° Factura</th>
                                    <th>Procesado Por</th>
                                    <th>Fecha Proceso</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $item): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $item['quotation_id'] ?>" target="_blank">
                                                <strong><?= htmlspecialchars($item['quotation_number']) ?></strong>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($item['customer_name']) ?></td>
                                        <td><strong><?= $item['currency'] ?> <?= number_format($item['total'], 2) ?></strong></td>
                                        <td>
                                            <?php
                                            $statusBadges = [
                                                'Pending' => 'bg-warning',
                                                'In_Process' => 'bg-info',
                                                'Invoiced' => 'bg-success',
                                                'Rejected' => 'bg-danger'
                                            ];
                                            $statusNames = [
                                                'Pending' => 'Pendiente',
                                                'In_Process' => 'En Proceso',
                                                'Invoiced' => 'Facturado',
                                                'Rejected' => 'Rechazado'
                                            ];
                                            ?>
                                            <span class="badge <?= $statusBadges[$item['status']] ?>">
                                                <?= $statusNames[$item['status']] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($item['invoice_number']): ?>
                                                <strong class="text-success"><?= htmlspecialchars($item['invoice_number']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['billing_user_name']): ?>
                                                <?= htmlspecialchars($item['billing_user_name']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($item['processed_at']): ?>
                                                <?= date('d/m/Y H:i', strtotime($item['processed_at'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($item['status'] === 'Rejected'): ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="showRejectionReason('<?= htmlspecialchars(addslashes($item['rejection_reason'] ?? '')) ?>')">
                                                    <i class="fas fa-exclamation-circle"></i> Ver Motivo
                                                </button>
                                            <?php elseif ($item['observations']): ?>
                                                <button class="btn btn-sm btn-outline-info"
                                                        onclick="showObservations('<?= htmlspecialchars(addslashes($item['observations'])) ?>')">
                                                    <i class="fas fa-info-circle"></i> Ver Obs.
                                                </button>
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

    <!-- Modal for Rejection Reason -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalTitle">Detalles</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showRejectionReason(reason) {
            document.getElementById('detailsModalTitle').innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> Motivo de Rechazo';
            document.getElementById('detailsModalBody').innerHTML = '<div class="alert alert-danger">' + reason + '</div>';
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        function showObservations(observations) {
            document.getElementById('detailsModalTitle').innerHTML = '<i class="fas fa-info-circle text-info"></i> Observaciones';
            document.getElementById('detailsModalBody').innerHTML = '<div class="alert alert-info">' + observations + '</div>';
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
