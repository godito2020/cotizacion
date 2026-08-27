<?php
// app/views/admin/dashboard.php

$conn = get_db_connection();
$stats = [
    'cotizaciones_total' => 0,
    'cotizaciones_borrador' => 0,
    'cotizaciones_enviadas' => 0,
    'cotizaciones_aceptadas' => 0,
    'clientes_activos' => 0,
    'productos_activos' => 0,
    'monto_aceptado_mes_actual' => 0.00,
    'moneda_principal' => get_config_value('DEFAULT_CURRENCY_CODE', $conn) ?: 'PEN'
];

if ($conn) {
    // Cotizaciones totales y por estado
    $res_cot_total = $conn->query("SELECT COUNT(*) as count FROM cotizaciones");
    if($res_cot_total) $stats['cotizaciones_total'] = $res_cot_total->fetch_assoc()['count'];

    $res_cot_borrador = $conn->query("SELECT COUNT(*) as count FROM cotizaciones WHERE estado = 'BORRADOR'");
    if($res_cot_borrador) $stats['cotizaciones_borrador'] = $res_cot_borrador->fetch_assoc()['count'];

    $res_cot_enviadas = $conn->query("SELECT COUNT(*) as count FROM cotizaciones WHERE estado = 'ENVIADA'");
    if($res_cot_enviadas) $stats['cotizaciones_enviadas'] = $res_cot_enviadas->fetch_assoc()['count'];

    $res_cot_aceptadas = $conn->query("SELECT COUNT(*) as count FROM cotizaciones WHERE estado = 'ACEPTADA'");
    if($res_cot_aceptadas) $stats['cotizaciones_aceptadas'] = $res_cot_aceptadas->fetch_assoc()['count'];

    // Clientes activos
    $res_cli_activos = $conn->query("SELECT COUNT(*) as count FROM clientes WHERE activo = TRUE");
    if($res_cli_activos) $stats['clientes_activos'] = $res_cli_activos->fetch_assoc()['count'];

    // Productos activos
    $res_prod_activos = $conn->query("SELECT COUNT(*) as count FROM productos WHERE activo = TRUE");
    if($res_prod_activos) $stats['productos_activos'] = $res_prod_activos->fetch_assoc()['count'];

    // Monto total de cotizaciones aceptadas en el mes actual (asumiendo misma moneda o convirtiendo)
    // Esta es una simplificación, ya que las cotizaciones pueden estar en diferentes monedas.
    // Para un cálculo preciso, se necesitaría convertir todas a una moneda base o agrupar por moneda.
    $primer_dia_mes = date('Y-m-01');
    $ultimo_dia_mes = date('Y-m-t');
    $sql_monto_mes = "SELECT SUM(total_cotizacion) as total_mes FROM cotizaciones WHERE estado = 'ACEPTADA' AND moneda = ? AND fecha_emision BETWEEN ? AND ?";
    $stmt_monto = $conn->prepare($sql_monto_mes);
    if ($stmt_monto) {
        $stmt_monto->bind_param("sss", $stats['moneda_principal'], $primer_dia_mes, $ultimo_dia_mes);
        $stmt_monto->execute();
        $res_monto = $stmt_monto->get_result();
        if($res_monto) {
            $monto_row = $res_monto->fetch_assoc();
            $stats['monto_aceptado_mes_actual'] = $monto_row['total_mes'] ?? 0.00;
        }
        $stmt_monto->close();
    }

    close_db_connection($conn);
}

?>
<div class="card">
    <div class="card-header">
        <h3>Bienvenido al Panel de Administración, <?php echo htmlspecialchars(get_current_user_name() ?? 'Usuario'); ?>!</h3>
    </div>
    <div class="card-body">
        <p>Seleccione una opción del menú de la izquierda para comenzar a gestionar el sistema de cotizaciones.</p>

        <h4>Resumen General</h4>
        <div class="dashboard-cards">
            <div class="dashboard-card">
                <h5><i class="fas fa-file-invoice-dollar"></i> Cotizaciones Aceptadas (Mes)</h5>
                <p class="value"><?php echo htmlspecialchars(number_format($stats['monto_aceptado_mes_actual'], 2)); ?> <small><?php echo $stats['moneda_principal']; ?></small></p>
                <p class="description">Monto total de cotizaciones aceptadas este mes (en <?php echo $stats['moneda_principal']; ?>).</p>
            </div>
            <div class="dashboard-card">
                <h5><i class="fas fa-file-alt"></i> Cotizaciones (Borrador)</h5>
                <p class="value"><?php echo $stats['cotizaciones_borrador']; ?></p>
                <p class="description">Cotizaciones pendientes de finalizar o enviar.</p>
                <a href="<?php echo BASE_URL; ?>admin.php?page=cotizaciones" class="btn btn-info btn-sm">Ver Cotizaciones</a>
            </div>

            <div class="dashboard-card">
                <h5><i class="fas fa-users"></i> Clientes Activos</h5>
                <p class="value"><?php echo $stats['clientes_activos']; ?></p>
                <p class="description">Total de clientes registrados y activos.</p>
                <a href="<?php echo BASE_URL; ?>admin.php?page=clientes" class="btn btn-primary btn-sm">Gestionar Clientes</a>
            </div>

            <div class="dashboard-card">
                <h5><i class="fas fa-box-open"></i> Productos Activos</h5>
                <p class="value"><?php echo $stats['productos_activos']; ?></p>
                <p class="description">Total de productos disponibles en catálogo.</p>
                <a href="<?php echo BASE_URL; ?>admin.php?page=productos" class="btn btn-primary btn-sm">Ver Productos</a>
            </div>
             <div class="dashboard-card">
                <h5><i class="fas fa-chart-pie"></i> Estados de Cotizaciones</h5>
                <ul class="list-unstyled">
                    <li>Borrador: <strong><?php echo $stats['cotizaciones_borrador']; ?></strong></li>
                    <li>Enviadas: <strong><?php echo $stats['cotizaciones_enviadas']; ?></strong></li>
                    <li>Aceptadas: <strong><?php echo $stats['cotizaciones_aceptadas']; ?></strong></li>
                    <li>Total: <strong><?php echo $stats['cotizaciones_total']; ?></strong></li>
                </ul>
            </div>
        </div>

        <h4 class="mt-20">Accesos Rápidos</h4>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>admin.php?page=empresa">Configurar Datos de la Empresa</a></li>
            <li><a href="<?php echo BASE_URL; ?>admin.php?page=configuraciones">Ajustes Generales del Sistema</a></li>
            <li><a href="<?php echo BASE_URL; ?>admin.php?page=usuarios">Administrar Usuarios</a></li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Notas Importantes</h3>
    </div>
    <div class="card-body">
        <p>Recuerde configurar correctamente los datos de su empresa y las opciones del sistema antes de comenzar a generar cotizaciones.</p>
        <p>Es fundamental realizar copias de seguridad de su base de datos periódicamente.</p>
        <?php if(file_exists(ROOT_PATH . '/install/install.php')): ?>
            <div class="alert alert-danger">
                <strong>¡Atención!</strong> La carpeta de instalación (<code><?php echo ROOT_PATH . '/install/'; ?></code>) todavía existe. Por favor, elimínela o cámbiele el nombre por razones de seguridad.
            </div>
        <?php endif; ?>
    </div>
</div>
