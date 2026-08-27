<?php
// Script de Instalación del Sistema de Cotizaciones

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('INSTALL_STEP', isset($_GET['step']) ? (int)$_GET['step'] : 1);
$error_message = '';
$success_message = '';

// Incluir configuración básica si ya existe (para reintentos o pasos posteriores)
if (file_exists('../config/config.php')) {
    // No queremos definir constantes si ya están definidas por el config.php principal
    // Así que lo incluimos de una manera que no choque si el instalador define las suyas.
    // O mejor aún, solo leemos lo que necesitamos si es necesario.
}

function check_php_version() {
    return version_compare(PHP_VERSION, '7.2.0', '>=');
}

function check_mysql_extension() {
    return extension_loaded('mysqli');
}

function check_pdo_mysql_extension() {
    return extension_loaded('pdo_mysql');
}

function check_gd_extension() {
    return extension_loaded('gd');
}

function check_fileinfo_extension() {
    return extension_loaded('fileinfo');
}


function check_writable_dirs() {
    $paths = [
        '../config/',
        '../uploads/',
        // '../cache/' // Si tuvieras una carpeta de caché
    ];
    $not_writable = [];
    foreach ($paths as $path) {
        if (!is_writable(dirname(__FILE__) . '/' . $path)) {
            $not_writable[] = $path;
        }
    }
    return $not_writable;
}

function test_db_connection($host, $user, $pass, $name) {
    try {
        $conn = new mysqli($host, $user, $pass);
        if ($conn->connect_error) {
            return "Error de conexión: " . $conn->connect_error;
        }
        // Intentar crear la base de datos si no existe
        if (!$conn->select_db($name)) {
            if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                return "Error al crear la base de datos '$name': " . $conn->error;
            }
            $conn->select_db($name);
        }
        $conn->close();
        return true;
    } catch (Exception $e) {
        return "Excepción: " . $e->getMessage();
    }
}

function create_config_file($db_host, $db_user, $db_pass, $db_name) {
    $config_template = file_get_contents('config.template.php');
    if ($config_template === false) {
        return "No se pudo leer config.template.php";
    }

    $config_content = str_replace(
        ['{{DB_HOST}}', '{{DB_USER}}', '{{DB_PASS}}', '{{DB_NAME}}'],
        [$db_host, $db_user, $db_pass, $db_name],
        $config_template
    );

    $config_path = dirname(__FILE__) . '/../config/config.php';
    if (file_put_contents($config_path, $config_content)) {
        return true;
    } else {
        return "No se pudo escribir en el archivo de configuración: $config_path. Verifique los permisos.";
    }
}

function import_sql_file($host, $user, $pass, $name, $sql_file) {
    if (!file_exists($sql_file)) {
        return "Archivo SQL no encontrado: $sql_file";
    }

    $conn = new mysqli($host, $user, $pass, $name);
    if ($conn->connect_error) {
        return "Error de conexión: " . $conn->connect_error;
    }
    $conn->set_charset("utf8mb4");

    $sql_content = file_get_contents($sql_file);
    // Remover comentarios y dividir en sentencias individuales
    $sql_content = preg_replace('/(--|#).*/', '', $sql_content); // Remover comentarios SQL
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content); // Remover comentarios multi-línea
    $statements = explode(';', $sql_content);

    $errors = [];
    foreach ($statements as $statement) {
        $stmt = trim($statement);
        if (!empty($stmt)) {
            if (!$conn->query($stmt)) {
                $errors[] = "Error ejecutando SQL: " . $conn->error . " (Query: " . substr($stmt, 0, 100) . "...)";
            }
        }
    }
    $conn->close();
    return empty($errors) ? true : implode("<br>", $errors);
}

function create_admin_user($conn_details, $admin_email, $admin_password) {
    $conn = new mysqli($conn_details['host'], $conn_details['user'], $conn_details['pass'], $conn_details['name']);
    if ($conn->connect_error) {
        return "Error de conexión al crear usuario admin: " . $conn->connect_error;
    }
    $conn->set_charset("utf8mb4");

    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $nombre_completo = "Administrador del Sistema";
    $rol = "admin"; // Asumiendo que la tabla usuarios tiene un campo 'rol'

    // Verificar si la tabla 'usuarios' existe y tiene los campos necesarios
    $result = $conn->query("SHOW COLUMNS FROM `usuarios` LIKE 'email'");
    if (!$result || $result->num_rows == 0) {
        return "La tabla 'usuarios' o el campo 'email' no existe. Asegúrate de que el SQL se haya importado correctamente.";
    }
    // Asumimos que la tabla 'usuarios' tiene al menos: id, nombre_completo, email, password_hash, rol, fecha_creacion
    $stmt = $conn->prepare("INSERT INTO `usuarios` (nombre_completo, email, password_hash, rol, fecha_creacion) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) {
        return "Error preparando la consulta para crear admin: " . $conn->error;
    }
    $stmt->bind_param("ssss", $nombre_completo, $admin_email, $hashed_password, $rol);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return true;
    } else {
        $error = "Error al crear usuario administrador: " . $stmt->error;
        $stmt->close();
        $conn->close();
        return $error;
    }
}


// Lógica de los pasos
if (INSTALL_STEP == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['db_host'] = $_POST['db_host'] ?? 'localhost';
    $_SESSION['db_user'] = $_POST['db_user'] ?? '';
    $_SESSION['db_pass'] = $_POST['db_pass'] ?? '';
    $_SESSION['db_name'] = $_POST['db_name'] ?? '';

    $db_test_result = test_db_connection(
        $_SESSION['db_host'],
        $_SESSION['db_user'],
        $_SESSION['db_pass'],
        $_SESSION['db_name']
    );

    if ($db_test_result === true) {
        $config_result = create_config_file(
            $_SESSION['db_host'],
            $_SESSION['db_user'],
            $_SESSION['db_pass'],
            $_SESSION['db_name']
        );
        if ($config_result === true) {
            header('Location: install.php?step=3');
            exit;
        } else {
            $error_message = $config_result; // Mensaje de error de create_config_file
        }
    } else {
        $error_message = $db_test_result; // Mensaje de error de test_db_connection
    }
} elseif (INSTALL_STEP == 3 && !isset($_SESSION['db_host'])) {
    // Si llega al paso 3 sin datos de DB, redirigir al paso 2
    header('Location: install.php?step=2');
    exit;
} elseif (INSTALL_STEP == 3 && isset($_SESSION['db_host'])) {
    // Importar SQL
    $sql_import_result = import_sql_file(
        $_SESSION['db_host'],
        $_SESSION['db_user'],
        $_SESSION['db_pass'],
        $_SESSION['db_name'],
        'database.sql' // Asegúrate que este archivo exista en la carpeta 'install'
    );

    if ($sql_import_result === true) {
        $success_message = "Tablas de la base de datos creadas/importadas correctamente.";
        // Automáticamente pasar al siguiente paso o mostrar un botón
         header('Location: install.php?step=4');
         exit;
    } else {
        $error_message = "Error al importar el archivo SQL: " . $sql_import_result;
    }
} elseif (INSTALL_STEP == 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';

    if (empty($admin_email) || empty($admin_password)) {
        $error_message = "El correo y la contraseña del administrador son obligatorios.";
    } elseif ($admin_password !== $admin_password_confirm) {
        $error_message = "Las contraseñas no coinciden.";
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "El formato del correo electrónico no es válido.";
    } else {
        $conn_details = [
            'host' => $_SESSION['db_host'],
            'user' => $_SESSION['db_user'],
            'pass' => $_SESSION['db_pass'],
            'name' => $_SESSION['db_name']
        ];
        $admin_creation_result = create_admin_user($conn_details, $admin_email, $admin_password);
        if ($admin_creation_result === true) {
            header('Location: install.php?step=5');
            exit;
        } else {
            $error_message = $admin_creation_result;
        }
    }
} elseif (INSTALL_STEP == 5) {
    // Limpiar sesión de instalación
    // session_destroy(); // Opcional, podrías querer mantener algunos datos para mostrar un resumen.
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación del Sistema de Cotizaciones</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f6f8; color: #333; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .installer-container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 600px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 20px; }
        h2 { color: #34495e; margin-top: 30px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;}
        .step-indicator { text-align: center; margin-bottom: 20px; font-size: 0.9em; color: #7f8c8d; }
        ul { list-style: none; padding: 0; }
        li { margin-bottom: 10px; padding: 8px; border-radius: 4px; }
        li.ok { background-color: #e8f5e9; color: #2e7d32; border-left: 3px solid #4caf50; }
        li.error { background-color: #ffebee; color: #c62828; border-left: 3px solid #f44336; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"], input[type="password"], input[type="email"] { width: calc(100% - 20px); padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input[type="text"]:focus, input[type="password"]:focus, input[type="email"]:focus { border-color: #3498db; box-shadow: 0 0 5px rgba(52,152,219,0.2); outline: none; }
        .button, button { background-color: #3498db; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 1em; text-align: center; }
        .button:hover, button:hover { background-color: #2980b9; }
        .button-next { float: right; }
        .button-disabled { background-color: #bdc3c7; cursor: not-allowed; }
        .error-message { background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #ef9a9a; }
        .success-message { background-color: #e8f5e9; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #a5d6a7; }
        .text-center { text-align: center; }
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>
    <div class="installer-container">
        <h1>Instalación del Sistema de Cotizaciones</h1>
        <p class="step-indicator">Paso <?php echo INSTALL_STEP; ?> de 5</p>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($success_message && INSTALL_STEP != 3 && INSTALL_STEP != 5): // Mensajes de éxito intermedios no tan necesarios si hay redirección ?>
             <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>


        <?php if (INSTALL_STEP == 1): ?>
            <h2>Paso 1: Verificación de Requisitos</h2>
            <ul>
                <li class="<?php echo check_php_version() ? 'ok' : 'error'; ?>">
                    Versión de PHP >= 7.2.0 (Actual: <?php echo PHP_VERSION; ?>)
                </li>
                <li class="<?php echo check_mysql_extension() ? 'ok' : 'error'; ?>">
                    Extensión MySQLi habilitada
                </li>
                <li class="<?php echo check_pdo_mysql_extension() ? 'ok' : 'error'; ?>">
                    Extensión PDO MySQL habilitada (recomendado para futuras mejoras)
                </li>
                 <li class="<?php echo check_gd_extension() ? 'ok' : 'error'; ?>">
                    Extensión GD habilitada (para manipulación de imágenes, ej: logos)
                </li>
                <li class="<?php echo check_fileinfo_extension() ? 'ok' : 'error'; ?>">
                    Extensión Fileinfo habilitada (para validación de tipos de archivo)
                </li>
                <?php $writable_dirs = check_writable_dirs(); ?>
                <?php if (empty($writable_dirs)): ?>
                    <li class="ok">Directorios requeridos (config/, uploads/) tienen permisos de escritura.</li>
                <?php else: ?>
                    <li class="error">
                        Los siguientes directorios necesitan permisos de escritura:
                        <ul>
                            <?php foreach ($writable_dirs as $dir): ?>
                                <li class="error" style="margin-left:20px;"><?php echo htmlspecialchars($dir); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <?php
                $all_ok = check_php_version() && check_mysql_extension() && check_pdo_mysql_extension() && check_gd_extension() && check_fileinfo_extension() && empty($writable_dirs);
            ?>
            <div class="text-center" style="margin-top: 20px;">
                <?php if ($all_ok): ?>
                    <a href="install.php?step=2" class="button">Siguiente Paso</a>
                <?php else: ?>
                    <p class="error-message">Por favor, corrija los errores anteriores para continuar.</p>
                    <a href="install.php?step=1" class="button">Reintentar Verificación</a>
                <?php endif; ?>
            </div>

        <?php elseif (INSTALL_STEP == 2): ?>
            <h2>Paso 2: Configuración de la Base de Datos</h2>
            <p>Por favor, ingrese los detalles de conexión a su base de datos MySQL. El instalador intentará crear la base de datos si no existe.</p>
            <form method="POST" action="install.php?step=2">
                <div class="form-group">
                    <label for="db_host">Host de la Base de Datos:</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_SESSION['db_host'] ?? 'localhost'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_name">Nombre de la Base de Datos:</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_SESSION['db_name'] ?? 'cotizador_db'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Usuario de la Base de Datos:</label>
                    <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_SESSION['db_user'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Contraseña del Usuario:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_SESSION['db_pass'] ?? ''); ?>">
                </div>
                <div class="clearfix" style="margin-top: 20px;">
                    <a href="install.php?step=1" class="button" style="float:left;">Anterior</a>
                    <button type="submit" class="button-next">Probar Conexión y Guardar</button>
                </div>
            </form>

        <?php elseif (INSTALL_STEP == 3): ?>
            <h2>Paso 3: Creación de Tablas</h2>
            <?php if ($success_message): ?>
                <div class="success-message"><?php echo $success_message; ?></div>
                 <p>Las tablas de la base de datos han sido creadas o importadas correctamente.</p>
                <div class="text-center" style="margin-top: 20px;">
                     <a href="install.php?step=4" class="button">Siguiente: Crear Usuario Administrador</a>
                </div>
            <?php elseif($error_message): ?>
                <!-- El mensaje de error ya se muestra arriba -->
                <p>Hubo un problema al importar la estructura de la base de datos. Verifique el mensaje de error y asegúrese de que el archivo <code>install/database.sql</code> exista y sea correcto.</p>
                <div class="text-center" style="margin-top: 20px;">
                    <a href="install.php?step=2" class="button">Volver a Configuración de BD</a>
                </div>
            <?php else: ?>
                <p>Importando estructura de la base de datos...</p>
                <p>Si esta página no se actualiza automáticamente, por favor <a href="install.php?step=3">intente de nuevo</a> o revise los mensajes de error.</p>
            <?php endif; ?>


        <?php elseif (INSTALL_STEP == 4): ?>
            <h2>Paso 4: Crear Usuario Administrador</h2>
            <p>Ingrese los datos para el usuario administrador principal del sistema.</p>
            <form method="POST" action="install.php?step=4">
                <div class="form-group">
                    <label for="admin_email">Correo Electrónico del Administrador:</label>
                    <input type="email" id="admin_email" name="admin_email" required value="<?php echo htmlspecialchars($_POST['admin_email'] ?? 'admin@example.com'); ?>">
                </div>
                <div class="form-group">
                    <label for="admin_password">Contraseña del Administrador:</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                <div class="form-group">
                    <label for="admin_password_confirm">Confirmar Contraseña:</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                </div>
                 <div class="clearfix" style="margin-top: 20px;">
                    <a href="install.php?step=3" class="button" style="float:left;">Anterior</a>
                    <button type="submit" class="button-next">Crear Administrador</button>
                </div>
            </form>

        <?php elseif (INSTALL_STEP == 5): ?>
            <h2>Paso 5: Instalación Completada</h2>
            <div class="success-message">
                ¡Felicidades! El Sistema de Cotizaciones ha sido instalado correctamente.
            </div>
            <p><strong>Importante:</strong> Por razones de seguridad, por favor elimine o renombre la carpeta <code><?php echo htmlspecialchars(dirname(__FILE__)); ?></code> ahora.</p>
            <p>Puede acceder al sistema a través de los siguientes enlaces:</p>
            <ul>
                <?php
                // Determinar la URL base de forma más robusta
                $base_url_installer = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                $script_dir = str_replace('/install/install.php', '', $_SERVER['SCRIPT_NAME']);
                $final_base_url = rtrim($base_url_installer . $script_dir, '/');
                ?>
                <li><a href="<?php echo $final_base_url; ?>/index.php" target="_blank">Acceder al Sistema</a></li>
                <li><a href="<?php echo $final_base_url; ?>/admin.php" target="_blank">Acceder al Panel de Administración</a> (si tienes un `admin.php` separado)</li>
            </ul>
            <p>Sus credenciales de administrador son:</p>
            <ul>
                <li><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['admin_email_created'] ?? 'El correo que ingresó en el paso anterior.'); ?></li>
                <li><strong>Contraseña:</strong> La contraseña que ingresó en el paso anterior.</li>
            </ul>
            <?php
                // Guardar el email para mostrarlo, luego limpiar la sesión
                if (isset($_POST['admin_email'])) $_SESSION['admin_email_created'] = $_POST['admin_email'];
                // session_destroy(); // Descomentar si quieres limpiar toda la sesión aquí
            ?>
        <?php else: ?>
            <p>Paso desconocido. Por favor <a href="install.php?step=1">comience de nuevo</a>.</p>
        <?php endif; ?>

        <footer style="text-align: center; margin-top: 30px; font-size: 0.8em; color: #7f8c8d;">
            <p>&copy; <?php echo date("Y"); ?> Sistema de Cotizaciones. Todos los derechos reservados.</p>
        </footer>
    </div>
</body>
</html>
<?php
// Crear archivo config.template.php si no existe, para el paso 2
$config_template_path = dirname(__FILE__) . '/config.template.php';
if (!file_exists($config_template_path)) {
    $template_content = <<<EOT
<?php
// Archivo de configuración de la aplicación

// Configuración de la base de datos
define('DB_HOST', '{{DB_HOST}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');
define('DB_NAME', '{{DB_NAME}}');

// Configuración de la URL base (ajustar según sea necesario)
// Detectar automáticamente si es HTTP o HTTPS
\$protocol = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off' || \$_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// Obtener el nombre del host
\$host = \$_SERVER['HTTP_HOST'];
// Obtener la ruta base del script actual y eliminar el nombre del script (index.php, install.php, etc.)
\$script_path = dirname(\$_SERVER['SCRIPT_NAME']);
// Asegurarse de que la ruta base termine con una barra si no es el directorio raíz
\$base_path = (\$script_path == '/' || \$script_path == '\\\') ? '' : \$script_path;

// Si el script_path es /install, debemos quitarlo para la URL base del proyecto
if (basename(\$script_path) === 'install') {
    \$base_path = dirname(\$script_path);
    \$base_path = (\$base_path == '/' || \$base_path == '\\\') ? '' : \$base_path;
}
define('BASE_URL', \$protocol . \$host . \$base_path . '/');


// Otras configuraciones generales
define('APP_NAME', 'Sistema de Cotizaciones Pro');
define('APP_VERSION', '1.0.0');

// Configuración de correo (ejemplo para PHPMailer)
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'noreply@example.com');
define('MAIL_PASSWORD', 'tu_contraseña_smtp');
define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', APP_NAME);

// Configuración de APIs (placeholders)
define('SUNAT_API_URL', 'URL_API_SUNAT');
define('SUNAT_API_TOKEN', 'TOKEN_API_SUNAT');
define('RENIEC_API_URL', 'URL_API_RENIEC');
define('RENIEC_API_TOKEN', 'TOKEN_API_RENIEC');

// Zonas horarias y localización
date_default_timezone_set('America/Lima'); // Ajustar a la zona horaria correcta
setlocale(LC_TIME, 'es_PE.UTF-8', 'Spanish_Peru.1252'); // Para formatos de fecha y moneda en español

// Habilitar/deshabilitar modo debug (mostrar errores detallados)
// En producción, esto debería ser false.
define('DEBUG_MODE', true); // Cambiar a false en producción

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Constantes para rutas de directorios (opcional pero útil)
// __DIR__ aquí se referirá a la carpeta 'config' donde se guardará este archivo.
define('CONFIG_DIR_PATH', __DIR__);
define('ROOT_PATH', dirname(CONFIG_DIR_PATH)); // Esto apuntará a la carpeta 'cotizacion'
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
// CONFIG_PATH ya está definido por CONFIG_DIR_PATH, pero si quieres ser explícito:
// define('CONFIG_PATH', ROOT_PATH . '/config');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UTILS_PATH', ROOT_PATH . '/utils');
define('INSTALL_PATH', ROOT_PATH . '/install'); // Aunque este se debe borrar post-instalación

// Iniciar sesión si no está iniciada (útil para toda la app)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

?>
EOT;
    file_put_contents($config_template_path, $template_content);
}

// No es necesario crear database.sql aquí si ya está versionado y completo.
// Solo nos aseguramos que exista para el paso 3.
$database_sql_path = dirname(__FILE__) . '/database.sql';
if (!file_exists($database_sql_path) && INSTALL_STEP == 3) {
     $error_message = "Error crítico: El archivo 'install/database.sql' no se encuentra. La instalación no puede continuar. Por favor, restaure este archivo.";
     // Podríamos forzar la detención o mostrar el error prominentemente.
}

?>
