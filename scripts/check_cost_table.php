<?php
require_once __DIR__ . '/../includes/init.php';

try {
    $db = getDBConnection();

    // Check if cost_analysis_access table exists
    $stmt = $db->query("SHOW TABLES LIKE 'cost_analysis_access'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "✓ Tabla cost_analysis_access existe\n\n";

        // Show records
        $stmt = $db->query("SELECT * FROM cost_analysis_access");
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Registros de acceso (" . count($records) . "):\n";
        foreach ($records as $r) {
            echo "  - User ID: {$r['id']}, Granted by: {$r['granted_by']}, Created: {$r['created_at']}\n";
        }
    } else {
        echo "✗ Tabla cost_analysis_access NO existe\n";
        echo "Ejecute: scripts/install_cost_analysis.sql\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
