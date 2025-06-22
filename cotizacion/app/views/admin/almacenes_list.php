<?php
// app/views/admin/almacenes_list.php

$feedback_message = $_SESSION['feedback_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['error_message']);

$conn = get_db_connection();
$almacenes = [];
if ($conn) {
    $result = $conn->query("SELECT id_almacen, nombre_almacen, direccion, responsable, activo FROM almacenes ORDER BY nombre_almacen ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $almacenes[] = $row;
        }
    } else {
        $error_message = "Error al cargar la lista de almacenes: " . $conn->error;
    }
    // No cerrar $conn aquí si se usa para eliminar
} else {
    $error_message = "No se pudo conectar a la base de datos.";
}

// Lógica para eliminar almacén
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_almacen_id'])) {
    if (!is_admin()) {
        $_SESSION['error_message'] = "No tiene permisos para eliminar almacenes.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        $id_almacen_a_eliminar = filter_var($_POST['eliminar_almacen_id'], FILTER_VALIDATE_INT);

        if ($id_almacen_a_eliminar) {
            if (!$conn) $conn = get_db_connection();
            if ($conn) {
                // Verificar si el almacén tiene stock de productos
                $stmt_check_stock = $conn->prepare("SELECT COUNT(*) as count FROM producto_almacen WHERE id_almacen = ? AND stock_actual > 0");
                if ($stmt_check_stock) {
                    $stmt_check_stock->bind_param("i", $id_almacen_a_eliminar);
                    $stmt_check_stock->execute();
                    $stock_count = $stmt_check_stock->get_result()->fetch_assoc()['count'];
                    $stmt_check_stock->close();

                    if ($stock_count > 0) {
                        $_SESSION['error_message'] = "No se puede eliminar el almacén porque tiene ($stock_count) producto(s) con stock asociado. Transfiera o ajuste el stock primero.";
                    } else {
                        // Si no hay stock, se puede eliminar el almacén y las entradas en producto_almacen (stock 0)
                        // Opcional: Eliminar primero de producto_almacen si hay registros con stock 0
                        $conn->query("DELETE FROM producto_almacen WHERE id_almacen = $id_almacen_a_eliminar");

                        $stmt_delete = $conn->prepare("DELETE FROM almacenes WHERE id_almacen = ?");
                        if ($stmt_delete) {
                            $stmt_delete->bind_param("i", $id_almacen_a_eliminar);
                            if ($stmt_delete->execute()) {
                                if ($stmt_delete->affected_rows > 0) {
                                    $_SESSION['feedback_message'] = "Almacén eliminado correctamente.";
                                } else {
                                    $_SESSION['error_message'] = "No se encontró el almacén para eliminar o ya fue eliminado.";
                                }
                            } else {
                                $_SESSION['error_message'] = "Error al eliminar el almacén: " . $stmt_delete->error;
                            }
                            $stmt_delete->close();
                        } else {
                             $_SESSION['error_message'] = "Error al preparar la eliminación del almacén: " . $conn->error;
                        }
                    }
                } else {
                     $_SESSION['error_message'] = "Error al verificar stock del almacén: " . $conn->error;
                }
            } else {
                $_SESSION['error_message'] = "Error de conexión al intentar eliminar el almacén.";
            }
        } else {
            $_SESSION['error_message'] = "ID de almacén inválido para eliminar.";
        }
    }
    if ($conn) close_db_connection($conn);
    header("Location: " . BASE_URL . "admin.php?page=almacenes");
    exit;
}
if ($conn) close_db_connection($conn);
$csrf_token = generate_csrf_token();
?>

<div class="card">
    <div class="card-header">
        <h3>Lista de Almacenes</h3>
        <?php if (is_admin()): // Solo admins pueden crear/editar/eliminar ?>
        <a href="<?php echo BASE_URL; ?>admin.php?page=almacen_crear" class="btn btn-success btn-sm float-right">
            <i class="fas fa-plus"></i> Crear Nuevo Almacén
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($almacenes) && empty($error_message)): ?>
            <p>No hay almacenes registrados. <?php if (is_admin()): ?><a href="<?php echo BASE_URL; ?>admin.php?page=almacen_crear">Cree el primero</a>.<?php endif; ?></p>
        <?php elseif (!empty($almacenes)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del Almacén</th>
                            <th>Dirección</th>
                            <th>Responsable</th>
                            <th>Estado</th>
                            <?php if (is_admin()): ?>
                            <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($almacenes as $almacen): ?>
                            <tr>
                                <td><?php echo $almacen['id_almacen']; ?></td>
                                <td><?php echo htmlspecialchars($almacen['nombre_almacen']); ?></td>
                                <td><?php echo htmlspecialchars($almacen['direccion'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($almacen['responsable'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($almacen['activo']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_admin()): ?>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>admin.php?page=almacen_editar&id=<?php echo $almacen['id_almacen']; ?>" class="btn btn-primary btn-xs" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=almacenes" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de que desea eliminar este almacén?');">
                                        <input type="hidden" name="eliminar_almacen_id" value="<?php echo $almacen['id_almacen']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" class="btn btn-danger btn-xs" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Iconos y estilos (revisar si ya están en admin_styles.css) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
.table-responsive { overflow-x: auto; }
.badge { display: inline-block; padding: .25em .4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }
.badge-success { color: #fff; background-color: #28a745; }
.badge-danger { color: #fff; background-color: #dc3545; }
.btn-xs { padding: .2rem .4rem; font-size: .75rem; line-height: 1.5; border-radius: .2rem; }
.btn i.fas { margin-right: 0; }
</style>
