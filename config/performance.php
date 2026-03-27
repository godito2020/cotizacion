<?php
// Performance optimization settings

// OPcache settings (only applied if not already set by PHP config)
// Note: opcache.enable cannot be changed at runtime, only via php.ini
if (function_exists('opcache_get_status')) {
    @ini_set('opcache.memory_consumption', 128);
    @ini_set('opcache.max_accelerated_files', 4000);
}

// Disable some unnecessary features for better performance
ini_set('html_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', APP_ROOT . '/logs/error.log');

// Set memory limit for large Excel imports
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Database connection optimization
define('DB_PERSISTENT', true);

// Cache settings
define('ENABLE_QUERY_CACHE', true);
define('CACHE_TIMEOUT', 300); // 5 minutes

// Create logs directory if it doesn't exist
$logDir = APP_ROOT . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
?>