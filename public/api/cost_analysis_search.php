<?php
/**
 * API: Búsqueda de productos con datos de costo para Análisis de Costos
 * Retorna: código, descripción, precio, costos (soles/dólares), fecha ingreso, imagen, fichas
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

// Verificar acceso al módulo
if (!Permissions::canAccessCostAnalysis($auth)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tiene acceso al módulo de análisis de costos']);
    exit;
}

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

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

    // Solo productos con stock
    $whereConditions[] = "p.saldo > 0";

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    // Query con costos y fecha de ingreso
    $sql = "SELECT p.codigo, p.descripcion, p.marca, p.saldo, p.precio, p.premium,
                   p.ultcosto, p.fecultcos, p.unidad, p.costo_soles, p.costo_dolares
            FROM vista_productos p
            {$whereClause}
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

    // Get exchange rate
    $companyId = $auth->getCompanyId();
    $companySettings = new CompanySettings();
    $exchangeRate = (float)($companySettings->getSetting($companyId, 'exchange_rate_usd_pen') ?? 3.80);

    echo json_encode([
        'success'      => true,
        'products'     => $pageProducts,
        'total'        => $total,
        'page'         => $page,
        'perPage'      => $perPage,
        'totalPages'   => $totalPages,
        'exchangeRate' => $exchangeRate
    ]);

} catch (Exception $e) {
    error_log("cost_analysis_search API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar productos']);
}
