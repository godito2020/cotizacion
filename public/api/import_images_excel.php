<?php
/**
 * API para importar imágenes de productos desde archivo Excel/CSV
 * Versión optimizada para evitar timeout
 */

header('Content-Type: application/json; charset=utf-8');

// Flush inmediato para que IIS sepa que estamos vivos
if (function_exists('fastcgi_finish_request')) {
    // No usamos esto aquí porque necesitamos la respuesta
}

try {
    ini_set('display_errors', '0');
    ini_set('max_execution_time', '600');
    ini_set('memory_limit', '512M');
    set_time_limit(600);

    require_once __DIR__ . '/../../includes/init.php';

    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        die(json_encode(['success' => false, 'message' => 'No autorizado']));
    }
    if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
        die(json_encode(['success' => false, 'message' => 'Sin permisos']));
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        die(json_encode(['success' => false, 'message' => 'Método no permitido']));
    }

    $file = $_FILES['excel_file'] ?? $_FILES['archivo'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'No se recibió archivo';
        if ($file && $file['error'] !== UPLOAD_ERR_OK) {
            $errs = [1=>'Límite PHP',2=>'Límite form',3=>'Incompleto',4=>'Sin archivo',6=>'Sin tmp',7=>'Escritura'];
            $msg = $errs[$file['error']] ?? 'Error '.$file['error'];
        }
        die(json_encode(['success' => false, 'message' => $msg]));
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        die(json_encode(['success' => false, 'message' => 'Use CSV o Excel']));
    }

    // Leer archivo
    $rows = [];

    if ($ext === 'csv') {
        $content = file_get_contents($file['tmp_name']);
        $lines = preg_split('/\r\n|\r|\n/', $content);

        if (empty($lines)) {
            die(json_encode(['success' => false, 'message' => 'Archivo vacío']));
        }

        $firstLine = $lines[0];
        $delimiter = (strpos($firstLine, '|') !== false) ? '|' :
                     ((strpos($firstLine, ';') !== false) ? ';' :
                     ((strpos($firstLine, "\t") !== false) ? "\t" : ','));

        $headerLine = str_replace("\xEF\xBB\xBF", '', $lines[0]);
        $headers = array_map(function($h) { return strtolower(trim(trim($h), '"')); }, explode($delimiter, $headerLine));

        $codigoIdx = -1;
        $urlIdx = -1;
        foreach ($headers as $i => $h) {
            if (in_array($h, ['codigo', 'código', 'code'])) $codigoIdx = $i;
            if (in_array($h, ['imagen_url', 'url', 'image_url', 'imagen'])) $urlIdx = $i;
        }

        if ($codigoIdx === -1 || $urlIdx === -1) {
            die(json_encode(['success' => false, 'message' => 'Columnas requeridas: codigo, imagen_url']));
        }

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;
            $cols = explode($delimiter, $line);
            $codigo = trim($cols[$codigoIdx] ?? '', '" ');
            $url = trim($cols[$urlIdx] ?? '', '" ');
            if ($codigo && $url && filter_var($url, FILTER_VALIDATE_URL)) {
                $rows[] = ['codigo' => $codigo, 'imagen_url' => $url];
            }
        }
    } else {
        $xlsxPath = __DIR__ . '/../../vendor/SimpleXLSX.php';
        if (!file_exists($xlsxPath)) {
            die(json_encode(['success' => false, 'message' => 'Guarde como CSV']));
        }
        require_once $xlsxPath;

        $xlsx = \Shuchkin\SimpleXLSX::parse($file['tmp_name']);
        if (!$xlsx) {
            die(json_encode(['success' => false, 'message' => 'Error Excel']));
        }

        $allRows = $xlsx->rows();
        if (empty($allRows)) {
            die(json_encode(['success' => false, 'message' => 'Excel vacío']));
        }

        $headers = array_map(function($h) { return strtolower(trim((string)$h)); }, array_shift($allRows));

        $codigoIdx = -1;
        $urlIdx = -1;
        foreach ($headers as $i => $h) {
            if (in_array($h, ['codigo', 'código', 'code'])) $codigoIdx = $i;
            if (in_array($h, ['imagen_url', 'url', 'image_url', 'imagen'])) $urlIdx = $i;
        }

        if ($codigoIdx === -1 || $urlIdx === -1) {
            die(json_encode(['success' => false, 'message' => 'Columnas requeridas: codigo, imagen_url']));
        }

        foreach ($allRows as $row) {
            $codigo = trim((string)($row[$codigoIdx] ?? ''));
            $url = trim((string)($row[$urlIdx] ?? ''));
            if ($codigo && $url && filter_var($url, FILTER_VALIDATE_URL)) {
                $rows[] = ['codigo' => $codigo, 'imagen_url' => $url];
            }
        }
    }

    if (empty($rows)) {
        die(json_encode(['success' => false, 'message' => 'Sin datos válidos']));
    }

    // Obtener conexión directa para optimizar
    $db = getDBConnection();

    // Pre-cargar imágenes existentes en un solo query
    $codigos = array_unique(array_column($rows, 'codigo'));
    $placeholders = implode(',', array_fill(0, count($codigos), '?'));

    $existingImages = [];
    $stmt = $db->prepare("SELECT codigo_producto, imagen_url FROM imagenes WHERE codigo_producto IN ($placeholders)");
    $stmt->execute($codigos);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['codigo_producto'] . '|' . $row['imagen_url'];
        $existingImages[$key] = true;
    }

    // Verificar qué productos tienen imágenes (para saber si es principal)
    $productsWithImages = [];
    $stmt = $db->prepare("SELECT DISTINCT codigo_producto FROM imagenes WHERE codigo_producto IN ($placeholders)");
    $stmt->execute($codigos);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $productsWithImages[$row['codigo_producto']] = true;
    }

    // Procesar importación con insert directo
    $imported = 0;
    $skipped = 0;
    $errors = [];

    $insertStmt = $db->prepare("INSERT INTO imagenes (codigo_producto, imagen_url, imagen_principal) VALUES (?, ?, ?)");

    foreach ($rows as $i => $row) {
        $codigo = $row['codigo'];
        $url = $row['imagen_url'];
        $key = $codigo . '|' . $url;

        // Verificar si ya existe
        if (isset($existingImages[$key])) {
            $skipped++;
            continue;
        }

        // Determinar si es principal
        $isPrincipal = !isset($productsWithImages[$codigo]) ? 1 : 0;

        try {
            $insertStmt->execute([$codigo, $url, $isPrincipal]);
            $imported++;
            // Marcar que ahora tiene imagen
            $productsWithImages[$codigo] = true;
            $existingImages[$key] = true;
        } catch (PDOException $e) {
            // Puede fallar si el producto no existe (FK constraint)
            $errors[] = "Fila " . ($i + 2) . ": $codigo - " . $e->getMessage();
        }
    }

    $total = count($rows);
    $msg = "Completado: $imported nuevas";
    if ($skipped > 0) $msg .= ", $skipped ya existían";

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'processed' => $total,
        'success_count' => $imported + $skipped,
        'error_count' => count($errors),
        'errors' => array_slice($errors, 0, 10)
    ]);

} catch (Throwable $e) {
    error_log("Import error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
