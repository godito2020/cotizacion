<?php
// app/views/admin/herramientas_mantenimiento.php

if (!is_admin()) {
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección requiere privilegios de administrador.</div>';
    return;
}

$db_name = defined('DB_NAME') ? DB_NAME : '[NOMBRE_BD]';
$db_user = defined('DB_USER') ? DB_USER : '[USUARIO_BD]';
$db_host = defined('DB_HOST') ? DB_HOST : '[HOST_BD]';

// Generar un nombre de archivo sugerido para el backup
$backup_filename = 'backup_' . $db_name . '_' . date('Ymd_His') . '.sql';

$mysqldump_command_example = "mysqldump --user={$db_user} --password=\"[SU_CONTRASENA_BD]\" --host={$db_host} {$db_name} > {$backup_filename}";
$mysqldump_command_example_no_pass = "mysqldump -u {$db_user} -p -h {$db_host} {$db_name} > {$backup_filename}";


$feedback_message = $_SESSION['feedback_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['error_message']);

// Placeholder para acción de limpieza de caché u otros
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpiar_cache_simulacion'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Error de validación CSRF.";
    } else {
        // Simular limpieza de caché
        // Ejemplo: podrías eliminar archivos de una carpeta 'cache/'
        // if (is_dir(ROOT_PATH . '/cache')) { ... }
        $_SESSION['feedback_message'] = "Simulación: Caché del sistema limpiada (ejemplo).";
    }
    header("Location: " . BASE_URL . "admin.php?page=herramientas_mantenimiento");
    exit;
}
$csrf_token = generate_csrf_token();

?>
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>Backup de Base de Datos</h3>
            </div>
            <div class="card-body">
                <p>Es <strong>crucial</strong> realizar copias de seguridad (backups) de su base de datos regularmente para proteger su información contra pérdidas accidentales, corrupción de datos o fallos del sistema.</p>

                <h4>Métodos Recomendados:</h4>
                <ul>
                    <li><strong>phpMyAdmin:</strong> Si su proveedor de hosting ofrece phpMyAdmin, puede usar su función de "Exportar" para generar un archivo SQL de respaldo. Seleccione todos las tablas y elija el formato SQL.</li>
                    <li><strong>Herramientas del Panel de Hosting:</strong> Muchos paneles de control (cPanel, Plesk, etc.) ofrecen herramientas integradas para realizar backups completos de su sitio, incluyendo la base de datos.</li>
                    <li><strong>Línea de Comandos (Acceso SSH - Avanzado):</strong> Si tiene acceso SSH a su servidor, `mysqldump` es la herramienta estándar para backups de MySQL.</li>
                </ul>

                <h4>Ejemplo de Comando `mysqldump` (para ejecutar manualmente):</h4>
                <p>El siguiente comando puede ser adaptado y ejecutado desde la línea de comandos de su servidor. Reemplace `[SU_CONTRASENA_BD]` con su contraseña real.</p>
                <pre style="background-color: #f5f5f5; padding: 10px; border: 1px solid #ccc; border-radius: 4px; overflow-x: auto;"><code><?php echo htmlspecialchars($mysqldump_command_example); ?></code></pre>
                <p>O, si prefiere que le pida la contraseña interactivamente (más seguro):</p>
                <pre style="background-color: #f5f5f5; padding: 10px; border: 1px solid #ccc; border-radius: 4px; overflow-x: auto;"><code><?php echo htmlspecialchars($mysqldump_command_example_no_pass); ?></code></pre>
                <p><small><strong>Nota:</strong> Este sistema no ejecuta el comando de backup automáticamente por razones de seguridad y variabilidad de entornos. El comando proporcionado es una guía para usuarios con acceso al servidor.</small></p>

                <p><strong>Recomendaciones Adicionales:</strong></p>
                <ul>
                    <li>Almacene sus backups en un lugar seguro y separado de su servidor principal (ej: almacenamiento en la nube, disco duro externo).</li>
                    <li>Pruebe restaurar sus backups periódicamente en un entorno de desarrollo para asegurarse de que son válidos.</li>
                    <li>Considere automatizar el proceso de backup si su entorno lo permite (ej: mediante cron jobs y scripts).</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3>Otras Herramientas</h3>
            </div>
            <div class="card-body">
                <?php if ($feedback_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($feedback_message); ?></div><?php endif; ?>
                <?php if ($error_message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

                <h4>Limpieza de Caché (Simulación)</h4>
                <p>Esto es un ejemplo de una acción de mantenimiento. En una aplicación real, podría limpiar cachés de plantillas, datos, etc.</p>
                <form method="POST" action="<?php echo BASE_URL; ?>admin.php?page=herramientas_mantenimiento" onsubmit="return confirm('¿Está seguro de que desea limpiar la caché (simulación)?');">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" name="limpiar_cache_simulacion" class="btn btn-warning">
                        <i class="fas fa-broom"></i> Limpiar Caché (Simulación)
                    </button>
                </form>
                <hr>
                <h4>Optimización de Base de Datos</h4>
                <p>Regularmente, puede ser útil optimizar las tablas de su base de datos. Esto se puede hacer a través de phpMyAdmin (seleccionando tablas y eligiendo "Optimizar tabla") o mediante comandos SQL como `OPTIMIZE TABLE nombre_tabla;`.</p>
                <p><small><strong>Nota:</strong> Realice estas operaciones con precaución y preferiblemente durante periodos de baja actividad.</small></p>

            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
/* Para que las columnas de row se comporten bien en diferentes tamaños */
.row { display: flex; flex-wrap: wrap; margin-right: -15px; margin-left: -15px; }
.col-md-8, .col-md-4 { position: relative; width: 100%; padding-right: 15px; padding-left: 15px; }
@media (min-width: 768px) { /* md breakpoint */
    .col-md-8 { flex: 0 0 66.666667%; max-width: 66.666667%; }
    .col-md-4 { flex: 0 0 33.333333%; max-width: 33.333333%; }
}
</style>
