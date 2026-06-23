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
    <meta name="theme-color" content="#15181d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Iniciar Sesión - Sistema de Cotizaciones</title>
    <!-- Fijar tema antes de pintar para evitar parpadeo -->
    <script>
        (function () {
            var t = localStorage.getItem('coti-theme')
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #d32f2f;
            --brand-dark: #b71c1c;
            --brand-rgb: 211, 47, 47;
            --card-bg: #ffffff;
            --card-fg: #1f2937;
            --muted: #6b7280;
            --input-bg: #f8f9fa;
            --input-border: #e2e5ea;
        }
        [data-bs-theme="dark"] {
            --brand: #ef5350;
            --brand-dark: #e53935;
            --brand-rgb: 239, 83, 80;
            --card-bg: #1b1e24;
            --card-fg: #e6e8ec;
            --muted: #9aa1ac;
            --input-bg: #14171c;
            --input-border: #2b3038;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: radial-gradient(1200px 600px at 50% -10%, rgba(var(--brand-rgb), .25), transparent 60%),
                        linear-gradient(160deg, #1f2329 0%, #15181d 55%, #0f1115 100%);
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
            inset: 0;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 46px 46px;
            animation: moveBackground 24s linear infinite;
            pointer-events: none;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(46px, 46px); }
        }

        /* Botón de cambio de tema */
        .theme-toggle {
            position: fixed;
            top: 18px; right: 18px;
            width: 42px; height: 42px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.22);
            background: rgba(255,255,255,.10);
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            z-index: 5;
            transition: background .18s ease;
        }
        .theme-toggle:hover { background: rgba(255,255,255,.2); }

        .login-container {
            background-color: var(--card-bg);
            color: var(--card-fg);
            padding: 36px 28px;
            border-radius: 20px;
            box-shadow: 0 24px 70px rgba(0,0,0,0.45);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,.06);
            animation: fadeIn 0.5s ease-out;
        }

        @media (max-width: 480px) {
            .login-container { padding: 26px 20px; border-radius: 18px; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo-container { text-align: center; margin-bottom: 26px; }

        .logo-container img {
            max-width: 190px;
            max-height: 104px;
            object-fit: contain;
            margin-bottom: 14px;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.15));
        }

        .company-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--card-fg);
            margin-bottom: 4px;
        }

        .logo-placeholder {
            width: 84px;
            height: 84px;
            margin: 0 auto 14px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            color: #fff;
            font-weight: 800;
            box-shadow: 0 8px 22px rgba(var(--brand-rgb), .4);
        }

        .login-container h1 {
            text-align: center;
            color: var(--card-fg);
            margin-bottom: 24px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -.3px;
        }

        .form-group { margin-bottom: 18px; }

        .login-container label {
            display: block;
            margin-bottom: 7px;
            color: var(--card-fg);
            font-weight: 600;
            font-size: 14.5px;
        }

        .login-container input[type="text"],
        .login-container input[type="email"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 15px 16px;
            border: 1.5px solid var(--input-border);
            border-radius: 11px;
            font-size: 16px;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
            background-color: var(--input-bg);
            color: var(--card-fg);
            -webkit-appearance: none;
            appearance: none;
        }

        @media (max-width: 480px) {
            .login-container h1 { font-size: 22px; margin-bottom: 20px; }
            .login-container label { font-size: 15px; }
            .login-container input { font-size: 16px; padding: 14px 15px; }
            .company-name { font-size: 17px; }
        }

        .login-container input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3.5px rgba(var(--brand-rgb), 0.18);
        }
        .login-container input:focus-visible { outline: none; }

        .login-container button[type="submit"] {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: white;
            border: none;
            border-radius: 11px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: transform .18s ease, box-shadow .18s ease;
            margin-top: 6px;
            letter-spacing: 0.3px;
            -webkit-appearance: none;
            appearance: none;
            touch-action: manipulation;
        }

        .login-container button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(var(--brand-rgb), 0.4);
        }
        .login-container button[type="submit"]:active { transform: scale(0.99); }

        .error-message {
            background-color: rgba(var(--brand-rgb), .10);
            color: var(--brand-dark);
            border: 1px solid rgba(var(--brand-rgb), .3);
            padding: 13px 15px;
            border-radius: 11px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 14.5px;
            font-weight: 500;
            animation: shake 0.4s;
        }
        [data-bs-theme="dark"] .error-message { color: #ff8a80; }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .info-message {
            background-color: rgba(22, 163, 74, .12);
            color: #15803d;
            border: 1px solid rgba(22, 163, 74, .3);
            padding: 13px 15px;
            border-radius: 11px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 14.5px;
        }
        [data-bs-theme="dark"] .info-message { color: #4ade80; }

        .footer-text {
            text-align: center;
            margin-top: 22px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
    <button type="button" class="theme-toggle" id="themeToggle" aria-label="Cambiar modo de color" title="Cambiar modo de color">🌙</button>

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

    <script>
        (function () {
            var btn = document.getElementById('themeToggle');
            function sync() {
                var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                btn.textContent = dark ? '☀️' : '🌙';
                btn.setAttribute('aria-label', dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            }
            btn.addEventListener('click', function () {
                var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                var next = dark ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', next);
                try { localStorage.setItem('coti-theme', next); } catch (e) {}
                sync();
            });
            sync();
        })();
    </script>
</body>
</html>
