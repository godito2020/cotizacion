<?php
<?php
// Este archivo manejará la lógica para cargar y guardar los datos de la empresa.
// Ahora se usará db_helper.php para interactuar con la base de datos.

// Incluir helper de base de datos (ya disponible)
// La inclusión de db_helper.php se hace en admin.php, así que las funciones deberían estar disponibles.
// Si no, se puede añadir: require_once UTILS_PATH . '/db_helper.php';

$empresa_id = 1; // ID de la empresa principal (configurable o fijo)
$empresa_data = [];
$feedback_message = '';
$error_message = '';

// Carga de datos de la empresa desde la BD
$conn = get_db_connection();
if ($conn) {
    $stmt = $conn->prepare("SELECT * FROM empresas WHERE id_empresa = ?");
    if ($stmt) {
        $stmt->bind_param("i", $empresa_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $empresa_data = $result->fetch_assoc();
        } else {
            // Si no existe la empresa con ID 1 (ej. después de una instalación limpia sin ese INSERT específico)
            // $error_message = "No se encontraron datos para la empresa principal (ID: $empresa_id). Verifique la base de datos o la instalación.";
            // Podríamos intentar insertar una empresa por defecto aquí si es apropiado,
            // o simplemente usar valores vacíos para el formulario.
            $empresa_data = [
                'nombre_comercial' => '', 'razon_social' => '', 'ruc' => '', 'direccion' => '',
                'telefono' => '', 'email' => '', 'logo_url' => 'public/img/logo_default.png', 'sitio_web' => '',
                'terminos_cotizacion' => '', 'moneda_defecto' => 'PEN', 'igv_porcentaje' => 18.00
            ];
             // Verificar si la tabla 'empresas' está vacía y si es así, insertar la empresa por defecto
            $check_empty_stmt = $conn->query("SELECT COUNT(*) as count FROM empresas");
            $row_count = $check_empty_stmt->fetch_assoc()['count'];
            if ($row_count == 0) {
                $default_insert_sql = "INSERT INTO `empresas` (`id_empresa`, `nombre_comercial`, `razon_social`, `ruc`, `igv_porcentaje`, `logo_url`) VALUES (1, 'Mi Empresa (Pendiente Configurar)', 'Razón Social (Pendiente Configurar)', '00000000000', 18.00, 'public/img/logo_default.png')";
                if ($conn->query($default_insert_sql)) {
                    // Recargar datos después de la inserción
                    $stmt->execute(); // Re-ejecutar la consulta original
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $empresa_data = $result->fetch_assoc();
                    }
                } else {
                     $error_message = "Error al intentar crear la empresa por defecto: " . $conn->error;
                }
            } else if ($result->num_rows == 0 && $row_count > 0) {
                 $error_message = "No se encontraron datos para la empresa principal (ID: $empresa_id), pero existen otras empresas. Esto podría indicar un problema de configuración.";
            }

        }
        $stmt->close();
    } else {
        $error_message = "Error al preparar la consulta para cargar datos de la empresa: " . $conn->error;
    }
    // No cerramos la conexión aquí si se va a usar más abajo en el POST.
} else {
    $error_message = "No se pudo conectar a la base de datos.";
    // Simular datos para que el formulario no falle catastróficamente si no hay BD
    $empresa_data = [
        'nombre_comercial' => 'Error BD', 'razon_social' => '', 'ruc' => '', 'direccion' => '',
        'telefono' => '', 'email' => '', 'logo_url' => 'public/img/logo_default.png', 'sitio_web' => '',
        'terminos_cotizacion' => '', 'moneda_defecto' => 'PEN', 'igv_porcentaje' => 18.00
    ];
}


// Procesamiento del formulario al enviar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_empresa'])) {
    if (!$conn) { // Si la conexión falló arriba, no intentar procesar.
        $error_message = "Error de base de datos. No se pueden guardar los cambios.";
    } else {
    // Recoger datos del formulario
    $nombre_comercial = trim($_POST['nombre_comercial'] ?? '');
    $razon_social = trim($_POST['razon_social'] ?? '');
    $ruc = trim($_POST['ruc'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $sitio_web = filter_var(trim($_POST['sitio_web'] ?? ''), FILTER_SANITIZE_URL);
    $terminos_cotizacion = trim($_POST['terminos_cotizacion'] ?? '');
    $moneda_defecto = trim($_POST['moneda_defecto'] ?? 'PEN');
    $igv_porcentaje = filter_var($_POST['igv_porcentaje'] ?? 18.00, FILTER_VALIDATE_FLOAT);

    // Validación (expandir según necesidad)
    if (empty($nombre_comercial) || empty($ruc)) {
        $error_message = "El Nombre Comercial y el RUC son obligatorios.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "El formato del correo electrónico no es válido.";
    } elseif ($igv_porcentaje === false || $igv_porcentaje < 0 || $igv_porcentaje > 100) {
        $error_message = "El porcentaje de IGV no es válido.";
    } else {
        // Manejo de subida de logo
        $current_logo_url = $empresa_data['logo_url'] ?? 'public/img/logo_default.png'; // Logo actual

        if (isset($_FILES['logo_empresa']) && $_FILES['logo_empresa']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = ROOT_PATH . '/uploads/logos/'; // Usar ROOT_PATH para la ruta física
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $error_message = "Error: No se pudo crear el directorio de logos en " . $upload_dir;
                }
            }

            if (empty($error_message)) { // Continuar solo si se pudo crear el dir
                $filename_base = "logo_empresa_" . $empresa_id . "_" . time();
                $imageFileType = strtolower(pathinfo(basename($_FILES['logo_empresa']['name']), PATHINFO_EXTENSION));
                $filename = $filename_base . "." . $imageFileType;
                $target_file_path = $upload_dir . $filename;

                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

                $check = getimagesize($_FILES['logo_empresa']['tmp_name']);
                if ($check === false) {
                    $error_message = "El archivo subido no es una imagen válida.";
                } elseif ($_FILES['logo_empresa']['size'] > 2097152) { // 2MB
                    $error_message = "El logo no puede exceder los 2MB.";
                } elseif (!in_array($imageFileType, $allowed_types)) {
                    $error_message = "Solo se permiten logos en formato JPG, JPEG, PNG o GIF.";
                } else {
                    if (move_uploaded_file($_FILES['logo_empresa']['tmp_name'], $target_file_path)) {
                        // Eliminar logo anterior si existe, no es el default y está en uploads/logos/
                        if (!empty($empresa_data['logo_url']) &&
                            $empresa_data['logo_url'] !== 'public/img/logo_default.png' &&
                            strpos($empresa_data['logo_url'], 'uploads/logos/') === 0 &&
                            file_exists(ROOT_PATH . '/' . $empresa_data['logo_url'])) {
                           unlink(ROOT_PATH . '/' . $empresa_data['logo_url']);
                        }
                        $current_logo_url = 'uploads/logos/' . $filename; // Ruta relativa para la BD y URL
                    } else {
                        $error_message = "Hubo un error al subir el nuevo logo. Verifique permisos en " . $upload_dir;
                    }
                }
            }
        }

        if (empty($error_message)) {
            // Guardado en BD
            $update_stmt = $conn->prepare("UPDATE empresas SET
                nombre_comercial = ?, razon_social = ?, ruc = ?, direccion = ?, telefono = ?, email = ?,
                logo_url = ?, sitio_web = ?, terminos_cotizacion = ?, moneda_defecto = ?, igv_porcentaje = ?
                WHERE id_empresa = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("ssssssssssdi",
                    $nombre_comercial, $razon_social, $ruc, $direccion, $telefono, $email,
                    $current_logo_url, $sitio_web, $terminos_cotizacion, $moneda_defecto, $igv_porcentaje,
                    $empresa_id
                );
                if ($update_stmt->execute()) {
                    $feedback_message = "Datos de la empresa actualizados correctamente.";
                    // Recargar datos para mostrar los actualizados
                    $empresa_data = [
                        'nombre_comercial' => $nombre_comercial, 'razon_social' => $razon_social, 'ruc' => $ruc,
                        'direccion' => $direccion, 'telefono' => $telefono, 'email' => $email,
                        'logo_url' => $current_logo_url, 'sitio_web' => $sitio_web,
                        'terminos_cotizacion' => $terminos_cotizacion, 'moneda_defecto' => $moneda_defecto,
                        'igv_porcentaje' => $igv_porcentaje, 'id_empresa' => $empresa_id
                    ];
                } else {
                    $error_message = "Error al actualizar los datos: " . $update_stmt->error;
                }
                $update_stmt->close();
            } else {
                 $error_message = "Error al preparar la actualización de datos de empresa: " . $conn->error;
            }
        }
    } // Cierre del else de if (!$conn)
} // Cierre del if ($_SERVER['REQUEST_METHOD'] === 'POST')

// Determinar la URL del logo para mostrar
$logo_display_url = BASE_URL . ($empresa_data['logo_url'] ?? 'public/img/logo_default.png');
// Verificar si el archivo de logo existe físicamente
if (!empty($empresa_data['logo_url']) && $empresa_data['logo_url'] !== 'public/img/logo_default.png') {
    if (file_exists(ROOT_PATH . '/' . $empresa_data['logo_url'])) {
        $logo_display_url = BASE_URL . $empresa_data['logo_url'] . '?t=' . time(); // Cache buster
    } else {
        // Si el archivo no existe, pero hay una URL configurada (que no sea la default), mostrar advertencia y usar default.
        $error_message .= (empty($error_message) ? '' : '<br>') . "Advertencia: El archivo de logo configurado ('" . htmlspecialchars($empresa_data['logo_url']) . "') no se encuentra en el servidor. Se mostrará el logo por defecto.";
        $logo_display_url = BASE_URL . 'public/img/logo_default.png';
        // Opcionalmente, podrías intentar actualizar $empresa_data['logo_url'] en la BD al default aquí si es deseable.
    }
}


if ($conn) { // Cerrar la conexión si se abrió
    close_db_connection($conn);
}
?>

<div class="card">
    <div class="card-header">
        <h3>Datos de la Empresa</h3>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo nl2br(htmlspecialchars($error_message)); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=empresa" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nombre_comercial">Nombre Comercial / Marca:</label>
                <input type="text" id="nombre_comercial" name="nombre_comercial" class="form-control" value="<?php echo htmlspecialchars($empresa_data['nombre_comercial'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="razon_social">Razón Social:</label>
                <input type="text" id="razon_social" name="razon_social" class="form-control" value="<?php echo htmlspecialchars($empresa_data['razon_social'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="ruc">RUC (o Identificación Fiscal):</label>
                <input type="text" id="ruc" name="ruc" class="form-control" value="<?php echo htmlspecialchars($empresa_data['ruc'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="direccion">Dirección Fiscal / Principal:</label>
                <textarea id="direccion" name="direccion" class="form-control"><?php echo htmlspecialchars($empresa_data['direccion'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono de Contacto:</label>
                <input type="tel" id="telefono" name="telefono" class="form-control" value="<?php echo htmlspecialchars($empresa_data['telefono'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email de Contacto:</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($empresa_data['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="sitio_web">Sitio Web:</label>
                <input type="url" id="sitio_web" name="sitio_web" class="form-control" placeholder="https://www.ejemplo.com" value="<?php echo htmlspecialchars($empresa_data['sitio_web'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="logo_empresa">Logo de la Empresa:</label>
                <input type="file" id="logo_empresa" name="logo_empresa" class="form-control" accept="image/png, image/jpeg, image/gif">
                <small class="form-text text-muted">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB. Recomendado: Fondo transparente, ancho máx 400px.</small>
                <div class="logo-preview-container">
                    <p>Vista Previa del Logo Actual:</p>
                    <?php // $logo_display_url ya está preparada arriba para mostrar el logo correcto o el default ?>
                    <img src="<?php echo $logo_display_url; ?>" alt="Logo Actual" id="logoPreviewImg">
                    <?php if ($logo_display_url === BASE_URL . 'public/img/logo_default.png'): ?>
                        <p class="placeholder" id="logoPlaceholder">No hay logo personalizado o se usa el predeterminado.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="terminos_cotizacion">Términos y Condiciones por Defecto (para cotizaciones):</label>
                <textarea id="terminos_cotizacion" name="terminos_cotizacion" class="form-control" rows="5"><?php echo htmlspecialchars($empresa_data['terminos_cotizacion'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="moneda_defecto">Moneda por Defecto:</label>
                <select id="moneda_defecto" name="moneda_defecto" class="form-control">
                    <option value="PEN" <?php echo (($empresa_data['moneda_defecto'] ?? 'PEN') == 'PEN') ? 'selected' : ''; ?>>Nuevo Sol (PEN)</option>
                    <option value="USD" <?php echo (($empresa_data['moneda_defecto'] ?? '') == 'USD') ? 'selected' : ''; ?>>Dólar Americano (USD)</option>
                    <!-- Agregar más monedas si es necesario -->
                </select>
            </div>

            <div class="form-group">
                <label for="igv_porcentaje">Porcentaje de IGV/IVA (%):</label>
                <input type="number" step="0.01" id="igv_porcentaje" name="igv_porcentaje" class="form-control" value="<?php echo htmlspecialchars($empresa_data['igv_porcentaje'] ?? '18.00'); ?>" required>
            </div>

            <div class="form-group text-right">
                <button type="submit" name="guardar_empresa" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>
