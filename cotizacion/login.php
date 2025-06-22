<?php
// login.php

// Cabeceras de Seguridad
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
}

require_once 'config/config.php';
require_once 'utils/auth_helper.php'; // auth_helper inicia sesión si no está iniciada

$page_title = "Iniciar Sesión";
$error_message = '';

// Si el usuario ya está logueado, redirigir al panel de administración
if (is_logged_in()) {
    header("Location: " . BASE_URL . "admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // No trimear passwords

    if (empty($email) || empty($password)) {
        $error_message = "Por favor, ingrese su correo electrónico y contraseña.";
    } else {
        if (login_user($email, $password)) {
            // Login exitoso, redirigir al panel de admin
            // $_SESSION['redirect_after_login'] podría usarse aquí si se implementó
            header("Location: " . BASE_URL . "admin.php?page=dashboard");
            exit;
        } else {
            // login_user() establece $_SESSION['login_error']
            $error_message = $_SESSION['login_error'] ?? "Error desconocido durante el inicio de sesión.";
        }
    }
}

// Limpiar cualquier error de login previo de la sesión si no es un POST actual
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['login_error'])) {
    unset($_SESSION['login_error']);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/login_styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2><?php echo APP_NAME; ?></h2>
            <h3><?php echo htmlspecialchars($page_title); ?></h3>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo BASE_URL; ?>login.php">
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <button type="submit" name="login" class="btn btn-primary btn-block">Ingresar</button>
                </div>
                <!-- Podrías añadir un enlace de "¿Olvidó su contraseña?" aquí -->
                <!-- <div class="form-group text-center">
                    <a href="forgot_password.php">¿Olvidó su contraseña?</a>
                </div> -->
            </form>
        </div>
        <footer class="login-footer">
            <p>&copy; <?php echo date("Y"); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>
