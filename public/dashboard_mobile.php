<?php
ob_start(); // Buffer output so session_start() can be called anywhere without "headers already sent" errors
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Optimized dashboard statistics - only basic counts
$db = getDBConnection();
$stats = [];

try {
    // Get quotation stats efficiently - filtered by current user (salesperson)
    $stmt = $db->prepare("SELECT
        COUNT(*) as total_quotations,
        SUM(CASE WHEN status = 'Accepted' THEN total ELSE 0 END) as accepted_amount,
        SUM(CASE WHEN status = 'Draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) as sent_count
        FROM quotations WHERE company_id = ? AND user_id = ?");
    $stmt->execute([$companyId, $user['id']]);
    $quotationStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get other counts
    $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $customersCount = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $productsCount = $stmt->fetchColumn();

    $stats = [
        'quotations' => $quotationStats,
        'customers_count' => $customersCount,
        'products_count' => $productsCount
    ];

    // Get recent quotations (only 5 for mobile) - filtered by current user (salesperson)
    $stmt = $db->prepare("SELECT q.id, q.quotation_number, q.quotation_date, q.total, q.status, c.name as customer_name
        FROM quotations q
        JOIN customers c ON q.customer_id = c.id
        WHERE q.company_id = ? AND q.user_id = ?
        ORDER BY q.created_at DESC LIMIT 5");
    $stmt->execute([$companyId, $user['id']]);
    $recentQuotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = [
        'quotations' => ['total_quotations' => 0, 'accepted_amount' => 0, 'draft_count' => 0, 'sent_count' => 0],
        'customers_count' => 0,
        'products_count' => 0
    ];
    $recentQuotations = [];
}

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $pageTitle ?></title>
    <?php include __DIR__ . '/../includes/pwa_head.php'; ?>

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

        .mobile-header .welcome {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 4px;
        }

        /* Container */
        .container-mobile {
            padding: 16px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .stat-card:active {
            transform: scale(0.98);
        }

        .stat-card.primary { border-left: 4px solid var(--primary-color); }
        .stat-card.success { border-left: 4px solid var(--success-color); }
        .stat-card.info { border-left: 4px solid var(--info-color); }
        .stat-card.warning { border-left: 4px solid var(--warning-color); }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: #6c757d;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 24px;
            opacity: 0.3;
            float: right;
        }

        /* Section Title */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #212529;
            margin: 24px 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Quick Actions Grid */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            min-height: 100px;
            justify-content: center;
        }

        .action-card:active {
            transform: scale(0.95);
        }

        .action-card i {
            font-size: 32px;
            margin-bottom: 4px;
        }

        .action-card span {
            font-size: 13px;
            font-weight: 600;
        }

        .action-card.primary { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .action-card.success { background: linear-gradient(135deg, #198754 0%, #146c43 100%); }
        .action-card.info { background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%); }
        .action-card.warning { background: linear-gradient(135deg, #ffc107 0%, #cc9a06 100%); }

        /* Quotation Card */
        .quotation-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s;
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
            font-size: 16px;
            font-weight: 700;
            color: #212529;
        }

        .quotation-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #0dcaf0; color: white; }
        .status-accepted { background: #198754; color: white; }
        .status-rejected { background: #dc3545; color: white; }

        .quotation-customer {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }

        .quotation-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
        }

        .quotation-date {
            font-size: 13px;
            color: #6c757d;
        }

        .quotation-total {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .empty-state p {
            margin-bottom: 16px;
        }

        /* View All Link */
        .view-all {
            display: inline-block;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <div class="welcome">Bienvenido, <?= htmlspecialchars($user['first_name'] ?: $user['username']) ?></div>
            </div>
            <div class="d-flex gap-2">
                <!-- Notification Bell -->
                <?php include __DIR__ . '/../includes/notification_bell.php'; ?>

                <!-- Menu -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_mobile.php"><i class="fas fa-tachometer-alt"></i> Inicio</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/index_mobile.php"><i class="fas fa-file-invoice"></i> Cotizaciones</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/customers/index_mobile.php"><i class="fas fa-users"></i> Clientes</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/products/index.php"><i class="fas fa-box"></i> Productos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/reports/index.php"><i class="fas fa-chart-bar"></i> Reportes</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/activities/index.php"><i class="fas fa-history"></i> Actividades</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="fas fa-user-circle"></i> Perfil</a></li>
                        <?php if ($auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php"><i class="fas fa-cog"></i> Panel Admin</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-mobile">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <i class="fas fa-file-invoice stat-icon"></i>
                <div class="stat-value"><?= number_format($stats['quotations']['total_quotations'] ?? 0) ?></div>
                <div class="stat-label">Cotizaciones</div>
            </div>

            <div class="stat-card success">
                <i class="fas fa-dollar-sign stat-icon"></i>
                <div class="stat-value">S/ <?= number_format($stats['quotations']['accepted_amount'] ?? 0, 0) ?></div>
                <div class="stat-label">Aceptado</div>
            </div>

            <div class="stat-card info">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?= number_format($stats['customers_count']) ?></div>
                <div class="stat-label">Clientes</div>
            </div>

            <div class="stat-card warning">
                <i class="fas fa-box stat-icon"></i>
                <div class="stat-value"><?= number_format($stats['products_count']) ?></div>
                <div class="stat-label">Productos</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-title">
            <i class="fas fa-bolt"></i> Acciones Rápidas
        </div>

        <div class="quick-actions">
            <a href="<?= BASE_URL ?>/quotations/create_mobile.php" class="action-card primary">
                <i class="fas fa-plus"></i>
                <span>Nueva<br>Cotización</span>
            </a>

            <a href="<?= BASE_URL ?>/customers/create.php" class="action-card success">
                <i class="fas fa-user-plus"></i>
                <span>Nuevo<br>Cliente</span>
            </a>

            <a href="<?= BASE_URL ?>/products/index.php" class="action-card info">
                <i class="fas fa-search"></i>
                <span>Consultar<br>Stock</span>
            </a>

            <a href="<?= BASE_URL ?>/quotations/index_mobile.php" class="action-card warning">
                <i class="fas fa-list"></i>
                <span>Ver<br>Cotizaciones</span>
            </a>
        </div>

        <!-- Recent Quotations -->
        <div class="section-title">
            <i class="fas fa-clock"></i> Cotizaciones Recientes
            <a href="<?= BASE_URL ?>/quotations/index_mobile.php" class="view-all">Ver todas <i class="fas fa-chevron-right"></i></a>
        </div>

        <?php if (!empty($recentQuotations)): ?>
            <?php foreach ($recentQuotations as $quotation): ?>
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
                <a href="<?= BASE_URL ?>/quotations/view_mobile.php?id=<?= $quotation['id'] ?>" class="quotation-card">
                    <div class="quotation-header">
                        <div class="quotation-number"><?= htmlspecialchars($quotation['quotation_number']) ?></div>
                        <span class="quotation-status <?= $statusClass ?>"><?= $statusName ?></span>
                    </div>
                    <div class="quotation-customer">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($quotation['customer_name']) ?>
                    </div>
                    <div class="quotation-footer">
                        <div class="quotation-date">
                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?>
                        </div>
                        <div class="quotation-total">
                            S/ <?= number_format($quotation['total'], 2) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <p>No hay cotizaciones recientes</p>
                <a href="<?= BASE_URL ?>/quotations/create_mobile.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crear primera cotización
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
