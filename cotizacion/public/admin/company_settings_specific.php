<?php
// cotizacion/public/admin/company_settings_specific.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$settingsRepo = new Settings();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole('Company Admin')) {
    $_SESSION['error_message'] = "No está autorizado para acceder a esta página.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$page_title = "Configuraciones Específicas de la Empresa";
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message_page = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
$errors = [];

// Define company-specific settings to be managed
$company_settings_keys = [
    Settings::KEY_COMPANY_TERMS_QUOTATION => [
        'label' => 'Términos y Condiciones para Cotizaciones (Empresa)',
        'type' => 'textarea',
        'description' => 'Si se deja vacío, se usarán los términos por defecto del sistema. Estos son específicos para su empresa.'
    ],
    Settings::KEY_COMPANY_NOTES_QUOTATION => [
        'label' => 'Notas por Defecto para Cotizaciones (Empresa)',
        'type' => 'textarea',
        'description' => 'Si se deja vacío, se usarán las notas por defecto del sistema. Estas son específicas para su empresa.'
    ],
    Settings::KEY_COMPANY_QUOTATION_PREFIX => [
        'label' => 'Prefijo para Número de Cotización (Empresa)',
        'type' => 'text',
        'description' => 'Ej: COT-, PRE-, PRO-. Si se deja vacío, el sistema podría usar un prefijo genérico. No incluye el año ni el correlativo.'
    ]
    // Add more company-specific settings here, e.g., KEY_COMPANY_LOGO_URL_OVERRIDE
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $all_success = true;
    foreach ($company_settings_keys as $key => $details) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            // If value is empty, decide whether to store an empty string or delete the setting
            // For simplicity, we store empty string. To "reset" to system default, user clears field.
            // The Settings::get() method will then fall back to system default if company specific is empty or not found.
            // However, if an empty string is a valid "override", this is fine.
            // If an empty string means "use system default", then we should delete the setting.
            // For now, let's assume empty string is a valid override.
            // A more advanced approach: if $value is empty string for a setting that can fallback, call $settingsRepo->delete($key, $company_id)

            if (!$settingsRepo->set($key, $value, $company_id, $details['description'] ?? $details['label'])) {
                $all_success = false;
                $errors[] = "Error al guardar la configuración: " . htmlspecialchars($details['label']);
            }
        }
    }

    if ($all_success && empty($errors)) {
        $_SESSION['message'] = "Configuraciones de la empresa actualizadas exitosamente.";
    } else {
        $_SESSION['error_message'] = "Algunas configuraciones no pudieron ser guardadas.";
    }
    $auth->redirect(BASE_URL . '/admin/company_settings_specific.php');
}

// Fetch current values for display
$current_company_settings = [];
foreach (array_keys($company_settings_keys) as $key) {
    // For company settings, we don't want to fall back to system default in the form fields,
    // because the form is specifically for *overriding* or setting company-specific values.
    // So, we fetch only the company-specific value.
    $stmt = $settingsRepo->db->prepare("SELECT setting_value FROM settings WHERE setting_key = :setting_key AND company_id = :company_id");
    $stmt->bindParam(':setting_key', $key);
    $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_company_settings[$key] = $result !== false ? $result['setting_value'] : '';
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; max-width: 800px; margin: 20px auto; }
        .admin-content { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 4px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 120px; }
        .form-group .description { font-size: 0.85em; color: #666; margin-top: 4px; }
        .form-actions button { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size:16px; background-color: #007bff; color: white; }
        .form-actions button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .error-messages ul { padding-left: 20px; margin: 0; }
    </style>
</head>
<body>
    <header class="admin-header"><h1>Admin Panel</h1></header>
    <div class="user-info">
        Usuario: <?php echo htmlspecialchars($loggedInUser['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($loggedInUser['id']), 'role_name'))); ?>) |
        Compañía ID: <?php echo htmlspecialchars($company_id); ?> |
        <a href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a>
    </div>
    <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Inicio Admin</a>
        <?php if ($auth->hasRole('System Admin')): ?>
            <a href="<?php echo BASE_URL; ?>/admin/companies.php">Empresas</a>
            <a href="<?php echo BASE_URL; ?>/admin/system_settings.php">Conf. Sistema</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'Salesperson', 'System Admin'])): ?>
             <a href="<?php echo BASE_URL; ?>/admin/customers.php">Clientes</a>
             <a href="<?php echo BASE_URL; ?>/admin/quotations.php">Cotizaciones</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'System Admin'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Productos</a>
            <a href="<?php echo BASE_URL; ?>/admin/product_import.php">Importar Productos</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Almacenes</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouse_import.php">Importar Almacenes</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Stock</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_import.php">Importar Stock</a>
            <a href="<?php echo BASE_URL; ?>/admin/company_profile.php">Perfil Empresa</a>
            <a href="<?php echo BASE_URL; ?>/admin/company_settings_specific.php">Conf. Empresa</a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Dashboard Principal</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2><?php echo $page_title; ?></h2>

            <?php if ($message): ?><div class="message success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error_message_page): ?><div class="message error"><?php echo htmlspecialchars($error_message_page); ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="message error"><strong>Por favor corrija los siguientes errores:</strong><ul>
                    <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>

            <form action="company_settings_specific.php" method="POST">
                <?php foreach ($company_settings_keys as $key => $details): ?>
                <div class="form-group">
                    <label for="<?php echo $key; ?>"><?php echo $details['label']; ?></label>
                    <?php if ($details['type'] === 'textarea'): ?>
                        <textarea id="<?php echo $key; ?>" name="<?php echo $key; ?>"><?php echo htmlspecialchars($current_company_settings[$key]); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $details['type']; ?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($current_company_settings[$key]); ?>">
                    <?php endif; ?>
                    <p class="description"><?php echo $details['description']; ?></p>
                </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit">Guardar Configuraciones de Empresa</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
