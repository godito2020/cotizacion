<?php
/**
 * API de búsqueda de productos
 * Lee desde vista_productos (COBOL) y vista_almacenes_anual (stock por almacén)
 * Búsqueda multi-palabra: "11r22.5 giti" busca productos con AMBAS palabras
 */

// Suprimir warnings que rompen el JSON
error_reporting(E_ERROR | E_PARSE);
ob_start();

require_once __DIR__ . '/../../includes/init.php';

// Limpiar cualquier output previo
ob_clean();
header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$search = trim($_GET['search'] ?? '');

// Requiere término de búsqueda de al menos 2 caracteres
if (strlen($search) < 2) {
    echo json_encode(['success' => true, 'products' => [], 'message' => 'Ingrese al menos 2 caracteres']);
    exit;
}

try {
    $dbCobol = getCobolConnection();
    $dbLocal = getDBConnection();

    // Obtener mes actual para el stock
    $meses = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo',
        4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
        10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
    ];
    $mesActual = $meses[(int)date('n')];

    // Obtener nombres de almacén desde BD local
    $warehouseNames = [];
    $stmtWh = $dbLocal->query("SELECT numero_almacen, nombre FROM desc_almacen WHERE activo = 1");
    while ($row = $stmtWh->fetch(PDO::FETCH_ASSOC)) {
        $warehouseNames[$row['numero_almacen']] = $row['nombre'];
    }

    // Construir condiciones de búsqueda multi-palabra
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        // Separar palabras para búsqueda múltiple
        $words = preg_split('/\s+/', trim($search));
        $words = array_values(array_filter($words, fn($w) => strlen($w) >= 2));

        $i = 0;
        foreach ($words as $word) {
            $paramCodigo = ":wc{$i}";
            $paramDesc = ":wd{$i}";
            $whereConditions[] = "(LOWER(codigo) LIKE LOWER({$paramCodigo}) OR LOWER(descripcion) LIKE LOWER({$paramDesc}))";
            $searchTerm = '%' . $word . '%';
            $params[$paramCodigo] = $searchTerm;
            $params[$paramDesc] = $searchTerm;
            $i++;
        }
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Buscar productos en vista_productos (usar alias explícitos)
    $sql = "SELECT
                codigo as prod_codigo,
                descripcion as prod_descripcion,
                saldo as prod_saldo,
                precio as prod_precio
            FROM vista_productos
            {$whereClause}
            ORDER BY descripcion ASC
            LIMIT 100";

    $stmt = $dbCobol->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapear a nombres estándar
    $products = [];
    foreach ($rawProducts as $row) {
        $products[] = [
            'codigo' => $row['prod_codigo'] ?? '',
            'descripcion' => $row['prod_descripcion'] ?? '',
            'saldo' => $row['prod_saldo'] ?? 0,
            'precio' => $row['prod_precio'] ?? 0
        ];
    }

    // Obtener códigos de productos encontrados
    $productCodes = array_column($products, 'codigo');
    $productCodes = array_filter($productCodes); // Eliminar vacíos

    // Obtener imágenes de BD local
    $images = [];
    if (!empty($productCodes)) {
        $placeholders = str_repeat('?,', count($productCodes) - 1) . '?';
        $sqlImages = "SELECT codigo_producto, imagen_url
                      FROM imagenes
                      WHERE codigo_producto IN ($placeholders)
                      AND imagen_principal = 1";
        $stmtImages = $dbLocal->prepare($sqlImages);
        $stmtImages->execute($productCodes);

        while ($row = $stmtImages->fetch(PDO::FETCH_ASSOC)) {
            $images[$row['codigo_producto']] = $row['imagen_url'];
        }
    }

    // Obtener stock por almacén desde COBOL (sin JOIN con BD local)
    $stockByProduct = [];
    if (!empty($productCodes)) {
        $placeholders = str_repeat('?,', count($productCodes) - 1) . '?';
        $sqlStock = "SELECT
                        codigo as cod,
                        almacen as num_almacen,
                        {$mesActual} as stock_qty
                     FROM vista_almacenes_anual
                     WHERE codigo IN ($placeholders)
                     AND {$mesActual} > 0";

        $stmtStock = $dbCobol->prepare($sqlStock);
        $stmtStock->execute($productCodes);

        while ($row = $stmtStock->fetch(PDO::FETCH_ASSOC)) {
            // Usar alias definidos en la consulta
            $codigo = $row['cod'] ?? null;
            $numAlmacen = $row['num_almacen'] ?? null;
            $stock = $row['stock_qty'] ?? 0;

            if ($codigo === null) continue;

            if (!isset($stockByProduct[$codigo])) {
                $stockByProduct[$codigo] = [];
            }
            // Usar nombre de almacén desde BD local
            $nombreAlmacen = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;
            $stockByProduct[$codigo][$nombreAlmacen] = (float)$stock;
        }
    }

    // Procesar productos - mapear campos de COBOL a formato esperado por el frontend
    // SOLO incluir productos que tengan stock > 0 en al menos un almacén
    $processedProducts = [];
    foreach ($products as $product) {
        $codigo = $product['codigo'];

        // Obtener stock de este producto
        $warehouses = $stockByProduct[$codigo] ?? [];
        $totalStock = array_sum($warehouses);

        // Solo incluir si tiene stock > 0
        if ($totalStock <= 0) {
            continue;
        }

        $processedProducts[] = [
            // Campos originales de COBOL
            'codigo' => $codigo,
            'descripcion' => $product['descripcion'],
            'saldo' => (float)$product['saldo'],
            'precio' => (float)$product['precio'],

            // Campos mapeados para compatibilidad con el frontend
            'id' => $codigo,  // Usar código como ID
            'code' => $codigo,
            'name' => $product['descripcion'],
            'description' => $product['descripcion'],
            'regular_price' => (float)$product['precio'],
            'price_currency' => 'USD',  // Precios en dólares

            // Imagen
            'image_url' => $images[$codigo] ?? null,
            'imagen_url' => $images[$codigo] ?? null,

            // Stock por almacén (solo almacenes con stock > 0)
            'warehouses' => $warehouses,
            'total_stock' => $totalStock
        ];
    }
    $products = $processedProducts;

    echo json_encode([
        'success' => true,
        'products' => $products,
        'month' => ucfirst($mesActual)
    ]);

} catch (Exception $e) {
    ob_clean(); // Limpiar cualquier output
    error_log("Error searching products: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la búsqueda: ' . $e->getMessage()
    ]);
}

ob_end_flush();
