<?php
// app/views/admin/usuarios_list.php

// Proteger esta página: solo administradores
if (!is_admin()) {
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección requiere privilegios de administrador.</div>';
    return; // No continuar renderizando si no es admin
}

$feedback_message = $_SESSION['feedback_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['error_message']);

$conn = get_db_connection();
$usuarios = [];
if ($conn) {
    $result = $conn->query("SELECT id_usuario, nombre_completo, email, rol, activo, fecha_creacion, ultimo_login FROM usuarios ORDER BY nombre_completo ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    } else {
        $error_message = "Error al cargar la lista de usuarios: " . $conn->error;
    }
    close_db_connection($conn);
} else {
    $error_message = "No se pudo conectar a la base de datos.";
}

// Lógica para eliminar usuario (si se confirma desde un POST a esta misma página o a un script dedicado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario_id'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        $id_usuario_a_eliminar = filter_var($_POST['eliminar_usuario_id'], FILTER_VALIDATE_INT);
        $current_user_id = get_current_user_id();

        if ($id_usuario_a_eliminar && $id_usuario_a_eliminar == $current_user_id) {
            $_SESSION['error_message'] = "No puede eliminar su propia cuenta de usuario.";
        } elseif ($id_usuario_a_eliminar) {
            $conn_delete = get_db_connection();
            if ($conn_delete) {
                // Opcional: Verificar si es el único admin antes de eliminar
                if ($id_usuario_a_eliminar == 1 && get_current_user_role() == 'admin') { // Asumiendo ID 1 es super admin o primer admin
                    $admin_count_stmt = $conn_delete->query("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'admin' AND activo = TRUE");
                    $admin_count = $admin_count_stmt->fetch_assoc()['count'];
                    if ($admin_count <= 1) {
                         $_SESSION['error_message'] = "No se puede eliminar el único administrador activo del sistema.";
                         // Redirigir para evitar reenvío de formulario
                         header("Location: " . BASE_URL . "admin.php?page=usuarios");
                         exit;
                    }
                }

                $stmt = $conn_delete->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $id_usuario_a_eliminar);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $_SESSION['feedback_message'] = "Usuario eliminado correctamente.";
                        } else {
                            $_SESSION['error_message'] = "No se encontró el usuario para eliminar o ya fue eliminado.";
                        }
                    } else {
                        $_SESSION['error_message'] = "Error al eliminar el usuario: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                     $_SESSION['error_message'] = "Error al preparar la eliminación: " . $conn_delete->error;
                }
                close_db_connection($conn_delete);
            } else {
                $_SESSION['error_message'] = "Error de conexión al intentar eliminar.";
            }
        } else {
            $_SESSION['error_message'] = "ID de usuario inválido para eliminar.";
        }
    }
    // Redirigir para evitar reenvío de formulario y mostrar mensajes de sesión
    header("Location: " . BASE_URL . "admin.php?page=usuarios");
    exit;
}
$csrf_token = generate_csrf_token();
?>

<div class="card">
    <div class="card-header">
        <h3>Lista de Usuarios</h3>
        <a href="<?php echo BASE_URL; ?>admin.php?page=usuario_crear" class="btn btn-success btn-sm float-right">
            <i class="fas fa-plus"></i> Crear Nuevo Usuario
        </a>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($usuarios) && empty($error_message)): ?>
            <p>No hay usuarios registrados. <a href="<?php echo BASE_URL; ?>admin.php?page=usuario_crear">Cree el primero</a>.</p>
        <?php elseif (!empty($usuarios)): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Último Login</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?php echo $usuario['id_usuario']; ?></td>
                            <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($usuario['rol'])); ?></td>
                            <td>
                                <?php if ($usuario['activo']): ?>
                                    <span class="badge badge-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></td>
                            <td><?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>admin.php?page=usuario_editar&id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-primary btn-xs" title="Editar">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if (get_current_user_id() != $usuario['id_usuario']): // No permitir auto-eliminación ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=usuarios" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de que desea eliminar este usuario? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="eliminar_usuario_id" value="<?php echo $usuario['id_usuario']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <button type="submit" class="btn btn-danger btn-xs" title="Eliminar">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
                                <?php else: ?>
                                     <button class="btn btn-secondary btn-xs" disabled title="No puede eliminar su propia cuenta">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- FontAwesome para iconos (si no está ya incluido globalmente) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Estilos para badges (si no usas Bootstrap completo) */
.badge { display: inline-block; padding: .25em .4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }
.badge-success { color: #fff; background-color: #28a745; }
.badge-danger { color: #fff; background-color: #dc3545; }
.float-right { float: right; }
.btn-xs { padding: .2rem .4rem; font-size: .75rem; line-height: 1.5; border-radius: .2rem; }
/* Iconos dentro de botones */
.btn i.fas { margin-right: 3px; }
</style>
