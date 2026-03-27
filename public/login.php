<?php
// cotizacion/public/login.php
require_once __DIR__ . '/../includes/init.php'; // Includes session_start, config, db, autoloader

$auth = new Auth(); // Auth class should be autoloaded

// Función para detectar dispositivos móviles
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone', 'webOS'];

    foreach ($mobileKeywords as $keyword) {
        if (stripos($userAgent, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

// If user is already logged in, redirect them away from login page
if ($auth->isLoggedIn()) {
    // Redirect to a dashboard or home page based on device type
    // Make sure BASE_URL is defined in config.php
    $isMobile = isMobileDevice();
    if ($isMobile) {
        $auth->redirect(BASE_URL . '/dashboard_mobile.php');
    } else {
        $auth->redirect(BASE_URL . '/dashboard_simple.php');
    }
}

$error_message = '';

// Obtener información de la empresa desde la tabla settings (company_id = 1 por defecto)
$db = getDBConnection();
$settingsStmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE company_id = 1 AND setting_key IN ('company_name', 'company_logo_url')");
$settingsStmt->execute();
$settingsData = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Convertir el array de settings a un array asociativo
$company = [
    'name' => '',
    'logo_url' => ''
];
foreach ($settingsData as $setting) {
    if ($setting['setting_key'] === 'company_name') {
        $company['name'] = $setting['setting_value'];
    } elseif ($setting['setting_key'] === 'company_logo_url') {
        $company['logo_url'] = $setting['setting_value'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = $_POST['username_or_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($usernameOrEmail) || empty($password)) {
        $error_message = 'Por favor ingrese usuario/email y contraseña.';
    } else {
        if ($auth->login($usernameOrEmail, $password)) {
            // Login successful, redirect based on role and device
            $userId = $auth->getUserId();
            $userRepo = new User();
            $userRoles = $userRepo->getRoles($userId);
            $isMobile = isMobileDevice();

            // Obtener nombres de roles del usuario
            $roleNames = array_column($userRoles, 'role_name');

            // Si el usuario tiene más de 1 rol, mostrar selector de rol
            if (count($userRoles) > 1) {
                $auth->redirect(BASE_URL . '/select_role.php');
            }
            // Verificar si el usuario SOLO tiene rol de Facturación
            elseif (count($userRoles) === 1 && $roleNames[0] === 'Facturación') {
                // Guardar rol en sesión
                $_SESSION['active_role_id'] = $userRoles[0]['role_id'];
                $_SESSION['active_role_name'] = $userRoles[0]['role_name'];
                // Redirigir directamente al módulo de facturación
                $auth->redirect(BASE_URL . '/billing/pending.php');
            }
            // Verificar si el usuario tiene rol de Inventario (Usuario o Supervisor)
            elseif (in_array('Usuario Inventario', $roleNames) || in_array('Supervisor Inventario', $roleNames)) {
                // Guardar rol en sesión (priorizar Supervisor)
                foreach ($userRoles as $role) {
                    if ($role['role_name'] === 'Supervisor Inventario') {
                        $_SESSION['active_role_id'] = $role['role_id'];
                        $_SESSION['active_role_name'] = $role['role_name'];
                        break;
                    } elseif ($role['role_name'] === 'Usuario Inventario') {
                        $_SESSION['active_role_id'] = $role['role_id'];
                        $_SESSION['active_role_name'] = $role['role_name'];
                    }
                }
                // Redirigir al módulo de inventario
                $auth->redirect(BASE_URL . '/inventario/');
            } else {
                // Guardar el primer rol por defecto
                if (!empty($userRoles)) {
                    $_SESSION['active_role_id'] = $userRoles[0]['role_id'];
                    $_SESSION['active_role_name'] = $userRoles[0]['role_name'];
                }
                // Redirigir al dashboard según el tipo de dispositivo
                if ($isMobile) {
                    $auth->redirect(BASE_URL . '/dashboard_mobile.php');
                } else {
                    $auth->redirect(BASE_URL . '/dashboard_simple.php');
                }
            }
        } else {
            // Login failed
            $error_message = 'Usuario/email o contraseña inválidos. Por favor intente nuevamente.';
            error_log("Failed login attempt for: " . htmlspecialchars($usernameOrEmail));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Iniciar Sesión - Sistema de Cotizaciones</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .login-container {
            background-color: #ffffff;
            padding: 32px 24px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            animation: fadeIn 0.5s ease-in;
        }

        /* Mobile optimizations */
        @media (max-width: 480px) {
            .login-container {
                padding: 24px 20px;
                border-radius: 20px;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-container img {
            max-width: 180px;
            max-height: 100px;
            object-fit: contain;
            margin-bottom: 15px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .company-name {
            font-size: 18px;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }

        .logo-placeholder {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            font-weight: 700;
        }

        .login-container h2 {
            text-align: center;
            color: #667eea;
            margin-bottom: 24px;
            font-size: 26px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .login-container label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 15px;
        }

        .login-container input[type="text"],
        .login-container input[type="email"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            -webkit-appearance: none;
            appearance: none;
        }

        /* Mobile font size optimization */
        @media (max-width: 480px) {
            .login-container h2 {
                font-size: 24px;
                margin-bottom: 20px;
            }

            .login-container label {
                font-size: 16px;
            }

            .login-container input[type="text"],
            .login-container input[type="email"],
            .login-container input[type="password"] {
                font-size: 16px;
                padding: 14px 16px;
            }

            .company-name {
                font-size: 17px;
            }
        }

        .login-container input[type="text"]:focus,
        .login-container input[type="email"]:focus,
        .login-container input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-container button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 17px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            -webkit-appearance: none;
            appearance: none;
            touch-action: manipulation;
        }

        .login-container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .login-container button:active {
            transform: scale(0.98);
        }

        @media (max-width: 480px) {
            .login-container button {
                font-size: 16px;
                padding: 15px;
            }
        }

        .error-message {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 15px;
            animation: shake 0.5s;
        }

        @media (max-width: 480px) {
            .error-message {
                font-size: 14px;
                padding: 12px 14px;
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .info-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 15px;
        }

        @media (max-width: 480px) {
            .info-message {
                font-size: 14px;
                padding: 12px 14px;
            }
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .footer-text {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo de la empresa -->
        <div class="logo-container">
            <?php
            $logoPath = !empty($company['logo_url']) ? $company['logo_url'] : '';
            // Si la ruta empieza con 'public/', removerla porque BASE_URL ya incluye /public
            if (strpos($logoPath, 'public/') === 0) {
                $logoPath = substr($logoPath, 7); // Remover 'public/'
            }
            $fullLogoPath = APP_ROOT . '/public/' . $logoPath;
            ?>
            <?php if (!empty($logoPath) && file_exists($fullLogoPath)): ?>
                <img src="<?php echo htmlspecialchars(BASE_URL . '/' . $logoPath); ?>"
                     alt="<?php echo htmlspecialchars($company['name']); ?>">
            <?php else: ?>
                <div class="logo-placeholder">
                    <?php echo !empty($company['name']) ? strtoupper(substr($company['name'], 0, 1)) : 'S'; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($company['name'])): ?>
                <div class="company-name"><?php echo htmlspecialchars($company['name']); ?></div>
            <?php endif; ?>
        </div>

        <h2>Iniciar Sesión</h2>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <div class="info-message">Ha cerrado sesión exitosamente.</div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="info-message">¡Registro exitoso! Por favor inicie sesión.</div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username_or_email">Usuario o Email:</label>
                <input type="text" id="username_or_email" name="username_or_email" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div>
                <button type="submit">Iniciar Sesión</button>
            </div>
        </form>

        <div class="footer-text">
            Sistema de Cotizaciones &copy; <?php echo date('Y'); ?>
        </div>
    </div>
</body>
</html>
