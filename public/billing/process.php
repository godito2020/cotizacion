<?php
/**
 * Process Billing Request - Billing User Interface
 * Allows billing users to approve or reject billing requests
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

// Handle AJAX approve request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    header('Content-Type: application/json');

    $trackingId = $_POST['tracking_id'] ?? 0;
    $invoiceNumber = $_POST['invoice_number'] ?? '';
    $observations = $_POST['observations'] ?? null;

    $billingManager = new BillingManager();
    $result = $billingManager->approveBilling($trackingId, $userId, $invoiceNumber, $observations);

    echo json_encode($result);
    exit;
}

// Handle AJAX reject request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    header('Content-Type: application/json');

    $trackingId = $_POST['tracking_id'] ?? 0;
    $rejectionReason = $_POST['rejection_reason'] ?? '';

    $billingManager = new BillingManager();
    $result = $billingManager->rejectBilling($trackingId, $userId, $rejectionReason);

    echo json_encode($result);
    exit;
}

// Get tracking ID
$trackingId = $_GET['id'] ?? 0;

// Check if user ONLY has Facturación role
$userRepo = new User();
$userRoles = $userRepo->getRoles($userId);
$isOnlyBilling = (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación');

// Get tracking details
$stmt = $db->prepare("
    SELECT
        t.*,
        q.quotation_number,
        q.quotation_date,
        q.total,
        q.currency,
        q.notes,
        c.name as customer_name,
        c.tax_id as customer_tax_id,
        c.email as customer_email,
        c.phone as customer_phone,
        u.username as seller_name,
        u.email as seller_email
    FROM quotation_billing_tracking t
    JOIN quotations q ON t.quotation_id = q.id
    JOIN customers c ON q.customer_id = c.id
    JOIN users u ON t.seller_id = u.id
    WHERE t.id = ? AND t.company_id = ? AND t.status IN ('Pending', 'In_Process')
");
$stmt->execute([$trackingId, $companyId]);
$tracking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tracking) {
    $_SESSION['error_message'] = 'Solicitud no encontrada o ya fue procesada';
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

$pageTitle = 'Procesar Solicitud de Facturación';
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

    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-tasks"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/billing/pending.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>

                <!-- Quotation Details -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Información de la Cotización</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Datos de la Cotización</h6>
                                <p class="mb-2"><strong>Número:</strong> <?= htmlspecialchars($tracking['quotation_number']) ?></p>
                                <p class="mb-2"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($tracking['quotation_date'])) ?></p>
                                <p class="mb-2"><strong>Total:</strong> <span class="fs-5 text-success"><?= $tracking['currency'] ?> <?= number_format($tracking['total'], 2) ?></span></p>
                                <?php if ($tracking['notes']): ?>
                                    <p class="mb-2"><strong>Notas de Cotización:</strong><br>
                                    <small><?= nl2br(htmlspecialchars($tracking['notes'])) ?></small></p>
                                <?php endif; ?>
                                <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $tracking['quotation_id'] ?>"
                                   target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-external-link-alt"></i> Ver Cotización Completa
                                </a>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Datos del Cliente</h6>
                                <p class="mb-2"><strong>Nombre:</strong> <?= htmlspecialchars($tracking['customer_name']) ?></p>
                                <?php if ($tracking['customer_tax_id']): ?>
                                    <p class="mb-2"><strong><?= strlen($tracking['customer_tax_id']) == 8 ? 'DNI' : 'RUC' ?>:</strong> <?= htmlspecialchars($tracking['customer_tax_id']) ?></p>
                                <?php endif; ?>
                                <?php if ($tracking['customer_email']): ?>
                                    <p class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($tracking['customer_email']) ?></p>
                                <?php endif; ?>
                                <?php if ($tracking['customer_phone']): ?>
                                    <p class="mb-2"><strong>Teléfono:</strong> <?= htmlspecialchars($tracking['customer_phone']) ?></p>
                                <?php endif; ?>

                                <h6 class="text-primary mt-4 mb-3">Vendedor</h6>
                                <p class="mb-2"><strong>Nombre:</strong> <?= htmlspecialchars($tracking['seller_name']) ?></p>
                                <p class="mb-2"><strong>Email:</strong> <?= htmlspecialchars($tracking['seller_email']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Request Details -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Detalles de la Solicitud</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Fecha de Solicitud:</strong> <?= date('d/m/Y H:i', strtotime($tracking['requested_at'])) ?></p>
                        <?php if ($tracking['observations']): ?>
                            <p class="mb-2"><strong>Observaciones del Vendedor:</strong></p>
                            <div class="alert alert-light">
                                <?= nl2br(htmlspecialchars($tracking['observations'])) ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Sin observaciones</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row">
                    <div class="col-md-6">
                        <!-- Approve Form -->
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-check-circle"></i> Aprobar Facturación</h5>
                            </div>
                            <div class="card-body">
                                <form id="approveForm">
                                    <input type="hidden" name="tracking_id" value="<?= $trackingId ?>">
                                    <input type="hidden" name="action" value="approve">

                                    <div class="mb-3">
                                        <label for="invoice_number" class="form-label"><strong>Número de Factura *</strong></label>
                                        <input type="text" class="form-control" id="invoice_number" name="invoice_number"
                                               required placeholder="Ej: F001-00001234">
                                    </div>

                                    <div class="mb-3">
                                        <label for="approve_observations" class="form-label">Observaciones (Opcional)</label>
                                        <textarea class="form-control" id="approve_observations" name="observations"
                                                  rows="3" placeholder="Observaciones adicionales..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-check-circle"></i> Aprobar y Facturar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Reject Form -->
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-times-circle"></i> Rechazar Solicitud</h5>
                            </div>
                            <div class="card-body">
                                <form id="rejectForm">
                                    <input type="hidden" name="tracking_id" value="<?= $trackingId ?>">
                                    <input type="hidden" name="action" value="reject">

                                    <div class="mb-3">
                                        <label for="rejection_reason" class="form-label"><strong>Motivo de Rechazo *</strong></label>
                                        <textarea class="form-control" id="rejection_reason" name="rejection_reason"
                                                  rows="6" required
                                                  placeholder="Explique el motivo del rechazo de forma clara para que el vendedor pueda tomar acciones correctivas..."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-times-circle"></i> Rechazar Solicitud
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Approve Form Handler
        document.getElementById('approveForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const invoiceNumber = document.getElementById('invoice_number').value.trim();
            if (!invoiceNumber) {
                alert('❌ Por favor ingrese el número de factura');
                return;
            }

            if (!confirm('¿Aprobar y facturar esta cotización con el número: ' + invoiceNumber + '?')) {
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            const formData = new FormData(this);

            fetch('<?= BASE_URL ?>/billing/process.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    window.location.href = '<?= BASE_URL ?>/billing/pending.php';
                } else {
                    alert('❌ Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                alert('❌ Error al procesar: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });

        // Reject Form Handler
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const reason = document.getElementById('rejection_reason').value.trim();
            if (!reason) {
                alert('❌ Por favor ingrese el motivo del rechazo');
                return;
            }

            if (!confirm('¿Está seguro de rechazar esta solicitud de facturación?')) {
                return;
            }

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rechazando...';

            const formData = new FormData(this);

            fetch('<?= BASE_URL ?>/billing/process.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    window.location.href = '<?= BASE_URL ?>/billing/pending.php';
                } else {
                    alert('❌ Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                alert('❌ Error al procesar: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
