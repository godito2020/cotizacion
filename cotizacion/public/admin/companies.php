<?php
// cotizacion/public/admin/companies.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$companyRepo = new Company(); // Autoloaded

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}
if (!$auth->hasRole('System Admin')) {
    $auth->redirect(BASE_URL . '/dashboard.php?unauthorized=true');
}

$user = $auth->getUser(); // For header display
$userRepo = new User(); // For header display of roles

$companies = $companyRepo->getAll();

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
    <title>Manage Companies - Admin</title>
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
        .actions a:hover { text-decoration: underline; }
        .add-button { display: inline-block; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        .add-button:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Main Dashboard</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2>Manage Companies</h2>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <a href="company_form.php" class="add-button">Add New Company</a>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Tax ID</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No companies found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($company['id']); ?></td>
                                <td><?php echo htmlspecialchars($company['name']); ?></td>
                                <td><?php echo htmlspecialchars($company['tax_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($company['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($company['phone'] ?? 'N/A'); ?></td>
                                <td class="actions">
                                    <a href="company_form.php?id=<?php echo $company['id']; ?>">Edit</a>
                                    <!-- Add delete link/button here later if needed -->
                                    <!-- <a href="company_delete.php?id=<?php echo $company['id']; ?>" onclick="return confirm('Are you sure you want to delete this company?');">Delete</a> -->
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
