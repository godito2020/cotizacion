<?php
// cotizacion/public/admin/company_form.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$companyRepo = new Company(); // Autoloaded

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}
if (!$auth->hasRole('System Admin')) {
    $auth->redirect(BASE_URL . '/dashboard.php?unauthorized=true');
}

$loggedInUser = $auth->getUser(); // For header display
$userRepo = new User(); // For header display of roles


$company_id = $_GET['id'] ?? null;
$is_edit_mode = false;
$company_data = [
    'id' => null,
    'name' => '',
    'tax_id' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'logo_url' => ''
];
$page_title = "Add New Company";
$errors = [];

if ($company_id) {
    $company_data = $companyRepo->getById((int)$company_id);
    if ($company_data) {
        $is_edit_mode = true;
        $page_title = "Edit Company: " . htmlspecialchars($company_data['name']);
    } else {
        $_SESSION['error_message'] = "Company not found.";
        $auth->redirect(BASE_URL . '/admin/companies.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize data
    $company_data['name'] = trim($_POST['name'] ?? '');
    $company_data['tax_id'] = trim($_POST['tax_id'] ?? null);
    $company_data['address'] = trim($_POST['address'] ?? null);
    $company_data['phone'] = trim($_POST['phone'] ?? null);
    $company_data['email'] = trim($_POST['email'] ?? null);
    $company_data['logo_url'] = trim($_POST['logo_url'] ?? null);

    // Validation
    if (empty($company_data['name'])) {
        $errors[] = "Company name is required.";
    }
    if (!empty($company_data['email']) && !filter_var($company_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    // Add other validation rules as needed (e.g., phone format, tax_id format based on country)

    if (empty($errors)) {
        if ($is_edit_mode) {
            $success = $companyRepo->update(
                (int)$company_id,
                $company_data['name'],
                $company_data['tax_id'],
                $company_data['address'],
                $company_data['phone'],
                $company_data['email'],
                $company_data['logo_url']
            );
            if ($success) {
                $_SESSION['message'] = "Company updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update company. Please try again.";
            }
        } else {
            $new_id = $companyRepo->create(
                $company_data['name'],
                $company_data['tax_id'],
                $company_data['address'],
                $company_data['phone'],
                $company_data['email'],
                $company_data['logo_url']
            );
            if ($new_id) {
                $_SESSION['message'] = "Company created successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to create company. Please try again.";
            }
        }
        $auth->redirect(BASE_URL . '/admin/companies.php');
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
        <a href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
    </div>

    <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Admin Home</a>
        <a href="<?php echo BASE_URL; ?>/admin/companies.php">Manage Companies</a>
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

            <form action="company_form.php<?php echo $is_edit_mode ? '?id=' . (int)$company_id : ''; ?>" method="POST">
                <div class="form-group">
                    <label for="name">Company Name <span style="color:red;">*</span>:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($company_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="tax_id">Tax ID:</label>
                    <input type="text" id="tax_id" name="tax_id" value="<?php echo htmlspecialchars($company_data['tax_id'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($company_data['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($company_data['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($company_data['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="logo_url">Logo URL:</label>
                    <input type="text" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($company_data['logo_url'] ?? ''); ?>">
                </div>
                <div class="form-actions">
                    <button type="submit"><?php echo $is_edit_mode ? 'Update Company' : 'Create Company'; ?></button>
                    <a href="companies.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
