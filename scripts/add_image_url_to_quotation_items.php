<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = getDBConnection();

    // Check if image_url column exists
    $stmt = $db->query("SHOW COLUMNS FROM quotation_items LIKE 'image_url'");
    $exists = $stmt->fetch();

    if (!$exists) {
        echo "Agregando columna image_url a la tabla quotation_items...\n";
        $db->exec("ALTER TABLE quotation_items ADD COLUMN image_url VARCHAR(500) NULL AFTER description");
        echo "Columna image_url agregada exitosamente.\n";
    } else {
        echo "La columna image_url ya existe.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>