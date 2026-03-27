<?php
// cotizacion/public/admin/product_form.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$productRepo = new Product(); // Autoloaded

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

if (!$auth->hasRole('Administrador de Empresa')) {
    $_SESSION['error_message'] = "You are not authorized to manage products.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$product_id_get = $_GET['id'] ?? null;
$is_edit_mode = false;
$product_data = [
    'id' => null,
    'name' => '',
    'sku' => '',
    'price' => '',
    'description' => ''
];
$page_title = "Add New Product";
$errors = [];

if ($product_id_get) {
    $product_data = $productRepo->getById((int)$product_id_get, $company_id);
    if ($product_data) {
        $is_edit_mode = true;
        $page_title = "Edit Product: " . htmlspecialchars($product_data['name']);
    } else {
        $_SESSION['error_message'] = "Product not found or you do not have permission to edit it.";
        $auth->redirect(BASE_URL . '/admin/products.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_data['name'] = trim($_POST['name'] ?? '');
    $product_data['sku'] = trim($_POST['sku'] ?? '');
    $product_data['price'] = trim($_POST['price'] ?? '');
    $product_data['description'] = trim($_POST['description'] ?? null);
    $current_product_id = $_POST['product_id'] ?? null; // Hidden field for ID in edit mode

    // Basic Validation
    if (empty($product_data['name'])) { $errors[] = "Product name is required."; }
    if (empty($product_data['sku'])) { $errors[] = "SKU is required."; }
    if (!is_numeric($product_data['price']) || floatval($product_data['price']) < 0) {
        $errors[] = "Price must be a non-negative number.";
    } else {
        $product_data['price'] = floatval($product_data['price']); // Ensure it's a float
    }

    // SKU Uniqueness Check
    $excludeProductId = $is_edit_mode ? (int)$current_product_id : null;
    if (!empty($product_data['sku']) && !$productRepo->isSkuUniqueForCompany($product_data['sku'], $company_id, $excludeProductId)) {
        $errors[] = "This SKU is already in use by another product in your company.";
    }

    if (empty($errors)) {
        if ($is_edit_mode) {
            if (!$current_product_id) { // Should not happen if form is correct
                $_SESSION['error_message'] = "Product ID missing for update.";
                $auth->redirect(BASE_URL . '/admin/products.php');
            }
            $success = $productRepo->update(
                (int)$current_product_id,
                $company_id,
                $product_data['name'],
                $product_data['sku'],
                $product_data['price'],
                $product_data['description']
            );
            if ($success) {
                $_SESSION['message'] = "Product updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update product. Please try again. Check logs for details.";
            }
        } else {
            $new_id = $productRepo->create(
                $company_id,
                $product_data['name'],
                $product_data['sku'],
                $product_data['price'],
                $product_data['description']
            );
            if ($new_id) {
                $_SESSION['message'] = "Product created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create product. Please try again. Check logs for details (e.g. SKU uniqueness).";
            }
        }
        // If there was an error message from the repo operation itself, it might be overwritten here.
        // Consider merging messages if repo methods return specific error strings.
        if (isset($_SESSION['message']) || !isset($_SESSION['error_message'])) { // Only redirect if success or no new error
             $auth->redirect(BASE_URL . '/admin/products.php');
        }
        // If error, stay on page to show $errors and potentially $_SESSION['error_message']
        if(isset($_SESSION['error_message'])) {
            $errors[] = $_SESSION['error_message']; // Add session error to form errors
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
        .form-group input[type="number"],
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
        <?php if ($auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Manage Products</a>
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

            <form action="product_form.php<?php echo $is_edit_mode ? '?id=' . (int)$product_id_get : ''; ?>" method="POST">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_data['id']); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Product Name <span style="color:red;">*</span>:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="sku">SKU <span style="color:red;">*</span>:</label>
                    <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($product_data['sku']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="price">Price <span style="color:red;">*</span>:</label>
                    <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($product_data['price']); ?>" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($product_data['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit"><?php echo $is_edit_mode ? 'Update Product' : 'Create Product'; ?></button>
                    <a href="products.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
