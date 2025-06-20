<?php
// cotizacion/public/admin/company_profile.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$companyRepo = new Company();

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

if (!$auth->hasRole('Company Admin')) { // Only Company Admins can edit their profile
    $_SESSION['error_message'] = "No está autorizado para acceder a esta página.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$page_title = "Perfil de Empresa";
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message_page = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
$errors = [];

$company_data = $companyRepo->getById($company_id); // Company ID is from user's session

if (!$company_data) {
    // This should ideally not happen if company_id in user session is valid
    $_SESSION['error_message'] = "No se encontró información de su empresa. Contacte al administrador del sistema.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

// Define upload path and allowed types for logo
define('LOGO_UPLOAD_DIR', PUBLIC_PATH . '/uploads/logos/');
define('ALLOWED_LOGO_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('MAX_LOGO_SIZE', 2 * 1024 * 1024); // 2MB

if (!is_dir(LOGO_UPLOAD_DIR)) {
    if (!mkdir(LOGO_UPLOAD_DIR, 0775, true)) {
        // This is a server configuration issue, should be logged.
        error_log("Failed to create logo upload directory: " . LOGO_UPLOAD_DIR);
        // For the user, it might mean logo upload won't work.
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? null);
    $address = trim($_POST['address'] ?? null);
    $phone = trim($_POST['phone'] ?? null);
    $email = trim($_POST['email'] ?? null);
    $current_logo_url = $company_data['logo_url']; // Keep current logo if not changed

    // Validation
    if (empty($name)) {
        $errors[] = "El nombre de la empresa es requerido.";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del correo electrónico no es válido.";
    }

    // Logo Upload Handling
    $new_logo_path_rel = $current_logo_url; // Relative path to store in DB
    if (isset($_FILES['logo_url']) && $_FILES['logo_url']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['logo_url']['tmp_name'];
        $fileName = basename($_FILES['logo_url']['name']); // Sanitize filename
        $fileSize = $_FILES['logo_url']['size'];
        $fileType = $_FILES['logo_url']['type'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileType, ALLOWED_LOGO_TYPES)) {
            $errors[] = "Tipo de archivo de logo no permitido. Use JPG, PNG o GIF.";
        }
        if ($fileSize > MAX_LOGO_SIZE) {
            $errors[] = "El archivo del logo excede el tamaño máximo de 2MB.";
        }

        if (empty($errors)) { // Proceed if no validation errors so far from other fields
            // Generate unique filename
            $uniqueFileName = "company_" . $company_id . "_" . time() . "." . $fileExtension;
            $destinationPath = LOGO_UPLOAD_DIR . $uniqueFileName;

            if (move_uploaded_file($fileTmpPath, $destinationPath)) {
                // Successfully uploaded new logo
                // Delete old logo if it exists and is different
                if ($current_logo_url && file_exists(PUBLIC_PATH . $current_logo_url) && $current_logo_url !== ('/uploads/logos/' . $uniqueFileName) ) {
                    unlink(PUBLIC_PATH . $current_logo_url);
                }
                $new_logo_path_rel = '/uploads/logos/' . $uniqueFileName; // Relative path for DB and web access
            } else {
                $errors[] = "Error al subir el nuevo logo. Intente de nuevo.";
            }
        }
    } elseif (isset($_FILES['logo_url']) && $_FILES['logo_url']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $errors[] = "Error con el archivo del logo: Código " . $_FILES['logo_url']['error'];
    }


    if (empty($errors)) {
        $success = $companyRepo->update(
            $company_id, $name, $tax_id, $address, $phone, $email, $new_logo_path_rel
        );

        if ($success) {
            $_SESSION['message'] = "Perfil de empresa actualizado exitosamente.";
            // Refresh company_data to show updated info immediately
            $company_data = $companyRepo->getById($company_id);
        } else {
            $errors[] = "Error al actualizar el perfil de la empresa.";
            // $error_message_page = "Error al actualizar el perfil de la empresa."; // Alternative way
        }
    }
    // If errors, they will be displayed below. $company_data will reflect POSTed values for sticky form.
    if(!empty($errors)){
         $company_data['name'] = $name;
         $company_data['tax_id'] = $tax_id;
         $company_data['address'] = $address;
         $company_data['phone'] = $phone;
         $company_data['email'] = $email;
         // $company_data['logo_url'] remains the old one or new one if upload was attempted but other errors occurred
    }
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
        .form-group input[type="email"],
        .form-group input[type="file"],
        .form-group textarea {
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
        .current-logo img { max-width: 150px; max-height: 100px; border:1px solid #ddd; padding:5px; border-radius:4px; margin-top:5px;}
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

            <form action="company_profile.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Nombre de la Empresa <span style="color:red;">*</span>:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($company_data['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="tax_id">ID Tributario (RUC):</label>
                    <input type="text" id="tax_id" name="tax_id" value="<?php echo htmlspecialchars($company_data['tax_id'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($company_data['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($company_data['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Dirección:</label>
                    <textarea id="address" name="address"><?php echo htmlspecialchars($company_data['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="logo_url">Logo de la Empresa:</label>
                    <input type="file" id="logo_url" name="logo_url" accept="image/jpeg,image/png,image/gif">
                    <p class="description">Subir un nuevo logo reemplazará el actual. Máx 2MB. JPG, PNG, GIF.</p>
                    <?php if (!empty($company_data['logo_url'])): ?>
                        <div class="current-logo">
                            <p>Logo Actual:</p>
                            <img src="<?php echo BASE_URL . htmlspecialchars($company_data['logo_url']); ?>" alt="Logo Actual">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button type="submit">Actualizar Perfil de Empresa</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
