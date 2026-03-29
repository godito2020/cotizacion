<?php
// Archivo de configuración de la aplicación

// Configuración de la base de datos (se llenará durante la instalación)
define('DB_HOST', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');

// Configuración de la URL base (ajustar según sea necesario)
// Detectar automáticamente si es HTTP o HTTPS
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
// Obtener el nombre del host
$host = $_SERVER['HTTP_HOST'];
// Obtener la ruta base del script actual y eliminar el nombre del script (index.php, install.php, etc.)
$script_path = dirname($_SERVER['SCRIPT_NAME']);
// Asegurarse de que la ruta base termine con una barra si no es el directorio raíz
$base_path = ($script_path == '/' || $script_path == '\\') ? '' : $script_path;
define('BASE_URL', $protocol . $host . $base_path . '/');


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
define('DEBUG_MODE', true);

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
define('ROOT_PATH', dirname(__DIR__)); // Esto apuntará a la carpeta 'cotizacion'
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('UTILS_PATH', ROOT_PATH . '/utils');
define('INSTALL_PATH', ROOT_PATH . '/install');

?>
