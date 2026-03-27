<?php
/**
 * API: Buscar productos con stock
 * GET /inventario/api/search_products.php?query=xxx&warehouse=1
 */

error_reporting(E_ERROR | E_PARSE);
ob_start();

require_once __DIR__ . '/../../../includes/init.php';

header('Content-Type: application/json; charset=utf-8');
ob_clean();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar permiso de inventario
if (!Permissions::canAccessInventoryPanel($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos de inventario']);
    exit;
}

try {
    $query = trim($_GET['query'] ?? '');
    $warehouseNumber = (int)($_GET['warehouse'] ?? 0);
    $withStock = (int)($_GET['with_stock'] ?? 1); // Por defecto, solo con stock
    $debug = isset($_GET['debug']);

    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Ingrese al menos 2 caracteres'
        ]);
        exit;
    }

    if ($warehouseNumber <= 0) {
        echo json_encode(['success' => false, 'message' => 'Almacén no válido']);
        exit;
    }

    // Verificar conexión COBOL
    try {
        $cobolDb = getCobolConnection();
        if ($debug) {
            // Verificar si la tabla existe
            $tables = $cobolDb->query("SHOW TABLES LIKE 'vista_almacenes_anual'")->fetchAll();
            if (empty($tables)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tabla vista_almacenes_anual no existe en BD COBOL',
                    'debug' => true
                ]);
                exit;
            }
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error conectando a BD COBOL: ' . $e->getMessage()
        ]);
        exit;
    }

    // Búsqueda directa (bypass de clase para debug)
    if ($debug) {
        $mes = 'febrero'; // mes actual
        $searchTerm = '%' . $query . '%';
        $directSql = "SELECT codigo, descripcion, almacen, {$mes} as stock_actual
                      FROM vista_almacenes_anual
                      WHERE almacen = {$warehouseNumber}
                      AND (codigo LIKE '{$searchTerm}' OR descripcion LIKE '{$searchTerm}')
                      LIMIT 10";
        $directResult = $cobolDb->query($directSql)->fetchAll(PDO::FETCH_ASSOC);

        // Normalizar claves a minúsculas manualmente
        $normalized = [];
        foreach ($directResult as $row) {
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $normalizedRow[strtolower($key)] = $value;
            }
            $normalized[] = $normalizedRow;
        }

        echo json_encode([
            'success' => true,
            'data' => $normalized,
            'debug' => [
                'sql' => $directSql,
                'raw_keys' => !empty($directResult) ? array_keys($directResult[0]) : [],
                'total_found' => count($directResult)
            ]
        ]);
        exit;
    }

    // Búsqueda con múltiples palabras
    $mesActual = strtolower(date('F', strtotime('first day of this month')));
    $mesesMap = ['january'=>'enero','february'=>'febrero','march'=>'marzo','april'=>'abril',
              'may'=>'mayo','june'=>'junio','july'=>'julio','august'=>'agosto',
              'september'=>'septiembre','october'=>'octubre','november'=>'noviembre','december'=>'diciembre'];
    $mes = $mesesMap[$mesActual] ?? 'febrero';

    // Dividir query en palabras (mínimo 2 caracteres)
    $words = array_values(array_filter(preg_split('/\s+/', trim($query)), function($word) {
        return strlen($word) >= 2;
    }));

    if (empty($words)) {
        echo json_encode(['success' => true, 'data' => [], 'message' => 'Búsqueda muy corta']);
        exit;
    }

    // Construir condiciones: cada palabra debe estar en codigo O descripcion
    $conditions = [];
    $params = [$warehouseNumber];

    foreach ($words as $word) {
        $conditions[] = "(codigo LIKE ? OR descripcion LIKE ?)";
        $params[] = '%' . $word . '%';
        $params[] = '%' . $word . '%';
    }

    $searchCondition = implode(' AND ', $conditions);
    $stockCond = $withStock ? "AND {$mes} > 0" : "";

    $directSql = "SELECT codigo, descripcion, almacen, {$mes} as stock_actual
                  FROM vista_almacenes_anual
                  WHERE almacen = ?
                  AND {$searchCondition}
                  {$stockCond}
                  LIMIT 50";
    $directStmt = $cobolDb->prepare($directSql);
    $directStmt->execute($params);
    $directResults = $directStmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalizar claves
    $products = [];
    foreach ($directResults as $row) {
        $normalizedRow = [];
        foreach ($row as $key => $value) {
            $normalizedRow[strtolower($key)] = $value;
        }
        $products[] = $normalizedRow;
    }

    // Debug: si no hay resultados, verificar si hay datos en general
    if (empty($products)) {
        $mesActual = strtolower(date('F', strtotime('first day of this month')));
        $meses = ['january'=>'enero','february'=>'febrero','march'=>'marzo','april'=>'abril',
                  'may'=>'mayo','june'=>'junio','july'=>'julio','august'=>'agosto',
                  'september'=>'septiembre','october'=>'octubre','november'=>'noviembre','december'=>'diciembre'];
        $mes = $meses[$mesActual] ?? 'febrero';

        // Test: buscar directamente con la misma lógica
        $searchTerm = '%' . $query . '%';
        $stockCond = $withStock ? "AND {$mes} > 0" : "";
        $testSql = "SELECT codigo, descripcion, {$mes} as stock
                    FROM vista_almacenes_anual
                    WHERE almacen = {$warehouseNumber}
                    AND (codigo LIKE '{$searchTerm}' OR descripcion LIKE '{$searchTerm}')
                    {$stockCond}
                    LIMIT 5";
        $testResults = $cobolDb->query($testSql)->fetchAll(PDO::FETCH_ASSOC);

        $countQuery = $cobolDb->query("SELECT COUNT(*) as total FROM vista_almacenes_anual WHERE almacen = {$warehouseNumber}");
        $countResult = $countQuery->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [],
            'debug' => [
                'warehouse' => $warehouseNumber,
                'total_in_warehouse' => $countResult['total'] ?? 0,
                'query' => $query,
                'with_stock' => $withStock,
                'mes_columna' => $mes,
                'test_sql' => $testSql,
                'test_results' => $testResults
            ]
        ]);
        exit;
    }

    // Verificar si hay sesión activa y obtener último registro del usuario
    $companyId = $auth->getCompanyId();
    $userId = $auth->getUserId();
    $session = new InventorySession();
    $activeSession = $session->getActiveSession($companyId);

    if ($activeSession) {
        $entry = new InventoryEntry();
        foreach ($products as &$product) {
            // Obtener resumen con suma total de todas las entradas
            $summary = $entry->getUserProductSummary($activeSession['id'], $userId, $product['codigo']);
            $product['user_entry_summary'] = $summary ? [
                'total_counted' => (float)$summary['total_counted'],
                'entry_count' => (int)$summary['entry_count'],
                'last_entry_at' => $summary['last_entry_at']
            ] : null;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $products,
        'total' => count($products)
    ]);

} catch (Exception $e) {
    error_log("API search_products Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
