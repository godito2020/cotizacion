<?php
// auth_helper.php - Funciones de autenticación y gestión de sesión

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Asegurarse que db_helper está disponible, ya que algunas funciones lo pueden necesitar
require_once __DIR__ . '/db_helper.php'; // Usar __DIR__ para rutas relativas fiables

/**
 * Hashea una contraseña usando el algoritmo por defecto de PHP.
 * @param string $password La contraseña en texto plano.
 * @return string|false El hash de la contraseña o false si falla.
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica una contraseña contra un hash.
 * @param string $password La contraseña en texto plano.
 * @param string $hashed_password El hash contra el cual verificar.
 * @return bool True si la contraseña coincide, false en caso contrario.
 */
function verify_password($password, $hashed_password) {
    return password_verify($password, $hashed_password);
}

/**
 * Intenta loguear a un usuario.
 * @param string $email El email del usuario.
 * @param string $password La contraseña del usuario.
 * @return bool True en login exitoso, false en caso contrario.
 */
function login_user($email, $password) {
    $conn = get_db_connection();
    if (!$conn) {
        $_SESSION['login_error'] = "Error de conexión a la base de datos.";
        return false;
    }

    $stmt = $conn->prepare("SELECT id_usuario, nombre_completo, email, password_hash, rol, activo FROM usuarios WHERE email = ? LIMIT 1");
    if (!$stmt) {
        $_SESSION['login_error'] = "Error al preparar la consulta: " . $conn->error;
        close_db_connection($conn);
        return false;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['activo'] == 1 && verify_password($password, $user['password_hash'])) {
            // Login exitoso
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['nombre_completo'];
            $_SESSION['user_role'] = $user['rol'];
            $_SESSION['logged_in'] = true;

            // Actualizar último login (opcional)
            $update_stmt = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("i", $user['id_usuario']);
                $update_stmt->execute();
                $update_stmt->close();
            }

            $stmt->close();
            close_db_connection($conn);
            unset($_SESSION['login_error']);
            return true;
        } else if ($user['activo'] != 1) {
            $_SESSION['login_error'] = "Su cuenta está desactivada. Contacte al administrador.";
        } else {
            $_SESSION['login_error'] = "Correo electrónico o contraseña incorrectos.";
        }
    } else {
        $_SESSION['login_error'] = "Correo electrónico o contraseña incorrectos.";
    }

    $stmt->close();
    close_db_connection($conn);
    return false;
}

/**
 * Cierra la sesión del usuario.
 */
function logout_user() {
    $_SESSION = array(); // Limpiar todas las variables de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Verifica si hay un usuario logueado.
 * @return bool True si está logueado, false en caso contrario.
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
}

/**
 * Verifica si el usuario está logueado y, si no, redirige a la página de login.
 * @param string $redirect_url La URL a la que redirigir si no está logueado.
 */
function check_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        // Guardar la URL solicitada para redirigir después del login (opcional)
        // $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

        // Asegurarse que BASE_URL esté disponible si $redirect_url es relativa a la raíz
        if (defined('BASE_URL') && strpos($redirect_url, 'http') !== 0) {
            $final_redirect_url = BASE_URL . $redirect_url;
        } else {
            $final_redirect_url = $redirect_url; // Asumir URL completa o relativa al script actual
        }
        header("Location: " . $final_redirect_url);
        exit;
    }
}

/**
 * Verifica si el usuario logueado tiene el rol de administrador.
 * @return bool True si es admin, false en caso contrario.
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Obtiene el ID del usuario actualmente logueado.
 * @return int|null El ID del usuario o null si no está logueado.
 */
function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtiene el nombre completo del usuario actualmente logueado.
 * @return string|null El nombre del usuario o null si no está logueado.
 */
function get_current_user_name() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Obtiene el rol del usuario actualmente logueado.
 * @return string|null El rol del usuario o null si no está logueado.
 */
function get_current_user_role() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Obtiene el email del usuario actualmente logueado.
 * @return string|null El email del usuario o null si no está logueado.
 */
function get_current_user_email() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Genera un token CSRF y lo guarda en sesión.
 * @return string El token CSRF generado.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF contra el que está en sesión.
 * @param string $token El token enviado desde el formulario.
 * @return bool True si el token es válido, false en caso contrario.
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Otras funciones de ayuda relacionadas con la autenticación o permisos podrían ir aquí.
?>
