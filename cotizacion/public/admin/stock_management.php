<?php
// cotizacion/public/admin/stock_management.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$stockRepo = new Stock();
$productRepo = new Product();
$warehouseRepo = new Warehouse();

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
    $_SESSION['error_message'] = "You are not authorized to manage stock.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
$errors = []; // For form validation errors

// Handle stock update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id_form = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $warehouse_id_form = filter_input(INPUT_POST, 'warehouse_id', FILTER_VALIDATE_INT);
    $quantity_form = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($product_id_form === false || $product_id_form <= 0) {
        $errors[] = "Invalid Product selected.";
    }
    if ($warehouse_id_form === false || $warehouse_id_form <= 0) {
        $errors[] = "Invalid Warehouse selected.";
    }
    if ($quantity_form === false || $quantity_form < 0) { // Quantity can be 0
        $errors[] = "Quantity must be a non-negative integer.";
    }

    if (empty($errors)) {
        if ($stockRepo->updateStock($product_id_form, $warehouse_id_form, $quantity_form, $company_id)) {
            $_SESSION['message'] = "Stock updated successfully for Product ID {$product_id_form} in Warehouse ID {$warehouse_id_form}.";
        } else {
            $_SESSION['error_message'] = "Failed to update stock. Ensure product and warehouse belong to your company.";
        }
        // Redirect to clear POST data and show messages
        $auth->redirect(BASE_URL . '/admin/stock_management.php');
    } else {
        // If errors, they will be displayed below the form.
        // We need to re-fetch data for the page if we were to stay, but redirect is simpler.
        // For this version, errors will make the form "disappear" after POST if not careful.
        // Better: store errors in session and redirect, or re-populate lists.
        // For now, errors are displayed if we reach here, and lists are re-fetched.
        $error_message = implode('<br>', $errors); // Use the main error_message display
    }
}


// Data for the page
$company_products = $productRepo->getAllByCompany($company_id);
$company_warehouses = $warehouseRepo->getAllByCompany($company_id);
$stock_overview = $stockRepo->getCompanyStockOverview($company_id);

$selected_filter_product_id = filter_input(INPUT_GET, 'filter_product_id', FILTER_VALIDATE_INT);
$selected_filter_warehouse_id = filter_input(INPUT_GET, 'filter_warehouse_id', FILTER_VALIDATE_INT);

if($selected_filter_product_id) {
    $stock_overview = array_filter($stock_overview, function($item) use ($selected_filter_product_id) {
        return $item['product_id'] == $selected_filter_product_id;
    });
    // Further refine stock_levels within each product if a warehouse is also selected
    if ($selected_filter_warehouse_id) {
        foreach ($stock_overview as &$product_item) {
            $product_item['stock_levels'] = array_filter($product_item['stock_levels'], function($level) use ($selected_filter_warehouse_id) {
                return $level['warehouse_id'] == $selected_filter_warehouse_id;
            });
            $product_item['total_stock'] = array_sum(array_column($product_item['stock_levels'], 'quantity'));
        }
        unset($product_item);
    }
} elseif ($selected_filter_warehouse_id) {
    // If only warehouse is filtered, we need to transform data or fetch specifically
    // For simplicity, this case will show all products but only stock levels for the selected warehouse
    foreach ($stock_overview as &$product_item) {
        $product_item['stock_levels'] = array_filter($product_item['stock_levels'], function($level) use ($selected_filter_warehouse_id) {
            return $level['warehouse_id'] == $selected_filter_warehouse_id;
        });
        $product_item['total_stock'] = array_sum(array_column($product_item['stock_levels'], 'quantity'));
         // If a product has no stock in the filtered warehouse, it will show 0 total and empty levels.
         // We might want to hide it completely if it has no stock in that warehouse.
    }
    unset($product_item);
    // Filter out products that now have no stock levels shown (because they aren't in the selected warehouse)
     $stock_overview = array_filter($stock_overview, function($item) {
        return !empty($item['stock_levels']);
    });
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stock - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; }
        .admin-content { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom:20px; }
        .admin-content h2, .admin-content h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background-color: #f0f0f0; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: inline-block; width: 100px; }
        .form-group select, .form-group input { padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
        .filter-form, .update-form { margin-bottom: 20px; padding:15px; border: 1px solid #eee; border-radius:5px;}
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

        <?php if ($message): ?>
            <div class="message success" style="margin-bottom:20px;"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="admin-content">
            <h3>Update Stock Quantity</h3>
            <form action="stock_management.php" method="POST" class="update-form">
                <div class="form-group">
                    <label for="product_id">Product:</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">-- Select Product --</option>
                        <?php foreach ($company_products as $product): ?>
                            <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']) . " (SKU: " . htmlspecialchars($product['sku']) . ")"; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="warehouse_id">Warehouse:</label>
                    <select name="warehouse_id" id="warehouse_id" required>
                        <option value="">-- Select Warehouse --</option>
                        <?php foreach ($company_warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['id']; ?>"><?php echo htmlspecialchars($warehouse['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">New Quantity:</label>
                    <input type="number" name="quantity" id="quantity" min="0" required>
                </div>
                <button type="submit" name="update_stock">Update Stock</button>
            </form>
        </div>


        <div class="admin-content">
            <h2>Stock Overview (Company: <?php echo htmlspecialchars($company_id); ?>)</h2>

            <form action="stock_management.php" method="GET" class="filter-form">
                Filter by:
                <select name="filter_product_id">
                    <option value="">-- All Products --</option>
                    <?php foreach ($company_products as $product): ?>
                        <option value="<?php echo $product['id']; ?>" <?php echo ($selected_filter_product_id == $product['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="filter_warehouse_id">
                    <option value="">-- All Warehouses --</option>
                    <?php foreach ($company_warehouses as $warehouse): ?>
                        <option value="<?php echo $warehouse['id']; ?>" <?php echo ($selected_filter_warehouse_id == $warehouse['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($warehouse['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Filter</button>
                <a href="stock_management.php" style="margin-left:10px;">Clear Filters</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Total Stock</th>
                        <th>Stock Details (Warehouse: Quantity)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stock_overview)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;">No stock information available for the current filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stock_overview as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td><strong><?php echo htmlspecialchars($item['total_stock']); ?></strong></td>
                                <td>
                                    <?php if (empty($item['stock_levels'])): ?>
                                        N/A
                                    <?php else: ?>
                                        <ul>
                                        <?php foreach ($item['stock_levels'] as $level): ?>
                                            <li><?php echo htmlspecialchars($level['warehouse_name']); ?>: <?php echo htmlspecialchars($level['quantity']); ?>
                                                (<em>Last updated: <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($level['last_updated']))); ?></em>)
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
