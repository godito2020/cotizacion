<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = getDBConnection();
    $db->exec("ALTER TABLE customers ADD COLUMN user_id INT NULL");
    $db->exec("ALTER TABLE customers ADD CONSTRAINT fk_customers_user_id FOREIGN KEY (user_id) REFERENCES users(id)");
    echo "Columna user_id agregada exitosamente a la tabla customers.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>