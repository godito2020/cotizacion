<?php
/**
 * Credit Approval Process Page
 * Allows credit users to approve or reject credit requests
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

$trackingId = $_GET['id'] ?? 0;
if (!$trackingId) {
    $_SESSION['error_message'] = 'Solicitud no especificada';
    header('Location: ' . BASE_URL . '/credits/pending.php');
    exit;
}

$creditManager = new CreditManager();
$request = $creditManager->getCreditRequest($trackingId);

if (!$request || $request['company_id'] != $companyId) {
    $_SESSION['error_message'] = 'Solicitud no encontrada';
    header('Location: ' . BASE_URL . '/credits/pending.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $observations = $_POST['observations'] ?? '';

    if ($action === 'approve') {
        $result = $creditManager->approveCredit($trackingId, $userId, $observations);
        if ($result['success']) {
            $_SESSION['success_message'] = 'Crédito aprobado exitosamente. Se envió a facturación.';
            header('Location: ' . BASE_URL . '/credits/pending.php');
            exit;
        } else {
            $_SESSION['error_message'] = $result['message'];
        }
    } elseif ($action === 'reject') {
        $rejectionReason = $_POST['rejection_reason'] ?? '';
        if (empty($rejectionReason)) {
            $_SESSION['error_message'] = 'Debe especificar el motivo del rechazo';
        } else {
            $result = $creditManager->rejectCredit($trackingId, $userId, $rejectionReason);
            if ($result['success']) {
                $_SESSION['success_message'] = 'Solicitud de crédito rechazada.';
                header('Location: ' . BASE_URL . '/credits/pending.php');
                exit;
            } else {
                $_SESSION['error_message'] = $result['message'];
            }
        }
    }
}

// Get customer history
$customerHistory = $creditManager->getCustomerHistory($request['customer_id'], $companyId);

$pageTitle = 'Revisar Solicitud de Crédito';
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
            <h1><i class="fas fa-credit-card"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/credits/pending.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column - Request Details -->
            <div class="col-lg-8">
                <!-- Quotation Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Información de la Cotización</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Número:</th>
                                        <td><strong><?= htmlspecialchars($request['quotation_number']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Fecha:</th>
                                        <td><?= date('d/m/Y', strtotime($request['quotation_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Vendedor:</th>
                                        <td><?= htmlspecialchars($request['seller_name']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="40%">Días Crédito:</th>
                                        <td><span class="badge bg-info fs-6"><?= $request['credit_days'] ?> días</span></td>
                                    </tr>
                                    <tr>
                                        <th>Total:</th>
                                        <td class="fs-5">
                                            <strong>
                                                <?= $request['currency'] === 'USD' ? '$' : 'S/' ?>
                                                <?= number_format($request['total'], 2) ?>
                                            </strong>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if (!empty($request['observations'])): ?>
                            <div class="alert alert-info mt-3">
                                <strong><i class="fas fa-comment"></i> Observaciones del vendedor:</strong>
                                <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($request['observations'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $request['quotation_id'] ?>" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i> Ver Cotización Completa
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-user"></i> Información del Cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nombre:</strong> <?= htmlspecialchars($request['customer_name']) ?></p>
                                <p><strong>RUC/DNI:</strong> <?= htmlspecialchars($request['customer_tax_id'] ?: 'No especificado') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?= htmlspecialchars($request['customer_email'] ?: 'No especificado') ?></p>
                                <p><strong>Teléfono:</strong> <?= htmlspecialchars($request['customer_phone'] ?: 'No especificado') ?></p>
                            </div>
                        </div>
                        <?php if (!empty($request['customer_address'])): ?>
                            <p><strong>Dirección:</strong> <?= htmlspecialchars($request['customer_address']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer History -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Historial del Cliente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($customerHistory)): ?>
                            <p class="text-muted mb-0">No hay historial de cotizaciones para este cliente.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Cotización</th>
                                            <th>Fecha</th>
                                            <th>Condición</th>
                                            <th class="text-end">Total</th>
                                            <th>Estado</th>
                                            <th>Crédito</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customerHistory as $history): ?>
                                            <tr class="<?= $history['id'] == $request['quotation_id'] ? 'table-active' : '' ?>">
                                                <td>
                                                    <?= htmlspecialchars($history['quotation_number']) ?>
                                                    <?php if ($history['id'] == $request['quotation_id']): ?>
                                                        <span class="badge bg-primary">Actual</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($history['quotation_date'])) ?></td>
                                                <td>
                                                    <?php if ($history['payment_condition'] === 'credit'): ?>
                                                        <span class="badge bg-warning text-dark">Crédito <?= $history['credit_days'] ?>d</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Contado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= $history['currency'] ?> <?= number_format($history['total'], 2) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = match($history['status']) {
                                                        'Accepted' => 'bg-success',
                                                        'Rejected' => 'bg-danger',
                                                        'Sent' => 'bg-info',
                                                        'Invoiced' => 'bg-primary',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= $history['status'] ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($history['credit_status']): ?>
                                                        <?php
                                                        $creditClass = match($history['credit_status']) {
                                                            'Credit_Approved' => 'bg-success',
                                                            'Credit_Rejected' => 'bg-danger',
                                                            'Pending_Credit' => 'bg-warning text-dark',
                                                            default => 'bg-secondary'
                                                        };
                                                        $creditText = match($history['credit_status']) {
                                                            'Credit_Approved' => 'Aprobado',
                                                            'Credit_Rejected' => 'Rechazado',
                                                            'Pending_Credit' => 'Pendiente',
                                                            default => $history['credit_status']
                                                        };
                                                        ?>
                                                        <span class="badge <?= $creditClass ?>"><?= $creditText ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
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
            </div>

            <!-- Right Column - Actions -->
            <div class="col-lg-4">
                <?php if ($request['status'] === 'Pending'): ?>
                    <!-- Approve Form -->
                    <div class="card mb-4 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check"></i> Aprobar Crédito</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return confirm('¿Está seguro de aprobar este crédito? La cotización pasará automáticamente a facturación.')">
                                <input type="hidden" name="action" value="approve">
                                <div class="mb-3">
                                    <label for="observations" class="form-label">Observaciones (Opcional)</label>
                                    <textarea class="form-control" id="observations" name="observations" rows="3"
                                              placeholder="Notas o comentarios sobre la aprobación..."></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-check-circle"></i> Aprobar y Enviar a Facturación
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Reject Form -->
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-times"></i> Rechazar Crédito</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return confirm('¿Está seguro de rechazar este crédito?')">
                                <input type="hidden" name="action" value="reject">
                                <div class="mb-3">
                                    <label for="rejection_reason" class="form-label">Motivo del Rechazo <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required
                                              placeholder="Especifique el motivo del rechazo..."></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-times-circle"></i> Rechazar Solicitud
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Already Processed -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Estado de la Solicitud</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($request['status'] === 'Approved'): ?>
                                <div class="text-success mb-3">
                                    <i class="fas fa-check-circle fa-4x"></i>
                                </div>
                                <h4 class="text-success">Crédito Aprobado</h4>
                                <p>Aprobado por: <?= htmlspecialchars($request['credit_user_name']) ?></p>
                                <p>Fecha: <?= date('d/m/Y H:i', strtotime($request['processed_at'])) ?></p>
                                <?php if (!empty($request['observations'])): ?>
                                    <div class="alert alert-success text-start mt-3">
                                        <strong><i class="fas fa-comment"></i> Observaciones:</strong><br>
                                        <?= nl2br(htmlspecialchars($request['observations'])) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-danger mb-3">
                                    <i class="fas fa-times-circle fa-4x"></i>
                                </div>
                                <h4 class="text-danger">Crédito Rechazado</h4>
                                <p>Rechazado por: <?= htmlspecialchars($request['credit_user_name']) ?></p>
                                <p>Fecha: <?= date('d/m/Y H:i', strtotime($request['processed_at'])) ?></p>
                                <?php if (!empty($request['rejection_reason'])): ?>
                                    <div class="alert alert-danger text-start mt-3">
                                        <strong>Motivo:</strong><br>
                                        <?= nl2br(htmlspecialchars($request['rejection_reason'])) ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Request Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-clock"></i> Información de Solicitud</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Solicitado:</strong><br>
                            <?= date('d/m/Y H:i', strtotime($request['requested_at'])) ?>
                        </p>
                        <p class="mb-0"><strong>Por vendedor:</strong><br>
                            <?= htmlspecialchars($request['seller_name']) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
