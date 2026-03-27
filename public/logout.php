<?php
// cotizacion/public/logout.php
require_once __DIR__ . '/../includes/init.php'; // Includes session_start, config, db, autoloader

$auth = new Auth(); // Auth class should be autoloaded

$auth->logout();

// Redirect to login page with a message
// Ensure BASE_URL is defined in config.php
$auth->redirect(BASE_URL . '/login.php?logged_out=true');
?>
