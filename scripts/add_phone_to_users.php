<?php
/**
 * Migración: agrega columna phone a la tabla users (si no existe).
 * Ejecutar una sola vez desde el navegador: /scripts/add_phone_to_users.php
 */
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    http_response_code(403);
    die('Solo el Administrador del Sistema puede ejecutar esta migración.');
}

$db = getDBConnection();

// Verificar si ya existe la columna
$cols = $db->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetchAll();
if (!empty($cols)) {
    echo "La columna <code>phone</code> ya existe en la tabla <code>users</code>. No se requiere migración.";
    exit;
}

$db->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL AFTER last_name");
echo "✅ Columna <code>phone</code> agregada correctamente a <code>users</code>.";
