<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: text/plain');

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

echo "\n=== QUOTATION_APPROVAL_TOKENS TABLE ===\n";
try {
    $result = $db->query('DESCRIBE quotation_approval_tokens');
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - Key:' . $row['Key'] . ' - Extra:' . $row['Extra'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: Table does not exist - " . $e->getMessage() . "\n";
}

echo "\n=== ACTIVITY_LOGS TABLE ===\n";
try {
    $result = $db->query('DESCRIBE activity_logs');
    while($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - Key:' . $row['Key'] . ' - Extra:' . $row['Extra'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: Table does not exist - " . $e->getMessage() . "\n";
}
