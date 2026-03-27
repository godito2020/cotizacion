<?php
/**
 * Script de instalación para integración con BD COBOL
 *
 * Este script:
 * 1. Crea la tabla desc_almacen para mapear números de almacén a nombres
 * 2. Crea la tabla imagenes para almacenar imágenes de productos
 * 3. Verifica la conexión con las vistas de COBOL
 *
 * Ejecutar una sola vez: php install_cobol_integration.php
 * O acceder via web: http://localhost/coti/install_cobol_integration.php
 */

require_once __DIR__ . '/includes/init.php';

// Solo permitir ejecución por administradores o CLI
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI) {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        die('Debe iniciar sesión para ejecutar este script.');
    }
}

echo $isCLI ? "" : "<pre>";
echo "===========================================\n";
echo "INSTALACIÓN DE INTEGRACIÓN CON BD COBOL\n";
echo "===========================================\n\n";

try {
    $db = getDBConnection();

    // =====================================================
    // 1. CREAR TABLA desc_almacen
    // =====================================================
    echo "1. Creando tabla desc_almacen...\n";

    $sql_desc_almacen = "
    CREATE TABLE IF NOT EXISTS desc_almacen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_almacen INT NOT NULL UNIQUE COMMENT 'Número del almacén en COBOL',
        nombre VARCHAR(100) NOT NULL COMMENT 'Nombre descriptivo del almacén',
        direccion VARCHAR(255) DEFAULT NULL,
        telefono VARCHAR(20) DEFAULT NULL,
        activo TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_numero_almacen (numero_almacen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Mapeo de números de almacén COBOL a nombres descriptivos'
    ";

    $db->exec($sql_desc_almacen);
    echo "   ✓ Tabla desc_almacen creada correctamente\n";

    // Insertar almacenes de ejemplo (ajustar según tus almacenes reales)
    echo "   Insertando almacenes de ejemplo...\n";

    $almacenes_ejemplo = [
        [1, 'Metro', 'Av. Principal 123', '01-234567'],
        [2, 'Productores', 'Jr. Industrial 456', '01-345678'],
        [3, 'Central', 'Av. Central 789', '01-456789'],
        [4, 'Almacén Norte', 'Av. Norte 321', '01-567890'],
        [5, 'Almacén Sur', 'Av. Sur 654', '01-678901'],
    ];

    $stmt_insert_almacen = $db->prepare("
        INSERT IGNORE INTO desc_almacen (numero_almacen, nombre, direccion, telefono)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($almacenes_ejemplo as $almacen) {
        $stmt_insert_almacen->execute($almacen);
    }
    echo "   ✓ Almacenes de ejemplo insertados\n\n";

    // =====================================================
    // 2. CREAR TABLA imagenes
    // =====================================================
    echo "2. Creando tabla imagenes...\n";

    $sql_imagenes = "
    CREATE TABLE IF NOT EXISTS imagenes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo_producto VARCHAR(50) NOT NULL COMMENT 'Código del producto (de vista_productos)',
        imagen_url VARCHAR(500) NOT NULL COMMENT 'Ruta de la imagen',
        imagen_principal TINYINT(1) DEFAULT 0 COMMENT '1 si es la imagen principal',
        orden INT DEFAULT 0 COMMENT 'Orden de visualización',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_codigo_producto (codigo_producto),
        INDEX idx_imagen_principal (codigo_producto, imagen_principal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Imágenes de productos vinculadas por código'
    ";

    $db->exec($sql_imagenes);
    echo "   ✓ Tabla imagenes creada correctamente\n\n";

    // =====================================================
    // 3. VERIFICAR CONEXIÓN CON VISTAS COBOL
    // =====================================================
    echo "3. Verificando conexión con BD COBOL...\n";

    // Obtener conexión a COBOL
    $dbCobol = getCobolConnection();

    // Verificar vista_productos
    echo "   Verificando vista_productos...\n";
    try {
        $stmt = $dbCobol->query("SELECT COUNT(*) as total FROM vista_productos LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✓ vista_productos accesible - Total registros: " . ($result['total'] ?? 'N/A') . "\n";

        // Mostrar columnas de la vista
        $stmt = $dbCobol->query("SELECT * FROM vista_productos LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sample) {
            echo "   Columnas encontradas: " . implode(', ', array_keys($sample)) . "\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ Error accediendo a vista_productos: " . $e->getMessage() . "\n";
    }

    // Verificar vista_almacenes_anual
    echo "\n   Verificando vista_almacenes_anual...\n";
    try {
        $stmt = $dbCobol->query("SELECT COUNT(*) as total FROM vista_almacenes_anual LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✓ vista_almacenes_anual accesible - Total registros: " . ($result['total'] ?? 'N/A') . "\n";

        // Mostrar columnas de la vista
        $stmt = $dbCobol->query("SELECT * FROM vista_almacenes_anual LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sample) {
            echo "   Columnas encontradas: " . implode(', ', array_keys($sample)) . "\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ Error accediendo a vista_almacenes_anual: " . $e->getMessage() . "\n";
    }

    echo "\n===========================================\n";
    echo "INSTALACIÓN COMPLETADA\n";
    echo "===========================================\n";
    echo "\nPróximos pasos:\n";
    echo "1. Actualiza la tabla desc_almacen con tus almacenes reales\n";
    echo "2. Las imágenes se cargarán desde Admin > Productos\n";
    echo "3. Los productos ahora se leen de vista_productos (COBOL)\n";
    echo "4. El stock se lee de vista_almacenes_anual (COBOL)\n";

} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
}

echo $isCLI ? "" : "</pre>";
?>
