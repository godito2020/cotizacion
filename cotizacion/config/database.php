<?php
// cotizacion/config/database.php

require_once __DIR__ . '/config.php'; // Ensure config constants are loaded

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // For a real application, log this error and show a generic message to the user.
            // Never expose detailed database errors in a production environment.
            error_log("Database Connection Error: " . $e->getMessage());
            // You could throw a custom exception here or die with a user-friendly message.
            // For development, it's okay to see the error, but for production, make it generic.
            die("Database connection failed. Please check logs or contact support. Error: " . $e->getMessage()); // Simplified for now
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}

// Global function to easily get the PDO connection
// This is an alternative to always calling Database::getInstance()->getConnection()
// Choose one pattern and stick to it. For simplicity, this function is convenient.
function getDBConnection(): PDO {
    return Database::getInstance()->getConnection();
}

// Test connection (optional, remove for production)
/*
try {
    $db = getDBConnection();
    echo "Database connection successful!";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
*/
?>
