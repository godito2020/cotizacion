<?php
// app/views/admin/producto_form.php

$is_editing = false;
$producto_id = null;
$producto_data = [ // Valores por defecto
    'codigo_producto' => '',
    'nombre_producto' => '',
    'descripcion' => '',
    'unidad_medida' => 'Unidad',
    'precio_compra' => '0.00',
    'precio_venta_base' => '0.00',
    'moneda' => 'PEN',
    'incluye_igv_en_precio_base' => 1, // Por defecto sí incluye
    'imagen_url' => '',
    'notas_internas' => '',
    'activo' => 1
];
$stock_por_almacen = []; // Array asociativo [id_almacen => ['stock_actual'=> S, 'stock_minimo'=> M, 'ubicacion_especifica'=> U]]
$almacenes_disponibles = [];

$page_form_title = 'Crear Nuevo Producto';
$error_message_form = '';
$feedback_message_form = '';

$conn = get_db_connection();
if (!$conn) {
    $error_message_form = "Error crítico: No se pudo conectar a la base de datos.";
} else {
    // Cargar almacenes disponibles
    $result_almacenes = $conn->query("SELECT id_almacen, nombre_almacen FROM almacenes WHERE activo = TRUE ORDER BY nombre_almacen ASC");
    if ($result_almacenes) {
        while ($row = $result_almacenes->fetch_assoc()) {
            $almacenes_disponibles[$row['id_almacen']] = $row['nombre_almacen'];
        }
    } else {
        $error_message_form .= "Error al cargar almacenes: " . $conn->error . "<br>";
    }
}


// Carga de datos para edición
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $producto_id = (int)$_GET['id'];
    $page_form_title = 'Editar Producto';

    if ($conn) {
        $stmt_load = $conn->prepare("SELECT * FROM productos WHERE id_producto = ?");
        if ($stmt_load) {
            $stmt_load->bind_param("i", $producto_id);
            $stmt_load->execute();
            $result_load = $stmt_load->get_result();
            if ($result_load->num_rows === 1) {
                $producto_data = array_merge($producto_data, $result_load->fetch_assoc());
                // Cargar stock por almacén para este producto
                $stmt_stock = $conn->prepare("SELECT id_almacen, stock_actual, stock_minimo, ubicacion_especifica FROM producto_almacen WHERE id_producto = ?");
                if ($stmt_stock) {
                    $stmt_stock->bind_param("i", $producto_id);
                    $stmt_stock->execute();
                    $result_stock = $stmt_stock->get_result();
                    while($row_stock = $result_stock->fetch_assoc()){
                        $stock_por_almacen[$row_stock['id_almacen']] = $row_stock;
                    }
                    $stmt_stock->close();
                } else {
                     $error_message_form .= "Error al cargar stock del producto: " . $conn->error . "<br>";
                }

            } else {
                $_SESSION['error_message'] = "Producto no encontrado para editar (ID: $producto_id).";
                if ($conn) close_db_connection($conn);
                header("Location: " . BASE_URL . "admin.php?page=productos");
                exit;
            }
            $stmt_load->close();
        } else {
            $error_message_form = "Error al preparar consulta para cargar producto: " . $conn->error;
        }
        // No cerrar $conn aquí, se usará si es POST
    }
}

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_producto'])) {
    if (!$conn) {
        $error_message_form = "Error de conexión. No se pueden guardar los cambios.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message_form = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        // Recoger y sanitizar datos del producto
        $codigo_producto = trim($_POST['codigo_producto'] ?? '');
        $nombre_producto = trim($_POST['nombre_producto'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $unidad_medida = trim($_POST['unidad_medida'] ?? 'Unidad');
        $precio_compra = filter_var(trim($_POST['precio_compra'] ?? '0.00'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $precio_venta_base = filter_var(trim($_POST['precio_venta_base'] ?? '0.00'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $moneda = $_POST['moneda'] ?? 'PEN';
        $incluye_igv_en_precio_base = isset($_POST['incluye_igv_en_precio_base']) ? 1 : 0;
        $notas_internas = trim($_POST['notas_internas'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Actualizar $producto_data para repoblar formulario
        $producto_data = array_merge($producto_data, $_POST);
        $producto_data['incluye_igv_en_precio_base'] = $incluye_igv_en_precio_base;
        $producto_data['activo'] = $activo;
        $producto_data['precio_compra'] = $precio_compra;
        $producto_data['precio_venta_base'] = $precio_venta_base;


        // Recoger datos de stock
        $form_stock_data = $_POST['stock'] ?? [];

        // Validaciones del producto
        if (empty($nombre_producto)) {
            $error_message_form .= "El nombre del producto es obligatorio.<br>";
        }
        if (!empty($codigo_producto)) { // Código es opcional, pero si se pone, debe ser único
            $sql_check_codigo = "SELECT id_producto FROM productos WHERE codigo_producto = ?";
            $params_check_codigo = [$codigo_producto];
            if ($is_editing) {
                $sql_check_codigo .= " AND id_producto != ?";
                $params_check_codigo[] = $producto_id;
            }
            $stmt_check_codigo = $conn->prepare($sql_check_codigo);
            if ($stmt_check_codigo) {
                $types_check_codigo = "s" . ($is_editing ? "i" : "");
                $stmt_check_codigo->bind_param($types_check_codigo, ...$params_check_codigo);
                $stmt_check_codigo->execute();
                if ($stmt_check_codigo->get_result()->num_rows > 0) {
                    $error_message_form .= "El código de producto '$codigo_producto' ya está en uso.<br>";
                }
                $stmt_check_codigo->close();
            } else {
                $error_message_form .= "Error al verificar código de producto: " . $conn->error . "<br>";
            }
        }
        if (!is_numeric($precio_venta_base) || floatval($precio_venta_base) < 0) {
            $error_message_form .= "El precio de venta base debe ser un número positivo.<br>";
        }
         if (!is_numeric($precio_compra) || floatval($precio_compra) < 0) {
            $error_message_form .= "El precio de compra debe ser un número positivo.<br>";
        }

        // Manejo de subida de imagen
        $current_imagen_url = $producto_data['imagen_url'] ?? '';
        if (isset($_FILES['imagen_producto']) && $_FILES['imagen_producto']['error'] == UPLOAD_ERR_OK) {
            $upload_dir_img = ROOT_PATH . '/uploads/productos/';
            if (!is_dir($upload_dir_img)) {
                if (!mkdir($upload_dir_img, 0755, true)) {
                     $error_message_form .= "Error: No se pudo crear el directorio de imágenes de productos.<br>";
                }
            }
            if (empty($error_message_form)) {
                $img_filename_base = "prod_" . ($is_editing ? $producto_id : 'new') . "_" . time();
                $img_fileType = strtolower(pathinfo(basename($_FILES['imagen_producto']['name']), PATHINFO_EXTENSION));
                $img_filename = $img_filename_base . "." . $img_fileType;
                $img_target_path = $upload_dir_img . $img_filename;
                $allowed_img_types = ['jpg', 'jpeg', 'png', 'gif'];

                $check_img = getimagesize($_FILES['imagen_producto']['tmp_name']);
                if ($check_img === false) {
                    $error_message_form .= "El archivo subido no es una imagen válida.<br>";
                } elseif ($_FILES['imagen_producto']['size'] > 2097152) { // 2MB
                    $error_message_form .= "La imagen no puede exceder los 2MB.<br>";
                } elseif (!in_array($img_fileType, $allowed_img_types)) {
                    $error_message_form .= "Solo se permiten imágenes JPG, JPEG, PNG o GIF.<br>";
                } else {
                    if (move_uploaded_file($_FILES['imagen_producto']['tmp_name'], $img_target_path)) {
                        // Eliminar imagen anterior
                        if (!empty($current_imagen_url) && strpos($current_imagen_url, 'uploads/productos/') === 0 && file_exists(ROOT_PATH . '/' . $current_imagen_url)) {
                           unlink(ROOT_PATH . '/' . $current_imagen_url);
                        }
                        $current_imagen_url = 'uploads/productos/' . $img_filename;
                    } else {
                        $error_message_form .= "Error al subir la imagen del producto.<br>";
                    }
                }
            }
        } elseif (isset($_POST['eliminar_imagen']) && $is_editing) {
            if (!empty($current_imagen_url) && strpos($current_imagen_url, 'uploads/productos/') === 0 && file_exists(ROOT_PATH . '/' . $current_imagen_url)) {
                unlink(ROOT_PATH . '/' . $current_imagen_url);
            }
            $current_imagen_url = ''; // Limpiar la URL de la imagen
        }


        if (empty($error_message_form)) {
            $conn->begin_transaction();
            try {
                if ($is_editing) {
                    $sql_prod = "UPDATE productos SET codigo_producto=?, nombre_producto=?, descripcion=?, unidad_medida=?, precio_compra=?, precio_venta_base=?, moneda=?, incluye_igv_en_precio_base=?, imagen_url=?, notas_internas=?, activo=? WHERE id_producto=?";
                    $stmt_prod = $conn->prepare($sql_prod);
                    $stmt_prod->bind_param("ssssddsisisi", $codigo_producto, $nombre_producto, $descripcion, $unidad_medida, $precio_compra, $precio_venta_base, $moneda, $incluye_igv_en_precio_base, $current_imagen_url, $notas_internas, $activo, $producto_id);
                } else {
                    $sql_prod = "INSERT INTO productos (codigo_producto, nombre_producto, descripcion, unidad_medida, precio_compra, precio_venta_base, moneda, incluye_igv_en_precio_base, imagen_url, notas_internas, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt_prod = $conn->prepare($sql_prod);
                    $stmt_prod->bind_param("ssssddsisisi", $codigo_producto, $nombre_producto, $descripcion, $unidad_medida, $precio_compra, $precio_venta_base, $moneda, $incluye_igv_en_precio_base, $current_imagen_url, $notas_internas, $activo);
                }

                if (!$stmt_prod || !$stmt_prod->execute()) {
                    throw new Exception("Error al guardar producto: " . ($stmt_prod ? $stmt_prod->error : $conn->error));
                }

                $current_producto_id = $is_editing ? $producto_id : $conn->insert_id;
                if (!$is_editing && $current_imagen_url && strpos($current_imagen_url, "prod_new_") === 0) {
                    // Renombrar imagen si es nuevo producto y se subió imagen
                    $new_img_filename = str_replace("prod_new_", "prod_".$current_producto_id."_", $current_imagen_url);
                    if (rename(ROOT_PATH . '/' . $current_imagen_url, ROOT_PATH . '/' . $new_img_filename)) {
                        $current_imagen_url = $new_img_filename;
                        $conn->query("UPDATE productos SET imagen_url = '". $conn->real_escape_string($current_imagen_url) ."' WHERE id_producto = $current_producto_id");
                    }
                }
                $stmt_prod->close();

                // Guardar/Actualizar stock por almacén
                // Primero, obtener los id_almacen que ya tienen stock para este producto
                $existing_stock_almacenes = [];
                if($is_editing) {
                    $res_ex_stock = $conn->query("SELECT id_almacen FROM producto_almacen WHERE id_producto = $current_producto_id");
                    while($r = $res_ex_stock->fetch_assoc()){ $existing_stock_almacenes[] = $r['id_almacen'];}
                }

                foreach ($form_stock_data as $id_almacen_form => $stock_info) {
                    $id_almacen = filter_var($id_almacen_form, FILTER_VALIDATE_INT);
                    if (!$id_almacen || !isset($almacenes_disponibles[$id_almacen])) continue; // Almacén no válido

                    $s_actual = filter_var(trim($stock_info['stock_actual'] ?? '0'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    $s_minimo = filter_var(trim($stock_info['stock_minimo'] ?? '0'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    $s_ubicacion = trim($stock_info['ubicacion_especifica'] ?? '');

                    if (!is_numeric($s_actual)) $s_actual = 0;
                    if (!is_numeric($s_minimo)) $s_minimo = 0;

                    // Verificar si ya existe una entrada para este producto y almacén
                    $stmt_check_pa = $conn->prepare("SELECT id_producto_almacen FROM producto_almacen WHERE id_producto = ? AND id_almacen = ?");
                    $stmt_check_pa->bind_param("ii", $current_producto_id, $id_almacen);
                    $stmt_check_pa->execute();
                    $result_check_pa = $stmt_check_pa->get_result();
                    $stmt_check_pa->close();

                    if ($result_check_pa->num_rows > 0) { // Actualizar stock existente
                        $stmt_stock_update = $conn->prepare("UPDATE producto_almacen SET stock_actual = ?, stock_minimo = ?, ubicacion_especifica = ? WHERE id_producto = ? AND id_almacen = ?");
                        $stmt_stock_update->bind_param("ddssi", $s_actual, $s_minimo, $s_ubicacion, $current_producto_id, $id_almacen);
                        if (!$stmt_stock_update || !$stmt_stock_update->execute()) {
                             throw new Exception("Error al actualizar stock en almacén ID $id_almacen: " . ($stmt_stock_update ? $stmt_stock_update->error : $conn->error));
                        }
                        $stmt_stock_update->close();
                        // Remover de $existing_stock_almacenes para no borrarlo después
                        if(($key = array_search($id_almacen, $existing_stock_almacenes)) !== false) {
                            unset($existing_stock_almacenes[$key]);
                        }
                    } else { // Insertar nuevo stock
                        // Solo insertar si el stock actual es > 0 o si hay stock mínimo o ubicación (para no llenar de ceros)
                        // O, si se quiere que siempre haya una entrada para cada almacén seleccionado, se quita esta condición.
                        // Por ahora, insertamos si hay algún dato relevante o si es explícitamente 0 y se quiere registrar.
                        // if (floatval($s_actual) != 0 || floatval($s_minimo) != 0 || !empty($s_ubicacion)) {
                            $stmt_stock_insert = $conn->prepare("INSERT INTO producto_almacen (id_producto, id_almacen, stock_actual, stock_minimo, ubicacion_especifica) VALUES (?, ?, ?, ?, ?)");
                            $stmt_stock_insert->bind_param("iidds", $current_producto_id, $id_almacen, $s_actual, $s_minimo, $s_ubicacion);
                            if (!$stmt_stock_insert || !$stmt_stock_insert->execute()) {
                                throw new Exception("Error al insertar stock en almacén ID $id_almacen: " . ($stmt_stock_insert ? $stmt_stock_insert->error : $conn->error));
                            }
                            $stmt_stock_insert->close();
                        // }
                    }
                }
                // Eliminar stock de almacenes que ya no están en el formulario (solo si se está editando)
                if ($is_editing && !empty($existing_stock_almacenes)) {
                    foreach($existing_stock_almacenes as $id_almacen_a_borrar) {
                        // Solo borrar si el stock es cero, o si la política es eliminar entradas no gestionadas
                        // $conn->query("DELETE FROM producto_almacen WHERE id_producto = $current_producto_id AND id_almacen = $id_almacen_a_borrar AND stock_actual = 0");
                        // Por ahora, si un almacén no viene en el POST, se asume que no se quiere gestionar/se borra la entrada
                         $conn->query("DELETE FROM producto_almacen WHERE id_producto = $current_producto_id AND id_almacen = $id_almacen_a_borrar");
                    }
                }


                $conn->commit();
                $_SESSION['feedback_message'] = "Producto " . ($is_editing ? "actualizado" : "creado") . " correctamente.";
                header("Location: " . BASE_URL . "admin.php?page=productos");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $error_message_form = $e->getMessage();
            }
        } // Fin if no hay errores de validación
    } // Fin else CSRF
} // Fin if POST

if ($conn) {
    close_db_connection($conn);
}
$csrf_token = generate_csrf_token();

$imagen_display_url = BASE_URL . 'public/img/producto_default.png';
if (!empty($producto_data['imagen_url']) && file_exists(ROOT_PATH . '/' . $producto_data['imagen_url'])) {
    $imagen_display_url = BASE_URL . $producto_data['imagen_url'] . '?t=' . time();
}

?>

<div class="card">
    <div class="card-header">
        <h3><?php echo $page_form_title; ?></h3>
    </div>
    <div class="card-body">
        <?php if (!empty($feedback_message_form)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message_form); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message_form)): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error_message_form)); ?></div>
        <?php endif; ?>

        <form id="productoForm" method="POST" action="<?php echo BASE_URL; ?>admin.php?page=<?php echo $is_editing ? 'producto_editar&id=' . $producto_id : 'producto_crear'; ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <h4>Datos del Producto</h4>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="codigo_producto">Código Producto (SKU, Opcional):</label>
                        <input type="text" id="codigo_producto" name="codigo_producto" class="form-control" value="<?php echo htmlspecialchars($producto_data['codigo_producto']); ?>">
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="nombre_producto">Nombre del Producto:</label>
                        <input type="text" id="nombre_producto" name="nombre_producto" class="form-control" value="<?php echo htmlspecialchars($producto_data['nombre_producto']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción (Opcional):</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="3"><?php echo htmlspecialchars($producto_data['descripcion']); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="unidad_medida">Unidad de Medida:</label>
                        <input type="text" id="unidad_medida" name="unidad_medida" class="form-control" value="<?php echo htmlspecialchars($producto_data['unidad_medida']); ?>" placeholder="Unidad, Caja, m2, Kg..." required>
                    </div>
                </div>
                <div class="col-md-3">
                     <div class="form-group">
                        <label for="precio_compra">Precio de Compra (Ref.):</label>
                        <input type="number" step="0.01" id="precio_compra" name="precio_compra" class="form-control" value="<?php echo htmlspecialchars(number_format(floatval($producto_data['precio_compra']), 2, '.', '')); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="precio_venta_base">Precio Venta Base:</label>
                        <input type="number" step="0.01" id="precio_venta_base" name="precio_venta_base" class="form-control" value="<?php echo htmlspecialchars(number_format(floatval($producto_data['precio_venta_base']), 2, '.', '')); ?>" required>
                    </div>
                </div>
                 <div class="col-md-3">
                    <div class="form-group">
                        <label for="moneda">Moneda:</label>
                        <select id="moneda" name="moneda" class="form-control">
                            <option value="PEN" <?php echo ($producto_data['moneda'] == 'PEN') ? 'selected' : ''; ?>>PEN</option>
                            <option value="USD" <?php echo ($producto_data['moneda'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                        </select>
                    </div>
                </div>
            </div>
             <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" id="incluye_igv_en_precio_base" name="incluye_igv_en_precio_base" class="form-check-input" value="1" <?php echo ($producto_data['incluye_igv_en_precio_base'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="incluye_igv_en_precio_base">El Precio de Venta Base YA incluye IGV/Impuestos</label>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="imagen_producto">Imagen del Producto (Opcional):</label>
                        <input type="file" id="imagen_producto" name="imagen_producto" class="form-control" accept="image/*">
                        <small class="form-text text-muted">Max 2MB. JPG, PNG, GIF.</small>
                         <?php if ($is_editing && !empty($producto_data['imagen_url'])): ?>
                            <div class="form-check mt-1">
                                <input type="checkbox" class="form-check-input" id="eliminar_imagen" name="eliminar_imagen" value="1">
                                <label class="form-check-label" for="eliminar_imagen">Eliminar imagen actual al guardar</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                     <label>Vista Previa:</label>
                    <div class="logo-preview-container" style="min-height: 50px; padding:5px; border-radius:4px;">
                        <img src="<?php echo $imagen_display_url; ?>" alt="Imagen Producto" id="imagenProductoPreview" style="max-width: 150px; max-height: 100px;">
                    </div>
                </div>
            </div>


            <div class="form-group">
                <label for="notas_internas">Notas Internas (Opcional):</label>
                <textarea id="notas_internas" name="notas_internas" class="form-control" rows="2"><?php echo htmlspecialchars($producto_data['notas_internas']); ?></textarea>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" id="activo" name="activo" class="form-check-input" value="1" <?php echo ($producto_data['activo'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="activo">Producto Activo (disponible para cotizar)</label>
                </div>
            </div>

            <h4 class="mt-20">Gestión de Stock por Almacén</h4>
            <hr>
            <?php if (empty($almacenes_disponibles)): ?>
                <p class="alert alert-warning">No hay almacenes activos registrados. <a href="<?php echo BASE_URL; ?>admin.php?page=almacen_crear">Cree un almacén</a> para gestionar el stock.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Almacén</th>
                                <th width="20%">Stock Actual</th>
                                <th width="20%">Stock Mínimo</th>
                                <th>Ubicación Específica</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($almacenes_disponibles as $id_alm => $nombre_alm):
                                $stock_actual_alm = htmlspecialchars($stock_por_almacen[$id_alm]['stock_actual'] ?? '0.00');
                                $stock_minimo_alm = htmlspecialchars($stock_por_almacen[$id_alm]['stock_minimo'] ?? '0.00');
                                $ubicacion_alm = htmlspecialchars($stock_por_almacen[$id_alm]['ubicacion_especifica'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($nombre_alm); ?></td>
                                <td><input type="number" step="0.01" name="stock[<?php echo $id_alm; ?>][stock_actual]" class="form-control form-control-sm" value="<?php echo $stock_actual_alm; ?>"></td>
                                <td><input type="number" step="0.01" name="stock[<?php echo $id_alm; ?>][stock_minimo]" class="form-control form-control-sm" value="<?php echo $stock_minimo_alm; ?>"></td>
                                <td><input type="text" name="stock[<?php echo $id_alm; ?>][ubicacion_especifica]" class="form-control form-control-sm" value="<?php echo $ubicacion_alm; ?>" placeholder="Ej: Est. A-Fila 3"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>


            <div class="form-group text-right mt-20">
                <a href="<?php echo BASE_URL; ?>admin.php?page=productos" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="guardar_producto" class="btn btn-primary">
                    <?php echo $is_editing ? 'Actualizar Producto' : 'Crear Producto'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<!-- CSS y JS para el formulario -->
<style>
.table-sm td, .table-sm th { padding: .3rem; }
.form-control-sm { height: calc(1.5em + .5rem + 2px); padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem; }
.mt-20 { margin-top: 20px; }
.row { display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; } /* Ya debería estar en admin_styles */
.col-md-3, .col-md-4, .col-md-6, .col-md-8 { position: relative; width: 100%; padding-right: 15px; padding-left: 15px; }
@media (min-width: 768px) { /* Asumiendo breakpoint md de Bootstrap */
    .col-md-3 { flex: 0 0 25%; max-width: 25%; }
    .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
    .col-md-6 { flex: 0 0 50%; max-width: 50%; }
    .col-md-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const imagenInput = document.getElementById('imagen_producto');
    const imagenPreview = document.getElementById('imagenProductoPreview');
    const defaultImageSrc = "<?php echo BASE_URL . 'public/img/producto_default.png'; ?>";

    if(imagenInput && imagenPreview) {
        imagenInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagenPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            } else {
                 // Si se deselecciona, volver a la imagen que estaba (o la default si no había)
                 imagenPreview.src = "<?php echo $imagen_display_url; ?>"; // $imagen_display_url tiene la imagen actual o la default
            }
        });
    }

    // Si se marca "eliminar imagen", limpiar la previsualización
    const eliminarImagenCheckbox = document.getElementById('eliminar_imagen');
    if (eliminarImagenCheckbox && imagenPreview) {
        eliminarImagenCheckbox.addEventListener('change', function() {
            if (this.checked) {
                imagenPreview.src = defaultImageSrc;
            } else {
                 imagenPreview.src = "<?php echo $imagen_display_url; ?>"; // Volver a la imagen actual
            }
        });
    }
});
</script>
