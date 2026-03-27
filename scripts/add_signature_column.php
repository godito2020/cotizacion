<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = getDBConnection();
    $db->exec("ALTER TABLE users ADD COLUMN signature_url VARCHAR(255) NULL");
    echo "Columna signature_url agregada exitosamente a la tabla users.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>