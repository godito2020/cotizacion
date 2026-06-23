<?php
/**
 * API: Búsqueda de productos con datos de costo para Análisis de Costos
 * Retorna: código, descripción, precio, costos (soles/dólares), fecha ingreso, imagen, fichas
 *
 * Filtros aplicados:
 * - Solo productos activos (FLAG_MST NOT IN '9', 'X')
 * - Solo productos CON costo (PRECOM_MST > 0)
 * - Productos con o sin stock (se calcula el stock total para mostrar)
 */
error_reporting(E_ERROR | E_PARSE);
ob_start();
require_once __DIR__ . '/../../includes/init.php';
ob_clean();
// init.php (via config.php) reactiva display_errors/E_ALL; volver a suprimir
// para que ningun warning se imprima dentro del JSON y lo corrompa.
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');
header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar acceso al módulo
if (!Permissions::canAccessCostAnalysis($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tiene acceso al módulo de análisis de costos']);
    exit;
}

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

// Mes actual para calcular stock (misma lógica que products/index.php)
$meses = [
    1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',
    7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'
];
$mesActual = $meses[(int)date('n')];

try {
    $dbCobol = getCobolConnection();
    $dbLocal = getDBConnection();

    // Build WHERE conditions
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

    // NO filtrar por costo - mostrar TODOS los productos (incluso sin costo)
    // Esto permite ver qué productos necesitan costo asignado

    if (!empty($whereConditions)) {
        $whereClause = ' AND ' . implode(' AND ', $whereConditions);
    } else {
        $whereClause = '';
    }

    // Get exchange rate first (needed for display)
    $companyId = $auth->getCompanyId();
    $companySettings = new CompanySettings();
    $exchangeRate = (float)($companySettings->getSetting($companyId, 'exchange_rate_usd_pen') ?? 3.80);

    // Query usando vista_productos que ya tiene COSTO_SOLES y COSTO_DOLARES calculados
    // IMPORTANTE: PDO::CASE_LOWER convierte nombres a minúsculas, por eso usamos alias en minúsculas
    // La vista vista_productos ya tiene las columnas COSTO_SOLES y COSTO_DOLARES con valores correctos
    // STOCK TOTAL: Suma de la columna del mes actual en vista_almacenes_anual
    // (misma lógica que la columna "Total" de Productos y Stock — products_search.php)
    $sql = "SELECT p.codigo AS codigo,
                   p.descripcion AS descripcion,
                   p.marca AS marca,
                   p.precio AS precio,
                   p.premium AS premium,
                   p.ultcosto AS ultcosto,
                   p.fecultcos AS fecultcos,
                   p.unidad AS unidad,
                   p.costo_soles AS costo_soles,
                   p.costo_dolares AS costo_dolares,
                   COALESCE((SELECT SUM(s.{$mesActual})
                            FROM vista_almacenes_anual s
                            WHERE s.codigo = p.codigo), 0) AS saldo
            FROM vista_productos p
            WHERE 1=1" . $whereClause . "
            ORDER BY p.descripcion ASC";

    $stmt = $dbCobol->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pagination
    $total = count($allResults);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $pageProducts = array_slice($allResults, ($page - 1) * $perPage, $perPage);

    // Get images and fichas for current page
    $productImages = [];
    $productFichas = [];

    if (!empty($pageProducts)) {
        $codes = array_column($pageProducts, 'codigo');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        // Images
        $stmtImg = $dbLocal->prepare(
            "SELECT codigo_producto, imagen_url FROM imagenes
             WHERE codigo_producto IN ({$placeholders}) AND imagen_principal = 1"
        );
        $stmtImg->execute($codes);
        foreach ($stmtImg->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $productImages[$r['codigo_producto']] = $r['imagen_url'];
        }

        // Fichas técnicas
        $stmtFicha = $dbLocal->prepare(
            "SELECT codigo_producto, ficha_url, nombre_archivo FROM fichas_tecnicas
             WHERE codigo_producto IN ({$placeholders})
             ORDER BY codigo_producto, created_at DESC"
        );
        $stmtFicha->execute($codes);
        $fichasRaw = $stmtFicha->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fichasRaw as $r) {
            if (!isset($productFichas[$r['codigo_producto']])) {
                $productFichas[$r['codigo_producto']] = [];
            }
            $productFichas[$r['codigo_producto']][] = [
                'url' => $r['ficha_url'],
                'nombre' => $r['nombre_archivo']
            ];
        }
    }

    // Attach images and fichas
    foreach ($pageProducts as &$p) {
        $p['imagen'] = $productImages[$p['codigo']] ?? null;
        $p['fichas'] = $productFichas[$p['codigo']] ?? [];
        $p['precio'] = (float)$p['precio'];
        $p['premium'] = (float)$p['premium'];
        $p['costo_soles'] = (float)$p['costo_soles'];
        $p['costo_dolares'] = (float)$p['costo_dolares'];
        $p['saldo'] = (float)$p['saldo'];
    }
    unset($p);

    echo json_encode([
        'success'      => true,
        'products'     => $pageProducts,
        'total'        => $total,
        'page'         => $page,
        'perPage'      => $perPage,
        'totalPages'   => $totalPages,
        'exchangeRate' => $exchangeRate
    ]);

} catch (Throwable $e) {
    error_log("cost_analysis_search API error: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar productos: ' . $e->getMessage()
    ]);
}
