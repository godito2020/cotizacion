<?php
// cotizacion/public/admin/stock_import.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$productRepo = new Product();
$warehouseRepo = new Warehouse();
$stockRepo = new Stock();
$importHelper = new ImportHelper();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole('Company Admin')) {
    $_SESSION['error_message'] = "No está autorizado para importar stock.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$page_title = "Importar Stock desde CSV";
$import_results = null;

$expectedHeaders = ['SKU_Producto', 'Nombre_Almacen', 'Cantidad'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['stock_file'])) {
    if ($_FILES['stock_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['stock_file']['tmp_name'];
        $fileNameCmps = explode(".", $_FILES['stock_file']['name']);
        $fileExtension = strtolower(end($fileNameCmps));

        if ($fileExtension === 'csv') {
            $parsedData = $importHelper->parseCsv($fileTmpPath, $expectedHeaders);

            if (isset($parsedData['error'])) {
                $import_results['error_summary'] = "Error al procesar el archivo CSV: " . htmlspecialchars($parsedData['error']);
            } else {
                $rowsToImport = $parsedData['data'];
                $processedCount = 0; // Counts successful updates/inserts
                $skippedCount = 0;
                $rowErrors = [];
                $rowNumber = 1; // Header is row 1

                foreach ($rowsToImport as $row) {
                    $rowNumber++;
                    $sku_producto = trim($row['SKU_Producto'] ?? '');
                    $nombre_almacen = trim($row['Nombre_Almacen'] ?? '');
                    $cantidad_str = trim($row['Cantidad'] ?? '');

                    $current_row_errors = [];
                    if (empty($sku_producto)) $current_row_errors[] = "SKU_Producto es requerido.";
                    if (empty($nombre_almacen)) $current_row_errors[] = "Nombre_Almacen es requerido.";
                    if ($cantidad_str === '') { // Check if empty string explicitly
                        $current_row_errors[] = "Cantidad es requerida.";
                    } elseif (!ctype_digit($cantidad_str) || intval($cantidad_str) < 0) { // ctype_digit checks for non-negative integers
                        $current_row_errors[] = "Cantidad debe ser un número entero no negativo. Valor encontrado: '" . htmlspecialchars($cantidad_str) . "'.";
                    }

                    $cantidad = intval($cantidad_str);

                    if (!empty($current_row_errors)) {
                        $skippedCount++;
                        $rowErrors[] = "Fila " . $rowNumber . ": " . implode(" ", $current_row_errors);
                        continue; // Skip to next row
                    }

                    $product = $productRepo->findBySku($sku_producto, $company_id);
                    if (!$product) {
                        $skippedCount++;
                        $rowErrors[] = "Fila " . $rowNumber . ": Producto con SKU '" . htmlspecialchars($sku_producto) . "' no encontrado en su compañía.";
                        continue;
                    }

                    $warehouse = $warehouseRepo->findByName($nombre_almacen, $company_id);
                    if (!$warehouse) {
                        $skippedCount++;
                        $rowErrors[] = "Fila " . $rowNumber . ": Almacén con nombre '" . htmlspecialchars($nombre_almacen) . "' no encontrado en su compañía.";
                        continue;
                    }

                    // If all checks pass, update stock
                    if ($stockRepo->updateStock($product['id'], $warehouse['id'], $cantidad, $company_id)) {
                        $processedCount++;
                    } else {
                        $skippedCount++;
                        $rowErrors[] = "Fila " . $rowNumber . ": Error al actualizar stock para SKU '" . htmlspecialchars($sku_producto) . "' en almacén '" . htmlspecialchars($nombre_almacen) . "'. Verifique logs.";
                    }
                }
                $import_results['success_summary'] = $processedCount . " registros de stock importados/actualizados exitosamente.";
                if ($skippedCount > 0) {
                    $import_results['skipped_summary'] = $skippedCount . " filas omitidas o con error.";
                }
                $import_results['detailed_errors'] = $rowErrors;
            }
        } else {
            $import_results['error_summary'] = 'Error de carga: Solo se permiten archivos CSV.';
        }
    } else {
        $uploadErrors = [ /* Standard upload error messages */ ];
        // ... (same error handling as product_import.php)
        $errorCode = $_FILES['stock_file']['error'];
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede la directiva upload_max_filesize en php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede la directiva MAX_FILE_SIZE especificada en el formulario HTML.',
            UPLOAD_ERR_PARTIAL => 'El archivo fue solo parcialmente cargado.',
            UPLOAD_ERR_NO_FILE => 'Ningún archivo fue cargado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta una carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga del archivo.',
        ];
        $import_results['error_summary'] = 'Error de carga: ' . ($uploadErrors[$errorCode] ?? 'Error desconocido.');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; max-width: 800px; margin: 20px auto; }
        .admin-content { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; }
        .form-group input[type="file"] { padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; width:100%; }
        .form-actions button { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size:16px; background-color: #007bff; color: white; }
        .form-actions button:hover { background-color: #0056b3; }
        .instructions { background-color: #e9f7fd; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; font-size: 0.9em; }
        .instructions ul { margin: 5px 0 0 20px; padding:0; }
        .import-results { margin-top: 20px; }
        .import-results .message { padding: 10px; margin-bottom: 10px; border-radius: 4px; }
        .import-results .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .import-results .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .import-results .skipped { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .import-results ul.errors-list { list-style-type: none; padding-left: 0; font-size: 0.9em; max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding:10px;}
        .import-results ul.errors-list li { padding: 3px 0; border-bottom:1px dotted #eee; }
        .import-results ul.errors-list li:last-child { border-bottom:none; }
    </style>
</head>
<body>
    <header class="admin-header"><h1>Admin Panel</h1></header>
    <div class="user-info">
        Usuario: <?php echo htmlspecialchars($loggedInUser['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($loggedInUser['id']), 'role_name'))); ?>) |
        Compañía ID: <?php echo htmlspecialchars($company_id); ?> |
        <a href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a>
    </div>
    <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Inicio Admin</a>
        <?php if ($auth->hasRole('System Admin')): ?><a href="<?php echo BASE_URL; ?>/admin/companies.php">Empresas</a><?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'Salesperson', 'System Admin'])): ?>
             <a href="<?php echo BASE_URL; ?>/admin/customers.php">Clientes</a>
             <a href="<?php echo BASE_URL; ?>/admin/quotations.php">Cotizaciones</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'System Admin'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Productos</a>
            <a href="<?php echo BASE_URL; ?>/admin/product_import.php">Importar Productos</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Almacenes</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouse_import.php">Importar Almacenes</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Stock</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_import.php">Importar Stock</a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Dashboard Principal</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2><?php echo $page_title; ?></h2>

            <div class="instructions">
                <p><strong>Instrucciones:</strong></p>
                <ul>
                    <li>Seleccione un archivo CSV para importar stock.</li>
                    <li>El archivo CSV debe tener las siguientes columnas en este orden exacto: <strong>SKU_Producto, Nombre_Almacen, Cantidad</strong>.</li>
                    <li>La primera fila del archivo debe ser la cabecera con estos nombres de columna.</li>
                    <li>La cantidad debe ser un número entero no negativo.</li>
                    <li>Asegúrese de que los SKUs de los productos y los nombres de los almacenes ya existan en el sistema para su compañía.</li>
                    <!-- <li><a href="path/to/plantilla_stock.csv" download>Descargar plantilla CSV</a></li> -->
                </ul>
            </div>

            <form action="stock_import.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="stock_file">Archivo CSV de Stock:</label>
                    <input type="file" id="stock_file" name="stock_file" accept=".csv" required>
                </div>
                <div class="form-actions">
                    <button type="submit">Importar Stock</button>
                </div>
            </form>

            <?php if ($import_results): ?>
            <div class="import-results">
                <h3>Resultados de la Importación:</h3>
                <?php if (isset($import_results['error_summary'])): ?>
                    <p class="message error"><?php echo $import_results['error_summary']; ?></p>
                <?php endif; ?>
                <?php if (isset($import_results['success_summary'])): ?>
                    <p class="message success"><?php echo $import_results['success_summary']; ?></p>
                <?php endif; ?>
                <?php if (isset($import_results['skipped_summary'])): ?>
                    <p class="message skipped"><?php echo $import_results['skipped_summary']; ?></p>
                <?php endif; ?>
                <?php if (!empty($import_results['detailed_errors'])): ?>
                    <p><strong>Errores detallados:</strong></p>
                    <ul class="errors-list">
                        <?php foreach ($import_results['detailed_errors'] as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
