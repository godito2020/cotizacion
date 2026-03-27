<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = getDBConnection();
    $db->exec("ALTER TABLE users ADD COLUMN can_view_all_quotations TINYINT(1) DEFAULT 0");
    echo "Columna can_view_all_quotations agregada exitosamente a la tabla users.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>