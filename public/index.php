<?php
// cotizacion/public/index.php
require_once __DIR__ . '/../includes/init.php'; // Includes session_start, config, db, autoloader

$auth = new Auth(); // Auth class should be autoloaded

if (!$auth->isLoggedIn()) {
    // If not logged in, redirect to login page
    $auth->redirect(BASE_URL . '/login.php');
}

// Get user details
$user = $auth->getUser(); // Fetches details of the logged-in user

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Cotizacion App</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .container a { color: #007bff; text-decoration: none; }
        .container a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to the Cotizacion Application!</h1>

        <?php if ($user): ?>
            <p>Hello, <?php echo htmlspecialchars($user['first_name'] ?: $user['username']); ?>!</p>
            <p>Your User ID is: <?php echo htmlspecialchars($user['id']); ?></p>
            <p>Your Company ID is: <?php echo htmlspecialchars($user['company_id']); ?></p>
            <p>Your Email is: <?php echo htmlspecialchars($user['email']); ?></p>
        <?php else: ?>
            <p>Could not retrieve user details.</p>
        <?php endif; ?>

        <p>This is the main entry point. You are logged in.</p>
        <p><a href="dashboard.php">Go to Dashboard</a></p>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>
