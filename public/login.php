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
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Iniciar Sesión - Sistema de Cotizaciones</title>

    <!-- Anti-Flash Theme Init (System default) -->
    <script>
        (function () {
            var mode = 'system';
            try {
                var saved = localStorage.getItem('coti-theme');
                if (saved === 'light' || saved === 'dark' || saved === 'system') mode = saved;
            } catch (e) {}
            var theme = mode === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.setAttribute('data-coti-theme-mode', mode);
        })();
    </script>

    <!-- gstack Fonts: Satoshi & DM Sans & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@700,900&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --brand: #D97706;
            --brand-dark: #B45309;
            --brand-rgb: 217, 119, 6;
            --page-bg: #FAFAF9;
            --card-bg: #FFFFFF;
            --card-fg: #292524;
            --card-ink: #1C1917;
            --muted: #78716C;
            --input-bg: #FFFFFF;
            --input-border: #E7E5E4;
            --paper-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 0 0 1px #E7E5E4;
            --grain-opacity: 0.02;
        }
        [data-bs-theme="dark"] {
            --brand: #F59E0B;
            --brand-dark: #FBBF24;
            --brand-rgb: 245, 158, 11;
            --page-bg: #0C0C0C;
            --card-bg: #141414;
            --card-fg: #D4D4D8;
            --card-ink: #FAFAFA;
            --muted: #A1A1AA;
            --input-bg: #0C0C0C;
            --input-border: #262626;
            --paper-shadow: 0 1px 3px rgba(0, 0, 0, 0.6), 0 0 0 1px #262626;
            --grain-opacity: 0.03;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--page-bg);
            color: var(--card-fg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
            position: relative;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        body::after {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 99999;
            opacity: var(--grain-opacity, 0.03);
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        }

        h1, h2, h3, .brand-title {
            font-family: 'Satoshi', sans-serif !important;
            font-weight: 700 !important;
            letter-spacing: -0.025em;
        }

        /* 3-State Theme Toggle Pill */
        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            min-height: 34px;
            border-radius: 9999px;
            border: 1px solid var(--input-border);
            background: var(--card-bg);
            color: var(--card-fg);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            z-index: 10;
            box-shadow: var(--paper-shadow);
            transition: transform 0.1s ease, background-color 0.15s ease, border-color 0.15s ease;
        }
        .theme-toggle-btn:hover {
            transform: scale(1.02);
            border-color: var(--brand);
        }
        .theme-toggle-btn:active {
            transform: scale(0.97);
        }
        .theme-toggle-btn i {
            color: var(--brand);
            font-size: 14px;
        }

        /* Notion Login Card */
        .login-container {
            background-color: var(--card-bg);
            color: var(--card-fg);
            padding: 36px 32px;
            border-radius: 12px;
            box-shadow: var(--paper-shadow);
            width: 100%;
            max-width: 410px;
            position: relative;
            z-index: 1;
            border: 1px solid var(--input-border);
            animation: fadeIn 0.3s ease-out;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 28px 20px;
                border-radius: 12px;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-container img {
            max-width: 160px;
            max-height: 70px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .company-name {
            font-size: 16px;
            font-weight: 600;
            letter-spacing: -0.01em;
            color: var(--card-ink);
            margin-bottom: 2px;
        }

        .logo-placeholder {
            width: 56px;
            height: 56px;
            margin: 0 auto 12px;
            background: var(--brand);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #fff;
            font-weight: 700;
        }

        .login-container h1 {
            text-align: center;
            color: var(--card-ink);
            margin-bottom: 24px;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .login-container label {
            display: block;
            margin-bottom: 6px;
            color: var(--card-ink);
            font-weight: 500;
            font-size: 13.5px;
        }

        .login-container input[type="text"],
        .login-container input[type="email"],
        .login-container input[type="password"] {
            width: 100%;
            height: 40px;
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 14.5px;
            font-family: inherit;
            transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
            background-color: var(--input-bg);
            color: var(--card-fg);
            -webkit-appearance: none;
            appearance: none;
        }

        .login-container input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(var(--brand-rgb), 0.16);
        }

        /* Notion 8px Rectangular Primary Action Button */
        .login-container button[type="submit"] {
            width: 100%;
            height: 40px;
            padding: 8px 16px;
            background: var(--brand);
            color: #ffffff;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14.5px;
            font-weight: 600;
            font-family: inherit;
            transition: background-color .15s ease, transform .08s ease;
            margin-top: 8px;
            user-select: none;
        }

        .login-container button[type="submit"]:hover {
            background-color: var(--brand-dark);
        }
        .login-container button[type="submit"]:active {
            transform: scale(0.985);
        }

        .error-message {
            background-color: rgba(220, 38, 38, 0.08);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.25);
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
            font-size: 13.5px;
            font-weight: 500;
        }
        [data-bs-theme="dark"] .error-message {
            background-color: rgba(239, 68, 68, 0.16);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.35);
        }

        .info-message {
            background-color: rgba(22, 163, 74, 0.08);
            color: #15803d;
            border: 1px solid rgba(22, 163, 74, 0.25);
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
            font-size: 13.5px;
        }
        [data-bs-theme="dark"] .info-message {
            background-color: rgba(34, 197, 94, 0.16);
            color: #4ade80;
            border-color: rgba(34, 197, 94, 0.35);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            color: var(--muted);
            font-size: 12.5px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">
</head>
<body>
    <!-- Theme Toggle Pill Button -->
    <button type="button" class="theme-toggle-btn" id="themeToggle" data-theme-toggle="" aria-label="Cambiar tema" title="Cambiar tema">
        <i class="fas fa-circle-half-stroke"></i>
        <span class="theme-mode-text">Sistema</span>
    </button>

    <div class="login-container">
        <!-- Logo de la empresa -->
        <div class="logo-container">
            <?php
            $logoPath = !empty($company['logo_url']) ? $company['logo_url'] : '';
            if (strpos($logoPath, 'public/') === 0) {
                $logoPath = substr($logoPath, 7);
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

        <h1>Iniciar Sesión</h1>

        <?php if (!empty($error_message)): ?>
            <div class="error-message" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <div class="info-message">Ha cerrado sesión exitosamente.</div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="info-message">¡Registro exitoso! Por favor inicie sesión.</div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username_or_email">Usuario o Email</label>
                <input type="text" id="username_or_email" name="username_or_email" required autocomplete="username" placeholder="tu_usuario o correo@empresa.com">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            </div>
            <div>
                <button type="submit">Iniciar Sesión</button>
            </div>
        </form>

        <div class="footer-text">
            Sistema de Cotizaciones &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js?v=<?= @filemtime(APP_ROOT . "/public/assets/js/theme-pro.js") ?>"></script>
</body>
</html>
