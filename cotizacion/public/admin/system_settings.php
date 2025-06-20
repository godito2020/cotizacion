<?php
// cotizacion/public/admin/system_settings.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$settingsRepo = new Settings(); // Autoloaded

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

if (!$auth->hasRole('System Admin')) {
    $_SESSION['error_message'] = "No está autorizado para acceder a esta página.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$loggedInUser = $auth->getUser();
$userRepo = new User(); // For header display of roles

$page_title = "Configuraciones del Sistema";
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message_page = $_SESSION['error_message'] ?? null; // Use different var to avoid conflict with form errors
unset($_SESSION['error_message']);
$errors = [];


// Define system settings to be managed on this page
// Using constants from Settings class for keys
$system_settings_keys = [
    Settings::KEY_SUNAT_API_URL => ['label' => 'URL API SUNAT', 'type' => 'text', 'description' => 'URL base para los servicios API de SUNAT.'],
    Settings::KEY_SUNAT_API_KEY => ['label' => 'Clave API SUNAT', 'type' => 'password', 'description' => 'Credencial para autenticación con API SUNAT.'],
    // RENIEC might not be used directly, but as an example
    // Settings::KEY_RENIEC_API_URL => ['label' => 'URL API RENIEC', 'type' => 'text'],
    // Settings::KEY_RENIEC_API_KEY => ['label' => 'Clave API RENIEC', 'type' => 'password'],

    Settings::KEY_SMTP_HOST => ['label' => 'Servidor SMTP (Host)', 'type' => 'text', 'description' => 'Ej: smtp.example.com'],
    Settings::KEY_SMTP_PORT => ['label' => 'Puerto SMTP', 'type' => 'number', 'description' => 'Ej: 587, 465, 25'],
    Settings::KEY_SMTP_USER => ['label' => 'Usuario SMTP', 'type' => 'text', 'description' => 'Su dirección de correo o nombre de usuario.'],
    Settings::KEY_SMTP_PASS => ['label' => 'Contraseña SMTP', 'type' => 'password', 'description' => 'Contraseña para el servidor SMTP.'],
    Settings::KEY_SMTP_ENCRYPTION => ['label' => 'Cifrado SMTP (tls/ssl)', 'type' => 'text', 'description' => 'Dejar vacío si no aplica.'],
    Settings::KEY_SMTP_FROM_EMAIL => ['label' => 'Email Remitente (Por Defecto)', 'type' => 'email', 'description' => 'Correo desde el cual se enviarán los emails del sistema.'],
    Settings::KEY_SMTP_FROM_NAME => ['label' => 'Nombre Remitente (Por Defecto)', 'type' => 'text', 'description' => 'Nombre que aparecerá como remitente.'],

    Settings::KEY_DEFAULT_TERMS_QUOTATION => ['label' => 'Términos y Condiciones por Defecto (Cotizaciones)', 'type' => 'textarea', 'description' => 'Plantilla por defecto para nuevas cotizaciones.'],
    Settings::KEY_DEFAULT_NOTES_QUOTATION => ['label' => 'Notas por Defecto (Cotizaciones)', 'type' => 'textarea', 'description' => 'Notas por defecto para nuevas cotizaciones.'],
    Settings::KEY_CURRENCY_SYMBOL => ['label' => 'Símbolo de Moneda por Defecto', 'type' => 'text', 'description' => 'Ej: S/, $, €, etc.'],
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $all_success = true;
    foreach ($system_settings_keys as $key => $details) {
        if (isset($_POST[$key])) { // Check if the field was submitted
            $value = trim($_POST[$key]);
            // For password fields, only update if a new value is provided
            if ($details['type'] === 'password' && empty($value)) {
                // If password field is empty, don't update it (keep existing)
                // To clear a password, user would need to enter a special value or have a 'clear' checkbox
                // For simplicity now, empty means no change.
                continue;
            }

            if (!$settingsRepo->set($key, $value, null, $details['description'] ?? $details['label'])) {
                $all_success = false;
                $errors[] = "Error al guardar la configuración: " . htmlspecialchars($details['label']);
            }
        }
    }

    if ($all_success && empty($errors)) {
        $_SESSION['message'] = "Configuraciones del sistema actualizadas exitosamente.";
    } else {
        $_SESSION['error_message'] = "Algunas configuraciones no pudieron ser guardadas. Verifique los errores.";
        // Errors array will be displayed below
    }
    // Redirect to self to show messages and clear POST data
    $auth->redirect(BASE_URL . '/admin/system_settings.php');
}

// Fetch current values for display
$current_settings = [];
foreach (array_keys($system_settings_keys) as $key) {
    $current_settings[$key] = $settingsRepo->get($key, null, ''); // Default to empty string if not set
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
        .admin-container { padding: 20px; max-width: 900px; margin: 20px auto; }
        .admin-content { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ddd;
            border-radius: 4px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 100px; }
        .form-group .description { font-size: 0.85em; color: #666; margin-top: 4px; }
        .form-actions button { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size:16px; background-color: #007bff; color: white; }
        .form-actions button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .error-messages ul { padding-left: 20px; margin: 0; }
        fieldset { border:1px solid #ddd; padding:15px; margin-bottom:20px; border-radius:5px;}
        legend {font-weight:bold; color:#337ab7; padding:0 5px;}
    </style>
</head>
<body>
    <header class="admin-header"><h1>Admin Panel</h1></header>
    <div class="user-info">
        Usuario: <?php echo htmlspecialchars($loggedInUser['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($loggedInUser['id']), 'role_name'))); ?>) |
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

            <form action="system_settings.php" method="POST">
                <fieldset>
                    <legend>APIs Externas</legend>
                    <div class="form-group">
                        <label for="<?php echo Settings::KEY_SUNAT_API_URL; ?>"><?php echo $system_settings_keys[Settings::KEY_SUNAT_API_URL]['label']; ?></label>
                        <input type="text" id="<?php echo Settings::KEY_SUNAT_API_URL; ?>" name="<?php echo Settings::KEY_SUNAT_API_URL; ?>" value="<?php echo htmlspecialchars($current_settings[Settings::KEY_SUNAT_API_URL]); ?>">
                        <p class="description"><?php echo $system_settings_keys[Settings::KEY_SUNAT_API_URL]['description']; ?></p>
                    </div>
                    <div class="form-group">
                        <label for="<?php echo Settings::KEY_SUNAT_API_KEY; ?>"><?php echo $system_settings_keys[Settings::KEY_SUNAT_API_KEY]['label']; ?></label>
                        <input type="password" id="<?php echo Settings::KEY_SUNAT_API_KEY; ?>" name="<?php echo Settings::KEY_SUNAT_API_KEY; ?>" value="" placeholder="Dejar vacío para no cambiar">
                         <p class="description"><?php echo $system_settings_keys[Settings::KEY_SUNAT_API_KEY]['description']; ?> Actual: <?php echo !empty($current_settings[Settings::KEY_SUNAT_API_KEY]) ? 'Configurada' : 'No configurada'; ?></p>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Configuración SMTP (Envío de Correo)</legend>
                    <?php foreach ($system_settings_keys as $key => $details): ?>
                        <?php if (strpos($key, 'system.smtp.') === 0): ?>
                        <div class="form-group">
                            <label for="<?php echo $key; ?>"><?php echo $details['label']; ?></label>
                            <?php if ($details['type'] === 'password'): ?>
                                <input type="password" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="" placeholder="Dejar vacío para no cambiar">
                                <p class="description"><?php echo $details['description']; ?> Actual: <?php echo !empty($current_settings[$key]) ? 'Configurada' : 'No configurada'; ?></p>
                            <?php else: ?>
                                <input type="<?php echo $details['type']; ?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($current_settings[$key]); ?>">
                                <p class="description"><?php echo $details['description']; ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset>
                    <legend>Valores por Defecto para Cotizaciones</legend>
                     <div class="form-group">
                        <label for="<?php echo Settings::KEY_DEFAULT_TERMS_QUOTATION; ?>"><?php echo $system_settings_keys[Settings::KEY_DEFAULT_TERMS_QUOTATION]['label']; ?></label>
                        <textarea id="<?php echo Settings::KEY_DEFAULT_TERMS_QUOTATION; ?>" name="<?php echo Settings::KEY_DEFAULT_TERMS_QUOTATION; ?>"><?php echo htmlspecialchars($current_settings[Settings::KEY_DEFAULT_TERMS_QUOTATION]); ?></textarea>
                        <p class="description"><?php echo $system_settings_keys[Settings::KEY_DEFAULT_TERMS_QUOTATION]['description']; ?></p>
                    </div>
                     <div class="form-group">
                        <label for="<?php echo Settings::KEY_DEFAULT_NOTES_QUOTATION; ?>"><?php echo $system_settings_keys[Settings::KEY_DEFAULT_NOTES_QUOTATION]['label']; ?></label>
                        <textarea id="<?php echo Settings::KEY_DEFAULT_NOTES_QUOTATION; ?>" name="<?php echo Settings::KEY_DEFAULT_NOTES_QUOTATION; ?>"><?php echo htmlspecialchars($current_settings[Settings::KEY_DEFAULT_NOTES_QUOTATION]); ?></textarea>
                        <p class="description"><?php echo $system_settings_keys[Settings::KEY_DEFAULT_NOTES_QUOTATION]['description']; ?></p>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Otros</legend>
                    <div class="form-group">
                        <label for="<?php echo Settings::KEY_CURRENCY_SYMBOL; ?>"><?php echo $system_settings_keys[Settings::KEY_CURRENCY_SYMBOL]['label']; ?></label>
                        <input type="text" id="<?php echo Settings::KEY_CURRENCY_SYMBOL; ?>" name="<?php echo Settings::KEY_CURRENCY_SYMBOL; ?>" value="<?php echo htmlspecialchars($current_settings[Settings::KEY_CURRENCY_SYMBOL]); ?>">
                        <p class="description"><?php echo $system_settings_keys[Settings::KEY_CURRENCY_SYMBOL]['description']; ?></p>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit">Guardar Configuraciones del Sistema</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
