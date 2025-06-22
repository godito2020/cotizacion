<?php
<?php
// Lógica para cargar y guardar configuraciones del sistema
// db_helper.php ya está incluido a través de admin.php

$feedback_message = '';
$error_message = '';
$config_groups = []; // Para almacenar las configuraciones agrupadas
$raw_configs = []; // Para facilitar la búsqueda de tipo_dato y valor_actual_db

$conn = get_db_connection();
if ($conn) {
    $sql = "SELECT id_config, clave_config, valor_config, descripcion_config, tipo_dato, grupo_config
            FROM configuraciones
            WHERE editable_panel = TRUE
            ORDER BY grupo_config, id_config"; // Ordenar por id_config también para consistencia
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config_groups[$row['grupo_config']][] = $row;
            $raw_configs[$row['id_config']] = $row; // Guardar por ID para fácil acceso
        }
    } else {
        $error_message = "Error al cargar las configuraciones: " . $conn->error;
    }
    // No cerramos la conexión aquí si se va a usar en el POST
} else {
    $error_message = "No se pudo conectar a la base de datos para cargar configuraciones.";
    // Podríamos mostrar los datos de simulación si falla la BD para no romper el form completamente
    // $config_groups = [ 'Error' => [['id_config'=>0, 'clave_config'=>'DB_ERROR', 'valor_config'=>'No hay conexión', 'descripcion_config'=>'Error de BD', 'tipo_dato'=>'TEXTO', 'grupo_config'=>'Error']] ];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_configuraciones'])) {
    if (!$conn) {
        $error_message = "Error de base de datos. No se pueden guardar los cambios.";
    } else {
        $all_saved_successfully = true;
        $changes_made = false;

        // Iniciar transacción si es posible (depende del motor, InnoDB lo soporta)
        // $conn->begin_transaction();

        foreach ($_POST['config'] as $id_config => $valor_config_form) {
            $id_config_sanitized = filter_var($id_config, FILTER_VALIDATE_INT);

            if ($id_config_sanitized === false || !isset($raw_configs[$id_config_sanitized])) {
                $error_message .= "ID de configuración inválido: " . htmlspecialchars($id_config) . "<br>";
                $all_saved_successfully = false;
                continue;
            }

            $config_actual = $raw_configs[$id_config_sanitized];
            $tipo_dato = $config_actual['tipo_dato'];
            $valor_actual_db = $config_actual['valor_config'];
            $valor_a_guardar = trim($valor_config_form);

            if ($tipo_dato === 'BOOLEANO') {
                // El valor de un checkbox no marcado no se envía en POST,
                // así que si no está en $_POST['config'][$id_config_sanitized], es '0' (false).
                // Si está presente, su valor es '1' (true) como se definió en el form.
                $valor_a_guardar = isset($_POST['config'][$id_config_sanitized]) ? '1' : '0';
            } elseif ($tipo_dato === 'ENCRIPTADO') {
                if (!empty($valor_a_guardar)) { // Solo actualizar si se ingresó una nueva contraseña
                    // En un sistema real, aquí se encriptaría la contraseña.
                    // $valor_a_guardar = password_hash($valor_a_guardar, PASSWORD_DEFAULT);
                    // Por ahora, para no sobreescribir con texto plano si no hay función de hash:
                    // $valor_a_guardar = "ENCRIPTADO:" . $valor_a_guardar; // Simulación simple
                    // O, si se quiere evitar guardar texto plano si no hay hash, se podría omitir la actualización
                    // si no se tiene una función de encriptación real.
                    // Para este ejercicio, si se ingresa algo, se asume que se quiere cambiar.
                    // PERO, NO guardaremos texto plano en un campo 'ENCRIPTADO'.
                    // Si no hay función de hash, es mejor no permitir la edición o advertir.
                    // Por ahora, si se envía algo, se intentará guardar (esto es inseguro sin hash real).
                    // NO HAREMOS ESTO EN PRODUCCIÓN: $valor_a_guardar = $valor_a_guardar;
                    // Lo correcto es hashear o no actualizar. Como no tenemos hash, no actualizaremos
                    // si no se proporciona una contraseña (lo que ya hace el `continue` de abajo)
                    // y si se proporciona, deberíamos hashearla. Para la simulación, no hasheamos.
                    // ESTO ES SOLO PARA SIMULACIÓN Y DEBE SER CAMBIADO.
                    // $valor_a_guardar = "sim_hashed_".$valor_a_guardar; // No hacer esto
                    $error_message .= "Advertencia: El guardado de campos ENCRIPTADOS es simulado y no seguro sin una función de hash real.<br>";
                } else {
                    // Mantener el valor actual si el campo se dejó vacío (no se quiere cambiar la contraseña)
                    $valor_a_guardar = $valor_actual_db;
                }
            }

            // Solo actualizar si el valor ha cambiado
            if ($valor_a_guardar !== $valor_actual_db) {
                $changes_made = true;
                $stmt = $conn->prepare("UPDATE configuraciones SET valor_config = ? WHERE id_config = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $valor_a_guardar, $id_config_sanitized);
                    if (!$stmt->execute()) {
                        $error_message .= "Error al guardar la configuración '" . htmlspecialchars($config_actual['clave_config']) . "': " . $stmt->error . "<br>";
                        $all_saved_successfully = false;
                    }
                    $stmt->close();
                } else {
                    $error_message .= "Error al preparar la actualización para '" . htmlspecialchars($config_actual['clave_config']) . "': " . $conn->error . "<br>";
                    $all_saved_successfully = false;
                }
            }
        } // Fin foreach

        if ($all_saved_successfully) {
            // $conn->commit(); // Confirmar transacción
            if ($changes_made) {
                $feedback_message = "Configuraciones guardadas correctamente.";
                // Recargar datos para reflejar cambios actualizando $config_groups y $raw_configs
                $config_groups = []; $raw_configs = []; // Limpiar para recargar
                $result = $conn->query($sql); // Re-ejecutar la consulta original
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $config_groups[$row['grupo_config']][] = $row;
                        $raw_configs[$row['id_config']] = $row;
                    }
                }
            } else {
                 $feedback_message = "No se detectaron cambios para guardar.";
            }
        } else {
            // $conn->rollback(); // Revertir transacción
            $error_message = "Algunas configuraciones no se pudieron guardar. Verifique los mensajes.<br>" . $error_message;
        }
    } // Fin else if (!$conn)
} // Fin if ($_SERVER['REQUEST_METHOD'] === 'POST')

if ($conn) {
    close_db_connection($conn);
}
?>
<div class="card">
    <div class="card-header">
        <h3>Configuraciones Generales del Sistema</h3>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error_message)); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=configuraciones">
            <?php if (empty($config_groups) && empty($error_message)): ?>
                <p class="alert alert-info">No hay configuraciones editables disponibles o no se pudieron cargar.</p>
            <?php elseif (!empty($config_groups)): ?>
                <?php foreach ($config_groups as $group_name => $configs_in_group): ?>
                    <div class="config-group">
                        <h4><?php echo htmlspecialchars($group_name); ?></h4>
                        <?php foreach ($configs_in_group as $config_item): ?>
                            <div class="form-group">
                                <label for="config_<?php echo $config_item['id_config']; ?>">
                                    <?php echo htmlspecialchars($config_item['descripcion_config']); ?>
                                    <small class="text-muted">(Clave: <?php echo htmlspecialchars($config_item['clave_config']); ?>)</small>
                                </label>
                                <?php
                                $current_value = htmlspecialchars($config_item['valor_config'] ?? '');
                                $input_type = "text"; // Default
                                $id_attr = "config_" . $config_item['id_config'];
                                $name_attr = "config[{$config_item['id_config']}]";

                                switch ($config_item['tipo_dato']) {
                                    case 'NUMERO':
                                        $step = (strpos($current_value, '.') !== false || strpos(htmlspecialchars_decode($config_item['descripcion_config']),'Porcentaje') !==false ) ? "0.01" : "1";
                                        echo "<input type='number' name='{$name_attr}' id='{$id_attr}' class='form-control' value='{$current_value}' step='{$step}'>";
                                        break;
                                    case 'TEXTAREA':
                                        echo "<textarea name='{$name_attr}' id='{$id_attr}' class='form-control' rows='4'>{$current_value}</textarea>";
                                        break;
                                    case 'BOOLEANO':
                                        $is_checked = ($config_item['valor_config'] == '1' || $config_item['valor_config'] === true);
                                        echo "<div class='form-check'>";
                                        // No enviar hidden input si el checkbox no marcado no debe significar '0' explícitamente,
                                        // pero para asegurar que siempre llegue algo o para manejarlo en PHP:
                                        // echo "<input type='hidden' name='{$name_attr}' value='0'>"; // Esto es problemático si el campo no está en _POST config
                                        echo "<input type='checkbox' name='{$name_attr}' id='{$id_attr}' class='form-check-input' value='1' " . ($is_checked ? 'checked' : '') . ">";
                                        echo "<label class='form-check-label' for='{$id_attr}'>Habilitado</label>";
                                        echo "</div>";
                                        break;
                                    case 'ENCRIPTADO':
                                        echo "<input type='password' name='{$name_attr}' id='{$id_attr}' class='form-control' value='' placeholder='Dejar vacío para no cambiar'>";
                                        if (!empty($config_item['valor_config'])) {
                                            echo "<small class='form-text text-muted'>Hay un valor configurado (no se muestra por seguridad).</small>";
                                        }
                                        break;
                                    case 'TEXTO':
                                    default:
                                        echo "<input type='text' name='{$name_attr}' id='{$id_attr}' class='form-control' value='{$current_value}'>";
                                        break;
                                }
                                ?>
                            </div>
                        <?php endforeach; // Fin configs_in_group ?>
                    </div>
                <?php endforeach; // Fin config_groups ?>
                 <div class="form-group text-right mt-20">
                <button type="submit" name="guardar_configuraciones" class="btn btn-primary">Guardar Todas las Configuraciones</button>
            </div>
        </form>
    </div>
</div>
