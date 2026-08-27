<?php
// app/views/admin/almacen_form.php

if (!is_admin()) {
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección requiere privilegios de administrador.</div>';
    return;
}

$is_editing = false;
$almacen_id = null;
$almacen_data = [ // Valores por defecto para un nuevo almacén
    'nombre_almacen' => '',
    'direccion' => '',
    'responsable' => '',
    'activo' => 1
];
$page_form_title = 'Crear Nuevo Almacén';
$error_message_form = '';
$feedback_message_form = '';

// Carga de datos para edición
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $almacen_id = (int)$_GET['id'];
    $page_form_title = 'Editar Almacén';

    $conn_load = get_db_connection();
    if ($conn_load) {
        $stmt_load = $conn_load->prepare("SELECT * FROM almacenes WHERE id_almacen = ?");
        if ($stmt_load) {
            $stmt_load->bind_param("i", $almacen_id);
            $stmt_load->execute();
            $result_load = $stmt_load->get_result();
            if ($result_load->num_rows === 1) {
                $almacen_data = array_merge($almacen_data, $result_load->fetch_assoc());
            } else {
                $_SESSION['error_message'] = "Almacén no encontrado para editar (ID: $almacen_id).";
                header("Location: " . BASE_URL . "admin.php?page=almacenes");
                exit;
            }
            $stmt_load->close();
        } else {
            $error_message_form = "Error al preparar consulta para cargar almacén: " . $conn_load->error;
        }
        close_db_connection($conn_load);
    } else {
        $error_message_form = "Error de conexión a la base de datos al cargar almacén.";
    }
}

// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_almacen'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message_form = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        $nombre_almacen = trim($_POST['nombre_almacen'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $responsable = trim($_POST['responsable'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        // Actualizar $almacen_data para repoblar formulario en caso de error
        $almacen_data = array_merge($almacen_data, $_POST);
        $almacen_data['activo'] = $activo;

        // Validaciones
        if (empty($nombre_almacen)) {
            $error_message_form .= "El nombre del almacén es obligatorio.<br>";
        }

        // Verificar unicidad del nombre del almacén
        if (empty($error_message_form)) {
            $conn_check = get_db_connection();
            if ($conn_check) {
                $sql_check_nombre = "SELECT id_almacen FROM almacenes WHERE nombre_almacen = ?";
                $params_check_nombre = [$nombre_almacen];
                if ($is_editing) {
                    $sql_check_nombre .= " AND id_almacen != ?";
                    $params_check_nombre[] = $almacen_id;
                }
                $stmt_check_nombre = $conn_check->prepare($sql_check_nombre);
                if ($stmt_check_nombre) {
                    $types_check_nombre = "s" . ($is_editing ? "i" : "");
                    $stmt_check_nombre->bind_param($types_check_nombre, ...$params_check_nombre);
                    $stmt_check_nombre->execute();
                    $result_check_nombre = $stmt_check_nombre->get_result();
                    if ($result_check_nombre->num_rows > 0) {
                        $error_message_form .= "Ya existe un almacén con el nombre '$nombre_almacen'.<br>";
                    }
                    $stmt_check_nombre->close();
                } else {
                    $error_message_form .= "Error al verificar nombre de almacén: " . $conn_check->error . "<br>";
                }
                // No cerrar $conn_check aquí, se usará para guardar si no hay errores
            } else {
                 $error_message_form .= "Error de conexión para verificar nombre.<br>";
            }
        }

        if (empty($error_message_form)) {
            $conn_save = $conn_check ?? get_db_connection();
            if ($conn_save) {
                if ($is_editing) {
                    $sql_save = "UPDATE almacenes SET nombre_almacen=?, direccion=?, responsable=?, activo=? WHERE id_almacen=?";
                    $stmt_save = $conn_save->prepare($sql_save);
                    $stmt_save->bind_param("sssii", $nombre_almacen, $direccion, $responsable, $activo, $almacen_id);
                } else {
                    $sql_save = "INSERT INTO almacenes (nombre_almacen, direccion, responsable, activo, fecha_creacion) VALUES (?, ?, ?, ?, NOW())";
                    $stmt_save = $conn_save->prepare($sql_save);
                    $stmt_save->bind_param("sssi", $nombre_almacen, $direccion, $responsable, $activo);
                }

                if ($stmt_save && $stmt_save->execute()) {
                    $_SESSION['feedback_message'] = "Almacén " . ($is_editing ? "actualizado" : "creado") . " correctamente.";
                    header("Location: " . BASE_URL . "admin.php?page=almacenes");
                    exit;
                } else {
                    $error_message_form = "Error al guardar almacén: " . ($stmt_save ? $stmt_save->error : $conn_save->error);
                }
                if ($stmt_save) $stmt_save->close();
                if ($conn_save === $conn_check) $conn_check = null;
                close_db_connection($conn_save);
            } else {
                $error_message_form = "Error de conexión al intentar guardar el almacén.";
            }
        }
         if ($conn_check) close_db_connection($conn_check);
    }
}
$csrf_token = generate_csrf_token();
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

        <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=<?php echo $is_editing ? 'almacen_editar&id=' . $almacen_id : 'almacen_crear'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="nombre_almacen">Nombre del Almacén:</label>
                <input type="text" id="nombre_almacen" name="nombre_almacen" class="form-control" value="<?php echo htmlspecialchars($almacen_data['nombre_almacen']); ?>" required>
            </div>

            <div class="form-group">
                <label for="direccion">Dirección (Opcional):</label>
                <textarea id="direccion" name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($almacen_data['direccion']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="responsable">Responsable (Opcional):</label>
                <input type="text" id="responsable" name="responsable" class="form-control" value="<?php echo htmlspecialchars($almacen_data['responsable']); ?>">
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" id="activo" name="activo" class="form-check-input" value="1" <?php echo ($almacen_data['activo'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="activo">Almacén Activo</label>
                </div>
            </div>

            <div class="form-group text-right">
                <a href="<?php echo BASE_URL; ?>admin.php?page=almacenes" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="guardar_almacen" class="btn btn-primary">
                    <?php echo $is_editing ? 'Actualizar Almacén' : 'Crear Almacén'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
