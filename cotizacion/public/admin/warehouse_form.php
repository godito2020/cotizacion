<?php
// cotizacion/public/admin/warehouse_form.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$warehouseRepo = new Warehouse(); // Autoloaded

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

if (!$auth->hasRole('Company Admin')) {
    $_SESSION['error_message'] = "You are not authorized to manage warehouses.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$warehouse_id_get = $_GET['id'] ?? null;
$is_edit_mode = false;
$warehouse_data = [
    'id' => null,
    'name' => '',
    'location' => ''
];
$page_title = "Add New Warehouse";
$errors = [];

if ($warehouse_id_get) {
    $warehouse_data = $warehouseRepo->getById((int)$warehouse_id_get, $company_id);
    if ($warehouse_data) {
        $is_edit_mode = true;
        $page_title = "Edit Warehouse: " . htmlspecialchars($warehouse_data['name']);
    } else {
        $_SESSION['error_message'] = "Warehouse not found or you do not have permission to edit it.";
        $auth->redirect(BASE_URL . '/admin/warehouses.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouse_data['name'] = trim($_POST['name'] ?? '');
    $warehouse_data['location'] = trim($_POST['location'] ?? null);
    $current_warehouse_id = $_POST['warehouse_id'] ?? null; // Hidden field for ID in edit mode

    // Basic Validation
    if (empty($warehouse_data['name'])) { $errors[] = "Warehouse name is required."; }

    if (empty($errors)) {
        if ($is_edit_mode) {
            if (!$current_warehouse_id) {
                $_SESSION['error_message'] = "Warehouse ID missing for update.";
                $auth->redirect(BASE_URL . '/admin/warehouses.php');
            }
            $success = $warehouseRepo->update(
                (int)$current_warehouse_id,
                $company_id,
                $warehouse_data['name'],
                $warehouse_data['location']
            );
            if ($success) {
                $_SESSION['message'] = "Warehouse updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update warehouse. Please try again.";
            }
        } else {
            $new_id = $warehouseRepo->create(
                $company_id,
                $warehouse_data['name'],
                $warehouse_data['location']
            );
            if ($new_id) {
                $_SESSION['message'] = "Warehouse created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create warehouse. Please try again.";
            }
        }

        if (isset($_SESSION['message']) || !isset($_SESSION['error_message'])) {
             $auth->redirect(BASE_URL . '/admin/warehouses.php');
        }
        if(isset($_SESSION['error_message'])) {
            $errors[] = $_SESSION['error_message'];
            unset($_SESSION['error_message']);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; max-width: 800px; margin: 20px auto; }
        .admin-content { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .admin-content h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group textarea { width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group textarea { min-height: 80px; }
        .form-actions button { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .form-actions button[type="submit"] { background-color: #007bff; color: white; }
        .form-actions button[type="submit"]:hover { background-color: #0056b3; }
        .form-actions a { display: inline-block; padding: 10px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px; }
        .form-actions a:hover { background-color: #5a6268; }
        .error-messages { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error-messages ul { padding-left: 20px; margin: 0; }
    </style>
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
        <?php if ($auth->hasRole('System Admin')): ?>
            <a href="<?php echo BASE_URL; ?>/admin/companies.php">Manage Companies</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'System Admin'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Manage Products</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Manage Warehouses</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Manage Stock</a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Main Dashboard</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2><?php echo $page_title; ?></h2>

            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <strong>Please correct the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="warehouse_form.php<?php echo $is_edit_mode ? '?id=' . (int)$warehouse_id_get : ''; ?>" method="POST">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="warehouse_id" value="<?php echo htmlspecialchars($warehouse_data['id']); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Warehouse Name <span style="color:red;">*</span>:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($warehouse_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="location">Location:</label>
                    <textarea id="location" name="location"><?php echo htmlspecialchars($warehouse_data['location'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit"><?php echo $is_edit_mode ? 'Update Warehouse' : 'Create Warehouse'; ?></button>
                    <a href="warehouses.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
