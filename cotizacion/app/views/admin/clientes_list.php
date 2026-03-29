<?php
// app/views/admin/clientes_list.php

// No se requiere ser admin, solo estar logueado (según admin.php)
// check_login(); // Ya hecho en admin.php

$feedback_message = $_SESSION['feedback_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['error_message']);

$conn = get_db_connection();
$clientes = [];
if ($conn) {
    $result = $conn->query("SELECT id_cliente, tipo_documento, numero_documento, nombre_razon_social, email, telefono, activo FROM clientes ORDER BY nombre_razon_social ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
    } else {
        $error_message = "Error al cargar la lista de clientes: " . $conn->error;
    }
    // No cerrar $conn aquí si se usa para eliminar
} else {
    $error_message = "No se pudo conectar a la base de datos.";
}

// Lógica para eliminar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_cliente_id'])) {
    if (!is_admin()) { // Solo admin puede eliminar clientes
        $_SESSION['error_message'] = "No tiene permisos para eliminar clientes.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        $id_cliente_a_eliminar = filter_var($_POST['eliminar_cliente_id'], FILTER_VALIDATE_INT);

        if ($id_cliente_a_eliminar) {
            if (!$conn) $conn = get_db_connection(); // Reabrir si se cerró
            if ($conn) {
                // Verificar si el cliente tiene cotizaciones asociadas (opcional, para evitar borrado o advertir)
                $stmt_check_cot = $conn->prepare("SELECT COUNT(*) as count FROM cotizaciones WHERE id_cliente = ?");
                if ($stmt_check_cot) {
                    $stmt_check_cot->bind_param("i", $id_cliente_a_eliminar);
                    $stmt_check_cot->execute();
                    $cot_count = $stmt_check_cot->get_result()->fetch_assoc()['count'];
                    $stmt_check_cot->close();

                    if ($cot_count > 0) {
                        $_SESSION['error_message'] = "No se puede eliminar el cliente porque tiene $cot_count cotización(es) asociada(s). Considere desactivarlo.";
                    } else {
                        // Proceder a eliminar
                        $stmt_delete = $conn->prepare("DELETE FROM clientes WHERE id_cliente = ?");
                        if ($stmt_delete) {
                            $stmt_delete->bind_param("i", $id_cliente_a_eliminar);
                            if ($stmt_delete->execute()) {
                                if ($stmt_delete->affected_rows > 0) {
                                    $_SESSION['feedback_message'] = "Cliente eliminado correctamente.";
                                } else {
                                    $_SESSION['error_message'] = "No se encontró el cliente para eliminar o ya fue eliminado.";
                                }
                            } else {
                                $_SESSION['error_message'] = "Error al eliminar el cliente: " . $stmt_delete->error;
                            }
                            $stmt_delete->close();
                        } else {
                             $_SESSION['error_message'] = "Error al preparar la eliminación del cliente: " . $conn->error;
                        }
                    }
                } else {
                     $_SESSION['error_message'] = "Error al verificar cotizaciones del cliente: " . $conn->error;
                }
            } else {
                $_SESSION['error_message'] = "Error de conexión al intentar eliminar el cliente.";
            }
        } else {
            $_SESSION['error_message'] = "ID de cliente inválido para eliminar.";
        }
    }
    if ($conn) close_db_connection($conn);
    // Redirigir para evitar reenvío de formulario y mostrar mensajes de sesión
    header("Location: " . BASE_URL . "admin.php?page=clientes");
    exit;
}
if ($conn) close_db_connection($conn); // Cerrar si no se usó para eliminar
$csrf_token = generate_csrf_token();
?>

<div class="card">
    <div class="card-header">
        <h3>Lista de Clientes</h3>
        <a href="<?php echo BASE_URL; ?>admin.php?page=cliente_crear" class="btn btn-success btn-sm float-right">
            <i class="fas fa-plus"></i> Crear Nuevo Cliente
        </a>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($clientes) && empty($error_message)): ?>
            <p>No hay clientes registrados. <a href="<?php echo BASE_URL; ?>admin.php?page=cliente_crear">Cree el primero</a>.</p>
        <?php elseif (!empty($clientes)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo Doc.</th>
                            <th>Nro. Doc.</th>
                            <th>Nombre / Razón Social</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo $cliente['id_cliente']; ?></td>
                                <td><?php echo htmlspecialchars($cliente['tipo_documento']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['numero_documento']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['nombre_razon_social']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($cliente['activo']): ?>
                                        <span class="badge badge-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>admin.php?page=cliente_editar&id=<?php echo $cliente['id_cliente']; ?>" class="btn btn-primary btn-xs" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (is_admin()): // Solo admin puede eliminar ?>
                                    <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=clientes" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de que desea eliminar este cliente? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="eliminar_cliente_id" value="<?php echo $cliente['id_cliente']; ?>">
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

<!-- Iconos (si no están globalmente) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Estilos adicionales si son necesarios */
.table-responsive { overflow-x: auto; }
/* Badges y botones ya deberían estar en admin_styles.css o aquí si es específico */
.badge { display: inline-block; padding: .25em .4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }
.badge-success { color: #fff; background-color: #28a745; }
.badge-danger { color: #fff; background-color: #dc3545; }
.btn-xs { padding: .2rem .4rem; font-size: .75rem; line-height: 1.5; border-radius: .2rem; }
.btn i.fas { margin-right: 0; } /* Sin margen para botones solo con icono */
.btn-primary i.fas, .btn-danger i.fas { color: white; }
</style>
