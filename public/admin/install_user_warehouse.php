<?php
/**
 * Instalador idempotente: agrega la columna users.default_warehouse
 * (almacén por defecto asignado a cada usuario para filtrar stock en cotizaciones).
 * Abrir en el navegador una sola vez. Seguro de ejecutar varias veces.
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = getDBConnection();

    // ¿Ya existe la columna?
    $check = $db->query("SHOW COLUMNS FROM `users` LIKE 'default_warehouse'");
    if ($check->fetch()) {
        echo "OK: La columna 'default_warehouse' ya existe en la tabla users. No se hizo nada.\n";
        exit;
    }

    $db->exec("ALTER TABLE `users` ADD COLUMN `default_warehouse` INT NULL DEFAULT NULL AFTER `address`");
    echo "EXITO: Columna 'default_warehouse' agregada correctamente a la tabla users.\n";
    echo "Ya puede asignar almacenes a los usuarios en Admin -> Gestion de Usuarios -> Editar.\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage() . "\n";
}
