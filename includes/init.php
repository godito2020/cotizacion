<?php
// cotizacion/includes/init.php

// 1. Start Session
// Should be called before any output is sent to the browser.
if (session_status() == PHP_SESSION_NONE) {
    // Configurar directorio de sesiones (soluciona problema de permisos en IIS)
    $sessionPath = dirname(__DIR__) . '/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0755, true);
    }
    session_save_path($sessionPath);

    // Set session cookie parameters for security
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
    // Release session file lock immediately after reading.
    // Code that needs to write to session (login, logout, role/user caching)
    // must call session_start() again before modifying $_SESSION.
    session_write_close();
}

// 2. Load Configuration
// __DIR__ is the directory of the current file (includes)
// dirname(__DIR__) is the parent directory (cotizacion)
require_once dirname(__DIR__) . '/config/config.php'; // Defines constants like DB_HOST, BASE_URL, LIB_PATH
require_once dirname(__DIR__) . '/config/performance.php'; // Performance optimizations
require_once dirname(__DIR__) . '/config/permissions.php'; // Permissions class

// 3. Load Database Connection Handler
// This makes getDBConnection() available globally if database.php defines it.
// Or makes Database class available if you prefer static methods.
require_once CONFIG_PATH . '/database.php';

// 4. Autoloader for classes in /lib directory
// This function will be called automatically when a class is used but not yet defined.
spl_autoload_register(function ($className) {
    // Construct the full path to the class file.
    // Assumes class names match file names (e.g., class Auth is in Auth.php)
    // Adjust if your naming convention or directory structure is different.
    $classFile = LIB_PATH . '/' . str_replace('\\', '/', $className) . '.php';

    // Debug: uncomment to see what files are being looked for
    // error_log("Autoloader looking for: $classFile (LIB_PATH: " . LIB_PATH . ")");

    if (file_exists($classFile)) {
        require_once $classFile;
    } else {
        // Optional: Log or throw an error if a class file is not found
        error_log("Autoloader: Class file not found: " . $classFile . " for class " . $className);
        // For development, it might be helpful to die here to catch issues early.
        // die("Autoloader: Class file not found for class $className at $classFile");
    }
});

// 5. Error and Exception Handling (Basic Example)
// You might want a more sophisticated handler.
// set_error_handler(function($severity, $message, $file, $line) {
//     if (!(error_reporting() & $severity)) {
//         // This error code is not included in error_reporting
//         return;
//     }
//     throw new ErrorException($message, 0, $severity, $file, $line);
// });

// set_exception_handler(function($exception) {
//     error_log("Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
//     // Show a generic error page in production
//     // For development:
//     echo "<b>Exception:</b> " . $exception->getMessage();
//     echo "<pre>" . $exception->getTraceAsString() . "</pre>";
// });


// 6. Instantiate common classes or utilities if needed globally
// For example, you might instantiate the Auth class here if it's used on every page,
// though it's often better to instantiate classes as needed.
// $auth = new Auth(); // Example

// Any other global initializations can go here.
// For example, setting up a CSRF token system, initializing a templating engine, etc.

?>
