<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$customerId = $_GET['id'] ?? 0;
$isAdmin = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']);

$customerRepo = new Customer();
if ($isAdmin) {
    $customer = $customerRepo->getByIdGlobal($customerId);
} else {
    $customer = $customerRepo->getById($customerId, $companyId);
}

if (!$customer) {
    $_SESSION['error'] = 'Cliente no encontrado';
    header('Location: ' . BASE_URL . '/customers/index_mobile.php');
    exit;
}

$customerCompanyId = $customer['company_id'];

// Get date filters
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Si no hay filtros de fecha, mostrar solo del mes actual
$hasDateFilters = !empty($dateFrom) || !empty($dateTo);
if (!$hasDateFilters && !isset($_GET['all'])) {
    $dateFrom = date('Y-m-01'); // Primer día del mes
    $dateTo = date('Y-m-t');     // Último día del mes
}

// Get customer quotations
$quotationRepo = new Quotation();
$filters = array_filter([
    'customer_id' => $customerId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);
$quotations = $quotationRepo->getQuotationsWithFilters($customerCompanyId, $filters, 1, 50);

// Calculate stats
$totalQuotations = $quotations['total'] ?? 0;
$acceptedQuotations = 0;
$totalAmount = 0;

if (!empty($quotations['quotations'])) {
    foreach ($quotations['quotations'] as $q) {
        if ($q['status'] === 'Accepted') {
            $acceptedQuotations++;
            $totalAmount += $q['total'];
        }
    }
}

$pageTitle = $customer['name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d6efd">
    <title><?= $pageTitle ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--gray-100);
            padding-bottom: 100px;
            font-size: 16px;
            overscroll-behavior: contain;
            margin: 0;
        }

        /* Mobile Header */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            color: white;
            padding: 16px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .mobile-header h1 {
            font-size: 18px;
            margin: 8px 0 0 0;
            font-weight: 600;
        }

        .back-btn {
            color: white;
            font-size: 20px;
            text-decoration: none;
            padding: 8px;
            margin-right: 8px;
        }

        .back-btn:active {
            opacity: 0.7;
        }

        /* Info Card */
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 100px;
            font-size: 14px;
        }

        .info-value {
            color: #212529;
            font-size: 15px;
            flex: 1;
        }

        .info-value a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .info-value a:active {
            opacity: 0.7;
        }

        /* Stats Card */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 0 16px 16px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Section Title */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #212529;
            padding: 0 16px 12px 16px;
            margin-top: 24px;
        }

        /* Quotation Card */
        .quotation-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin: 0 16px 12px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .quotation-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .quotation-number {
            font-size: 16px;
            font-weight: 700;
            color: #212529;
        }

        .quotation-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #0dcaf0; color: white; }
        .status-accepted { background: #198754; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-invoiced { background: #0d6efd; color: white; }

        .quotation-details {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .quotation-amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 12px;
        }

        .quotation-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .action-btn {
            padding: 10px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .action-btn:active {
            transform: scale(0.95);
            background: var(--gray-100);
        }

        .action-btn.primary { border-color: var(--primary-color); color: var(--primary-color); }
        .action-btn.success { border-color: var(--success-color); color: var(--success-color); }

        /* Action Buttons Fixed Bottom */
        .bottom-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 16px;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            z-index: 999;
        }

        .bottom-actions .action-btn {
            min-height: 48px;
            flex-direction: column;
            gap: 4px;
        }

        .bottom-actions .action-btn i {
            font-size: 18px;
        }

        .bottom-actions .action-btn span {
            font-size: 11px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            background: white;
            border-radius: 12px;
            margin: 0 16px;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div style="font-size: 12px; opacity: 0.9;">Cliente</div>
                <h1><?= htmlspecialchars($customer['name']) ?></h1>
            </div>
        </div>
    </div>

    <!-- Customer Info Card -->
    <div class="info-card">
        <?php if ($customer['contact_person']): ?>
        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-user"></i> Contacto
            </div>
            <div class="info-value"><?= htmlspecialchars($customer['contact_person']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($customer['email']): ?>
        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-envelope"></i> Email
            </div>
            <div class="info-value">
                <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                    <?= htmlspecialchars($customer['email']) ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($customer['phone']): ?>
        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-phone"></i> Teléfono
            </div>
            <div class="info-value">
                <a href="tel:<?= htmlspecialchars($customer['phone']) ?>">
                    <?= htmlspecialchars($customer['phone']) ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($customer['tax_id']): ?>
        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-id-card"></i> Documento
            </div>
            <div class="info-value"><?= htmlspecialchars($customer['tax_id']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($customer['address']): ?>
        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-map-marker-alt"></i> Dirección
            </div>
            <div class="info-value"><?= nl2br(htmlspecialchars($customer['address'])) ?></div>
        </div>
        <?php endif; ?>

        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-calendar"></i> Registrado
            </div>
            <div class="info-value"><?= date('d/m/Y', strtotime($customer['created_at'])) ?></div>
        </div>

        <?php if (!empty($customer['owner_name'])): ?>
        <div class="info-row">
            <div class="info-label">
                <i class="fas fa-user-tie"></i> Vendedor
            </div>
            <div class="info-value"><?= htmlspecialchars($customer['owner_name']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stats Card -->
    <div class="stats-card">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value" style="color: var(--primary-color);"><?= $totalQuotations ?></div>
                <div class="stat-label">Cotizaciones</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: var(--success-color);"><?= $acceptedQuotations ?></div>
                <div class="stat-label">Aceptadas</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" style="color: var(--warning-color);">
                    <?= $totalQuotations - $acceptedQuotations ?>
                </div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div style="background: white; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#dateFilterCollapse" style="border-radius: 8px; min-height: 48px; font-size: 16px;">
            <i class="fas fa-calendar"></i> Filtrar por fecha
            <?php if ($hasDateFilters): ?>
                <span class="badge bg-primary">1</span>
            <?php endif; ?>
        </button>

        <div class="collapse <?= $hasDateFilters ? 'show' : '' ?>" id="dateFilterCollapse" style="margin-top: 12px;">
            <form method="GET">
                <input type="hidden" name="id" value="<?= $customerId ?>">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label" style="font-size: 14px; font-weight: 600;">Desde</label>
                        <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                               style="min-height: 48px; font-size: 16px; border-radius: 8px;">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size: 14px; font-weight: 600;">Hasta</label>
                        <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                               style="min-height: 48px; font-size: 16px; border-radius: 8px;">
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; min-height: 48px; font-size: 16px; border-radius: 8px;">
                        <i class="fas fa-search"></i> Aplicar
                    </button>
                    <a href="?id=<?= $customerId ?>" class="btn btn-outline-secondary" style="flex: 1; min-height: 48px; font-size: 16px; border-radius: 8px;">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Info message if showing only current month -->
    <?php if (!$hasDateFilters && !isset($_GET['all'])): ?>
        <?php
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $mesActual = $meses[date('n', strtotime($dateFrom)) - 1];
        $anioActual = date('Y', strtotime($dateFrom));
        ?>
        <div class="alert alert-info" style="margin-bottom: 16px; border-radius: 12px; font-size: 14px;">
            <i class="fas fa-info-circle"></i> Mostrando cotizaciones de <strong><?= $mesActual ?> <?= $anioActual ?></strong>
            <a href="?id=<?= $customerId ?>&all=1" class="btn btn-sm btn-outline-primary" style="float: right; font-size: 12px;">
                <i class="fas fa-list"></i> Ver todas
            </a>
        </div>
    <?php endif; ?>

    <!-- Quotations Section -->
    <?php if (!empty($quotations['quotations'])): ?>
        <div class="section-title">
            <i class="fas fa-file-invoice"></i> Cotizaciones (<?= count($quotations['quotations']) ?>)
        </div>

        <?php foreach ($quotations['quotations'] as $quotation): ?>
            <?php
            $statusClasses = [
                'Draft' => 'status-draft',
                'Sent' => 'status-sent',
                'Accepted' => 'status-accepted',
                'Rejected' => 'status-rejected',
                'Invoiced' => 'status-invoiced'
            ];
            $statusNames = [
                'Draft' => 'Borrador',
                'Sent' => 'Enviada',
                'Accepted' => 'Aceptada',
                'Rejected' => 'Rechazada',
                'Invoiced' => 'Facturada'
            ];
            $statusClass = $statusClasses[$quotation['status']] ?? 'status-draft';
            $statusName = $statusNames[$quotation['status']] ?? $quotation['status'];
            ?>
            <div class="quotation-card">
                <div class="quotation-header">
                    <div class="quotation-number">
                        #<?= htmlspecialchars($quotation['quotation_number']) ?>
                    </div>
                    <div class="quotation-status <?= $statusClass ?>">
                        <?= $statusName ?>
                    </div>
                </div>

                <div class="quotation-details">
                    <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?>
                    <?php if ($quotation['valid_until']): ?>
                        • Válida hasta: <?= date('d/m/Y', strtotime($quotation['valid_until'])) ?>
                    <?php endif; ?>
                </div>

                <div class="quotation-amount">
                    <?= $quotation['currency'] === 'USD' ? '$' : 'S/' ?>
                    <?= number_format($quotation['total'], 2) ?>
                </div>

                <div class="quotation-actions">
                    <a href="<?= BASE_URL ?>/quotations/view_mobile.php?id=<?= $quotation['id'] ?>"
                       class="action-btn primary">
                        <i class="fas fa-eye"></i> Ver Detalles
                    </a>
                    <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>"
                       class="action-btn success" target="_blank">
                        <i class="fas fa-file-pdf"></i> Ver PDF
                    </a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($quotations['total_pages'] > 1): ?>
            <div style="text-align: center; padding: 16px; color: #6c757d; font-size: 14px;">
                Mostrando <?= count($quotations['quotations']) ?> de <?= $quotations['total'] ?> cotizaciones
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="section-title">
            <i class="fas fa-file-invoice"></i> Cotizaciones
        </div>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h3>Sin cotizaciones</h3>
            <p>Este cliente aún no tiene cotizaciones registradas</p>
        </div>
    <?php endif; ?>

    <!-- Bottom Actions -->
    <div class="bottom-actions">
        <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>"
           class="action-btn primary">
            <i class="fas fa-edit"></i>
            <span>Editar</span>
        </a>
        <a href="<?= BASE_URL ?>/quotations/create_mobile.php?customer_id=<?= $customer['id'] ?>"
           class="action-btn success">
            <i class="fas fa-plus"></i>
            <span>Cotizar</span>
        </a>
        <button onclick="deleteCustomer()" class="action-btn danger">
            <i class="fas fa-trash"></i>
            <span>Eliminar</span>
        </button>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const customerId = <?= $customer['id'] ?>;
        const totalQuotations = <?= $totalQuotations ?>;

        function deleteCustomer() {
            let confirmMessage = '¿Está seguro de eliminar este cliente?';

            if (totalQuotations > 0) {
                confirmMessage += `\n\n⚠️ Este cliente tiene ${totalQuotations} cotización${totalQuotations > 1 ? 'es' : ''} asociada${totalQuotations > 1 ? 's' : ''} que también ${totalQuotations > 1 ? 'serán eliminadas' : 'será eliminada'}.`;
            }

            if (confirm(confirmMessage)) {
                fetch(`${BASE_URL}/api/delete_customer.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: customerId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Cliente eliminado exitosamente');
                        window.location.href = `${BASE_URL}/customers/index_mobile.php`;
                    } else {
                        alert('❌ Error: ' + (data.message || 'No se pudo eliminar el cliente'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error al eliminar el cliente');
                });
            }
        }
    </script>
</body>
</html>
