<?php
// app/views/admin/cliente_form.php

$is_editing = false;
$cliente_id = null;
$cliente_data = [ // Valores por defecto para un nuevo cliente
    'tipo_documento' => 'RUC',
    'numero_documento' => '',
    'nombre_razon_social' => '',
    'nombre_comercial' => '',
    'direccion' => '',
    'direccion_fiscal' => '',
    'email' => '',
    'telefono' => '',
    'contacto_principal' => '',
    'notas' => '',
    'activo' => 1,
    'origen_datos' => 'MANUAL'
];
$page_form_title = 'Crear Nuevo Cliente';
$error_message_form = ''; // Para errores específicos del formulario
$feedback_message_form = '';


// Carga de datos para edición
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $is_editing = true;
    $cliente_id = (int)$_GET['id'];
    $page_form_title = 'Editar Cliente';

    $conn_load = get_db_connection();
    if ($conn_load) {
        $stmt_load = $conn_load->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
        if ($stmt_load) {
            $stmt_load->bind_param("i", $cliente_id);
            $stmt_load->execute();
            $result_load = $stmt_load->get_result();
            if ($result_load->num_rows === 1) {
                $cliente_data = array_merge($cliente_data, $result_load->fetch_assoc()); // Merge para mantener defaults si algún campo es NULL
            } else {
                $_SESSION['error_message'] = "Cliente no encontrado para editar (ID: $cliente_id).";
                header("Location: " . BASE_URL . "admin.php?page=clientes");
                exit;
            }
            $stmt_load->close();
        } else {
            $error_message_form = "Error al preparar consulta para cargar cliente: " . $conn_load->error;
        }
        close_db_connection($conn_load); // Cerrar conexión después de cargar
    } else {
        $error_message_form = "Error de conexión a la base de datos al cargar cliente.";
    }
}


// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cliente'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message_form = "Error de validación CSRF. Inténtelo de nuevo.";
    } else {
        // Recoger y sanitizar datos
        $tipo_documento = trim($_POST['tipo_documento'] ?? 'RUC');
        $numero_documento = trim($_POST['numero_documento'] ?? '');
        $nombre_razon_social = trim($_POST['nombre_razon_social'] ?? '');
        $nombre_comercial = trim($_POST['nombre_comercial'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $direccion_fiscal = trim($_POST['direccion_fiscal'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $telefono = trim($_POST['telefono'] ?? '');
        $contacto_principal = trim($_POST['contacto_principal'] ?? '');
        $notas = trim($_POST['notas'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
        $origen_datos = trim($_POST['origen_datos'] ?? 'MANUAL'); // Podría venir de una consulta API

        // Actualizar $cliente_data para repoblar formulario en caso de error
        $cliente_data = array_merge($cliente_data, $_POST);
        $cliente_data['activo'] = $activo; // Asegurar que el checkbox se refleje bien

        // Validaciones
        if (empty($tipo_documento) || empty($numero_documento) || empty($nombre_razon_social)) {
            $error_message_form .= "Tipo de documento, número de documento y nombre/razón social son obligatorios.<br>";
        }
        if (!in_array($tipo_documento, ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO'])) {
             $error_message_form .= "Tipo de documento no válido.<br>";
        }
        if ($tipo_documento === 'DNI' && (strlen($numero_documento) != 8 || !ctype_digit($numero_documento))) {
            $error_message_form .= "DNI debe tener 8 dígitos numéricos.<br>";
        }
        if ($tipo_documento === 'RUC' && (strlen($numero_documento) != 11 || !ctype_digit($numero_documento))) {
            $error_message_form .= "RUC debe tener 11 dígitos numéricos.<br>";
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message_form .= "El formato del correo electrónico no es válido.<br>";
        }

        // Verificar unicidad de tipo_documento + numero_documento
        if (empty($error_message_form)) {
            $conn_check = get_db_connection();
            if ($conn_check) {
                $sql_check_doc = "SELECT id_cliente FROM clientes WHERE tipo_documento = ? AND numero_documento = ?";
                $params_check_doc = [$tipo_documento, $numero_documento];
                if ($is_editing) {
                    $sql_check_doc .= " AND id_cliente != ?";
                    $params_check_doc[] = $cliente_id;
                }
                $stmt_check_doc = $conn_check->prepare($sql_check_doc);
                if ($stmt_check_doc) {
                    $types_check_doc = "ss" . ($is_editing ? "i" : "");
                    $stmt_check_doc->bind_param($types_check_doc, ...$params_check_doc);
                    $stmt_check_doc->execute();
                    $result_check_doc = $stmt_check_doc->get_result();
                    if ($result_check_doc->num_rows > 0) {
                        $error_message_form .= "Ya existe un cliente con el mismo tipo y número de documento.<br>";
                    }
                    $stmt_check_doc->close();
                } else {
                    $error_message_form .= "Error al verificar documento: " . $conn_check->error . "<br>";
                }
                // No cerrar $conn_check aquí, se usará para guardar si no hay errores
            } else {
                $error_message_form .= "Error de conexión para verificar documento.<br>";
            }
        }

        if (empty($error_message_form)) {
            $conn_save = $conn_check ?? get_db_connection(); // Usar conexión existente o crear una nueva
            if ($conn_save) {
                if ($is_editing) {
                    $sql_save = "UPDATE clientes SET tipo_documento=?, numero_documento=?, nombre_razon_social=?, nombre_comercial=?, direccion=?, direccion_fiscal=?, email=?, telefono=?, contacto_principal=?, notas=?, activo=?, origen_datos=? WHERE id_cliente=?";
                    $stmt_save = $conn_save->prepare($sql_save);
                    $stmt_save->bind_param("ssssssssssisi", $tipo_documento, $numero_documento, $nombre_razon_social, $nombre_comercial, $direccion, $direccion_fiscal, $email, $telefono, $contacto_principal, $notas, $activo, $origen_datos, $cliente_id);
                } else {
                    $sql_save = "INSERT INTO clientes (tipo_documento, numero_documento, nombre_razon_social, nombre_comercial, direccion, direccion_fiscal, email, telefono, contacto_principal, notas, activo, origen_datos, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt_save = $conn_save->prepare($sql_save);
                    $stmt_save->bind_param("ssssssssssis", $tipo_documento, $numero_documento, $nombre_razon_social, $nombre_comercial, $direccion, $direccion_fiscal, $email, $telefono, $contacto_principal, $notas, $activo, $origen_datos);
                }

                if ($stmt_save && $stmt_save->execute()) {
                    $_SESSION['feedback_message'] = "Cliente " . ($is_editing ? "actualizado" : "creado") . " correctamente.";
                    header("Location: " . BASE_URL . "admin.php?page=clientes");
                    exit;
                } else {
                    $error_message_form = "Error al guardar cliente: " . ($stmt_save ? $stmt_save->error : $conn_save->error);
                }
                if ($stmt_save) $stmt_save->close();
                if ($conn_save === $conn_check) $conn_check = null; // Evitar doble cierre si es la misma conexión
                close_db_connection($conn_save);
            } else {
                 $error_message_form = "Error de conexión al intentar guardar el cliente.";
            }
        }
        if ($conn_check) close_db_connection($conn_check); // Cerrar si se usó para check y no para guardar
    } // Fin else CSRF
} // Fin if POST

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

        <form id="clienteForm" method="POST" action="<?php echo BASE_URL; ?>admin.php?page=<?php echo $is_editing ? 'cliente_editar&id=' . $cliente_id : 'cliente_crear'; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="origen_datos" id="origen_datos" value="<?php echo htmlspecialchars($cliente_data['origen_datos']); ?>">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="tipo_documento">Tipo de Documento:</label>
                        <select id="tipo_documento" name="tipo_documento" class="form-control" required>
                            <option value="RUC" <?php echo ($cliente_data['tipo_documento'] == 'RUC') ? 'selected' : ''; ?>>RUC (Registro Único de Contribuyentes)</option>
                            <option value="DNI" <?php echo ($cliente_data['tipo_documento'] == 'DNI') ? 'selected' : ''; ?>>DNI (Documento Nacional de Identidad)</option>
                            <option value="CE" <?php echo ($cliente_data['tipo_documento'] == 'CE') ? 'selected' : ''; ?>>CE (Carnet de Extranjería)</option>
                            <option value="PASAPORTE" <?php echo ($cliente_data['tipo_documento'] == 'PASAPORTE') ? 'selected' : ''; ?>>Pasaporte</option>
                            <option value="OTRO" <?php echo ($cliente_data['tipo_documento'] == 'OTRO') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="numero_documento">Número de Documento:</label>
                        <div class="input-group">
                            <input type="text" id="numero_documento" name="numero_documento" class="form-control" value="<?php echo htmlspecialchars($cliente_data['numero_documento']); ?>" required>
                            <div class="input-group-append">
                                <button type="button" id="btnConsultarDoc" class="btn btn-info" title="Consultar Documento (SUNAT/RENIEC)">
                                    <i class="fas fa-search"></i> Consultar
                                </button>
                            </div>
                        </div>
                         <small id="docHelp" class="form-text text-muted">Para RUC (11 dígitos) o DNI (8 dígitos). La consulta a SUNAT/RENIEC es una funcionalidad futura.</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="nombre_razon_social">Nombre / Razón Social:</label>
                <input type="text" id="nombre_razon_social" name="nombre_razon_social" class="form-control" value="<?php echo htmlspecialchars($cliente_data['nombre_razon_social']); ?>" required>
            </div>

            <div class="form-group">
                <label for="nombre_comercial">Nombre Comercial (Opcional):</label>
                <input type="text" id="nombre_comercial" name="nombre_comercial" class="form-control" value="<?php echo htmlspecialchars($cliente_data['nombre_comercial']); ?>">
            </div>

            <div class="form-group">
                <label for="direccion">Dirección (Principal / Comercial):</label>
                <textarea id="direccion" name="direccion" class="form-control" rows="2"><?php echo htmlspecialchars($cliente_data['direccion']); ?></textarea>
            </div>

            <div class="form-group" id="group_direccion_fiscal" style="<?php echo ($cliente_data['tipo_documento'] == 'RUC') ? '' : 'display:none;'; ?>">
                <label for="direccion_fiscal">Dirección Fiscal (Para RUC, si es diferente):</label>
                <textarea id="direccion_fiscal" name="direccion_fiscal" class="form-control" rows="2"><?php echo htmlspecialchars($cliente_data['direccion_fiscal']); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="email">Correo Electrónico:</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($cliente_data['email']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="telefono">Teléfono:</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente_data['telefono']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="contacto_principal">Persona de Contacto (Opcional):</label>
                <input type="text" id="contacto_principal" name="contacto_principal" class="form-control" value="<?php echo htmlspecialchars($cliente_data['contacto_principal']); ?>">
            </div>

            <div class="form-group">
                <label for="notas">Notas Adicionales (Uso interno):</label>
                <textarea id="notas" name="notas" class="form-control" rows="3"><?php echo htmlspecialchars($cliente_data['notas']); ?></textarea>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" id="activo" name="activo" class="form-check-input" value="1" <?php echo ($cliente_data['activo'] == 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="activo">Cliente Activo</label>
                </div>
            </div>

            <div class="form-group text-right">
                <a href="<?php echo BASE_URL; ?>admin.php?page=clientes" class="btn btn-secondary">Cancelar</a>
                <button type="submit" name="guardar_cliente" class="btn btn-primary">
                    <?php echo $is_editing ? 'Actualizar Cliente' : 'Crear Cliente'; ?>
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Iconos y estilos si es necesario -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
.input-group { display: flex; }
.input-group .form-control { flex: 1 1 auto; }
.input-group-append { display: flex; }
.input-group-append .btn { border-top-left-radius: 0; border-bottom-left-radius: 0; }
.row { display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; }
.col-md-6 { position: relative; width: 100%; padding-right: 15px; padding-left: 15px; flex: 0 0 50%; max-width: 50%; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoDocumentoSelect = document.getElementById('tipo_documento');
    const numeroDocumentoInput = document.getElementById('numero_documento');
    const nombreRazonSocialInput = document.getElementById('nombre_razon_social');
    const nombreComercialInput = document.getElementById('nombre_comercial');
    const direccionInput = document.getElementById('direccion');
    const direccionFiscalInput = document.getElementById('direccion_fiscal');
    const groupDireccionFiscal = document.getElementById('group_direccion_fiscal');
    const origenDatosInput = document.getElementById('origen_datos');
    const btnConsultarDoc = document.getElementById('btnConsultarDoc');

    function toggleDireccionFiscal() {
        if (tipoDocumentoSelect.value === 'RUC') {
            groupDireccionFiscal.style.display = '';
        } else {
            groupDireccionFiscal.style.display = 'none';
            // direccionFiscalInput.value = ''; // Opcional: limpiar si se cambia de RUC a otro
        }
    }
    tipoDocumentoSelect.addEventListener('change', toggleDireccionFiscal);
    toggleDireccionFiscal(); // Llamar al inicio por si se carga en edición

    btnConsultarDoc.addEventListener('click', function() {
        const tipoDoc = tipoDocumentoSelect.value;
        const numDoc = numeroDocumentoInput.value.trim();

        if (!numDoc) {
            alert('Por favor, ingrese un número de documento.');
            numeroDocumentoInput.focus();
            return;
        }

        alert('La consulta a SUNAT/RENIEC es una funcionalidad futura y requiere configuración de API.\nPor ahora, esta función es un placeholder.');

        // Aquí iría la lógica de la llamada AJAX a un backend que consulte la API
        // Ejemplo de cómo se podría manejar (simulado):
        /*
        fetch(BASE_URL + 'ajax_handler.php?action=consultar_documento&tipo=' + tipoDoc + '&numero=' + numDoc)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    nombreRazonSocialInput.value = data.nombre_razon_social || '';
                    nombreComercialInput.value = data.nombre_comercial || '';
                    direccionInput.value = data.direccion || ''; // O la dirección principal
                    direccionFiscalInput.value = data.direccion_fiscal || data.direccion || ''; // Para RUC
                    origenDatosInput.value = tipoDoc === 'RUC' ? 'SUNAT' : 'RENIEC';
                    // Otros campos...
                    alert('Datos obtenidos (simulación).');
                } else {
                    alert('Error: ' . (data.message || 'No se pudo consultar el documento.'));
                }
            })
            .catch(error => {
                console.error('Error en consulta:', error);
                alert('Ocurrió un error al intentar consultar el documento.');
            });
        */
    });
});
</script>
