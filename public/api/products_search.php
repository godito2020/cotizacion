<?php
/**
 * API: Búsqueda de productos con stock (COBOL)
 * Una sola query optimizada con paginación en PHP
 */
error_reporting(E_ERROR | E_PARSE);
ob_start();
require_once __DIR__ . '/../../includes/init.php';
ob_clean();
header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$startTime = microtime(true);

$search    = trim($_GET['search'] ?? '');
$warehouse = $_GET['warehouse'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 50;

// Mes actual
$meses = [
    1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
];
$mesActual = $meses[(int)date('n')];

try {
    $dbCobol = getCobolConnection();

    // Construir WHERE
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $words = array_values(array_filter(preg_split('/\s+/', $search), fn($w) => strlen($w) >= 2));
        foreach ($words as $i => $word) {
            $whereConditions[] = "(LOWER(p.codigo) LIKE LOWER(:wc{$i}) OR LOWER(p.descripcion) LIKE LOWER(:wd{$i}))";
            $params[":wc{$i}"] = '%' . $word . '%';
            $params[":wd{$i}"] = '%' . $word . '%';
        }
    }
    if (!empty($warehouse)) {
        $whereConditions[] = "s.almacen = :warehouse";
        $params[':warehouse'] = $warehouse;
    }
    $whereConditions[] = "s.{$mesActual} > 0";
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    // UNA sola query — traer todo y paginar en PHP (el COBOL no soporta bien LIMIT/OFFSET con DISTINCT + JOIN)
    $sql = "SELECT p.codigo, p.descripcion, p.precio, s.almacen AS numero_almacen, s.{$mesActual} AS stock_actual
            FROM vista_productos p
            INNER JOIN vista_almacenes_anual s ON p.codigo = s.codigo
            {$whereClause}
            ORDER BY p.descripcion ASC, s.almacen ASC";

    $stmt = $dbCobol->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por producto
    $groupedProducts = [];
    $warehousesFound = [];

    foreach ($results as $row) {
        $codigo     = $row['codigo'];
        $numAlmacen = $row['numero_almacen'];
        $stock      = (float)$row['stock_actual'];

        if (!isset($warehousesFound[$numAlmacen])) {
            $warehousesFound[$numAlmacen] = $numAlmacen;
        }

        if (!isset($groupedProducts[$codigo])) {
            $groupedProducts[$codigo] = [
                'codigo'      => $codigo,
                'descripcion' => $row['descripcion'],
                'precio'      => (float)$row['precio'],
                'warehouses'  => [],
                'total_stock' => 0,
            ];
        }
        $groupedProducts[$codigo]['warehouses'][$numAlmacen] = $stock;
        $groupedProducts[$codigo]['total_stock'] += $stock;
    }

    // Paginación en PHP
    $totalProducts = count($groupedProducts);
    $totalPages    = max(1, (int)ceil($totalProducts / $perPage));
    $page          = min($page, $totalPages);
    $pageProducts  = array_values(array_slice($groupedProducts, ($page - 1) * $perPage, $perPage));

    // Obtener nombres de almacenes e imágenes/fichas solo para la página actual
    $stockRepo = new Stock();
    $allWarehouses = $stockRepo->getWarehouses();
    $warehouseNames = [];
    foreach ($allWarehouses as $w) {
        $warehouseNames[$w['numero_almacen']] = $w['nombre'];
    }

    // Almacenes visibles en la página actual
    $visibleWarehouses = [];
    foreach ($pageProducts as $p) {
        foreach ($p['warehouses'] as $wNum => $stock) {
            if (!isset($visibleWarehouses[$wNum])) {
                $visibleWarehouses[$wNum] = [
                    'numero_almacen' => $wNum,
                    'nombre' => $warehouseNames[$wNum] ?? 'Almacén ' . $wNum
                ];
            }
        }
    }
    usort($visibleWarehouses, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

    // Imágenes y fichas (DB local, rápido)
    $productImages = [];
    $productFichas = [];

    if (!empty($pageProducts)) {
        $dbLocal = getDBConnection();
        $codes = array_column($pageProducts, 'codigo');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        $stmtImg = $dbLocal->prepare(
            "SELECT codigo_producto, imagen_url FROM imagenes
             WHERE codigo_producto IN ({$placeholders}) AND imagen_principal = 1"
        );
        $stmtImg->execute($codes);
        foreach ($stmtImg->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $productImages[$r['codigo_producto']] = $r['imagen_url'];
        }

        $stmtFicha = $dbLocal->prepare(
            "SELECT codigo_producto, COUNT(*) AS cnt FROM fichas_tecnicas
             WHERE codigo_producto IN ({$placeholders})
             GROUP BY codigo_producto"
        );
        $stmtFicha->execute($codes);
        foreach ($stmtFicha->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $productFichas[$r['codigo_producto']] = (int)$r['cnt'];
        }
    }

    // Agregar imágenes y fichas a los productos
    foreach ($pageProducts as &$p) {
        $p['imagen'] = $productImages[$p['codigo']] ?? null;
        $p['fichas'] = $productFichas[$p['codigo']] ?? 0;
    }
    unset($p);

    $elapsed = round(microtime(true) - $startTime, 2);

    echo json_encode([
        'success'    => true,
        'products'   => $pageProducts,
        'warehouses' => $visibleWarehouses,
        'total'      => $totalProducts,
        'page'       => $page,
        'perPage'    => $perPage,
        'totalPages' => $totalPages,
        'mes'        => ucfirst($mesActual),
        'time'       => $elapsed
    ]);

} catch (Exception $e) {
    error_log("products_search API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar productos']);
}
