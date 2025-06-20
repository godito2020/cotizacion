<?php
// Application Name
define('APP_NAME', 'Sistema de Cotizaciones');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'cotizacion_db'); // A default name, will be configured during installation
define('DB_USER', 'root');          // Default XAMPP/MAMP user, change for production
define('DB_PASS', '');              // Default XAMPP/MAMP password, change for production
define('DB_CHARSET', 'utf8mb4');

// Base URL
// Adjust this if your project is in a subdirectory or on a different domain
define('BASE_URL', 'http://localhost/cotizacion/public');

// Application Paths
define('APP_ROOT', dirname(__DIR__)); // Points to 'cotizacion' directory
define('CONFIG_PATH', APP_ROOT . '/config');
define('LIB_PATH', APP_ROOT . '/lib');
define('INCLUDES_PATH', APP_ROOT . '/includes');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('TEMPLATES_PATH', APP_ROOT . '/templates'); // Corrected path
define('SCRIPTS_PATH', APP_ROOT . '/scripts');

// Error reporting - recommended for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('America/Lima'); // Set your default timezone
?>
