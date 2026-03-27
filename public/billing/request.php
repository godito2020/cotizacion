<?php
/**
 * Billing Request - Vendor Interface
 * Allows vendors to mark accepted quotations for billing
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userId = $auth->getUserId();
$companyId = $auth->getCompanyId();

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $quotationId = $_POST['quotation_id'] ?? 0;
    $observations = $_POST['observations'] ?? null;

    $billingManager = new BillingManager();
    $result = $billingManager->requestBilling($quotationId, $userId, $companyId, $observations);

    echo json_encode($result);
    exit;
}

// Get quotation details if ID is provided
$quotationId = $_GET['id'] ?? 0;
$quotation = null;

if ($quotationId) {
    $quotationRepo = new Quotation();
    $quotation = $quotationRepo->getById($quotationId, $companyId);

    // Verify ownership and status
    if (!$quotation || $quotation['user_id'] != $userId || $quotation['status'] !== 'Accepted') {
        $_SESSION['error_message'] = 'Cotización no válida para facturación';
        header('Location: ' . BASE_URL . '/quotations/index.php');
        exit;
    }
}

$pageTitle = 'Solicitar Facturación';
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

    <main class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-file-invoice"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>

                <?php if ($quotation): ?>
                    <!-- Quotation Summary -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-file-invoice"></i> Resumen de Cotización</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Número:</strong> <?= htmlspecialchars($quotation['quotation_number']) ?></p>
                                    <p class="mb-2"><strong>Cliente:</strong> <?= htmlspecialchars($quotation['customer_name']) ?></p>
                                    <p class="mb-2"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Total:</strong> <?= $quotation['currency'] ?> <?= number_format($quotation['total'], 2) ?></p>
                                    <p class="mb-2"><strong>Estado:</strong> <span class="badge bg-success">Aceptada</span></p>
                                    <?php if ($quotation['billing_status']): ?>
                                        <p class="mb-2"><strong>Estado Facturación:</strong>
                                            <span class="badge bg-warning"><?= htmlspecialchars($quotation['billing_status']) ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Request Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Solicitar Facturación</h5>
                        </div>
                        <div class="card-body">
                            <form id="billingRequestForm">
                                <input type="hidden" name="quotation_id" value="<?= $quotationId ?>">
                                <input type="hidden" name="ajax" value="1">

                                <div class="mb-3">
                                    <label for="observations" class="form-label">Observaciones (Opcional)</label>
                                    <textarea class="form-control" id="observations" name="observations" rows="4"
                                              placeholder="Ingrese cualquier observación o nota para el equipo de facturación..."></textarea>
                                    <small class="text-muted">Por ejemplo: cliente solicita factura urgente, datos específicos para la factura, etc.</small>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>¿Qué sucede después?</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Su solicitud será enviada al equipo de facturación</li>
                                        <li>Recibirá una notificación cuando sea procesada</li>
                                        <li>Puede revisar el estado en "Mis Solicitudes de Facturación"</li>
                                    </ul>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Enviar Solicitud
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No se especificó una cotización válida.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('billingRequestForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

            const formData = new FormData(this);

            fetch('<?= BASE_URL ?>/billing/request.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Solicitud de facturación enviada exitosamente');
                    window.location.href = '<?= BASE_URL ?>/quotations/view.php?id=<?= $quotationId ?>';
                } else {
                    alert('❌ Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                alert('❌ Error al enviar solicitud: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
