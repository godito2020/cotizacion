<?php
// app/views/admin/usuario_form.php

// Proteger esta página: solo administradores
if (!is_admin()) {
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección requiere privilegios de administrador.</div>';
    return;
}

$is_editing = false;
$usuario_id = null;
$usuario_data = [ // Valores por defecto para un nuevo usuario
    'nombre_completo' => '',
    'email' => '',
    'rol' => 'vendedor', // Rol por defecto
    'activo' => 1, // Activo por defecto
];
$page_form_title = 'Crear Nuevo Usuario';

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $usuario_id = (int)$_GET['id'];
    $page_form_title = 'Editar Usuario';

    $conn = get_db_connection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT nombre_completo, email, rol, activo FROM usuarios WHERE id_usuario = ?");
        if ($stmt) {
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $usuario_data = $result->fetch_assoc();
            } else {
                $_SESSION['error_message'] = "Usuario no encontrado para editar (ID: $usuario_id).";
                header("Location: " . BASE_URL . "admin.php?page=usuarios");
                exit;
            }
            $stmt->close();
        } else {
             $error_message_form = "Error al preparar consulta para cargar usuario: " . $conn->error;
        }
        // No cerrar $conn aquí, se usará si es POST
    } else {
        $error_message_form = "Error de conexión a la base de datos.";
    }
}


$feedback_message_form = '';
$error_message_form = $error_message_form ?? ''; // Mantener error de carga si existe

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_usuario'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message_form = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $rol = $_POST['rol'] ?? 'vendedor';
        $activo = isset($_POST['activo']) ? 1 : 0;
        $password = $_POST['password'] ?? ''; // No trimear
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validaciones
        if (empty($nombre_completo) || empty($email) || empty($rol)) {
            $error_message_form .= "Nombre completo, email y rol son obligatorios.<br>";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message_form .= "El formato del correo electrónico no es válido.<br>";
        }
        if (!in_array($rol, ['admin', 'vendedor', 'almacenista'])) {
            $error_message_form .= "Rol de usuario no válido.<br>";
        }

        // Validaciones de contraseña (solo si se está creando o si se ingresó una nueva contraseña para editar)
        if (!$is_editing || ($is_editing && !empty($password))) {
            if (empty($password) || strlen($password) < 6) { // Mínimo 6 caracteres
                $error_message_form .= "La contraseña es obligatoria y debe tener al menos 6 caracteres.<br>";
            }
            if ($password !== $password_confirm) {
                $error_message_form .= "Las contraseñas no coinciden.<br>";
            }
        }

        // Verificar unicidad de email (excepto para el usuario actual si se está editando)
        if (empty($error_message_form)) {
            if (!$conn) $conn = get_db_connection(); // Reabrir si se cerró o falló antes
            if ($conn) {
                $sql_check_email = "SELECT id_usuario FROM usuarios WHERE email = ?";
                $params_check_email = [$email];
                if ($is_editing) {
                    $sql_check_email .= " AND id_usuario != ?";
                    $params_check_email[] = $usuario_id;
                }
                $stmt_check = $conn->prepare($sql_check_email);
                if ($stmt_check) {
                    $types = str_repeat('s', count($params_check_email) - ($is_editing ? 1:0)) . ($is_editing ? 'i' : '');
                    $stmt_check->bind_param($types, ...$params_check_email);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    if ($result_check->num_rows > 0) {
                        $error_message_form .= "El correo electrónico '$email' ya está en uso por otro usuario.<br>";
                    }
                    $stmt_check->close();
                } else {
                    $error_message_form .= "Error al verificar email: " . $conn->error . "<br>";
                }
            } else {
                 $error_message_form .= "Error de conexión para verificar email.<br>";
            }
        }


        if (empty($error_message_form)) {
            if (!$conn) $conn = get_db_connection();
            if ($conn) {
                if ($is_editing) {
                    // Actualizar usuario
                    if (!empty($password)) {
                        $hashed_password = hash_password($password);
                        $stmt_update = $conn->prepare("UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, activo = ?, password_hash = ? WHERE id_usuario = ?");
                        $stmt_update->bind_param("sssisi", $nombre_completo, $email, $rol, $activo, $hashed_password, $usuario_id);
                    } else {
                        // No actualizar contraseña si el campo está vacío
                        $stmt_update = $conn->prepare("UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ?, activo = ? WHERE id_usuario = ?");
                        $stmt_update->bind_param("sssii", $nombre_completo, $email, $rol, $activo, $usuario_id);
                    }

                    if ($stmt_update && $stmt_update->execute()) {
                        $_SESSION['feedback_message'] = "Usuario actualizado correctamente.";
                        if ($usuario_id == get_current_user_id()) { // Si el admin edita su propia cuenta
                             $_SESSION['user_name'] = $nombre_completo; // Actualizar nombre en sesión
                             $_SESSION['user_email'] = $email; // Actualizar email en sesión
                             // No se actualiza el rol en sesión por si se degrada a sí mismo, para evitar bloqueo inmediato.
                        }
                        header("Location: " . BASE_URL . "admin.php?page=usuarios");
                        exit;
                    } else {
                        $error_message_form = "Error al actualizar usuario: " . ($stmt_update ? $stmt_update->error : $conn->error);
                    }
                    if ($stmt_update) $stmt_update->close();

                } else {
                    // Crear nuevo usuario
                    $hashed_password = hash_password($password);
                    $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre_completo, email, password_hash, rol, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, NOW())");
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssssi", $nombre_completo, $email, $hashed_password, $rol, $activo);
                        if ($stmt_insert->execute()) {
                            $_SESSION['feedback_message'] = "Usuario creado correctamente.";
                            header("Location: " . BASE_URL . "admin.php?page=usuarios");
                            exit;
                        } else {
                            $error_message_form = "Error al crear usuario: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                         $error_message_form = "Error al preparar la inserción: " . $conn->error;
                    }
                }
            } else {
                $error_message_form = "Error de conexión al intentar guardar el usuario.";
            }
        }
        // Si hay errores, actualizar $usuario_data para repoblar el formulario
        $usuario_data['nombre_completo'] = $nombre_completo;
        $usuario_data['email'] = $email; // Ya sanitizado
        $usuario_data['rol'] = $rol;
        $usuario_data['activo'] = $activo;
    } // Fin else CSRF
} // Fin if POST

if ($conn) {
    close_db_connection($conn);
}
$csrf_token = generate_csrf_token();
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo $page_form_title; ?></h3>
    </div>
    <div class="card-body">
        <?php if ($feedback_message_form): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message_form); ?></div>
        <?php endif; ?>
        <?php if ($error_message_form): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error_message_form)); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=<?php echo $is_editing ? 'usuario_editar&id=' . $usuario_id : 'usuario_crear'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label for="nombre_completo">Nombre Completo:</label>
                <input type="text" id="nombre_completo" name="nombre_completo" class="form-control" value="<?php echo htmlspecialchars($usuario_data['nombre_completo']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario_data['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" class="form-control" <?php echo !$is_editing ? 'required' : ''; ?> autocomplete="new-password">
                <?php if ($is_editing): ?>
                    <small class="form-text text-muted">Dejar en blanco si no desea cambiar la contraseña.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmar Contraseña:</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" <?php echo !$is_editing ? 'required' : ''; ?>>
            </div>

            <div class="form-group">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" class="form-control" required>
                    <option value="vendedor" <?php echo ($usuario_data['rol'] == 'vendedor') ? 'selected' : ''; ?>>Vendedor</option>
                    <option value="almacenista" <?php echo ($usuario_data['rol'] == 'almacenista') ? 'selected' : ''; ?>>Almacenista</option>
                    <option value="admin" <?php echo ($usuario_data['rol'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>

            <?php
            // No permitir desactivar o cambiar de rol al usuario actual si es el único administrador
            $is_current_user_self = ($is_editing && $usuario_id == get_current_user_id());
            $is_only_admin = false;
            if ($is_current_user_self && $usuario_data['rol'] == 'admin') {
                $conn_check_admin = get_db_connection();
                if ($conn_check_admin) {
                    $stmt_count = $conn_check_admin->query("SELECT COUNT(*) as count FROM usuarios WHERE rol = 'admin' AND activo = TRUE");
                    $admin_count = $stmt_count->fetch_assoc()['count'];
                    if ($admin_count <= 1) {
                        $is_only_admin = true;
                    }
                    close_db_connection($conn_check_admin);
                }
            }
            ?>

            <?php if ($is_only_admin && $is_current_user_self): ?>
                 <div class="form-group">
                    <label>Estado:</label>
                    <input type="hidden" name="activo" value="1"> <!-- Forzar activo -->
                    <p class="form-control-static"><span class="badge badge-success">Activo</span> (No se puede desactivar al único administrador)</p>
                </div>
                <div class="form-group">
                    <label>Rol:</label>
                     <input type="hidden" name="rol" value="admin"> <!-- Forzar rol admin -->
                    <p class="form-control-static"><span class="badge badge-info">Administrador</span> (No se puede cambiar el rol al único administrador)</p>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" id="activo" name="activo" class="form-check-input" value="1" <?php echo ($usuario_data['activo'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="activo">Usuario Activo</label>
                    </div>
                </div>
            <?php endif; ?>


            <div class="form-group text-right">
                <a href="<?php echo BASE_URL; ?>admin.php?page=usuarios" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="guardar_usuario" class="btn btn-primary">
                    <?php echo $is_editing ? 'Actualizar Usuario' : 'Crear Usuario'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<style> /* Estilos rápidos para badge-info si no está en admin_styles.css */
.badge-info { color: #fff; background-color: #17a2b8; }
.form-control-static { padding-top: calc(.375rem + 1px); padding-bottom: calc(.375rem + 1px); margin-bottom: 0; line-height: 1.5; border: 1px solid transparent; }
</style>
