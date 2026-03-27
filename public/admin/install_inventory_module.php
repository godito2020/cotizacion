<?php
/**
 * Instalador del Módulo de Inventario Físico
 *
 * Este script crea las tablas y roles necesarios para el módulo de inventario.
 * Ejecutar una sola vez desde: /admin/install_inventory_module.php
 */

require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

// Solo administradores pueden ejecutar este instalador
if (!$auth->isLoggedIn() || !$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    die('Acceso denegado. Solo administradores pueden ejecutar este instalador.');
}

$db = getDBConnection();
$results = [];
$errors = [];

/**
 * Ejecuta una consulta SQL y registra el resultado
 */
function executeSQL($db, $sql, $description, &$results, &$errors) {
    try {
        $db->exec($sql);
        $results[] = "✅ $description";
        return true;
    } catch (PDOException $e) {
        // Ignorar errores de "ya existe"
        if (strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'Duplicate') !== false) {
            $results[] = "⚠️ $description (ya existe)";
            return true;
        }
        $errors[] = "❌ $description: " . $e->getMessage();
        return false;
    }
}

// =====================================================
// INICIO DE LA INSTALACIÓN
// =====================================================

$pageTitle = 'Instalador - Módulo de Inventario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; padding: 40px 20px; }
        .installer-card { max-width: 800px; margin: 0 auto; }
        .result-item { padding: 8px 12px; margin: 4px 0; border-radius: 4px; font-family: monospace; }
        .result-success { background: #d4edda; color: #155724; }
        .result-warning { background: #fff3cd; color: #856404; }
        .result-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="installer-card">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-boxes-stacked me-2"></i>Instalador - Módulo de Inventario Físico</h4>
            </div>
            <div class="card-body">
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    echo '<h5 class="mb-3"><i class="fas fa-cog fa-spin me-2"></i>Ejecutando instalación...</h5>';
    echo '<div class="results-container">';

    // 1. CREAR ROLES
    echo '<h6 class="mt-3 text-primary">1. Creando Roles</h6>';

    // Función auxiliar para insertar rol con ID explícito
    function insertRole($db, $roleName, $description, &$results, &$errors) {
        try {
            // Verificar si el rol ya existe
            $stmt = $db->prepare("SELECT id FROM roles WHERE role_name = ?");
            $stmt->execute([$roleName]);
            if ($stmt->fetch()) {
                // Actualizar descripción si ya existe
                $stmt = $db->prepare("UPDATE roles SET description = ? WHERE role_name = ?");
                $stmt->execute([$description, $roleName]);
                $results[] = "⚠️ Rol: $roleName (ya existe, actualizado)";
                return true;
            }

            // Obtener el siguiente ID disponible
            $stmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM roles");
            $nextId = $stmt->fetch(PDO::FETCH_ASSOC)['next_id'];

            // Insertar con ID explícito
            $stmt = $db->prepare("INSERT INTO roles (id, role_name, description) VALUES (?, ?, ?)");
            $stmt->execute([$nextId, $roleName, $description]);
            $results[] = "✅ Rol: $roleName (ID: $nextId)";
            return true;
        } catch (PDOException $e) {
            $errors[] = "❌ Rol: $roleName: " . $e->getMessage();
            return false;
        }
    }

    insertRole($db, 'Supervisor Inventario',
        'Administrador de sesiones de inventario. Puede abrir/cerrar sesiones, ver progreso en tiempo real y generar reportes.',
        $results, $errors);

    insertRole($db, 'Usuario Inventario',
        'Operador que registra conteos físicos durante una sesión de inventario activa.',
        $results, $errors);

    // 2. CREAR TABLAS
    echo '<h6 class="mt-3 text-primary">2. Creando Tablas</h6>';

    // Tabla inventory_sessions
    $sql = "CREATE TABLE IF NOT EXISTS `inventory_sessions` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `company_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL COMMENT 'Nombre descriptivo: Inventario Enero 2026',
        `description` TEXT NULL COMMENT 'Descripción o notas adicionales',
        `status` ENUM('Open', 'Closed', 'Cancelled') DEFAULT 'Open',
        `created_by` INT NOT NULL COMMENT 'User ID del supervisor que creó la sesión',
        `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha/hora de apertura',
        `closed_at` TIMESTAMP NULL COMMENT 'Fecha/hora de cierre',
        `closed_by` INT NULL COMMENT 'User ID del supervisor que cerró la sesión',
        `close_notes` TEXT NULL COMMENT 'Notas del cierre',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_inv_session_company` (`company_id`),
        INDEX `idx_inv_session_status` (`status`),
        INDEX `idx_inv_session_created_by` (`created_by`),
        INDEX `idx_inv_session_opened_at` (`opened_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    executeSQL($db, $sql, "Tabla: inventory_sessions", $results, $errors);

    // Foreign keys para inventory_sessions
    // FK a companies es opcional (puede fallar si companies no tiene PK correcto)
    try {
        $sql = "ALTER TABLE `inventory_sessions`
            ADD CONSTRAINT `fk_inv_session_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE";
        $db->exec($sql);
        $results[] = "✅ FK: inventory_sessions → companies";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
            $results[] = "⚠️ FK: inventory_sessions → companies (ya existe)";
        } else {
            // No es crítico, continuar sin FK
            $results[] = "⚠️ FK: inventory_sessions → companies (omitido - companies sin PK)";
        }
    }

    $sql = "ALTER TABLE `inventory_sessions`
        ADD CONSTRAINT `fk_inv_session_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT";
    executeSQL($db, $sql, "FK: inventory_sessions.created_by → users", $results, $errors);

    $sql = "ALTER TABLE `inventory_sessions`
        ADD CONSTRAINT `fk_inv_session_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
    executeSQL($db, $sql, "FK: inventory_sessions.closed_by → users", $results, $errors);

    // Tabla inventory_session_warehouses
    $sql = "CREATE TABLE IF NOT EXISTS `inventory_session_warehouses` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `session_id` INT NOT NULL,
        `warehouse_number` INT NOT NULL COMMENT 'Número de almacén (de desc_almacen)',
        `warehouse_name` VARCHAR(255) NOT NULL COMMENT 'Nombre cacheado al momento de crear sesión',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_session_warehouse` (`session_id`, `warehouse_number`),
        INDEX `idx_inv_sw_session` (`session_id`),
        INDEX `idx_inv_sw_warehouse` (`warehouse_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    executeSQL($db, $sql, "Tabla: inventory_session_warehouses", $results, $errors);

    $sql = "ALTER TABLE `inventory_session_warehouses`
        ADD CONSTRAINT `fk_inv_sw_session` FOREIGN KEY (`session_id`) REFERENCES `inventory_sessions`(`id`) ON DELETE CASCADE";
    executeSQL($db, $sql, "FK: inventory_session_warehouses → inventory_sessions", $results, $errors);

    // Tabla inventory_entries
    $sql = "CREATE TABLE IF NOT EXISTS `inventory_entries` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `session_id` INT NOT NULL,
        `user_id` INT NOT NULL COMMENT 'Usuario que registró el conteo',
        `warehouse_number` INT NOT NULL COMMENT 'Almacén donde se contó',
        `product_code` VARCHAR(100) NOT NULL COMMENT 'Código del producto',
        `product_description` VARCHAR(500) NULL COMMENT 'Descripción cacheada al momento del registro',
        `system_stock` DECIMAL(12, 2) NOT NULL COMMENT 'Stock del sistema al momento del registro',
        `counted_quantity` DECIMAL(12, 2) NOT NULL COMMENT 'Cantidad física contada',
        `difference` DECIMAL(12, 2) AS (counted_quantity - system_stock) STORED COMMENT 'Diferencia calculada',
        `comments` TEXT NULL COMMENT 'Comentarios opcionales del usuario',
        `is_edited` BOOLEAN DEFAULT FALSE COMMENT 'Flag si fue editado después de crearse',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_inv_entry_session` (`session_id`),
        INDEX `idx_inv_entry_user` (`user_id`),
        INDEX `idx_inv_entry_warehouse` (`warehouse_number`),
        INDEX `idx_inv_entry_product` (`product_code`),
        INDEX `idx_inv_entry_difference` (`difference`),
        INDEX `idx_inv_entry_created` (`created_at`),
        INDEX `idx_inv_entry_session_product` (`session_id`, `product_code`),
        INDEX `idx_inv_entry_session_user` (`session_id`, `user_id`),
        INDEX `idx_inv_entry_session_warehouse` (`session_id`, `warehouse_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    executeSQL($db, $sql, "Tabla: inventory_entries", $results, $errors);

    $sql = "ALTER TABLE `inventory_entries`
        ADD CONSTRAINT `fk_inv_entry_session` FOREIGN KEY (`session_id`) REFERENCES `inventory_sessions`(`id`) ON DELETE CASCADE";
    executeSQL($db, $sql, "FK: inventory_entries → inventory_sessions", $results, $errors);

    $sql = "ALTER TABLE `inventory_entries`
        ADD CONSTRAINT `fk_inv_entry_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT";
    executeSQL($db, $sql, "FK: inventory_entries → users", $results, $errors);

    // Tabla inventory_entry_history
    $sql = "CREATE TABLE IF NOT EXISTS `inventory_entry_history` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `entry_id` INT NOT NULL,
        `user_id` INT NOT NULL COMMENT 'Usuario que realizó la acción',
        `action` ENUM('created', 'updated', 'deleted') NOT NULL,
        `old_counted_quantity` DECIMAL(12, 2) NULL,
        `new_counted_quantity` DECIMAL(12, 2) NULL,
        `old_comments` TEXT NULL,
        `new_comments` TEXT NULL,
        `ip_address` VARCHAR(45) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_inv_history_entry` (`entry_id`),
        INDEX `idx_inv_history_user` (`user_id`),
        INDEX `idx_inv_history_action` (`action`),
        INDEX `idx_inv_history_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    executeSQL($db, $sql, "Tabla: inventory_entry_history", $results, $errors);

    $sql = "ALTER TABLE `inventory_entry_history`
        ADD CONSTRAINT `fk_inv_history_entry` FOREIGN KEY (`entry_id`) REFERENCES `inventory_entries`(`id`) ON DELETE CASCADE";
    executeSQL($db, $sql, "FK: inventory_entry_history → inventory_entries", $results, $errors);

    $sql = "ALTER TABLE `inventory_entry_history`
        ADD CONSTRAINT `fk_inv_history_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT";
    executeSQL($db, $sql, "FK: inventory_entry_history → users", $results, $errors);

    // Tabla inventory_session_users
    $sql = "CREATE TABLE IF NOT EXISTS `inventory_session_users` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `session_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `assigned_warehouse_number` INT NULL COMMENT 'Si es NULL, puede registrar en cualquier almacén de la sesión',
        `assigned_by` INT NOT NULL COMMENT 'Supervisor que asignó al usuario',
        `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `is_active` BOOLEAN DEFAULT TRUE,
        UNIQUE KEY `uk_session_user` (`session_id`, `user_id`),
        INDEX `idx_inv_su_session` (`session_id`),
        INDEX `idx_inv_su_user` (`user_id`),
        INDEX `idx_inv_su_warehouse` (`assigned_warehouse_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    executeSQL($db, $sql, "Tabla: inventory_session_users", $results, $errors);

    $sql = "ALTER TABLE `inventory_session_users`
        ADD CONSTRAINT `fk_inv_su_session` FOREIGN KEY (`session_id`) REFERENCES `inventory_sessions`(`id`) ON DELETE CASCADE";
    executeSQL($db, $sql, "FK: inventory_session_users → inventory_sessions", $results, $errors);

    $sql = "ALTER TABLE `inventory_session_users`
        ADD CONSTRAINT `fk_inv_su_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE";
    executeSQL($db, $sql, "FK: inventory_session_users → users", $results, $errors);

    $sql = "ALTER TABLE `inventory_session_users`
        ADD CONSTRAINT `fk_inv_su_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT";
    executeSQL($db, $sql, "FK: inventory_session_users.assigned_by → users", $results, $errors);

    // 3. CREAR VISTAS
    echo '<h6 class="mt-3 text-primary">3. Creando Vistas SQL</h6>';

    $sql = "CREATE OR REPLACE VIEW `v_inventory_session_summary` AS
        SELECT
            s.id AS session_id,
            s.company_id,
            s.name AS session_name,
            s.status,
            s.opened_at,
            s.closed_at,
            creator.username AS created_by_username,
            closer.username AS closed_by_username,
            COUNT(DISTINCT e.id) AS total_entries,
            COUNT(DISTINCT e.user_id) AS total_users,
            COUNT(DISTINCT e.product_code) AS total_products,
            SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
            SUM(CASE WHEN e.difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
            SUM(CASE WHEN e.difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count
        FROM inventory_sessions s
        LEFT JOIN users creator ON s.created_by = creator.id
        LEFT JOIN users closer ON s.closed_by = closer.id
        LEFT JOIN inventory_entries e ON s.id = e.session_id
        GROUP BY s.id";
    executeSQL($db, $sql, "Vista: v_inventory_session_summary", $results, $errors);

    $sql = "CREATE OR REPLACE VIEW `v_inventory_user_stats` AS
        SELECT
            e.session_id,
            e.user_id,
            u.username,
            u.first_name,
            u.last_name,
            COUNT(*) AS total_entries,
            COUNT(DISTINCT e.product_code) AS unique_products,
            SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
            SUM(CASE WHEN e.difference != 0 THEN 1 ELSE 0 END) AS discrepancy_count,
            ROUND(SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS accuracy_percentage,
            MIN(e.created_at) AS first_entry_at,
            MAX(e.created_at) AS last_entry_at,
            TIMESTAMPDIFF(MINUTE, MIN(e.created_at), MAX(e.created_at)) AS active_minutes
        FROM inventory_entries e
        JOIN users u ON e.user_id = u.id
        GROUP BY e.session_id, e.user_id";
    executeSQL($db, $sql, "Vista: v_inventory_user_stats", $results, $errors);

    $sql = "CREATE OR REPLACE VIEW `v_inventory_discrepancies` AS
        SELECT
            e.id AS entry_id,
            e.session_id,
            e.user_id,
            u.username,
            e.warehouse_number,
            e.product_code,
            e.product_description,
            e.system_stock,
            e.counted_quantity,
            e.difference,
            CASE
                WHEN e.difference = 0 THEN 'Coincide'
                WHEN e.difference < 0 THEN 'Faltante'
                ELSE 'Sobrante'
            END AS status,
            e.comments,
            e.created_at
        FROM inventory_entries e
        JOIN users u ON e.user_id = u.id";
    executeSQL($db, $sql, "Vista: v_inventory_discrepancies", $results, $errors);

    // 4. CREAR ÍNDICE ADICIONAL
    echo '<h6 class="mt-3 text-primary">4. Creando Índices Adicionales</h6>';

    // Verificar si el índice existe antes de crearlo
    try {
        $stmt = $db->query("SHOW INDEX FROM inventory_sessions WHERE Key_name = 'idx_inv_session_active'");
        if ($stmt->rowCount() == 0) {
            $sql = "CREATE INDEX `idx_inv_session_active` ON `inventory_sessions`(`company_id`, `status`)";
            executeSQL($db, $sql, "Índice: idx_inv_session_active", $results, $errors);
        } else {
            $results[] = "⚠️ Índice: idx_inv_session_active (ya existe)";
        }
    } catch (PDOException $e) {
        $errors[] = "❌ Error verificando índice: " . $e->getMessage();
    }

    echo '</div>';

    // Mostrar resultados
    echo '<h5 class="mt-4">Resultados de la instalación</h5>';
    echo '<div class="results-list">';
    foreach ($results as $result) {
        $class = 'result-success';
        if (strpos($result, '⚠️') !== false) $class = 'result-warning';
        if (strpos($result, '❌') !== false) $class = 'result-error';
        echo "<div class='result-item $class'>$result</div>";
    }
    foreach ($errors as $error) {
        echo "<div class='result-item result-error'>$error</div>";
    }
    echo '</div>';

    // Resumen
    $totalSuccess = count(array_filter($results, fn($r) => strpos($r, '✅') !== false));
    $totalWarning = count(array_filter($results, fn($r) => strpos($r, '⚠️') !== false));
    $totalErrors = count($errors);

    echo '<div class="alert ' . ($totalErrors > 0 ? 'alert-warning' : 'alert-success') . ' mt-4">';
    echo "<strong>Resumen:</strong> $totalSuccess éxitos, $totalWarning ya existían, $totalErrors errores";
    if ($totalErrors == 0) {
        echo '<br><br><strong>¡Instalación completada!</strong> El módulo de inventario está listo para usar.';
    }
    echo '</div>';

    echo '<div class="mt-4">';
    echo '<a href="' . BASE_URL . '/inventario/" class="btn btn-success me-2"><i class="fas fa-boxes-stacked me-2"></i>Ir al Módulo de Inventario</a>';
    echo '<a href="' . BASE_URL . '/admin/" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Volver al Admin</a>';
    echo '</div>';

} else {
    // Mostrar formulario de confirmación
?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>Información</h5>
                    <p>Este instalador creará los siguientes elementos en la base de datos:</p>
                    <ul>
                        <li><strong>2 Roles:</strong> Supervisor Inventario, Usuario Inventario</li>
                        <li><strong>5 Tablas:</strong> inventory_sessions, inventory_session_warehouses, inventory_entries, inventory_entry_history, inventory_session_users</li>
                        <li><strong>3 Vistas:</strong> v_inventory_session_summary, v_inventory_user_stats, v_inventory_discrepancies</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Nota Importante</h5>
                    <p>Si las tablas ya existen, no se modificarán. Los roles se actualizarán con las descripciones correctas.</p>
                </div>

                <form method="POST">
                    <button type="submit" name="install" value="1" class="btn btn-primary btn-lg">
                        <i class="fas fa-download me-2"></i>Ejecutar Instalación
                    </button>
                    <a href="<?= BASE_URL ?>/admin/" class="btn btn-outline-secondary btn-lg ms-2">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </a>
                </form>
<?php
}
?>
            </div>
            <div class="card-footer text-muted text-center">
                <small>Módulo de Inventario Físico v1.0 - COTI</small>
            </div>
        </div>
    </div>
</body>
</html>
