<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Get filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);

// Si no hay filtros aplicados, mostrar solo del mes actual
$hasFilters = !empty($status) || !empty($search) || !empty($dateFrom) || !empty($dateTo);
if (!$hasFilters && !isset($_GET['all'])) {
    // Por defecto mostrar cotizaciones del mes actual
    $dateFrom = date('Y-m-01'); // Primer día del mes
    $dateTo = date('Y-m-t');     // Último día del mes
}

$filters = array_filter([
    'status' => $status,
    'search' => $search,
    'date_from' => $dateFrom,
    'date_to' => $dateTo
]);

// Check if user can view all quotations (admins always can)
if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']) && !($user['can_view_all_quotations'] ?? 0)) {
    $filters['user_id'] = $user['id'];
}

// Get quotations
$quotationRepo = new Quotation();
$result = $quotationRepo->getQuotationsWithFilters($companyId, $filters, $page, 20);

$pageTitle = ($user['can_view_all_quotations'] ?? 0) ? 'Cotizaciones' : 'Mis Cotizaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?></title>
    <?php include __DIR__ . '/../../includes/pwa_head.php'; ?>

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
            padding-bottom: 80px;
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
            font-size: 20px;
            margin: 0;
            font-weight: 600;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 16px;
            margin-bottom: 16px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-section .form-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #495057;
        }

        .filter-section .form-control,
        .filter-section .form-select {
            min-height: 48px;
            font-size: 16px;
            border-radius: 8px;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .filter-buttons button,
        .filter-buttons a {
            flex: 1;
            min-height: 48px;
            font-size: 16px;
            border-radius: 8px;
            font-weight: 600;
        }

        /* Quotation Card */
        .quotation-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .quotation-card:active {
            transform: scale(0.98);
        }

        .quotation-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .quotation-number {
            font-size: 18px;
            font-weight: 700;
            color: #212529;
        }

        .quotation-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #0dcaf0; color: white; }
        .status-accepted { background: #198754; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .status-invoiced { background: #0d6efd; color: white; }

        .quotation-customer {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        .quotation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }

        .detail-item {
            font-size: 14px;
        }

        .detail-label {
            color: #6c757d;
            font-size: 12px;
            display: block;
        }

        .detail-value {
            color: #212529;
            font-weight: 600;
        }

        .quotation-total {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            text-align: right;
            margin-bottom: 12px;
        }

        .quotation-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
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
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .action-btn:active {
            transform: scale(0.95);
            background: var(--gray-100);
        }

        .action-btn i {
            font-size: 16px;
        }

        .action-btn.primary { border-color: var(--primary-color); color: var(--primary-color); }
        .action-btn.success { border-color: var(--success-color); color: var(--success-color); }
        .action-btn.danger { border-color: var(--danger-color); color: var(--danger-color); }

        /* FAB Button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--success-color);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-size: 24px;
            z-index: 1000;
            transition: transform 0.2s;
        }

        .fab:active {
            transform: scale(0.9);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        /* Pagination */
        .pagination-mobile {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            margin-top: 16px;
        }

        .pagination-mobile .btn {
            min-height: 48px;
            min-width: 48px;
            border-radius: 8px;
        }

        /* Collapse toggle for filters */
        .filter-toggle {
            width: 100%;
            min-height: 48px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .filter-collapse {
            margin-top: 12px;
        }

        .expired-badge {
            color: var(--danger-color);
            font-size: 11px;
            font-weight: 600;
        }

        /* Container */
        .container-mobile {
            padding: 16px;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1><i class="fas fa-file-invoice"></i> <?= $pageTitle ?></h1>
            <div class="d-flex gap-2">
                <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
                <a href="<?= BASE_URL ?>/dashboard_mobile.php" class="btn btn-sm btn-light">
                    <i class="fas fa-home"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-mobile">
        <!-- Filters -->
        <div class="filter-section">
            <button class="btn btn-outline-primary filter-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="fas fa-filter"></i> Filtros
                <?php if (!empty($filters)): ?>
                    <span class="badge bg-primary"><?= count($filters) ?></span>
                <?php endif; ?>
            </button>

            <div class="collapse <?= !empty($filters) ? 'show' : '' ?>" id="filterCollapse">
                <form method="GET" class="filter-collapse">
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="status">
                            <option value="">Todos los estados</option>
                            <option value="Draft" <?= $status === 'Draft' ? 'selected' : '' ?>>Borrador</option>
                            <option value="Sent" <?= $status === 'Sent' ? 'selected' : '' ?>>Enviada</option>
                            <option value="Accepted" <?= $status === 'Accepted' ? 'selected' : '' ?>>Aceptada</option>
                            <option value="Rejected" <?= $status === 'Rejected' ? 'selected' : '' ?>>Rechazada</option>
                            <option value="Invoiced" <?= $status === 'Invoiced' ? 'selected' : '' ?>>Facturada</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="search"
                               placeholder="Número o cliente..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Desde</label>
                            <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Hasta</label>
                            <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <a href="<?= BASE_URL ?>/quotations/index_mobile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info message if showing only current month -->
        <?php if (!$hasFilters && !isset($_GET['all'])): ?>
            <?php
            $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $mesActual = $meses[date('n', strtotime($dateFrom)) - 1];
            $anioActual = date('Y', strtotime($dateFrom));
            ?>
            <div class="alert alert-info" style="margin-bottom: 16px; border-radius: 12px; font-size: 14px;">
                <i class="fas fa-info-circle"></i> Mostrando cotizaciones de <strong><?= $mesActual ?> <?= $anioActual ?></strong>
                <a href="?all=1" class="btn btn-sm btn-outline-primary" style="float: right; font-size: 12px;">
                    <i class="fas fa-list"></i> Ver todas
                </a>
            </div>
        <?php endif; ?>

        <!-- Quotations List -->
        <?php if (!empty($result['quotations'])): ?>
            <?php foreach ($result['quotations'] as $quotation): ?>
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

                $isExpired = $quotation['valid_until'] && strtotime($quotation['valid_until']) < time();
                ?>
                <div class="quotation-card">
                    <div class="quotation-header">
                        <div class="quotation-number">
                            #<?= htmlspecialchars($quotation['quotation_number']) ?>
                        </div>
                        <div>
                            <div class="quotation-status <?= $statusClass ?>">
                                <?= $statusName ?>
                            </div>
                            <?php
                            // Show billing status for accepted quotations
                            if ($quotation['status'] === 'Accepted' && !empty($quotation['billing_status'])):
                                $billingBadgeClass = '';
                                $billingText = '';
                                switch ($quotation['billing_status']) {
                                    case 'Pending_Invoice':
                                        $billingBadgeClass = 'bg-warning text-dark';
                                        $billingText = 'Pendiente Fact.';
                                        break;
                                    case 'Invoiced':
                                        $billingBadgeClass = 'bg-success';
                                        $billingText = 'Facturado';
                                        break;
                                    case 'Invoice_Rejected':
                                        $billingBadgeClass = 'bg-danger';
                                        $billingText = 'Rechazado';
                                        break;
                                }
                            ?>
                                <div class="quotation-status <?= $billingBadgeClass ?>" style="font-size: 11px; margin-top: 4px;">
                                    <i class="fas fa-file-invoice"></i> <?= $billingText ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="quotation-customer">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($quotation['customer_name']) ?>
                    </div>

                    <div class="quotation-details">
                        <div class="detail-item">
                            <span class="detail-label">Fecha</span>
                            <div class="detail-value">
                                <?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Válida hasta</span>
                            <div class="detail-value">
                                <?php if ($quotation['valid_until']): ?>
                                    <?= date('d/m/Y', strtotime($quotation['valid_until'])) ?>
                                    <?php if ($isExpired): ?>
                                        <br><span class="expired-badge">VENCIDA</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Creado por</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($quotation['creator_name'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Moneda</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($quotation['currency'] ?? 'PEN') ?>
                            </div>
                        </div>
                    </div>

                    <div class="quotation-total">
                        <?= $quotation['currency'] === 'USD' ? '$' : 'S/' ?>
                        <?= number_format($quotation['total'], 2) ?>
                    </div>

                    <div class="quotation-actions">
                        <a href="<?= BASE_URL ?>/quotations/view_mobile.php?id=<?= $quotation['id'] ?>"
                           class="action-btn primary">
                            <i class="fas fa-eye"></i>
                            <span>Ver</span>
                        </a>
                        <?php if ($quotation['status'] === 'Draft'): ?>
                            <a href="<?= BASE_URL ?>/quotations/edit.php?id=<?= $quotation['id'] ?>"
                               class="action-btn success">
                                <i class="fas fa-edit"></i>
                                <span>Editar</span>
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/quotations/duplicate.php?id=<?= $quotation['id'] ?>"
                               class="action-btn success">
                                <i class="fas fa-copy"></i>
                                <span>Duplicar</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/quotations/pdf.php?id=<?= $quotation['id'] ?>"
                           class="action-btn danger" target="_blank">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($result['total_pages'] > 1): ?>
                <div class="pagination-mobile">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                           class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <span class="btn btn-primary">
                        <?= $page ?> / <?= $result['total_pages'] ?>
                    </span>

                    <?php if ($page < $result['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                           class="btn btn-outline-primary">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No hay cotizaciones</h3>
                <p class="text-muted">
                    <?php if (!empty($filters)): ?>
                        No se encontraron cotizaciones con los filtros aplicados
                    <?php else: ?>
                        Crea tu primera cotización usando el botón +
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- FAB - Nueva Cotización -->
    <button class="fab" onclick="window.location.href='<?= BASE_URL ?>/quotations/create_mobile.php'">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Define BASE_URL for JavaScript -->
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>

    <!-- PWA -->
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>

    <!-- Boton flotante de instalacion PWA -->
    <button id="pwa-install-btn" onclick="installPWA()" class="btn btn-primary position-fixed d-none align-items-center gap-2"
            style="bottom: 80px; right: 15px; z-index: 1000; border-radius: 50px; padding: 10px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
        <i class="fas fa-download"></i>
        Instalar
    </button>
</body>
</html>
