<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Check if user is admin (can delete customers)
$isAdmin = $auth->hasRole(['Admin', 'Administrador', 'Administrador del Sistema', 'Administrador de Empresa']);

// Get customers
$customerRepo = new Customer();
if ($isAdmin) {
    $customers = $customerRepo->getAll();
} else {
    $customers = $customerRepo->getAllByCompany($companyId);
}

// Handle search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $customers = array_filter($customers, function($customer) use ($search) {
        return stripos($customer['name'], $search) !== false ||
               stripos($customer['email'], $search) !== false ||
               stripos($customer['phone'], $search) !== false ||
               stripos($customer['tax_id'], $search) !== false;
    });
}

$pageTitle = 'Clientes';
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

        /* Search Bar */
        .search-bar {
            background: white;
            padding: 16px;
            margin-bottom: 16px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .search-bar .form-control {
            min-height: 48px;
            font-size: 16px;
            border-radius: 8px;
        }

        .search-bar .btn {
            min-height: 48px;
            font-size: 16px;
            font-weight: 600;
        }

        /* Customer Card */
        .customer-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .customer-card:active {
            transform: scale(0.98);
        }

        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .customer-name {
            font-size: 18px;
            font-weight: 700;
            color: #212529;
            margin: 0;
        }

        .customer-tax {
            font-size: 13px;
            color: #6c757d;
            margin-top: 2px;
        }

        .customer-details {
            margin-bottom: 12px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            font-size: 14px;
            color: #495057;
        }

        .detail-row i {
            width: 20px;
            color: var(--primary-color);
            font-size: 14px;
        }

        .detail-row a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .detail-row a:active {
            opacity: 0.7;
        }

        .customer-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .meta-badge {
            padding: 4px 10px;
            border-radius: 12px;
            background: var(--gray-200);
            color: #495057;
            font-weight: 600;
        }

        .meta-badge i {
            font-size: 11px;
            margin-right: 4px;
        }

        .customer-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }

        .customer-actions.with-delete {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }

        .action-btn {
            padding: 10px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 8px;
            font-size: 12px;
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
        .action-btn.info { border-color: var(--info-color); color: var(--info-color); }
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

        /* Container */
        .container-mobile {
            padding: 16px;
        }

        /* Stats */
        .stats-row {
            display: flex;
            justify-content: space-around;
            background: white;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1><i class="fas fa-users"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-sm btn-light">
                <i class="fas fa-home"></i>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-mobile">
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" class="d-flex gap-2">
                <input type="text"
                       class="form-control"
                       name="search"
                       placeholder="Buscar por nombre, email, teléfono, RUC..."
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value"><?= count($customers) ?></div>
                <div class="stat-label">Cliente<?= count($customers) != 1 ? 's' : '' ?></div>
            </div>
            <?php if (!empty($search)): ?>
                <div class="stat-item">
                    <div class="stat-value" style="color: var(--info-color);">
                        <i class="fas fa-filter"></i>
                    </div>
                    <div class="stat-label">Filtrado</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customers List -->
        <?php if (!empty($customers)): ?>
            <?php foreach ($customers as $customer): ?>
                <div class="customer-card">
                    <div class="customer-header">
                        <div>
                            <h3 class="customer-name"><?= htmlspecialchars($customer['name']) ?></h3>
                            <?php if ($customer['tax_id']): ?>
                                <div class="customer-tax">
                                    <i class="fas fa-id-card"></i> <?= htmlspecialchars($customer['tax_id']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="customer-details">
                        <?php if ($customer['contact_person']): ?>
                            <div class="detail-row">
                                <i class="fas fa-user"></i>
                                <span><?= htmlspecialchars($customer['contact_person']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($customer['email']): ?>
                            <div class="detail-row">
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                                    <?= htmlspecialchars($customer['email']) ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($customer['phone']): ?>
                            <div class="detail-row">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?= htmlspecialchars($customer['phone']) ?>">
                                    <?= htmlspecialchars($customer['phone']) ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($customer['address']): ?>
                            <div class="detail-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($customer['address']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="customer-meta">
                        <?php if (!empty($customer['owner_name'])): ?>
                            <div class="meta-badge">
                                <i class="fas fa-user-tie"></i> <?= htmlspecialchars($customer['owner_name']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($isAdmin && !empty($customer['company_name'])): ?>
                            <div class="meta-badge" style="background: #cfe2ff; color: #084298;">
                                <i class="fas fa-building"></i> <?= htmlspecialchars($customer['company_name']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="meta-badge">
                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($customer['created_at'])) ?>
                        </div>
                    </div>

                    <div class="customer-actions<?= $isAdmin ? ' with-delete' : '' ?>">
                        <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customer['id'] ?>"
                           class="action-btn info">
                            <i class="fas fa-eye"></i>
                            <span>Ver</span>
                        </a>
                        <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customer['id'] ?>"
                           class="action-btn primary">
                            <i class="fas fa-edit"></i>
                            <span>Editar</span>
                        </a>
                        <a href="<?= BASE_URL ?>/quotations/create_mobile.php?customer_id=<?= $customer['id'] ?>"
                           class="action-btn success">
                            <i class="fas fa-file-invoice"></i>
                            <span>Cotizar</span>
                        </a>
                        <?php if ($isAdmin): ?>
                        <button onclick="deleteCustomer(<?= $customer['id'] ?>)"
                                class="action-btn danger">
                            <i class="fas fa-trash"></i>
                            <span>Eliminar</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No hay clientes</h3>
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        No se encontraron clientes con el término: <strong><?= htmlspecialchars($search) ?></strong>
                    <?php else: ?>
                        Aún no has registrado clientes
                    <?php endif; ?>
                </p>
                <?php if (empty($search)): ?>
                    <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-success mt-3">
                        <i class="fas fa-plus"></i> Registrar Primer Cliente
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- FAB - Nuevo Cliente -->
    <button class="fab" onclick="window.location.href='<?= BASE_URL ?>/customers/create.php'">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($isAdmin): ?>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';

        function deleteCustomer(customerId) {
            if (confirm('¿Está seguro de eliminar este cliente?\n\nSe eliminarán también todas sus cotizaciones asociadas.')) {
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
                        window.location.reload();
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
    <?php endif; ?>
</body>
</html>
