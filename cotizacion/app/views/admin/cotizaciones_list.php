<?php
// app/views/admin/cotizaciones_list.php

$feedback_message = $_SESSION['feedback_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['error_message']);

$conn = get_db_connection();
$cotizaciones = [];

// TODO: Implementar filtros (cliente, estado, fechas)
// $filtro_cliente_id = $_GET['filtro_cliente_id'] ?? null;
// $filtro_estado = $_GET['filtro_estado'] ?? null;
// ...

if ($conn) {
    // Unir con clientes para mostrar nombre, y con usuarios para el creador
    $sql = "SELECT c.*, cl.nombre_razon_social as nombre_cliente, u.nombre_completo as nombre_creador
            FROM cotizaciones c
            JOIN clientes cl ON c.id_cliente = cl.id_cliente
            JOIN usuarios u ON c.id_usuario_creador = u.id_usuario
            ORDER BY c.fecha_emision DESC, c.id_cotizacion DESC";
            // TODO: Añadir WHERE para filtros
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cotizaciones[] = $row;
        }
    } else {
        $error_message = "Error al cargar la lista de cotizaciones: " . $conn->error;
    }
    close_db_connection($conn);
} else {
    $error_message = "No se pudo conectar a la base de datos.";
}

// Estados de cotización y sus clases de badge para Bootstrap-like styling
$estados_cotizacion_badges = [
    'BORRADOR' => 'secondary',
    'ENVIADA' => 'info',
    'ACEPTADA' => 'success',
    'RECHAZADA' => 'danger',
    'ANULADA' => 'warning',
    'VENCIDA' => 'dark'
];

$csrf_token = generate_csrf_token(); // Para acciones futuras como anular
?>

<div class="card">
    <div class="card-header">
        <h3>Lista de Cotizaciones</h3>
        <a href="<?php echo BASE_URL; ?>admin.php?page=cotizacion_crear" class="btn btn-success btn-sm float-right">
            <i class="fas fa-plus"></i> Crear Nueva Cotización
        </a>
    </div>
    <div class="card-body">
        <?php if ($feedback_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- TODO: Sección de Filtros aquí -->

        <?php if (empty($cotizaciones) && empty($error_message)): ?>
            <p>No hay cotizaciones registradas. <a href="<?php echo BASE_URL; ?>admin.php?page=cotizacion_crear">Cree la primera</a>.</p>
        <?php elseif (!empty($cotizaciones)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Fecha Emisión</th>
                            <th>Fecha Validez</th>
                            <th>Total</th>
                            <th>Moneda</th>
                            <th>Estado</th>
                            <th>Creador</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotizaciones as $cot): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>admin.php?page=cotizacion_ver&id=<?php echo $cot['id_cotizacion']; ?>">
                                        <?php echo htmlspecialchars($cot['codigo_cotizacion']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($cot['nombre_cliente']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cot['fecha_emision'])); ?></td>
                                <td><?php echo $cot['fecha_validez'] ? date('d/m/Y', strtotime($cot['fecha_validez'])) : '-'; ?></td>
                                <td style="text-align: right;"><?php echo htmlspecialchars(number_format($cot['total_cotizacion'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($cot['moneda']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $estados_cotizacion_badges[$cot['estado']] ?? 'light'; ?>">
                                        <?php echo htmlspecialchars(ucfirst(strtolower(str_replace('_', ' ', $cot['estado'])))); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($cot['nombre_creador']); ?></td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>admin.php?page=cotizacion_ver&id=<?php echo $cot['id_cotizacion']; ?>" class="btn btn-info btn-xs" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($cot['estado'] === 'BORRADOR' || (is_admin() && $cot['estado'] !== 'ACEPTADA' && $cot['estado'] !== 'ANULADA' )): // Permitir editar borradores o si es admin (con restricciones) ?>
                                    <a href="<?php echo BASE_URL; ?>admin.php?page=cotizacion_editar&id=<?php echo $cot['id_cotizacion']; ?>" class="btn btn-primary btn-xs" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>

                                    <a href="<?php echo BASE_URL; ?>generate_pdf.php?id=<?php echo $cot['id_cotizacion']; ?>" target="_blank" class="btn btn-warning btn-xs" title="Generar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>

                                    <?php if (is_admin() && $cot['estado'] !== 'ANULADA' && $cot['estado'] !== 'ACEPTADA'): ?>
                                    <!-- Formulario para anular cotización (ejemplo) -->
                                    <!-- <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=cotizaciones&action=anular" style="display:inline-block;" onsubmit="return confirm('¿Está seguro de anular esta cotización?');">
                                        <input type="hidden" name="cotizacion_id_anular" value="<?php echo $cot['id_cotizacion']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <button type="submit" class="btn btn-danger btn-xs" title="Anular">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    </form> -->
                                    <?php endif; ?>
                                    <!-- Más acciones: enviar por email, marcar como aceptada/rechazada, etc. -->
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
/* Estilos para badges (si no usas Bootstrap completo o quieres asegurar) */
.badge { display: inline-block; padding: .35em .65em; font-size: .75em; font-weight: 700; line-height: 1; color: #fff; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; }
.badge-secondary { background-color: #6c757d; }
.badge-info { background-color: #17a2b8; }
.badge-success { background-color: #28a745; }
.badge-danger { background-color: #dc3545; }
.badge-warning { background-color: #ffc107; color: #212529;}
.badge-dark { background-color: #343a40; }
.badge-light { background-color: #f8f9fa; color: #212529;}

.table-responsive { overflow-x: auto; }
.btn-xs { padding: .2rem .4rem; font-size: .75rem; line-height: 1.5; border-radius: .2rem; }
.btn i.fas { margin-right: 0; } /* Sin margen para botones solo con icono */
.btn-primary i.fas, .btn-danger i.fas, .btn-success i.fas, .btn-info i.fas, .btn-warning i.fas { color: white; }
.btn-warning i.fas { color: #212529; } /* Para que se vea bien en fondo amarillo */
.float-right { float: right; }
.card-header h3 { display:inline-block; margin-right:10px;}
</style>
