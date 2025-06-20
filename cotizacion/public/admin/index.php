<?php
// cotizacion/public/admin/index.php
require_once __DIR__ . '/../../includes/init.php'; // Path to init.php from admin subdirectory

$auth = new Auth();

// Protect this page: Check if the user is logged in AND has the 'System Admin' role.
// Role name 'System Admin' should match what's in the database.sql and roles table.
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}
if (!$auth->hasRole('System Admin')) {
    // User is logged in but not a System Admin.
    // You can redirect to a generic "unauthorized" page or back to the dashboard.
    // For now, redirect to main dashboard with an error message (not implemented yet, just an idea)
    // $_SESSION['error_message'] = "You are not authorized to access the admin area.";
    $auth->redirect(BASE_URL . '/dashboard.php?unauthorized=true');
}

$user = $auth->getUser();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cotizacion App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; }
        .admin-content { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-content h2 { margin-top: 0; }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
    </style>
</head>
<body>

    <header class="admin-header">
        <h1>Admin Panel</h1>
    </header>

    <div class="user-info">
        Logged in as: <?php echo htmlspecialchars($user['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($user['id']), 'role_name'))); ?>) |
        <a href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
    </div>

    <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Admin Home</a>
        <a href="<?php echo BASE_URL; ?>/admin/companies.php">Manage Companies</a>
        <!-- Add links to other admin sections here: Users, Settings, etc. -->
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Main Dashboard</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2>Welcome to the Admin Dashboard</h2>
            <p>This is the central administration area for the Cotizacion Application.</p>
            <p>From here, you can manage companies, users, system settings, and more.</p>

            <h3>Quick Links:</h3>
            <ul>
                <?php if ($auth->hasRole('System Admin')): ?>
                    <li><a href="<?php echo BASE_URL; ?>/admin/companies.php">Manage Companies</a></li>
                <?php endif; ?>
                <?php if ($auth->hasRole(['Company Admin', 'Salesperson', 'System Admin'])): ?>
                    <li><a href="<?php echo BASE_URL; ?>/admin/customers.php">Manage Customers</a> (for your company)</li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/quotations.php">Manage Quotations</a> (for your company)</li>
                <?php endif; ?>
                <?php if ($auth->hasRole(['Company Admin', 'System Admin'])): ?>
                    <li><a href="<?php echo BASE_URL; ?>/admin/products.php">Manage Products</a> (for your company)</li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Manage Warehouses</a> (for your company)</li>
                    <li><a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Manage Stock</a> (for your company)</li>
                <?php endif; ?>
                <!-- <li><a href="<?php echo BASE_URL; ?>/admin/users.php">Manage Users</a></li> -->
                <!-- <li><a href="<?php echo BASE_URL; ?>/admin/settings.php">System Settings</a></li> -->
            </ul>
        </div>
    </div>

</body>
</html>
