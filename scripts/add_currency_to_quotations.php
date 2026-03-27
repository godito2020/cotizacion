<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = getDBConnection();

    // Check if currency column exists
    $stmt = $db->query("SHOW COLUMNS FROM quotations LIKE 'currency'");
    $exists = $stmt->fetch();

    if (!$exists) {
        echo "Agregando columna currency a la tabla quotations...\n";
        $db->exec("ALTER TABLE quotations ADD COLUMN currency VARCHAR(3) DEFAULT 'PEN' AFTER terms_and_conditions");
        echo "Columna currency agregada exitosamente.\n";
    } else {
        echo "La columna currency ya existe.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>