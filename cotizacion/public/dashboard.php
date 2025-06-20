<?php
// cotizacion/public/dashboard.php
require_once __DIR__ . '/../includes/init.php'; // Includes session_start, config, db, autoloader

$auth = new Auth(); // Auth class should be autoloaded

if (!$auth->isLoggedIn()) {
    // If not logged in, redirect to login page
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cotizacion App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .container a { color: #007bff; text-decoration: none; }
        .container a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Dashboard</h2>
        <p>Welcome to your dashboard, <?php echo htmlspecialchars($user['first_name'] ?: $user['username']); ?>!</p>
        <p>This is a protected area.</p>
        <ul>
            <li><a href="index.php">Home Page (also protected)</a></li>
            <!-- Add links to other features here -->
            <!-- Example: <li><a href="quotations.php">Manage Quotations</a></li> -->

            <?php if ($auth->hasRole('System Admin')): ?>
                <li style="margin-top: 15px; padding-top:10px; border-top:1px solid #eee; background-color:#f0f8ff; padding:10px; border-radius:5px;">
                    <strong style="color:#337ab7;">Admin Area:</strong><br>
                    <a href="<?php echo BASE_URL; ?>/admin/index.php">Go to Admin Panel</a>
                </li>
            <?php endif; ?>
        </ul>
        <p style="margin-top: 20px;"><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>
