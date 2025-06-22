<?php
// app/views/admin/producto_importar.php

if (!is_admin()) { // Solo administradores pueden importar masivamente
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección requiere privilegios de administrador.</div>';
    return;
}

$feedback_message = '';
$error_message = '';
$summary_message = ''; // Para resumen de importación

// --- INICIO SIMULACIÓN PHPSPREADSHEET ---
// En un entorno real, esto se manejaría con Composer y autoloading.
// Aquí simulamos su presencia para la lógica.
if (!class_exists('SimulatedPhpSpreadsheetIOFactory')) {
    class SimulatedPhpSpreadsheetReader {
        private $filePath;
        private $data = [];
        private $header = [];

        public function __construct($filePath) {
            $this->filePath = $filePath;
            // Simular lectura de un CSV simple para este ejemplo
            // En un caso real, PhpSpreadsheet manejaría XLSX, XLS, CSV, etc.
            if (($handle = fopen($this->filePath, "r")) !== FALSE) {
                if (($this->header = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($this->header) == count($row)) { // Asegurar que el número de columnas coincida
                           $this->data[] = array_combine($this->header, $row);
                        }
                    }
                }
                fclose($handle);
            } else {
                throw new Exception("No se pudo abrir el archivo (simulación).");
            }
        }
        public function getSheetData() { return $this->data; }
        public function getHeaderRow() { return $this->header; }
    }
    class SimulatedPhpSpreadsheetIOFactory {
        public static function load($filePath) {
            // Simular que solo funciona con .csv para este placeholder
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'csv') {
                 throw new Exception("Simulación: Solo se pueden cargar archivos .csv en este modo simulado.");
            }
            return new SimulatedPhpSpreadsheetReader($filePath);
        }
    }
}
// --- FIN SIMULACIÓN PHPSPREADSHEET ---


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Error de validación CSRF. Inténtelo de nuevo.";
    } elseif ($_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Error al subir el archivo. Código: " . $_FILES['archivo_excel']['error'];
    } else {
        $upload_dir = ROOT_PATH . '/uploads/temp_import/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $filename = "import_prod_" . time() . "_" . basename($_FILES['archivo_excel']['name']);
        $target_file = $upload_dir . $filename;
        $file_extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // $allowed_extensions = ['xlsx', 'xls', 'csv']; // PhpSpreadsheet soporta estos
        $allowed_extensions_simulacion = ['csv']; // Para la simulación

        if (!in_array($file_extension, $allowed_extensions_simulacion)) {
            $error_message = "Formato de archivo no permitido. Solo se aceptan: " . implode(', ', $allowed_extensions_simulacion) . " (en modo simulación).";
        } elseif (move_uploaded_file($_FILES['archivo_excel']['tmp_name'], $target_file)) {
            $conn = get_db_connection();
            if (!$conn) {
                $error_message = "Error de conexión a la base de datos.";
            } else {
                try {
                    // $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($target_file); // Uso real
                    $spreadsheet = SimulatedPhpSpreadsheetIOFactory::load($target_file); // Uso simulado
                    // $sheet = $spreadsheet->getActiveSheet();
                    // $rows = $sheet->toArray(); // O iterar con ->getRowIterator()

                    $header = $spreadsheet->getHeaderRow(); // ['codigo_producto', 'nombre_producto', 'descripcion', 'unidad_medida', 'precio_venta_base', 'stock_almacen_principal', ...]
                    $data_rows = $spreadsheet->getSheetData();

                    // Validar cabecera esperada (ejemplo mínimo)
                    $expected_headers = ['codigo_producto', 'nombre_producto', 'precio_venta_base', 'unidad_medida'];
                                        // Podría añadir 'descripcion', 'moneda', 'incluye_igv', y columnas de stock como 'AlmacenX_stock', 'AlmacenX_stock_minimo'

                    $missing_headers = array_diff($expected_headers, array_map('strtolower', array_map('trim', $header)));
                    if (!empty($missing_headers)) {
                        throw new Exception("Cabeceras faltantes o incorrectas en el archivo: " . implode(', ', $missing_headers) . ". Cabeceras esperadas (mínimo): " . implode(', ', $expected_headers));
                    }

                    // Mapear cabeceras a minúsculas para flexibilidad
                    $header_map = array_flip(array_map('strtolower', array_map('trim', $header)));


                    $conn->begin_transaction();
                    $productos_creados = 0;
                    $productos_actualizados = 0;
                    $errores_fila = [];
                    $fila_num = 1; // Empezar después de la cabecera

                    // Obtener almacenes para mapeo de stock (si hay columnas de stock)
                    $almacenes_db = [];
                    $res_almacenes = $conn->query("SELECT id_almacen, LOWER(nombre_almacen) as nombre_lower FROM almacenes WHERE activo = TRUE");
                    while($alm = $res_almacenes->fetch_assoc()) {
                        $almacenes_db[$alm['nombre_lower']] = $alm['id_almacen'];
                    }

                    foreach ($data_rows as $row_data_assoc) {
                        $fila_num++;
                        $codigo = trim($row_data_assoc[$header[$header_map['codigo_producto']]] ?? ''); // Usar header original para acceder a $row_data_assoc
                        $nombre = trim($row_data_assoc[$header[$header_map['nombre_producto']]] ?? '');
                        $precio_str = trim($row_data_assoc[$header[$header_map['precio_venta_base']]] ?? '0');
                        $unidad = trim($row_data_assoc[$header[$header_map['unidad_medida']]] ?? 'Unidad');
                        $descripcion = trim($row_data_assoc[$header[$header_map['descripcion'] ?? -1]] ?? ''); // -1 si no existe
                        $moneda = trim($row_data_assoc[$header[$header_map['moneda'] ?? -1]] ?? 'PEN');
                        $incluye_igv_str = strtolower(trim($row_data_assoc[$header[$header_map['incluye_igv_en_precio_base'] ?? -1]] ?? 'si'));
                        $incluye_igv = ($incluye_igv_str === 'si' || $incluye_igv_str === '1' || $incluye_igv_str === 'true') ? 1 : 0;

                        if (empty($nombre) || empty($precio_str)) {
                            $errores_fila[] = "Fila $fila_num: Nombre y Precio Venta son obligatorios.";
                            continue;
                        }
                        if (!is_numeric($precio_str) || floatval($precio_str) < 0) {
                             $errores_fila[] = "Fila $fila_num: Precio Venta ('$precio_str') no es válido.";
                            continue;
                        }
                        $precio = floatval($precio_str);

                        $producto_existente_id = null;
                        if (!empty($codigo)) {
                            $stmt_find = $conn->prepare("SELECT id_producto FROM productos WHERE codigo_producto = ?");
                            $stmt_find->bind_param("s", $codigo);
                            $stmt_find->execute();
                            $res_find = $stmt_find->get_result();
                            if ($res_find->num_rows > 0) {
                                $producto_existente_id = $res_find->fetch_assoc()['id_producto'];
                            }
                            $stmt_find->close();
                        }

                        if ($producto_existente_id) { // Actualizar
                            $stmt_update = $conn->prepare("UPDATE productos SET nombre_producto=?, descripcion=?, unidad_medida=?, precio_venta_base=?, moneda=?, incluye_igv_en_precio_base=?, activo=1 WHERE id_producto=?");
                            $stmt_update->bind_param("sssdsiii", $nombre, $descripcion, $unidad, $precio, $moneda, $incluye_igv, $producto_existente_id);
                            if ($stmt_update->execute()) {
                                $productos_actualizados++;
                            } else {
                                $errores_fila[] = "Fila $fila_num (Cod: $codigo): Error al actualizar - " . $stmt_update->error;
                            }
                            $stmt_update->close();
                            $current_prod_id = $producto_existente_id;
                        } else { // Crear
                            $stmt_insert = $conn->prepare("INSERT INTO productos (codigo_producto, nombre_producto, descripcion, unidad_medida, precio_venta_base, moneda, incluye_igv_en_precio_base, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                            $stmt_insert->bind_param("ssssdsi", $codigo, $nombre, $descripcion, $unidad, $precio, $moneda, $incluye_igv);
                            if ($stmt_insert->execute()) {
                                $productos_creados++;
                                $current_prod_id = $conn->insert_id;
                            } else {
                                $errores_fila[] = "Fila $fila_num (Cod: $codigo): Error al crear - " . $stmt_insert->error;
                                continue; // Saltar stock si el producto no se pudo crear
                            }
                            $stmt_insert->close();
                        }

                        // Procesar stock por almacén (si las columnas existen en el Excel)
                        // Ejemplo: buscar columnas como 'stock_Almacen Principal', 'stock_min_Almacen Principal'
                        foreach ($almacenes_db as $nombre_alm_lower => $id_alm_db) {
                            $col_stock_actual = 'stock_' . str_replace(' ', '_', $nombre_alm_lower); // ej: stock_almacen_principal
                            $col_stock_minimo = 'stock_min_' . str_replace(' ', '_', $nombre_alm_lower); // ej: stock_min_almacen_principal

                            if (isset($header_map[$col_stock_actual])) {
                                $stock_val_str = trim($row_data_assoc[$header[$header_map[$col_stock_actual]]] ?? '0');
                                $stock_min_val_str = trim($row_data_assoc[$header[$header_map[$col_stock_minimo] ?? -1]] ?? '0');

                                $stock_val = is_numeric($stock_val_str) ? floatval($stock_val_str) : 0;
                                $stock_min_val = is_numeric($stock_min_val_str) ? floatval($stock_min_val_str) : 0;

                                // Upsert en producto_almacen
                                $stmt_upsert_stock = $conn->prepare("INSERT INTO producto_almacen (id_producto, id_almacen, stock_actual, stock_minimo) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE stock_actual=VALUES(stock_actual), stock_minimo=VALUES(stock_minimo)");
                                $stmt_upsert_stock->bind_param("iidd", $current_prod_id, $id_alm_db, $stock_val, $stock_min_val);
                                if (!$stmt_upsert_stock->execute()) {
                                     $errores_fila[] = "Fila $fila_num (Cod: $codigo): Error al actualizar stock para almacén '$nombre_alm_lower' - " . $stmt_upsert_stock->error;
                                }
                                $stmt_upsert_stock->close();
                            }
                        }

                    } // Fin foreach $data_rows

                    if (!empty($errores_fila)) {
                        $conn->rollback();
                        $error_message = "Importación fallida debido a errores en algunas filas. No se guardaron cambios.<br>" . implode("<br>", $errores_fila);
                    } else {
                        $conn->commit();
                        $feedback_message = "Importación completada.";
                        $summary_message = "Resumen: <br>Productos creados: $productos_creados <br>Productos actualizados: $productos_actualizados";
                        if (!empty($errores_fila)) { // Aunque no debería llegar aquí si hay errores y se hizo rollback
                            $summary_message .= "<br>Errores encontrados: " . count($errores_fila);
                        }
                    }

                } catch (Exception $e) {
                    if ($conn && $conn->server_version) $conn->rollback(); // Solo si la transacción se inició
                    $error_message = "Error durante la importación: " . $e->getMessage();
                } finally {
                    if ($conn && $conn->server_version) close_db_connection($conn);
                    if (file_exists($target_file)) unlink($target_file); // Eliminar archivo temporal
                }
            } // Fin if $conn
        } else {
            $error_message = "Error al mover el archivo subido al directorio temporal.";
        }
    } // Fin else CSRF
} // Fin if POST

$csrf_token = generate_csrf_token();
?>

<div class="card">
    <div class="card-header">
        <h3>Importar Productos desde Archivo</h3>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error_message)); ?></div>
        <?php endif; ?>
        <?php if ($summary_message): ?>
            <div class="alert alert-info"><?php echo nl2br(htmlspecialchars($summary_message)); ?></div>
        <?php endif; ?>

        <p>Suba un archivo CSV (codificado en UTF-8) con los productos a importar. <br>
           La primera fila debe contener las cabeceras. Las cabeceras deben ser en <strong>minúsculas</strong> y sin espacios (usar guion bajo).
        </p>

        <h4>Formato Esperado del Archivo CSV:</h4>
        <ul>
            <li><strong>Columnas Obligatorias:</strong>
                <ul>
                    <li><code>nombre_producto</code>: Nombre del producto.</li>
                    <li><code>precio_venta_base</code>: Precio de venta (número).</li>
                    <li><code>unidad_medida</code>: Ej: Unidad, Caja, Kg.</li>
                </ul>
            </li>
            <li><strong>Columnas Opcionales Recomendadas:</strong>
                <ul>
                    <li><code>codigo_producto</code>: SKU o código único. Si existe, se actualiza el producto. Si no, se crea (si el código no existe).</li>
                    <li><code>descripcion</code>: Descripción detallada.</li>
                    <li><code>moneda</code>: PEN o USD (por defecto PEN).</li>
                    <li><code>incluye_igv_en_precio_base</code>: 'si' o 'no' (o '1'/'0', 'true'/'false'). Por defecto 'si'.</li>
                </ul>
            </li>
            <li><strong>Columnas de Stock (Opcionales, por cada almacén existente en el sistema):</strong>
                <ul>
                    <li><code>stock_nombre_del_almacen</code>: Stock actual para ese almacén. Ej: <code>stock_almacen_principal</code>, <code>stock_tienda_sur</code> (reemplazar espacios con guion bajo, todo en minúsculas).</li>
                    <li><code>stock_min_nombre_del_almacen</code>: Stock mínimo para ese almacén. Ej: <code>stock_min_almacen_principal</code>.</li>
                </ul>
                 <small>Los nombres de almacén en las cabeceras deben coincidir (en minúsculas y con '_' en vez de espacio) con los nombres de los almacenes registrados en el sistema.</small>
            </li>
        </ul>
        <p><a href="<?php echo BASE_URL; ?>public/ejemplos/plantilla_importacion_productos.csv" download>Descargar plantilla de ejemplo CSV</a></p>


        <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=producto_importar" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="archivo_excel">Seleccionar Archivo (CSV):</label>
                <input type="file" id="archivo_excel" name="archivo_excel" class="form-control" accept=".csv" required>
                <small class="form-text text-muted">Asegúrese de que el archivo esté codificado en UTF-8.</small>
            </div>

            <div class="form-group text-right mt-20">
                <a href="<?php echo BASE_URL; ?>admin.php?page=productos" class="btn btn-secondary">Volver a Productos</a>
                <button type="submit" name="importar_productos" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Importar Productos
                </button>
            </div>
        </form>
    </div>
</div>
<?php
// Crear archivo CSV de ejemplo si no existe
$ejemplo_csv_path = ROOT_PATH . '/public/ejemplos/plantilla_importacion_productos.csv';
if (!file_exists(dirname($ejemplo_csv_path))) {
    mkdir(dirname($ejemplo_csv_path), 0755, true);
}
if (!file_exists($ejemplo_csv_path)) {
    $csv_content = "codigo_producto,nombre_producto,descripcion,unidad_medida,precio_venta_base,moneda,incluye_igv_en_precio_base,stock_almacen_principal,stock_min_almacen_principal\n";
    $csv_content .= "SKU001,Producto Ejemplo 1,\"Descripción detallada del producto 1\",Unidad,150.99,PEN,si,100,10\n";
    $csv_content .= "SKU002,Producto Ejemplo 2 con comas \"Producto, con comillas\",Descripción del producto 2,Caja,25.50,USD,no,50,5\n";
    $csv_content .= ",Producto Sin Codigo,Solo nombre y precio,Unidad,10.00,PEN,si,20,\n";
    file_put_contents($ejemplo_csv_path, $csv_content);
}
?>
<!-- Iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
