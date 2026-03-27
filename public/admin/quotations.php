<?php
// cotizacion/public/admin/quotations.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$quotationRepo = new Quotation(); // Autoloaded

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "User or company information is missing. Please re-login.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

// Role check: 'Administrador de Empresa' or 'Vendedor'
if (!$auth->hasRole(['Administrador de Empresa', 'Vendedor'])) {
    $_SESSION['error_message'] = "You are not authorized to manage quotations.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$quotations = $quotationRepo->getAllByCompany($company_id);

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quotations - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; }
        .admin-content { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-content h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        table th { background-color: #f0f0f0; }
        .actions a { margin-right: 10px; color: #007bff; text-decoration: none; }
        .actions a.delete { color: #dc3545; }
        .actions a:hover { text-decoration: underline; }
        .add-button { display: inline-block; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        .add-button:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-Draft { color: #6c757d; font-weight:bold; }
        .status-Sent { color: #007bff; font-weight:bold; }
        .status-Accepted { color: #28a745; font-weight:bold; }
        .status-Rejected { color: #dc3545; font-weight:bold; }
        .status-Invoiced { color: #ffc107; font-weight:bold; }
    </style>
    <style>
    /* EMERGENCY LIGHT THEME ENFORCEMENT - Override ANY dark styles */
    html, body {
        background-color: #ffffff !important;
        color: #212529 !important;
    }

    html[data-theme="dark"] body {
        background-color: #121212 !important;
        color: #e0e0e0 !important;
    }

    /* Force all components to light theme unless in dark mode */
    body:not([data-theme="dark"]) * {
        --bs-body-bg: #ffffff !important;
        --bs-body-color: #212529 !important;
        --bs-border-color: #dee2e6 !important;
    }

    /* Ultra-specific overrides for stubborn dark elements */
    body:not([data-theme="dark"]) .card,
    body:not([data-theme="dark"]) .modal-content,
    body:not([data-theme="dark"]) .form-control,
    body:not([data-theme="dark"]) .form-select,
    body:not([data-theme="dark"]) .table,
    body:not([data-theme="dark"]) .table td,
    body:not([data-theme="dark"]) .table th,
    body:not([data-theme="dark"]) .dropdown-menu,
    body:not([data-theme="dark"]) .list-group-item,
    body:not([data-theme="dark"]) .page-link,
    body:not([data-theme="dark"]) .breadcrumb,
    body:not([data-theme="dark"]) .accordion-item,
    body:not([data-theme="dark"]) .offcanvas,
    body:not([data-theme="dark"]) .toast {
        background-color: #ffffff !important;
        color: #212529 !important;
        border-color: #dee2e6 !important;
    }

    /* Force navbar to be blue with white text */
    .navbar,
    .navbar-dark,
    .navbar-light {
        background-color: #0d6efd !important;
    }

    .navbar .navbar-brand,
    .navbar .navbar-nav .nav-link,
    .navbar-dark .navbar-brand,
    .navbar-dark .navbar-nav .nav-link,
    .navbar-light .navbar-brand,
    .navbar-light .navbar-nav .nav-link {
        color: #ffffff !important;
    }
    </style>

    <script>
    // Emergency theme enforcement
    (function() {
        // Remove any dark theme attributes on page load
        document.documentElement.removeAttribute('data-theme');

        // Set light theme in localStorage if not explicitly dark
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme !== 'dark') {
            localStorage.setItem('theme', 'light');
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Force body styles
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'light') {
                document.body.style.backgroundColor = '#ffffff';
                document.body.style.color = '#212529';
            }
        });
    })();
    </script>
</head>
<body>

    <header class="admin-header">
        <h1>Admin Panel</h1>
    </header>

    <div class="user-info">
        Logged in as: <?php echo htmlspecialchars($loggedInUser['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($loggedInUser['id']), 'role_name'))); ?>) |
        Company ID: <?php echo htmlspecialchars($company_id); ?> |
        <a href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
    </div>

    <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Admin Home</a>
        <?php if ($auth->hasRole('Administrador del Sistema')): ?>
            <a href="<?php echo BASE_URL; ?>/admin/companies.php">Manage Companies</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Administrador de Empresa', 'Vendedor', 'Administrador del Sistema'])): ?>
             <a href="<?php echo BASE_URL; ?>/admin/customers.php">Manage Customers</a>
             <a href="<?php echo BASE_URL; ?>/admin/quotations.php">Manage Quotations</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Manage Products</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Manage Warehouses</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Manage Stock</a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Main Dashboard</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2>Manage Quotations (Company: <?php echo htmlspecialchars($company_id); ?>)</h2>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <a href="quotation_form.php" class="add-button">Create New Quotation</a>

            <table>
                <thead>
                    <tr>
                        <th>Quotation #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Creator</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quotations)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No quotations found for your company.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quotations as $quotation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($quotation['quotation_number']); ?></td>
                                <td><?php echo htmlspecialchars($quotation['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars(date("M d, Y", strtotime($quotation['quotation_date']))); ?></td>
                                 <td><?php $symbol = $quotation['currency'] == 'PEN' ? 'S/' : $quotation['currency']; echo htmlspecialchars($symbol . ' ' . number_format($quotation['total'], 2)); ?></td>
                                <td><span class="status-<?php echo htmlspecialchars(str_replace(' ', '', $quotation['status'])); ?>"><?php echo htmlspecialchars($quotation['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($quotation['creator_username']); ?></td>
                                <td class="actions">
                                    <a href="quotation_view.php?id=<?php echo $quotation['id']; ?>">View</a>
                                    <?php if ($quotation['status'] == 'Draft' || $auth->hasRole('Administrador de Empresa')): // Allow edit for Drafts or if Company Admin ?>
                                        <a href="quotation_form.php?id=<?php echo $quotation['id']; ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if ($auth->hasRole('Administrador de Empresa')): // Only Company Admin can delete for now ?>
                                        <!-- <a href="quotation_delete.php?id=<?php echo $quotation['id']; ?>" class="delete" onclick="return confirm('Are you sure? This cannot be undone.');">Delete</a> -->
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
