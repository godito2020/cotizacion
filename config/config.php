<?php
// Database Configuration - Sistema de Cotizaciones
define('DB_HOST', 'localhost');
define('DB_NAME', 'cotizacion'); // Base de datos del sistema de cotizaciones
define('DB_USER', 'admin');
define('DB_PASS', 'Sistemas*2025');
define('DB_CHARSET', 'utf8mb4');

// Database Configuration - COBOL (para productos y stock)
define('COBOL_DB_HOST', 'localhost');
define('COBOL_DB_NAME', 'cobol'); // Base de datos COBOL con vistas de productos
define('COBOL_DB_USER', 'admin');
define('COBOL_DB_PASS', 'Sistemas*2025');
define('COBOL_DB_CHARSET', 'utf8mb4');


// Base URL
// Adjust this if your project is in a subdirectory or on a different domain

// Detectar automáticamente el entorno (local vs producción)
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Para desarrollo local en IIS:
    define('BASE_URL', 'http://localhost/coti/public');
} else {
    // Para producción (incluir /public porque IIS no tiene URL Rewrite):
    define('BASE_URL', 'https://coti.gsm.pe/public');
}

// Application Paths
define('APP_ROOT', dirname(__DIR__)); // Points to 'cotizacion' directory
define('CONFIG_PATH', APP_ROOT . '/config');
define('LIB_PATH', APP_ROOT . '/lib');
define('INCLUDES_PATH', APP_ROOT . '/includes');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('TEMPLATES_PATH', APP_ROOT . '/templates');
define('SCRIPTS_PATH', APP_ROOT . '/scripts');

// Error reporting
// TEMPORAL: Habilitar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Para producción (desactivar mensajes de error visibles):
// ini_set('display_errors', 0);
// ini_set('display_startup_errors', 0);
// error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', APP_ROOT . '/logs/php_errors.log');

// Timezone
date_default_timezone_set('America/Lima'); // Set your default timezone

/**
 * Genera la URL para servir un archivo de uploads a través del proxy PHP.
 * En IIS/Windows los archivos creados por PHP no tienen permisos NTFS para IUSR,
 * por eso se sirven con img.php que corre bajo el mismo contexto que PHP.
 *
 * @param  string $uploadRelativePath  Ruta relativa almacenada en BD, ej: "uploads/company/foto.png"
 * @return string  URL completa para usar en <img src="...">, manifest, etc.
 */
function upload_url(string $uploadRelativePath): string {
    $f = ltrim($uploadRelativePath, '/');
    // Quitar el prefijo "uploads/" si ya existe para normalizar
    $f = preg_replace('#^uploads/#', '', $f);
    // No codificar barras para evitar problemas de interpretación en IIS
    return BASE_URL . '/img.php?f=' . $f;
}
?>
