<?php
/**
 * Selección de Rol - Para usuarios con múltiples roles
 * Se muestra después del login cuando el usuario tiene más de 1 rol
 */

require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

// Si no está logueado, redirigir al login
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Si ya tiene un rol seleccionado y no viene forzado, redirigir al dashboard
if (isset($_SESSION['active_role_id']) && !isset($_GET['change'])) {
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

$userId = $auth->getUserId();
$userRepo = new User();
$userRoles = $userRepo->getRoles($userId);

// Si tiene 1 o menos roles, no debería estar aquí
if (count($userRoles) <= 1 && !isset($_GET['change'])) {
    // Asignar el primer rol por defecto y redirigir
    if (!empty($userRoles)) {
        $_SESSION['active_role_id'] = $userRoles[0]['role_id'];
        $_SESSION['active_role_name'] = $userRoles[0]['role_name'];
    }
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

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

// Procesar selección de rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['role_id'])) {
    $selectedRoleId = (int)$_POST['role_id'];

    // Verificar que el rol pertenece al usuario
    $validRole = false;
    $selectedRoleName = '';
    foreach ($userRoles as $role) {
        if ((int)$role['role_id'] === $selectedRoleId) {
            $validRole = true;
            $selectedRoleName = $role['role_name'];
            break;
        }
    }

    if ($validRole) {
        // Guardar el rol seleccionado en la sesión
        $_SESSION['active_role_id'] = $selectedRoleId;
        $_SESSION['active_role_name'] = $selectedRoleName;

        // Redirigir según el rol seleccionado
        $isMobile = isMobileDevice();

        // Redirigir según el rol
        switch ($selectedRoleName) {
            case 'Facturación':
                header('Location: ' . BASE_URL . '/billing/pending.php');
                break;
            case 'Usuario Inventario':
            case 'Supervisor Inventario':
                header('Location: ' . BASE_URL . '/inventario/');
                break;
            case 'Admin':
            case 'Administrador':
                header('Location: ' . BASE_URL . '/admin/');
                break;
            default:
                if ($isMobile) {
                    header('Location: ' . BASE_URL . '/dashboard_mobile.php');
                } else {
                    header('Location: ' . BASE_URL . '/dashboard_simple.php');
                }
        }
        exit;
    }
}

// Obtener información de la empresa
$db = getDBConnection();
$settingsStmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE company_id = 1 AND setting_key IN ('company_name', 'company_logo_url')");
$settingsStmt->execute();
$settingsData = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

$company = ['name' => '', 'logo_url' => ''];
foreach ($settingsData as $setting) {
    if ($setting['setting_key'] === 'company_name') {
        $company['name'] = $setting['setting_value'];
    } elseif ($setting['setting_key'] === 'company_logo_url') {
        $company['logo_url'] = $setting['setting_value'];
    }
}

// Obtener info del usuario
$user = $auth->getUser();
$userName = $user['first_name'] ?? $user['username'] ?? 'Usuario';

// Mapeo de iconos para cada rol
$roleIcons = [
    'Admin' => 'fa-user-shield',
    'Administrador' => 'fa-user-shield',
    'Supervisor Inventario' => 'fa-clipboard-list',
    'Usuario Inventario' => 'fa-boxes',
    'Facturación' => 'fa-file-invoice-dollar',
    'Ventas' => 'fa-shopping-cart',
    'Compras' => 'fa-truck',
    'Almacén' => 'fa-warehouse',
    'Contador' => 'fa-calculator',
    'Gerente' => 'fa-briefcase',
    'default' => 'fa-user-tag'
];

// Mapeo de colores para cada rol
$roleColors = [
    'Admin' => '#dc3545',
    'Administrador' => '#dc3545',
    'Supervisor Inventario' => '#198754',
    'Usuario Inventario' => '#0d6efd',
    'Facturación' => '#fd7e14',
    'Ventas' => '#6f42c1',
    'Compras' => '#20c997',
    'Almacén' => '#6c757d',
    'Contador' => '#ffc107',
    'Gerente' => '#0dcaf0',
    'default' => '#667eea'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <title>Seleccionar Rol - <?= htmlspecialchars($company['name'] ?: 'Sistema') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            padding: 32px 24px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header .greeting {
            font-size: 14px;
            color: #888;
            margin-bottom: 8px;
        }

        .header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            color: #666;
        }

        .user-name {
            color: #667eea;
            font-weight: 600;
        }

        .roles-grid {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }

        .role-card {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .role-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .role-card:active {
            transform: scale(0.98);
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .role-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .role-info {
            flex: 1;
        }

        .role-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .role-desc {
            font-size: 13px;
            color: #888;
            line-height: 1.4;
        }

        .role-check {
            width: 24px;
            height: 24px;
            border: 2px solid #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            transition: all 0.2s;
        }

        .role-card.selected .role-check {
            background: #667eea;
            border-color: #667eea;
        }

        .btn-continue {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-continue:active {
            transform: scale(0.98);
        }

        .btn-continue:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .logout-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 14px;
            text-decoration: none;
        }

        .logout-link:hover {
            color: #667eea;
        }

        @media (max-width: 480px) {
            .container {
                padding: 24px 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .role-card {
                padding: 14px 16px;
            }

            .role-icon {
                width: 44px;
                height: 44px;
                font-size: 20px;
            }

            .role-name {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <p class="greeting">Bienvenido,</p>
            <h1 class="user-name"><?= htmlspecialchars($userName) ?></h1>
            <p>Selecciona el rol con el que deseas trabajar hoy</p>
        </div>

        <form method="POST" id="roleForm">
            <div class="roles-grid">
                <?php foreach ($userRoles as $index => $role): ?>
                    <?php
                    $icon = $roleIcons[$role['role_name']] ?? $roleIcons['default'];
                    $color = $roleColors[$role['role_name']] ?? $roleColors['default'];
                    ?>
                    <label class="role-card" data-role-id="<?= $role['role_id'] ?>">
                        <input type="radio" name="role_id" value="<?= $role['role_id'] ?>" <?= $index === 0 ? 'checked' : '' ?>>
                        <div class="role-icon" style="background: <?= $color ?>;">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div class="role-info">
                            <div class="role-name"><?= htmlspecialchars($role['role_name']) ?></div>
                            <div class="role-desc"><?= htmlspecialchars($role['description'] ?? '') ?></div>
                        </div>
                        <div class="role-check">
                            <i class="fas fa-check"></i>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-continue" id="continueBtn">
                <i class="fas fa-arrow-right"></i> Continuar
            </button>
        </form>

        <a href="<?= BASE_URL ?>/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </a>
    </div>

    <script>
        // Manejar selección visual de roles
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remover selección de todas las tarjetas
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                // Agregar selección a la tarjeta clickeada
                this.classList.add('selected');
                // Marcar el radio button
                this.querySelector('input[type="radio"]').checked = true;
            });

            // Marcar la primera como seleccionada por defecto
            if (card.querySelector('input[type="radio"]').checked) {
                card.classList.add('selected');
            }
        });

        // Animación al enviar
        document.getElementById('roleForm').addEventListener('submit', function() {
            const btn = document.getElementById('continueBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
        });
    </script>
</body>
</html>
