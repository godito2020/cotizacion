<?php
// cotizacion/public/register.php
require_once __DIR__ . '/../includes/init.php'; // Includes session_start, config, db, autoloader

$auth = new Auth();
$userRepo = new User(); // User class for DB operations, should be autoloaded

// If user is already logged in, redirect them away from registration page
if ($auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/dashboard.php');
}

$errors = [];
$success_message = '';
$default_company_id = 1; // Placeholder: In a real app, this would be dynamic or part of a company creation process.
$default_role_id = 2;    // Placeholder: 'Administrador de Empresa' (assuming ID 2 from database.sql inserts)

// Ensure a default company exists for testing, otherwise foreign key constraint will fail
// In a real setup, an installation script or company creation UI would handle this.
// For now, we might need to manually insert a company with ID 1 into the `companies` table if it doesn't exist.
// e.g., INSERT INTO `companies` (`id`, `name`) VALUES (1, 'Default Company');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');

    // Basic Validation
    if (empty($username)) { $errors[] = 'Username is required.'; }
    if (empty($email)) { $errors[] = 'Email is required.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($email)) { $errors[] = 'Invalid email format.'; }
    if (empty($password)) { $errors[] = 'Password is required.'; }
    if (strlen($password) < 6 && !empty($password)) { $errors[] = 'Password must be at least 6 characters long.'; }
    if ($password !== $confirm_password) { $errors[] = 'Passwords do not match.'; }
    if (empty($first_name)) { $errors[] = 'First name is required.'; }
    if (empty($last_name)) { $errors[] = 'Last name is required.'; }

    // Check if username or email already exists
    if (empty($errors)) {
        if ($userRepo->findByUsername($username)) {
            $errors[] = 'Username already taken. Please choose another.';
        }
        if ($userRepo->findByEmail($email)) {
            $errors[] = 'Email already registered. Please use another or login.';
        }
    }

    // If no errors, proceed to create user
    if (empty($errors)) {
        $newUserId = $userRepo->create(
            $default_company_id,
            $username,
            $password,
            $email,
            $first_name,
            $last_name
        );

        if ($newUserId) {
            // Assign a default role
            if ($userRepo->assignRole($newUserId, $default_role_id)) {
                // Role assigned successfully
                error_log("User $newUserId assigned role $default_role_id");
            } else {
                // Role assignment failed - log this, but registration itself was successful
                error_log("Failed to assign role $default_role_id to user $newUserId.");
                // You might want to add a message to the user or handle this more gracefully
            }

            // Registration successful
            // Optional: Automatically log the user in
            // if ($auth->login($email, $password)) {
            //    $auth->redirect(BASE_URL . '/dashboard.php?registered=true');
            // } else {
            //    // Should not happen if creation was fine and login logic is correct
            //    $success_message = 'Registration successful! However, auto-login failed. Please try logging in manually.';
            // }

            // For now, redirect to login page with a success message
            $auth->redirect(BASE_URL . '/login.php?registered=true');

        } else {
            $errors[] = 'Registration failed due to a server error. Please try again later.';
            // Detailed error is logged by User::create method
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Cotizacion App</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px 0; }
        .register-container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 400px; }
        .register-container h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .register-container label { display: block; margin-bottom: 5px; color: #555; }
        .register-container input[type="text"],
        .register-container input[type="email"],
        .register-container input[type="password"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        .register-container button { width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .register-container button:hover { background-color: #218838; }
        .error-messages, .success-message { padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: left; }
        .error-messages { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .error-messages ul { padding-left: 20px; margin: 0; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .login-link { text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Create Account</h2>

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

        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" novalidate>
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div>
                <label for="password">Password (min 6 characters):</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <button type="submit">Register</button>
            </div>
        </form>
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
