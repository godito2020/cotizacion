<?php
// Definir HTTP_HOST para evitar warning
$_SERVER['HTTP_HOST'] = 'localhost';

require_once __DIR__ . '/../includes/init.php';

$db = getDBConnection();

echo "=== QUOTATIONS TABLE ===\n";
$result = $db->query('DESCRIBE quotations');
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - Key:' . $row['Key'] . ' - Extra:' . $row['Extra'] . "\n";
}

echo "\n=== QUOTATION_ITEMS TABLE ===\n";
$result = $db->query('DESCRIBE quotation_items');
while($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - Key:' . $row['Key'] . ' - Extra:' . $row['Extra'] . "\n";
}
