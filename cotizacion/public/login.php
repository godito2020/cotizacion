<?php
// cotizacion/public/login.php
require_once __DIR__ . '/../includes/init.php'; // Includes session_start, config, db, autoloader

$auth = new Auth(); // Auth class should be autoloaded

// If user is already logged in, redirect them away from login page
if ($auth->isLoggedIn()) {
    // Redirect to a dashboard or home page
    // Make sure BASE_URL is defined in config.php
    $auth->redirect(BASE_URL . '/dashboard.php'); // Assuming dashboard.php will exist
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = $_POST['username_or_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($usernameOrEmail) || empty($password)) {
        $error_message = 'Please enter both username/email and password.';
    } else {
        if ($auth->login($usernameOrEmail, $password)) {
            // Login successful, redirect to dashboard or desired page
            $auth->redirect(BASE_URL . '/dashboard.php'); // Create dashboard.php later
        } else {
            // Login failed
            $error_message = 'Invalid username/email or password. Please try again.';
            // It's good practice to log failed login attempts for security monitoring,
            // but avoid revealing whether the username exists or not in the error message.
            error_log("Failed login attempt for: " . htmlspecialchars($usernameOrEmail));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cotizacion App</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; }
        .login-container h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .login-container label { display: block; margin-bottom: 5px; color: #555; }
        .login-container input[type="text"],
        .login-container input[type="email"],
        .login-container input[type="password"] { width: calc(100% - 20px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .login-container button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .login-container button:hover { background-color: #0056b3; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .info-message { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <div class="info-message">You have been successfully logged out.</div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="info-message">Registration successful! Please log in.</div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div>
                <label for="username_or_email">Username or Email:</label>
                <input type="text" id="username_or_email" name="username_or_email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
        <!-- Optional: Add link to registration page -->
        <!-- <p style="text-align:center; margin-top:15px;">Don't have an account? <a href="register.php">Register here</a></p> -->
    </div>
</body>
</html>
