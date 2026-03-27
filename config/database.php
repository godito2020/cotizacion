<?php
// cotizacion/config/database.php

require_once __DIR__ . '/config.php'; // Ensure config constants are loaded

/**
 * Clase para conexión a BD del sistema de cotizaciones
 */
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
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please check logs or contact support. Error: " . $e->getMessage());
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

    private function __clone() {}
    public function __wakeup() {}
}

/**
 * Clase para conexión a BD COBOL (productos y stock)
 */
class CobolDatabase {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . COBOL_DB_HOST . ";dbname=" . COBOL_DB_NAME . ";charset=" . COBOL_DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_CASE               => PDO::CASE_LOWER, // Convertir columnas a minúsculas
        ];

        try {
            $this->pdo = new PDO($dsn, COBOL_DB_USER, COBOL_DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("COBOL Database Connection Error: " . $e->getMessage());
            die("COBOL Database connection failed. Error: " . $e->getMessage());
        }
    }

    public static function getInstance(): CobolDatabase {
        if (self::$instance === null) {
            self::$instance = new CobolDatabase();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    private function __clone() {}
    public function __wakeup() {}
}

/**
 * Obtener conexión a BD del sistema de cotizaciones
 */
function getDBConnection(): PDO {
    return Database::getInstance()->getConnection();
}

/**
 * Obtener conexión a BD COBOL (productos y stock)
 */
function getCobolConnection(): PDO {
    return CobolDatabase::getInstance()->getConnection();
}
?>
