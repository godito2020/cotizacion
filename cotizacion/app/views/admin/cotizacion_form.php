<?php
// app/views/admin/cotizacion_form.php

$is_editing = false;
$cotizacion_id = null;
$cotizacion_data = [ // Valores por defecto
    'id_cliente' => null,
    'codigo_cotizacion' => '', // Se generará al guardar si es nueva
    'fecha_emision' => date('Y-m-d'),
    'fecha_validez' => date('Y-m-d', strtotime('+15 days')),
    'moneda' => get_config_value('DEFAULT_CURRENCY_CODE', $conn ?? null) ?: 'PEN', // Cargar de config o default
    'subtotal_bruto' => 0.00,
    'descuento_global_tipo' => 'NINGUNO',
    'descuento_global_valor' => 0.00,
    'monto_descuento_global' => 0.00,
    'subtotal_neto' => 0.00,
    'monto_igv' => 0.00,
    'porcentaje_igv_aplicado' => floatval(get_config_value('DEFAULT_IGV_PERCENTAGE', $conn ?? null) ?: 18.00),
    'total_cotizacion' => 0.00,
    'estado' => 'BORRADOR',
    'observaciones_publicas' => '',
    'observaciones_internas' => '',
    'terminos_condiciones' => get_config_value('TERMS_AND_CONDITIONS_COTIZACION', $conn ?? null) ?: "Validez: 15 días.\nPrecios no incluyen envío.", // Cargar de config
    'id_usuario_creador' => get_current_user_id(),
    'id_empresa' => 1 // Asumir empresa principal por ahora
];
$detalles_cotizacion = []; // Array de líneas de producto
$page_form_title = 'Crear Nueva Cotización';
$error_message_form = '';
$feedback_message_form = '';

$conn = get_db_connection(); // Asegurar conexión para operaciones iniciales
if (!$conn) {
    $error_message_form = "Error crítico: No se pudo conectar a la base de datos.";
    // Detener o manejar el error críticamente, ya que el formulario depende de la BD
} else {
    // Cargar datos de la empresa para IGV y términos por defecto (hecho en $cotizacion_data)
    // Cargar clientes para el select
    $clientes_activos = [];
    $result_clientes = $conn->query("SELECT id_cliente, nombre_razon_social, numero_documento FROM clientes WHERE activo = TRUE ORDER BY nombre_razon_social ASC");
    if ($result_clientes) {
        while ($row_cli = $result_clientes->fetch_assoc()) {
            $clientes_activos[] = $row_cli;
        }
    } else {
        $error_message_form .= "Error al cargar clientes: " . $conn->error . "<br>";
    }
}


// Carga de datos para edición
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $cotizacion_id = (int)$_GET['id'];
    $page_form_title = 'Editar Cotización';

    if ($conn) {
        $stmt_load_cot = $conn->prepare("SELECT * FROM cotizaciones WHERE id_cotizacion = ?");
        if ($stmt_load_cot) {
            $stmt_load_cot->bind_param("i", $cotizacion_id);
            $stmt_load_cot->execute();
            $result_load_cot = $stmt_load_cot->get_result();
            if ($result_load_cot->num_rows === 1) {
                $cotizacion_data_db = $result_load_cot->fetch_assoc();
                // Solo permitir edición si está en BORRADOR (o admin con más permisos)
                if ($cotizacion_data_db['estado'] !== 'BORRADOR' && !is_admin()) {
                     $_SESSION['error_message'] = "Solo se pueden editar cotizaciones en estado BORRADOR.";
                     if ($conn) close_db_connection($conn);
                     header("Location: " . BASE_URL . "admin.php?page=cotizaciones");
                     exit;
                }
                $cotizacion_data = array_merge($cotizacion_data, $cotizacion_data_db);

                // Cargar detalles de la cotización
                $stmt_load_det = $conn->prepare(
                    "SELECT cd.*, p.nombre_producto as nombre_producto_original, p.codigo_producto as codigo_producto_original, p.incluye_igv_en_precio_base as producto_incluye_igv_original
                     FROM cotizacion_detalles cd
                     JOIN productos p ON cd.id_producto = p.id_producto
                     WHERE cd.id_cotizacion = ? ORDER BY cd.id_detalle_cotizacion ASC"
                );
                if ($stmt_load_det) {
                    $stmt_load_det->bind_param("i", $cotizacion_id);
                    $stmt_load_det->execute();
                    $result_load_det = $stmt_load_det->get_result();
                    while ($row_det = $result_load_det->fetch_assoc()) {
                        $detalles_cotizacion[] = $row_det;
                    }
                    $stmt_load_det->close();
                } else {
                     $error_message_form .= "Error al cargar detalles de la cotización: " . $conn->error . "<br>";
                }
            } else {
                $_SESSION['error_message'] = "Cotización no encontrada (ID: $cotizacion_id).";
                if ($conn) close_db_connection($conn);
                header("Location: " . BASE_URL . "admin.php?page=cotizaciones");
                exit;
            }
            $stmt_load_cot->close();
        } else {
            $error_message_form = "Error al preparar consulta para cargar cotización: " . $conn->error;
        }
    } // Fin if $conn
} // Fin if $is_editing

// Lógica de guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cotizacion'])) {
    if (!$conn) {
        $error_message_form = "Error de conexión. No se pueden guardar los cambios.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message_form = "Error de validación CSRF.";
    } else {
        // Recoger datos de la cabecera
        $cotizacion_data['id_cliente'] = filter_var($_POST['id_cliente'], FILTER_VALIDATE_INT);
        $cotizacion_data['fecha_emision'] = $_POST['fecha_emision'] ?? date('Y-m-d');
        $cotizacion_data['fecha_validez'] = $_POST['fecha_validez'] ?? null;
        $cotizacion_data['moneda'] = $_POST['moneda'] ?? 'PEN';
        $cotizacion_data['observaciones_publicas'] = trim($_POST['observaciones_publicas'] ?? '');
        $cotizacion_data['observaciones_internas'] = trim($_POST['observaciones_internas'] ?? '');
        $cotizacion_data['terminos_condiciones'] = trim($_POST['terminos_condiciones'] ?? '');
        $cotizacion_data['estado'] = $_POST['estado_cotizacion'] ?? 'BORRADOR'; // Podría haber un select de estado para admins

        // Recoger datos de totales (calculados en JS y enviados)
        $cotizacion_data['subtotal_bruto'] = filter_var($_POST['subtotal_bruto_hidden'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $cotizacion_data['descuento_global_tipo'] = $_POST['descuento_global_tipo'] ?? 'NINGUNO';
        $cotizacion_data['descuento_global_valor'] = filter_var($_POST['descuento_global_valor'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $cotizacion_data['monto_descuento_global'] = filter_var($_POST['monto_descuento_global_hidden'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $cotizacion_data['subtotal_neto'] = filter_var($_POST['subtotal_neto_hidden'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $cotizacion_data['porcentaje_igv_aplicado'] = filter_var($_POST['porcentaje_igv_aplicado_hidden'] ?? $cotizacion_data['porcentaje_igv_aplicado'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $cotizacion_data['monto_igv'] = filter_var($_POST['monto_igv_hidden'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $cotizacion_data['total_cotizacion'] = filter_var($_POST['total_cotizacion_hidden'] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        // Recoger detalles de la cotización
        $detalles_form = [];
        if (isset($_POST['detalle_producto_id']) && is_array($_POST['detalle_producto_id'])) {
            foreach ($_POST['detalle_producto_id'] as $key => $id_prod) {
                $detalles_form[] = [
                    'id_producto' => filter_var($id_prod, FILTER_VALIDATE_INT),
                    'descripcion_producto_cot' => trim($_POST['detalle_descripcion'][$key] ?? ''),
                    'codigo_producto_cot' => trim($_POST['detalle_codigo_producto'][$key] ?? ''),
                    'unidad_medida_cot' => trim($_POST['detalle_unidad'][$key] ?? 'Unidad'),
                    'cantidad' => filter_var($_POST['detalle_cantidad'][$key] ?? 1, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'precio_unitario_base' => filter_var($_POST['detalle_precio_unitario_base'][$key] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'incluye_igv_producto' => isset($_POST['detalle_incluye_igv_producto'][$key]) ? 1 : 0,
                    'descuento_linea_tipo' => $_POST['detalle_descuento_tipo'][$key] ?? 'NINGUNO',
                    'descuento_linea_valor' => filter_var($_POST['detalle_descuento_valor'][$key] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'monto_descuento_linea' => filter_var($_POST['detalle_monto_descuento_linea_hidden'][$key] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'precio_unitario_final_linea' => filter_var($_POST['detalle_precio_final_linea_hidden'][$key] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'subtotal_linea' => filter_var($_POST['detalle_subtotal_linea_hidden'][$key] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'id_detalle_cotizacion' => filter_var($_POST['id_detalle_cotizacion'][$key] ?? null, FILTER_VALIDATE_INT) // Para edición
                ];
            }
        }
        $detalles_cotizacion = $detalles_form; // Para repoblar formulario en caso de error

        // Validaciones
        if (empty($cotizacion_data['id_cliente'])) {
            $error_message_form .= "Debe seleccionar un cliente.<br>";
        }
        if (empty($detalles_form)) {
            $error_message_form .= "Debe añadir al menos un producto a la cotización.<br>";
        }
        // Más validaciones aquí (fechas, montos, etc.)

        if (empty($error_message_form)) {
            $conn->begin_transaction();
            try {
                if ($is_editing) {
                    // Actualizar cotización
                    $sql_cot = "UPDATE cotizaciones SET id_cliente=?, fecha_emision=?, fecha_validez=?, moneda=?, subtotal_bruto=?, descuento_global_tipo=?, descuento_global_valor=?, monto_descuento_global=?, subtotal_neto=?, monto_igv=?, porcentaje_igv_aplicado=?, total_cotizacion=?, estado=?, observaciones_publicas=?, observaciones_internas=?, terminos_condiciones=? WHERE id_cotizacion=?";
                    $stmt_cot = $conn->prepare($sql_cot);
                    $stmt_cot->bind_param("isssdsdsdddsisssi",
                        $cotizacion_data['id_cliente'], $cotizacion_data['fecha_emision'], $cotizacion_data['fecha_validez'], $cotizacion_data['moneda'],
                        $cotizacion_data['subtotal_bruto'], $cotizacion_data['descuento_global_tipo'], $cotizacion_data['descuento_global_valor'], $cotizacion_data['monto_descuento_global'],
                        $cotizacion_data['subtotal_neto'], $cotizacion_data['monto_igv'], $cotizacion_data['porcentaje_igv_aplicado'], $cotizacion_data['total_cotizacion'],
                        $cotizacion_data['estado'], $cotizacion_data['observaciones_publicas'], $cotizacion_data['observaciones_internas'], $cotizacion_data['terminos_condiciones'],
                        $cotizacion_id
                    );
                } else {
                    // Crear nueva cotización
                    // Generar código de cotización
                    $prefijo_cot = get_config_value('COTIZACION_CODE_PREFIX', $conn) ?: 'COT-';
                    $next_num_stmt = $conn->query("SELECT valor_config FROM configuraciones WHERE clave_config = 'COTIZACION_NEXT_NUMBER'");
                    $next_num = $next_num_stmt->fetch_assoc()['valor_config'] ?? 1;
                    $cotizacion_data['codigo_cotizacion'] = $prefijo_cot . str_pad($next_num, 5, '0', STR_PAD_LEFT);

                    $sql_cot = "INSERT INTO cotizaciones (codigo_cotizacion, id_cliente, id_usuario_creador, id_empresa, fecha_emision, fecha_validez, moneda, subtotal_bruto, descuento_global_tipo, descuento_global_valor, monto_descuento_global, subtotal_neto, monto_igv, porcentaje_igv_aplicado, total_cotizacion, estado, observaciones_publicas, observaciones_internas, terminos_condiciones, fecha_creacion)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt_cot = $conn->prepare($sql_cot);
                    $stmt_cot->bind_param("siiisssdsdsdddsisss",
                        $cotizacion_data['codigo_cotizacion'], $cotizacion_data['id_cliente'], $cotizacion_data['id_usuario_creador'], $cotizacion_data['id_empresa'],
                        $cotizacion_data['fecha_emision'], $cotizacion_data['fecha_validez'], $cotizacion_data['moneda'],
                        $cotizacion_data['subtotal_bruto'], $cotizacion_data['descuento_global_tipo'], $cotizacion_data['descuento_global_valor'], $cotizacion_data['monto_descuento_global'],
                        $cotizacion_data['subtotal_neto'], $cotizacion_data['monto_igv'], $cotizacion_data['porcentaje_igv_aplicado'], $cotizacion_data['total_cotizacion'],
                        $cotizacion_data['estado'], $cotizacion_data['observaciones_publicas'], $cotizacion_data['observaciones_internas'], $cotizacion_data['terminos_condiciones']
                    );
                }

                if (!$stmt_cot || !$stmt_cot->execute()) {
                    throw new Exception("Error al guardar cotización: " . ($stmt_cot ? $stmt_cot->error : $conn->error));
                }

                $current_cotizacion_id = $is_editing ? $cotizacion_id : $conn->insert_id;
                if (!$is_editing) { // Actualizar el siguiente número de cotización
                    $new_next_num = intval($next_num) + 1;
                    update_config_value('COTIZACION_NEXT_NUMBER', (string)$new_next_num, $conn);
                }
                $stmt_cot->close();

                // Guardar/Actualizar detalles
                $ids_detalles_actuales_form = [];
                foreach ($detalles_form as $detalle) {
                    if ($detalle['id_detalle_cotizacion'] && $is_editing) { // Actualizar detalle existente
                        $ids_detalles_actuales_form[] = $detalle['id_detalle_cotizacion'];
                        $sql_det = "UPDATE cotizacion_detalles SET id_producto=?, descripcion_producto_cot=?, codigo_producto_cot=?, unidad_medida_cot=?, cantidad=?, precio_unitario_base=?, incluye_igv_producto=?, descuento_linea_tipo=?, descuento_linea_valor=?, monto_descuento_linea=?, precio_unitario_final_linea=?, subtotal_linea=? WHERE id_detalle_cotizacion=?";
                        $stmt_det = $conn->prepare($sql_det);
                        $stmt_det->bind_param("isssdDisddsddi",
                            $detalle['id_producto'], $detalle['descripcion_producto_cot'], $detalle['codigo_producto_cot'], $detalle['unidad_medida_cot'],
                            $detalle['cantidad'], $detalle['precio_unitario_base'], $detalle['incluye_igv_producto'], $detalle['descuento_linea_tipo'],
                            $detalle['descuento_linea_valor'], $detalle['monto_descuento_linea'], $detalle['precio_unitario_final_linea'], $detalle['subtotal_linea'],
                            $detalle['id_detalle_cotizacion']
                        );
                    } else { // Insertar nuevo detalle
                        $sql_det = "INSERT INTO cotizacion_detalles (id_cotizacion, id_producto, descripcion_producto_cot, codigo_producto_cot, unidad_medida_cot, cantidad, precio_unitario_base, incluye_igv_producto, descuento_linea_tipo, descuento_linea_valor, monto_descuento_linea, precio_unitario_final_linea, subtotal_linea) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_det = $conn->prepare($sql_det);
                        $stmt_det->bind_param("iisssdDisddsdd",
                            $current_cotizacion_id, $detalle['id_producto'], $detalle['descripcion_producto_cot'], $detalle['codigo_producto_cot'], $detalle['unidad_medida_cot'],
                            $detalle['cantidad'], $detalle['precio_unitario_base'], $detalle['incluye_igv_producto'], $detalle['descuento_linea_tipo'],
                            $detalle['descuento_linea_valor'], $detalle['monto_descuento_linea'], $detalle['precio_unitario_final_linea'], $detalle['subtotal_linea']
                        );
                    }
                    if (!$stmt_det || !$stmt_det->execute()) {
                        throw new Exception("Error al guardar detalle de cotización: " . ($stmt_det ? $stmt_det->error : $conn->error));
                    }
                    $stmt_det->close();
                }

                // Eliminar detalles que ya no están en el formulario (solo si es edición)
                if ($is_editing) {
                    $stmt_get_old_ids = $conn->prepare("SELECT id_detalle_cotizacion FROM cotizacion_detalles WHERE id_cotizacion = ?");
                    $stmt_get_old_ids->bind_param("i", $cotizacion_id);
                    $stmt_get_old_ids->execute();
                    $result_old_ids = $stmt_get_old_ids->get_result();
                    while ($row_old_id = $result_old_ids->fetch_assoc()) {
                        if (!in_array($row_old_id['id_detalle_cotizacion'], $ids_detalles_actuales_form)) {
                            $stmt_delete_det = $conn->prepare("DELETE FROM cotizacion_detalles WHERE id_detalle_cotizacion = ?");
                            $stmt_delete_det->bind_param("i", $row_old_id['id_detalle_cotizacion']);
                            if (!$stmt_delete_det || !$stmt_delete_det->execute()) {
                                throw new Exception("Error al eliminar detalle obsoleto: " . ($stmt_delete_det ? $stmt_delete_det->error : $conn->error));
                            }
                            $stmt_delete_det->close();
                        }
                    }
                    $stmt_get_old_ids->close();
                }

                $conn->commit();
                $_SESSION['feedback_message'] = "Cotización " . ($is_editing ? "actualizada" : "creada") . " correctamente con código: <strong>" . htmlspecialchars($cotizacion_data['codigo_cotizacion']) . "</strong>";
                header("Location: " . BASE_URL . "admin.php?page=cotizacion_ver&id=" . $current_cotizacion_id);
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
$default_igv_percentage = floatval(get_config_value('DEFAULT_IGV_PERCENTAGE') ?: 18.00);
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo $page_form_title; ?> <?php if($is_editing) echo " (".htmlspecialchars($cotizacion_data['codigo_cotizacion']).")"; ?></h3>
    </div>
    <div class="card-body">
        <?php if (!empty($feedback_message_form)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message_form); ?></div>
        <?php endif; ?>
        <?php if (!empty($error_message_form)): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error_message_form)); ?></div>
        <?php endif; ?>

        <form id="cotizacionForm" method="POST" action="<?php echo BASE_URL; ?>admin.php?page=<?php echo $is_editing ? 'cotizacion_editar&id=' . $cotizacion_id : 'cotizacion_crear'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <h4>Datos Generales</h4>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="id_cliente">Cliente:</label>
                        <select id="id_cliente" name="id_cliente" class="form-control select2-clientes" required>
                            <option value="">Seleccione un cliente...</option>
                            <?php foreach($clientes_activos as $cli): ?>
                                <option value="<?php echo $cli['id_cliente']; ?>" <?php echo ($cotizacion_data['id_cliente'] == $cli['id_cliente']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cli['nombre_razon_social'] . " (".$cli['tipo_documento'].": ".$cli['numero_documento'].")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         <small><a href="<?php echo BASE_URL; ?>admin.php?page=cliente_crear" target="_blank">Crear nuevo cliente</a></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="fecha_emision">Fecha de Emisión:</label>
                        <input type="date" id="fecha_emision" name="fecha_emision" class="form-control" value="<?php echo htmlspecialchars($cotizacion_data['fecha_emision']); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="fecha_validez">Fecha de Validez:</label>
                        <input type="date" id="fecha_validez" name="fecha_validez" class="form-control" value="<?php echo htmlspecialchars($cotizacion_data['fecha_validez']); ?>">
                    </div>
                </div>
            </div>
             <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="moneda">Moneda:</label>
                        <select id="moneda" name="moneda" class="form-control" required>
                            <option value="PEN" <?php echo ($cotizacion_data['moneda'] == 'PEN') ? 'selected' : ''; ?>>PEN (Soles)</option>
                            <option value="USD" <?php echo ($cotizacion_data['moneda'] == 'USD') ? 'selected' : ''; ?>>USD (Dólares)</option>
                        </select>
                    </div>
                </div>
                 <?php if (is_admin() && $is_editing): // Permitir cambiar estado solo a admin y en edición ?>
                 <div class="col-md-3">
                    <div class="form-group">
                        <label for="estado_cotizacion">Estado:</label>
                        <select id="estado_cotizacion" name="estado_cotizacion" class="form-control">
                            <option value="BORRADOR" <?php echo ($cotizacion_data['estado'] == 'BORRADOR') ? 'selected' : ''; ?>>Borrador</option>
                            <option value="ENVIADA" <?php echo ($cotizacion_data['estado'] == 'ENVIADA') ? 'selected' : ''; ?>>Enviada</option>
                            <option value="ACEPTADA" <?php echo ($cotizacion_data['estado'] == 'ACEPTADA') ? 'selected' : ''; ?>>Aceptada</option>
                            <option value="RECHAZADA" <?php echo ($cotizacion_data['estado'] == 'RECHAZADA') ? 'selected' : ''; ?>>Rechazada</option>
                            <option value="ANULADA" <?php echo ($cotizacion_data['estado'] == 'ANULADA') ? 'selected' : ''; ?>>Anulada</option>
                             <option value="VENCIDA" <?php echo ($cotizacion_data['estado'] == 'VENCIDA') ? 'selected' : ''; ?>>Vencida</option>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="estado_cotizacion" value="<?php echo htmlspecialchars($cotizacion_data['estado']); ?>">
                <?php endif; ?>
            </div>


            <h4 class="mt-20">Detalle de Productos/Servicios</h4>
            <hr>
            <div id="detalleCotizacionContainer" class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th width="3%">#</th>
                            <th width="30%">Producto/Servicio</th>
                            <th width="10%">Cantidad</th>
                            <th width="12%">Precio Unit.</th>
                            <th width="8%">Desc. Tipo</th>
                            <th width="8%">Desc. Valor</th>
                            <th width="12%">Subtotal Línea</th>
                            <th width="5%">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="cotizacionDetallesBody">
                        <?php if (empty($detalles_cotizacion)): ?>
                            <tr id="noRowsPlaceholder">
                                <td colspan="8" class="text-center">Aún no se han añadido productos.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detalles_cotizacion as $index => $detalle): ?>
                                <tr class="detalle-row" data-index="<?php echo $index; ?>">
                                    <td>
                                        <span class="row-number"><?php echo $index + 1; ?></span>
                                        <input type="hidden" name="id_detalle_cotizacion[]" value="<?php echo htmlspecialchars($detalle['id_detalle_cotizacion'] ?? ''); ?>">
                                        <input type="hidden" name="detalle_producto_id[]" class="detalle_producto_id" value="<?php echo htmlspecialchars($detalle['id_producto']); ?>">
                                        <input type="hidden" name="detalle_incluye_igv_producto[]" class="detalle_incluye_igv_producto" value="<?php echo htmlspecialchars($detalle['incluye_igv_producto'] ?? $detalle['producto_incluye_igv_original'] ?? '1'); ?>">
                                        <input type="hidden" name="detalle_codigo_producto[]" class="detalle_codigo_producto" value="<?php echo htmlspecialchars($detalle['codigo_producto_cot'] ?? $detalle['codigo_producto_original'] ?? ''); ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="detalle_descripcion[]" class="form-control form-control-sm detalle_descripcion" value="<?php echo htmlspecialchars($detalle['descripcion_producto_cot'] ?? $detalle['nombre_producto_original'] ?? ''); ?>" placeholder="Descripción personalizada">
                                        <small class="form-text text-muted">Original: <?php echo htmlspecialchars($detalle['nombre_producto_original'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><input type="number" name="detalle_cantidad[]" class="form-control form-control-sm detalle_cantidad" value="<?php echo htmlspecialchars(number_format(floatval($detalle['cantidad']),2,'.','')); ?>" step="0.01" min="0.01" required></td>
                                    <td>
                                        <input type="number" name="detalle_precio_unitario_base[]" class="form-control form-control-sm detalle_precio_unitario_base" value="<?php echo htmlspecialchars(number_format(floatval($detalle['precio_unit_base'] ?? $detalle['precio_unitario_base']),2,'.','')); ?>" step="0.01" min="0" required>
                                        <input type="hidden" name="detalle_precio_final_linea_hidden[]" class="detalle_precio_final_linea_hidden" value="<?php echo htmlspecialchars(number_format(floatval($detalle['precio_unitario_final_linea']),2,'.','')); ?>">
                                        <input type="hidden" name="detalle_monto_descuento_linea_hidden[]" class="detalle_monto_descuento_linea_hidden" value="<?php echo htmlspecialchars(number_format(floatval($detalle['monto_descuento_linea']),2,'.','')); ?>">
                                    </td>
                                    <td>
                                        <select name="detalle_descuento_tipo[]" class="form-control form-control-sm detalle_descuento_tipo">
                                            <option value="NINGUNO" <?php selected($detalle['descuento_linea_tipo'], 'NINGUNO'); ?>>Ninguno</option>
                                            <option value="PORCENTAJE" <?php selected($detalle['descuento_linea_tipo'], 'PORCENTAJE'); ?>>%</option>
                                            <option value="MONTO_FIJO_UNITARIO" <?php selected($detalle['descuento_linea_tipo'], 'MONTO_FIJO_UNITARIO'); ?>>Monto Unit.</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="detalle_descuento_valor[]" class="form-control form-control-sm detalle_descuento_valor" value="<?php echo htmlspecialchars(number_format(floatval($detalle['descuento_linea_valor']),2,'.','')); ?>" step="0.01" min="0"></td>
                                    <td><input type="text" name="detalle_subtotal_linea_hidden[]" class="form-control form-control-sm detalle_subtotal_linea_hidden" value="<?php echo htmlspecialchars(number_format(floatval($detalle['subtotal_linea']),2,'.','')); ?>" readonly></td>
                                    <td><button type="button" class="btn btn-danger btn-xs remove-detalle-row"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="addProductoRow" class="btn btn-info btn-sm mt-2"><i class="fas fa-plus"></i> Añadir Producto/Servicio</button>
            <div class="form-group mt-2">
                <label for="buscar_producto">Buscar Producto para añadir:</label>
                <input type="text" id="buscar_producto" class="form-control" placeholder="Escriba código o nombre del producto...">
                <div id="suggestions_productos"></div>
            </div>


            <h4 class="mt-20">Resumen y Totales</h4>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="observaciones_publicas">Observaciones (para el cliente):</label>
                        <textarea id="observaciones_publicas" name="observaciones_publicas" class="form-control" rows="3"><?php echo htmlspecialchars($cotizacion_data['observaciones_publicas']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="terminos_condiciones">Términos y Condiciones:</label>
                        <textarea id="terminos_condiciones" name="terminos_condiciones" class="form-control" rows="4"><?php echo htmlspecialchars($cotizacion_data['terminos_condiciones']); ?></textarea>
                    </div>
                     <div class="form-group">
                        <label for="observaciones_internas">Observaciones Internas (no visible para el cliente):</label>
                        <textarea id="observaciones_internas" name="observaciones_internas" class="form-control" rows="2"><?php echo htmlspecialchars($cotizacion_data['observaciones_internas']); ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm" id="tablaResumenCotizacion">
                        <tbody>
                            <tr>
                                <th>Subtotal Bruto:</th>
                                <td id="display_subtotal_bruto" class="text-right">0.00</td>
                                <input type="hidden" name="subtotal_bruto_hidden" id="subtotal_bruto_hidden" value="<?php echo $cotizacion_data['subtotal_bruto']; ?>">
                            </tr>
                            <tr>
                                <th>Descuento Global:</th>
                                <td class="text-right">
                                    <div class="input-group input-group-sm">
                                        <select name="descuento_global_tipo" id="descuento_global_tipo" class="form-control form-control-sm" style="width: 40%;">
                                            <option value="NINGUNO" <?php selected($cotizacion_data['descuento_global_tipo'], 'NINGUNO'); ?>>Ninguno</option>
                                            <option value="PORCENTAJE" <?php selected($cotizacion_data['descuento_global_tipo'], 'PORCENTAJE'); ?>>%</option>
                                            <option value="MONTO_FIJO" <?php selected($cotizacion_data['descuento_global_tipo'], 'MONTO_FIJO'); ?>>Monto Fijo</option>
                                        </select>
                                        <input type="number" step="0.01" name="descuento_global_valor" id="descuento_global_valor" class="form-control form-control-sm" value="<?php echo htmlspecialchars(number_format(floatval($cotizacion_data['descuento_global_valor']),2,'.','')); ?>" style="width: 60%;">
                                    </div>
                                    <span id="display_monto_descuento_global" class="text-danger">(-0.00)</span>
                                    <input type="hidden" name="monto_descuento_global_hidden" id="monto_descuento_global_hidden" value="<?php echo $cotizacion_data['monto_descuento_global']; ?>">
                                </td>
                            </tr>
                             <tr>
                                <th>Subtotal Neto:</th>
                                <td id="display_subtotal_neto" class="text-right">0.00</td>
                                <input type="hidden" name="subtotal_neto_hidden" id="subtotal_neto_hidden" value="<?php echo $cotizacion_data['subtotal_neto']; ?>">
                            </tr>
                            <tr>
                                <th>IGV (<span id="display_porcentaje_igv"><?php echo $cotizacion_data['porcentaje_igv_aplicado']; ?></span>%):</th>
                                <td id="display_monto_igv" class="text-right">0.00</td>
                                <input type="hidden" name="porcentaje_igv_aplicado_hidden" id="porcentaje_igv_aplicado_hidden" value="<?php echo $cotizacion_data['porcentaje_igv_aplicado']; ?>">
                                <input type="hidden" name="monto_igv_hidden" id="monto_igv_hidden" value="<?php echo $cotizacion_data['monto_igv']; ?>">
                            </tr>
                            <tr class="font-weight-bold" style="font-size: 1.2em;">
                                <th>TOTAL:</th>
                                <td id="display_total_cotizacion" class="text-right">0.00</td>
                                <input type="hidden" name="total_cotizacion_hidden" id="total_cotizacion_hidden" value="<?php echo $cotizacion_data['total_cotizacion']; ?>">
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


            <div class="form-group text-right mt-20">
                <a href="<?php echo BASE_URL; ?>admin.php?page=cotizaciones" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="guardar_cotizacion" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $is_editing ? 'Actualizar Cotización' : 'Guardar Borrador'; ?>
                </button>
                <?php if (!$is_editing || $cotizacion_data['estado'] == 'BORRADOR'): ?>
                <!-- <button type="submit" name="guardar_y_enviar_cotizacion" class="btn btn-info">
                    <i class="fas fa-paper-plane"></i> Guardar y Marcar como Enviada
                </button> -->
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php
// Helper para 'selected' en selects
function selected($value, $option) {
    if ($value == $option) {
        echo 'selected';
    }
}
?>
<!-- Select2 para búsqueda de clientes y productos (opcional, requiere JS y CSS) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Iconos y estilos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
.mt-20 { margin-top: 20px; }
.text-right { text-align: right; }
.table-sm td, .table-sm th { padding: .3rem; vertical-align: middle;}
.form-control-sm { height: calc(1.5em + .5rem + 2px); padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem; }
#suggestions_productos { border: 1px solid #ddd; max-height: 200px; overflow-y: auto; background-color: white; position: absolute; z-index: 1000; width: calc(100% - 30px); /* Ajustar */ }
#suggestions_productos div { padding: 8px; cursor: pointer; }
#suggestions_productos div:hover { background-color: #f0f0f0; }
.select2-container .select2-selection--single { height: calc(1.5em + .75rem + 2px); } /* Ajustar altura de select2 */
</style>

<script>
// Lógica JS para añadir filas de detalle, buscar productos, calcular totales, etc. irá aquí
// Esta parte es compleja y se desarrollará incrementalmente.
// Por ahora, un placeholder para la estructura básica.

let detalleIndex = <?php echo count($detalles_cotizacion); ?>; // Para nuevos productos
const defaultIGVPercentage = parseFloat(document.getElementById('porcentaje_igv_aplicado_hidden').value) || <?php echo $default_igv_percentage; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Select2 para clientes
    if (jQuery.fn.select2) { // Verificar si jQuery y Select2 están cargados
        $('#id_cliente').select2({
            placeholder: "Seleccione un cliente...",
            width: '100%'
        });
    } else {
        console.warn("Select2 no está cargado. La búsqueda de clientes será un select normal.");
    }

    // Recalcular todo al cargar si es edición
    if (<?php echo $is_editing ? 'true' : 'false'; ?>) {
        recalcularTodosLosTotales();
    }


    document.getElementById('addProductoRow').addEventListener('click', function() {
        addDetalleRow();
        renumerarFilas();
    });

    document.getElementById('cotizacionDetallesBody').addEventListener('click', function(e) {
        if (e.target.closest('.remove-detalle-row')) {
            e.target.closest('tr').remove();
            renumerarFilas();
            recalcularTodosLosTotales();
        }
    });

    // Event listeners para cambios en cantidades, precios, descuentos, etc.
    // Se usa delegación de eventos en el tbody para filas nuevas y existentes
    document.getElementById('cotizacionDetallesBody').addEventListener('change', function(e) {
        if (e.target.matches('.detalle_cantidad, .detalle_precio_unitario_base, .detalle_descuento_tipo, .detalle_descuento_valor')) {
            recalcularFila(e.target.closest('tr'));
            recalcularTodosLosTotales();
        }
    });

    // Event listeners para descuento global e IGV
    document.getElementById('descuento_global_tipo').addEventListener('change', recalcularTodosLosTotales);
    document.getElementById('descuento_global_valor').addEventListener('change', recalcularTodosLosTotales);
    // Si se permite cambiar el IGV en el form (ej. input para porcentaje_igv_aplicado_hidden)
    // document.getElementById('porcentaje_igv_aplicado_hidden').addEventListener('change', recalcularTodosLosTotales);


    // Búsqueda de productos (AJAX)
    const buscarProductoInput = document.getElementById('buscar_producto');
    const suggestionsDiv = document.getElementById('suggestions_productos');
    let searchTimeout;

    buscarProductoInput.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        suggestionsDiv.innerHTML = '';
        if (query.length < 2) { // No buscar con menos de 2 caracteres
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch(BASE_URL + 'ajax_handler.php?action=buscar_productos&term=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    suggestionsDiv.innerHTML = '';
                    if (data.success && data.productos.length > 0) {
                        data.productos.forEach(prod => {
                            const div = document.createElement('div');
                            div.innerHTML = `${prod.nombre_producto} (Cod: ${prod.codigo_producto || 'N/A'}) - Precio: ${prod.precio_venta_base} ${prod.moneda} - Stock: ${prod.stock_total_disponible || 0}`;
                            div.dataset.productoId = prod.id_producto;
                            div.dataset.nombre = prod.nombre_producto;
                            div.dataset.codigo = prod.codigo_producto || '';
                            div.dataset.precio = prod.precio_venta_base;
                            div.dataset.moneda = prod.moneda;
                            div.dataset.unidad = prod.unidad_medida;
                            div.dataset.incluyeIgv = prod.incluye_igv_en_precio_base; // 1 o 0
                            div.addEventListener('click', function() {
                                addDetalleRow(this.dataset);
                                buscarProductoInput.value = '';
                                suggestionsDiv.innerHTML = '';
                                renumerarFilas();
                            });
                            suggestionsDiv.appendChild(div);
                        });
                    } else if (data.success && data.productos.length === 0) {
                         suggestionsDiv.innerHTML = '<div>No se encontraron productos.</div>';
                    } else {
                        suggestionsDiv.innerHTML = '<div>Error buscando productos.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error en búsqueda AJAX:', error);
                    suggestionsDiv.innerHTML = '<div>Error de conexión.</div>';
                });
        }, 300); // Esperar 300ms después de dejar de teclear
    });
    // Ocultar sugerencias si se hace clic fuera
    document.addEventListener('click', function(e) {
        if (!suggestionsDiv.contains(e.target) && e.target !== buscarProductoInput) {
            suggestionsDiv.innerHTML = '';
        }
    });

}); // Fin DOMContentLoaded

function addDetalleRow(producto = null) {
    const tbody = document.getElementById('cotizacionDetallesBody');
    const placeholder = document.getElementById('noRowsPlaceholder');
    if (placeholder) placeholder.remove();

    detalleIndex++;
    const newRow = document.createElement('tr');
    newRow.classList.add('detalle-row');
    newRow.dataset.index = detalleIndex;

    newRow.innerHTML = `
        <td>
            <span class="row-number">${tbody.children.length + 1}</span>
            <input type="hidden" name="id_detalle_cotizacion[]" value=""> <!-- Vacío para nuevos -->
            <input type="hidden" name="detalle_producto_id[]" class="detalle_producto_id" value="${producto ? producto.productoId : ''}">
            <input type="hidden" name="detalle_incluye_igv_producto[]" class="detalle_incluye_igv_producto" value="${producto ? producto.incluyeIgv : '1'}">
            <input type="hidden" name="detalle_codigo_producto[]" class="detalle_codigo_producto" value="${producto ? producto.codigo : ''}">
        </td>
        <td>
            <input type="text" name="detalle_descripcion[]" class="form-control form-control-sm detalle_descripcion" value="${producto ? producto.nombre : ''}" placeholder="Descripción personalizada">
            ${producto ? `<small class="form-text text-muted">Original: ${producto.nombre}</small>` : ''}
        </td>
        <td><input type="number" name="detalle_cantidad[]" class="form-control form-control-sm detalle_cantidad" value="1.00" step="0.01" min="0.01" required></td>
        <td>
            <input type="number" name="detalle_precio_unitario_base[]" class="form-control form-control-sm detalle_precio_unitario_base" value="${producto ? parseFloat(producto.precio).toFixed(2) : '0.00'}" step="0.01" min="0" required>
            <input type="hidden" name="detalle_precio_final_linea_hidden[]" class="detalle_precio_final_linea_hidden" value="${producto ? parseFloat(producto.precio).toFixed(2) : '0.00'}">
            <input type="hidden" name="detalle_monto_descuento_linea_hidden[]" class="detalle_monto_descuento_linea_hidden" value="0.00">
        </td>
        <td>
            <select name="detalle_descuento_tipo[]" class="form-control form-control-sm detalle_descuento_tipo">
                <option value="NINGUNO" selected>Ninguno</option>
                <option value="PORCENTAJE">%</option>
                <option value="MONTO_FIJO_UNITARIO">Monto Unit.</option>
            </select>
        </td>
        <td><input type="number" name="detalle_descuento_valor[]" class="form-control form-control-sm detalle_descuento_valor" value="0.00" step="0.01" min="0"></td>
        <td><input type="text" name="detalle_subtotal_linea_hidden[]" class="form-control form-control-sm detalle_subtotal_linea_hidden" value="${producto ? parseFloat(producto.precio).toFixed(2) : '0.00'}" readonly></td>
        <td><button type="button" class="btn btn-danger btn-xs remove-detalle-row"><i class="fas fa-trash"></i></button></td>
    `;
    tbody.appendChild(newRow);
    recalcularFila(newRow); // Calcular para la nueva fila
    recalcularTodosLosTotales(); // Recalcular totales generales
}

function renumerarFilas() {
    const rows = document.querySelectorAll('#cotizacionDetallesBody .detalle-row');
    rows.forEach((row, idx) => {
        row.querySelector('.row-number').textContent = idx + 1;
    });
    if (rows.length === 0 && !document.getElementById('noRowsPlaceholder')) {
        const tbody = document.getElementById('cotizacionDetallesBody');
        tbody.innerHTML = '<tr id="noRowsPlaceholder"><td colspan="8" class="text-center">Aún no se han añadido productos.</td></tr>';
    }
}

function recalcularFila(row) {
    const cantidad = parseFloat(row.querySelector('.detalle_cantidad').value) || 0;
    const precioUnitarioMostrado = parseFloat(row.querySelector('.detalle_precio_unitario_base').value) || 0; // Este es el que el usuario edita
    const productoOriginalmenteIncluyeIGV = row.querySelector('.detalle_incluye_igv_producto').value === '1';
    const descuentoTipo = row.querySelector('.detalle_descuento_tipo').value;
    const descuentoValor = parseFloat(row.querySelector('.detalle_descuento_valor').value) || 0;

    let precioUnitarioBaseImponible; // Precio unitario SIN IGV
    let igvUnitarioDelPrecioBase = 0;

    if (productoOriginalmenteIncluyeIGV) {
        precioUnitarioBaseImponible = precioUnitarioMostrado / (1 + (defaultIGVPercentage / 100));
        igvUnitarioDelPrecioBase = precioUnitarioMostrado - precioUnitarioBaseImponible;
    } else {
        precioUnitarioBaseImponible = precioUnitarioMostrado;
        // igvUnitarioDelPrecioBase es 0, se calculará después si es necesario
    }

    let baseImponibleUnitariaNeta = precioUnitarioBaseImponible;
    let montoDescuentoUnitarioAplicadoSobreBase = 0;

    if (descuentoTipo === 'PORCENTAJE' && descuentoValor > 0) {
        montoDescuentoUnitarioAplicadoSobreBase = precioUnitarioBaseImponible * (descuentoValor / 100);
        baseImponibleUnitariaNeta = precioUnitarioBaseImponible - montoDescuentoUnitarioAplicadoSobreBase;
    } else if (descuentoTipo === 'MONTO_FIJO_UNITARIO' && descuentoValor > 0) {
        // Asumimos que el monto fijo se aplica a la base imponible
        montoDescuentoUnitarioAplicadoSobreBase = Math.min(descuentoValor, precioUnitarioBaseImponible); // No descontar más que la base
        baseImponibleUnitariaNeta = precioUnitarioBaseImponible - montoDescuentoUnitarioAplicadoSobreBase;
    }

    baseImponibleUnitariaNeta = Math.max(0, baseImponibleUnitariaNeta);

    // El IGV de la línea siempre se calcula sobre la base imponible neta de la línea
    const igvUnitarioLinea = baseImponibleUnitariaNeta * (defaultIGVPercentage / 100);
    const precioFinalUnitarioConIGV = baseImponibleUnitariaNeta + igvUnitarioLinea;

    const subtotalLineaConIGV = precioFinalUnitarioConIGV * cantidad;
    const montoTotalDescuentoLinea = montoDescuentoUnitarioAplicadoSobreBase * cantidad;

    // Guardar valores calculados en campos hidden de la fila (para referencia en recalcularTodosLosTotales)
    row.querySelector('.detalle_precio_final_linea_hidden').value = precioFinalUnitarioConIGV.toFixed(2);
    row.querySelector('.detalle_monto_descuento_linea_hidden').value = montoTotalDescuentoLinea.toFixed(2); // Descuento total de esta línea
    row.querySelector('.detalle_subtotal_linea_hidden').value = subtotalLineaConIGV.toFixed(2); // Este es el que se muestra como subtotal de línea

    // Guardar base imponible total e igv total de la línea
    if (!row.querySelector('.detalle_base_imponible_linea_hidden')) { // Crear si no existen
        const biHidden = document.createElement('input'); biHidden.type = 'hidden'; biHidden.name = 'detalle_base_imponible_linea_hidden[]'; biHidden.classList.add('detalle_base_imponible_linea_hidden'); row.appendChild(biHidden);
        const igvHidden = document.createElement('input'); igvHidden.type = 'hidden'; igvHidden.name = 'detalle_igv_linea_hidden[]'; igvHidden.classList.add('detalle_igv_linea_hidden'); row.appendChild(igvHidden);
    }
    row.querySelector('.detalle_base_imponible_linea_hidden').value = (baseImponibleUnitariaNeta * cantidad).toFixed(2);
    row.querySelector('.detalle_igv_linea_hidden').value = (igvUnitarioLinea * cantidad).toFixed(2);
}


function recalcularTodosLosTotales() {
    let sumatoriaSubtotalesDeLineaConIGV = 0; // Suma de (Precio Final Unitario con IGV * Cantidad) para cada línea
    let sumatoriaBasesImponiblesDeLineaNetas = 0; // Suma de (Base Imponible Unitaria Neta * Cantidad) para cada línea
    let sumatoriaIGVsDeLinea = 0; // Suma de (IGV Unitario de Línea * Cantidad) para cada línea

    const rows = document.querySelectorAll('#cotizacionDetallesBody .detalle-row');
    rows.forEach(row => {
        sumatoriaSubtotalesDeLineaConIGV += parseFloat(row.querySelector('.detalle_subtotal_linea_hidden').value) || 0;
        sumatoriaBasesImponiblesDeLineaNetas += parseFloat(row.querySelector('.detalle_base_imponible_linea_hidden').value) || 0;
        sumatoriaIGVsDeLinea += parseFloat(row.querySelector('.detalle_igv_linea_hidden').value) || 0;
    });

    // El Subtotal Bruto es la suma de los subtotales de línea (que ya incluyen IGV y descuentos de línea)
    document.getElementById('display_subtotal_bruto').textContent = sumatoriaSubtotalesDeLineaConIGV.toFixed(2);
    document.getElementById('subtotal_bruto_hidden').value = sumatoriaSubtotalesDeLineaConIGV.toFixed(2);

    // Descuento Global
    const descGlobalTipo = document.getElementById('descuento_global_tipo').value;
    const descGlobalValor = parseFloat(document.getElementById('descuento_global_valor').value) || 0;
    let montoDescuentoGlobalAplicado = 0;

    if (descGlobalTipo === 'PORCENTAJE' && descGlobalValor > 0) {
        // Aplicar porcentaje sobre la sumatoria de subtotales de línea (que ya tienen IGV)
        montoDescuentoGlobalAplicado = sumatoriaSubtotalesDeLineaConIGV * (descGlobalValor / 100);
    } else if (descGlobalTipo === 'MONTO_FIJO' && descGlobalValor > 0) {
        montoDescuentoGlobalAplicado = descGlobalValor;
    }
    montoDescuentoGlobalAplicado = Math.min(montoDescuentoGlobalAplicado, sumatoriaSubtotalesDeLineaConIGV); // No descontar más que el total

    document.getElementById('display_monto_descuento_global').textContent = `(-${montoDescuentoGlobalAplicado.toFixed(2)})`;
    document.getElementById('monto_descuento_global_hidden').value = montoDescuentoGlobalAplicado.toFixed(2);

    // Subtotal Neto CON IGV (después del descuento global)
    const subtotalNetoConIGV = sumatoriaSubtotalesDeLineaConIGV - montoDescuentoGlobalAplicado;
    document.getElementById('display_subtotal_neto').textContent = subtotalNetoConIGV.toFixed(2);
    document.getElementById('subtotal_neto_hidden').value = subtotalNetoConIGV.toFixed(2);

    // Para desglosar el IGV y la Base Imponible del Subtotal Neto Con IGV:
    // Asumimos que el descuento global se aplica proporcionalmente a la base y al IGV contenidos en sumatoriaSubtotalesDeLineaConIGV
    let igvFinalDeCotizacion = 0;
    let baseImponibleFinalDeCotizacion = 0;

    if (sumatoriaSubtotalesDeLineaConIGV > 0) { // Evitar división por cero si todo es 0
        const proporcionDescuento = montoDescuentoGlobalAplicado / sumatoriaSubtotalesDeLineaConIGV;

        const descuentoAplicadoABases = sumatoriaBasesImponiblesDeLineaNetas * proporcionDescuento;
        const descuentoAplicadoAIgvs = sumatoriaIGVsDeLinea * proporcionDescuento;

        baseImponibleFinalDeCotizacion = sumatoriaBasesImponiblesDeLineaNetas - descuentoAplicadoABases;
        igvFinalDeCotizacion = sumatoriaIGVsDeLinea - descuentoAplicadoAIgvs;
    } else { // Si el subtotal es 0, todo es 0
        baseImponibleFinalDeCotizacion = 0;
        igvFinalDeCotizacion = 0;
    }

    // Si por algún redondeo, la suma de base + igv no da el subtotalNetoConIGV, ajustar el IGV (o la base)
    // Esto es más una verificación, la proporcionalidad debería mantenerlo correcto.
    if (Math.abs((baseImponibleFinalDeCotizacion + igvFinalDeCotizacion) - subtotalNetoConIGV) > 0.005 && subtotalNetoConIGV > 0) {
         // Ajustar el IGV para que cuadre, puede ser necesario si los productos tienen diferentes tasas o si se redondea mucho.
         // Con una tasa única (defaultIGVPercentage), esto es menos probable, pero la extracción inicial puede causar pequeños diffs.
         // Una forma más directa si la tasa de IGV es única y se aplica a la base neta final:
         // baseImponibleFinalDeCotizacion = subtotalNetoConIGV / (1 + (defaultIGVPercentage / 100));
         // igvFinalDeCotizacion = subtotalNetoConIGV - baseImponibleFinalDeCotizacion;
         // Esta forma asume que el subtotalNetoConIGV es el monto final y de ahí se desglosa el IGV.
         // La anterior intenta mantener la proporcionalidad del IGV original de las líneas.
         // Por simplicidad y consistencia con una tasa de IGV global para la cotización:
         baseImponibleFinalDeCotizacion = subtotalNetoConIGV / (1 + (defaultIGVPercentage / 100));
         igvFinalDeCotizacion = subtotalNetoConIGV - baseImponibleFinalDeCotizacion;
    }


    document.getElementById('display_monto_igv').textContent = igvFinalDeCotizacion.toFixed(2);
    document.getElementById('monto_igv_hidden').value = igvFinalDeCotizacion.toFixed(2);
    document.getElementById('display_porcentaje_igv').textContent = defaultIGVPercentage.toFixed(2); // Mostrar el que se usa
    document.getElementById('porcentaje_igv_aplicado_hidden').value = defaultIGVPercentage.toFixed(2);


    // El Total de la Cotización es el Subtotal Neto (que ya tiene IGV)
    document.getElementById('display_total_cotizacion').textContent = subtotalNetoConIGV.toFixed(2);
    document.getElementById('total_cotizacion_hidden').value = subtotalNetoConIGV.toFixed(2);
    // También se podría mostrar la baseImponibleFinalDeCotizacion si se quiere más detalle en el resumen.
}


</script>
<?php
// Crear ajax_handler.php si no existe
$ajax_handler_path = ROOT_PATH . '/ajax_handler.php';
if (!file_exists($ajax_handler_path)) {
    $ajax_content = <<<EOT
<?php
require_once 'config/config.php';
require_once 'utils/db_helper.php';
// session_start(); // Si se necesita validar sesión para AJAX

header('Content-Type: application/json');

\$action = \$_GET['action'] ?? '';
\$response = ['success' => false, 'message' => 'Acción no válida.'];

if (\$action === 'buscar_productos') {
    \$term = \$_GET['term'] ?? '';
    if (empty(\$term) || strlen(\$term) < 2) {
        \$response['message'] = 'Término de búsqueda muy corto.';
        echo json_encode(\$response);
        exit;
    }

    \$conn = get_db_connection();
    if (!\$conn) {
        \$response['message'] = 'Error de conexión a BD.';
        echo json_encode(\$response);
        exit;
    }

    \$searchTerm = "%" . \$conn->real_escape_string(\$term) . "%";
    // Sumar stock total disponible
    \$sql = "SELECT p.id_producto, p.codigo_producto, p.nombre_producto, p.precio_venta_base, p.moneda, p.unidad_medida, p.incluye_igv_en_precio_base,
                   COALESCE((SELECT SUM(pa.stock_actual) FROM producto_almacen pa WHERE pa.id_producto = p.id_producto), 0) as stock_total_disponible
            FROM productos p
            WHERE p.activo = TRUE AND (p.nombre_producto LIKE ? OR p.codigo_producto LIKE ?)
            LIMIT 10";

    \$stmt = \$conn->prepare(\$sql);
    \$stmt->bind_param("ss", \$searchTerm, \$searchTerm);
    \$stmt->execute();
    \$result = \$stmt->get_result();
    \$productos = [];
    while (\$row = \$result->fetch_assoc()) {
        \$productos[] = \$row;
    }
    \$stmt->close();
    close_db_connection(\$conn);

    \$response['success'] = true;
    \$response['productos'] = \$productos;
    \$response['message'] = count(\$productos) > 0 ? 'Productos encontrados.' : 'No se encontraron productos.';
}

echo json_encode(\$response);
exit;
?>
EOT;
    file_put_contents($ajax_handler_path, $ajax_content);
}
?>
