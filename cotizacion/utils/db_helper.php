<?php
// Archivo de utilidades para la base de datos

/**
 * Establece una conexión con la base de datos usando MySQLi.
 * Las constantes DB_HOST, DB_USER, DB_PASS, DB_NAME deben estar definidas en config.php
 *
 * @return mysqli|false Retorna un objeto mysqli en caso de éxito, o false en caso de error.
 */
function get_db_connection() {
    // Asegurarse de que config.php se haya incluido y las constantes estén disponibles
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
        // Log error o mostrar un mensaje amigable, pero no revelar detalles de config.
        error_log("Error de conexión a BD: Constantes de BD no definidas.");
        // En un entorno de producción, no deberías mostrar errores detallados al usuario.
        // Considera lanzar una excepción o retornar un error específico que la app pueda manejar.
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            die("Error crítico: Las constantes de configuración de la base de datos no están definidas. Verifique su archivo config.php.");
        }
        return false;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("Error de conexión a la base de datos: " . $conn->connect_error);
        if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
            die("Error de conexión a la base de datos: " . $conn->connect_error);
        }
        return false;
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Cierra una conexión de base de datos MySQLi.
 *
 * @param mysqli $conn El objeto de conexión MySQLi.
 */
function close_db_connection($conn) {
    if ($conn) {
        $conn->close();
    }
}

/**
 * Obtiene una configuración específica de la tabla `configuraciones`.
 *
 * @param string $clave_config La clave de la configuración a obtener.
 * @param mysqli|null $db_conn Una conexión opcional a la BD. Si es null, se creará una nueva.
 * @return string|null El valor de la configuración o null si no se encuentra o hay error.
 */
function get_config_value($clave_config, $db_conn = null) {
    $should_close_conn = false;
    if ($db_conn === null) {
        $db_conn = get_db_connection();
        if (!$db_conn) return null;
        $should_close_conn = true;
    }

    $stmt = $db_conn->prepare("SELECT valor_config FROM configuraciones WHERE clave_config = ?");
    if (!$stmt) {
        error_log("Error al preparar consulta para obtener config: " . $db_conn->error);
        if ($should_close_conn) close_db_connection($db_conn);
        return null;
    }
    $stmt->bind_param("s", $clave_config);
    $stmt->execute();
    $result = $stmt->get_result();

    $value = null;
    if ($row = $result->fetch_assoc()) {
        $value = $row['valor_config'];
    }

    $stmt->close();
    if ($should_close_conn) {
        close_db_connection($db_conn);
    }
    return $value;
}

/**
 * Actualiza una configuración específica en la tabla `configuraciones`.
 *
 * @param string $clave_config La clave de la configuración a actualizar.
 * @param string $valor_config El nuevo valor para la configuración.
 * @param mysqli|null $db_conn Una conexión opcional a la BD. Si es null, se creará una nueva.
 * @return bool True si la actualización fue exitosa, false en caso contrario.
 */
function update_config_value($clave_config, $valor_config, $db_conn = null) {
    $should_close_conn = false;
    if ($db_conn === null) {
        $db_conn = get_db_connection();
        if (!$db_conn) return false;
        $should_close_conn = true;
    }

    $stmt = $db_conn->prepare("UPDATE configuraciones SET valor_config = ? WHERE clave_config = ?");
     if (!$stmt) {
        error_log("Error al preparar consulta para actualizar config: " . $db_conn->error);
        if ($should_close_conn) close_db_connection($db_conn);
        return false;
    }
    $stmt->bind_param("ss", $valor_config, $clave_config);
    $success = $stmt->execute();

    if (!$success) {
        error_log("Error al actualizar config '$clave_config': " . $stmt->error);
    }

    $stmt->close();
    if ($should_close_conn) {
        close_db_connection($db_conn);
    }
    return $success;
}

/**
 * Escapa una cadena para usarla de forma segura en consultas SQL (mejor usar sentencias preparadas).
 * Esta función es un wrapper para mysqli_real_escape_string.
 * ¡ADVERTENCIA! Es MUCHO MEJOR usar sentencias preparadas para prevenir inyección SQL.
 * Usar solo si es absolutamente necesario y con pleno conocimiento de los riesgos.
 *
 * @param mysqli $conn El objeto de conexión MySQLi.
 * @param string $string La cadena a escapar.
 * @return string La cadena escapada.
 */
function escape_string_for_sql($conn, $string) {
    if (!$conn) {
        // No se puede escapar sin conexión, podría ser un riesgo de seguridad
        // o simplemente devolver la cadena original con una advertencia.
        error_log("Intento de escapar cadena SQL sin conexión a BD válida.");
        return $string; // Devolver original puede ser peligroso. Considerar lanzar error.
    }
    return $conn->real_escape_string($string);
}

// Podrías añadir más funciones de ayuda aquí, como:
// - fetch_all_rows($sql, $params = [], $types = "")
// - fetch_single_row($sql, $params = [], $types = "")
// - execute_query($sql, $params = [], $types = "")
// - get_last_insert_id($conn)

?>
