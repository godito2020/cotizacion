<?php
// cotizacion/public/admin/customer_form.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$customerRepo = new Customer(); // Autoloaded

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

// Role check: 'Company Admin' or 'Salesperson'
if (!$auth->hasRole(['Company Admin', 'Salesperson'])) {
    $_SESSION['error_message'] = "You are not authorized to manage customers.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$customer_id_get = $_GET['id'] ?? null;
$is_edit_mode = false;
$customer_data = [
    'id' => null, 'name' => '', 'contact_person' => '', 'email' => '',
    'phone' => '', 'address' => '', 'tax_id' => ''
];
$page_title = "Add New Customer";
$errors = [];

if ($customer_id_get) {
    $customer_data = $customerRepo->getById((int)$customer_id_get, $company_id);
    if ($customer_data) {
        $is_edit_mode = true;
        $page_title = "Edit Customer: " . htmlspecialchars($customer_data['name']);
    } else {
        $_SESSION['error_message'] = "Customer not found or you do not have permission to edit it.";
        $auth->redirect(BASE_URL . '/admin/customers.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_data['name'] = trim($_POST['name'] ?? '');
    $customer_data['contact_person'] = trim($_POST['contact_person'] ?? null);
    $customer_data['email'] = trim($_POST['email'] ?? null);
    $customer_data['phone'] = trim($_POST['phone'] ?? null);
    $customer_data['address'] = trim($_POST['address'] ?? null);
    $customer_data['tax_id'] = trim($_POST['tax_id'] ?? null);
    $current_customer_id = $_POST['customer_id'] ?? null; // Hidden field

    // Validation
    if (empty($customer_data['name'])) { $errors[] = "Customer name is required."; }
    if (!empty($customer_data['email']) && !filter_var($customer_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!empty($customer_data['tax_id'])) {
        // Basic Tax ID validation (e.g., RUC for Peru is 11 digits, DNI is 8 digits)
        // This is a placeholder - real validation would be more complex.
        if (!preg_match('/^[0-9]{8}$|^[0-9]{11}$/', $customer_data['tax_id'])) {
            //$errors[] = "Tax ID format is invalid (should be 8 or 11 digits for DNI/RUC).";
        }
        // Check for Tax ID uniqueness within the company
        $existingCustomerByTax = $customerRepo->findByTaxId($customer_data['tax_id'], $company_id);
        if ($existingCustomerByTax && (!$is_edit_mode || $existingCustomerByTax['id'] != $current_customer_id)) {
            $errors[] = "This Tax ID is already registered to another customer in your company.";
        }
    }

    if (empty($errors)) {
        if ($is_edit_mode) {
            if (!$current_customer_id) {
                $_SESSION['error_message'] = "Customer ID missing for update.";
                $auth->redirect(BASE_URL . '/admin/customers.php');
            }
            $success = $customerRepo->update(
                (int)$current_customer_id, $company_id, $customer_data['name'],
                $customer_data['contact_person'], $customer_data['email'], $customer_data['phone'],
                $customer_data['address'], $customer_data['tax_id']
            );
            if ($success) {
                $_SESSION['message'] = "Customer updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update customer. Please try again.";
            }
        } else {
            $new_id = $customerRepo->create(
                $company_id, $customer_data['name'], $customer_data['contact_person'],
                $customer_data['email'], $customer_data['phone'], $customer_data['address'],
                $customer_data['tax_id']
            );
            if ($new_id) {
                $_SESSION['message'] = "Customer created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create customer. Please try again (e.g., check Tax ID uniqueness).";
            }
        }
        if (isset($_SESSION['message']) || !isset($_SESSION['error_message'])) {
             $auth->redirect(BASE_URL . '/admin/customers.php');
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
        .form-group input[type="email"],
        .form-group input[type="tel"], /* Changed phone to tel for better mobile experience */
        .form-group textarea { width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .form-group textarea { min-height: 80px; }
        .form-actions button { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .form-actions button[type="submit"] { background-color: #007bff; color: white; }
        .form-actions button[type="submit"]:hover { background-color: #0056b3; }
        .form-actions a { display: inline-block; padding: 10px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px; }
        .form-actions a:hover { background-color: #5a6268; }
        .error-messages { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error-messages ul { padding-left: 20px; margin: 0; }
        .sunat-ruc-note { font-size: 0.9em; color: #666; margin-left: 5px; }
        .sunat-button-placeholder { display:inline-block; padding: 5px 8px; font-size:0.8em; background-color:#f0f0f0; border:1px solid #ccc; border-radius:3px; margin-left:10px; color:#555; cursor:not-allowed; }
    </style>
</head>
<body>
    <header class="admin-header"><h1>Admin Panel</h1></header>
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
        <?php if ($auth->hasRole(['Company Admin', 'Salesperson', 'System Admin'])): ?>
             <a href="<?php echo BASE_URL; ?>/admin/customers.php">Manage Customers</a>
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
                <div class="error-messages"><strong>Please correct the following errors:</strong><ul>
                    <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <form action="customer_form.php<?php echo $is_edit_mode ? '?id=' . (int)$customer_id_get : ''; ?>" method="POST">
                <?php if ($is_edit_mode): ?><input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer_data['id']); ?>"><?php endif; ?>
                <div class="form-group">
                    <label for="name">Customer Name / Company Name <span style="color:red;">*</span>:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="tax_id">Tax ID (RUC/DNI):</label>
                    <input type="text" id="tax_id" name="tax_id" value="<?php echo htmlspecialchars($customer_data['tax_id'] ?? ''); ?>">
                    <span class="sunat-button-placeholder">Fetch from SUNAT/RENIEC</span> <span class="sunat-ruc-note">(Peru: 11 digits for RUC, 8 for DNI)</span>
                </div>
                <div class="form-group">
                    <label for="contact_person">Contact Person:</label>
                    <input type="text" id="contact_person" name="contact_person" value="<?php echo htmlspecialchars($customer_data['contact_person'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer_data['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($customer_data['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($customer_data['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit"><?php echo $is_edit_mode ? 'Update Customer' : 'Create Customer'; ?></button>
                    <a href="customers.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
