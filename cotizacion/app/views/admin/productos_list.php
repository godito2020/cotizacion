<?php
// app/views/admin/productos_list.php

$feedback_message = $_SESSION['feedback_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['error_message']);

$conn = get_db_connection();
$productos = [];
if ($conn) {
    // Consulta para obtener productos y sumar el stock total de todos los almacenes
    $sql = "SELECT p.id_producto, p.codigo_producto, p.nombre_producto, p.precio_venta_base, p.moneda, p.unidad_medida, p.activo,
                   (SELECT SUM(pa.stock_actual) FROM producto_almacen pa WHERE pa.id_producto = p.id_producto) as stock_total
            FROM productos p
            ORDER BY p.nombre_producto ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productos[] = $row;
        }
    } else {
        $error_message = "Error al cargar la lista de productos: " . $conn->error;
    }
    // No cerrar $conn aquí si se usa para eliminar
} else {
    $error_message = "No se pudo conectar a la base de datos.";
}

// Lógica para eliminar producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_producto_id'])) {
    if (!is_admin()) {
        $_SESSION['error_message'] = "No tiene permisos para eliminar productos.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        $id_producto_a_eliminar = filter_var($_POST['eliminar_producto_id'], FILTER_VALIDATE_INT);

        if ($id_producto_a_eliminar) {
            if (!$conn) $conn = get_db_connection();
            if ($conn) {
                // 1. Verificar si el producto está en alguna cotización_detalle
                $stmt_check_cot = $conn->prepare("SELECT COUNT(*) as count FROM cotizacion_detalles WHERE id_producto = ?");
                $stmt_check_cot->bind_param("i", $id_producto_a_eliminar);
                $stmt_check_cot->execute();
                $cot_count = $stmt_check_cot->get_result()->fetch_assoc()['count'];
                $stmt_check_cot->close();

                if ($cot_count > 0) {
                    $_SESSION['error_message'] = "No se puede eliminar el producto porque está referenciado en $cot_count detalle(s) de cotización. Considere desactivarlo.";
                } else {
                    // 2. Verificar si tiene stock en algún almacén (producto_almacen)
                    $stmt_check_stock = $conn->prepare("SELECT SUM(stock_actual) as total_stock FROM producto_almacen WHERE id_producto = ?");
                    $stmt_check_stock->bind_param("i", $id_producto_a_eliminar);
                    $stmt_check_stock->execute();
                    $total_stock = $stmt_check_stock->get_result()->fetch_assoc()['total_stock'];
                    $stmt_check_stock->close();

                    if ($total_stock > 0) {
                        $_SESSION['error_message'] = "No se puede eliminar el producto porque tiene stock (total: $total_stock) en uno o más almacenes. Ajuste el stock a cero primero.";
                    } else {
                        // Si no está en cotizaciones y no tiene stock, proceder a eliminar
                        // Primero eliminar de producto_almacen (aunque el stock sea 0, puede haber registros)
                        $conn->query("DELETE FROM producto_almacen WHERE id_producto = $id_producto_a_eliminar");

                        // Luego eliminar de productos
                        $stmt_delete = $conn->prepare("DELETE FROM productos WHERE id_producto = ?");
                        if ($stmt_delete) {
                            $stmt_delete->bind_param("i", $id_producto_a_eliminar);
                            if ($stmt_delete->execute()) {
                                if ($stmt_delete->affected_rows > 0) {
                                    // Opcional: eliminar imagen asociada si existe
                                    // Se necesitaría obtener la imagen_url antes de eliminar el registro.
                                    $_SESSION['feedback_message'] = "Producto eliminado correctamente.";
                                } else {
                                    $_SESSION['error_message'] = "No se encontró el producto para eliminar o ya fue eliminado.";
                                }
                            } else {
                                $_SESSION['error_message'] = "Error al eliminar el producto: " . $stmt_delete->error;
                            }
                            $stmt_delete->close();
                        } else {
                             $_SESSION['error_message'] = "Error al preparar la eliminación del producto: " . $conn->error;
                        }
                    }
                }
            } else {
                $_SESSION['error_message'] = "Error de conexión al intentar eliminar el producto.";
            }
        } else {
            $_SESSION['error_message'] = "ID de producto inválido para eliminar.";
        }
    }
    if ($conn) close_db_connection($conn);
    header("Location: " . BASE_URL . "admin.php?page=productos");
    exit;
}
if ($conn) close_db_connection($conn);
$csrf_token = generate_csrf_token();
?>

<div class="card">
    <div class="card-header">
        <h3>Lista de Productos</h3>
        <div class="float-right">
            <a href="<?php echo BASE_URL; ?>admin.php?page=producto_importar" class="btn btn-info btn-sm">
                <i class="fas fa-file-excel"></i> Importar desde Excel
            </a>
            <a href="<?php echo BASE_URL; ?>admin.php?page=producto_crear" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Crear Nuevo Producto
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($productos) && empty($error_message)): ?>
            <p>No hay productos registrados. <a href="<?php echo BASE_URL; ?>admin.php?page=producto_crear">Cree el primero</a> o <a href="<?php echo BASE_URL; ?>admin.php?page=producto_importar">impórtelos desde Excel</a>.</p>
        <?php elseif (!empty($productos)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Precio Venta</th>
                            <th>Unidad</th>
                            <th>Stock Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?php echo $producto['id_producto']; ?></td>
                                <td><?php echo htmlspecialchars($producto['codigo_producto'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($producto['nombre_producto']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($producto['precio_venta_base'], 2)); ?> <?php echo htmlspecialchars($producto['moneda']); ?></td>
                                <td><?php echo htmlspecialchars($producto['unidad_medida']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($producto['stock_total'] ?? 0, 2)); ?></td>
                                <td>
                                    <?php if ($producto['activo']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>admin.php?page=producto_editar&id=<?php echo $producto['id_producto']; ?>" class="btn btn-primary btn-xs" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (is_admin()): // Solo admin puede eliminar ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=productos" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de que desea eliminar este producto? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="eliminar_producto_id" value="<?php echo $producto['id_producto']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" class="btn btn-danger btn-xs" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
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
.btn-primary i.fas, .btn-danger i.fas, .btn-success i.fas, .btn-info i.fas { color: white; margin-right: 3px;}
.float-right { float: right; margin-left: 5px;} /* Ajuste para múltiples botones */
.card-header h3 { display:inline-block; margin-right:10px;} /* Para que el título y botones estén en línea */
</style>
