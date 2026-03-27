<?php
/**
 * Módulo de Inventario - Página principal
 * Redirige según el rol del usuario
 */

require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canAccessInventoryPanel($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder al módulo de inventario';
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

// Verificar si es supervisor o usuario inventario
if (Permissions::canManageInventorySessions($auth)) {
    // Redirigir al panel de administración
    header('Location: ' . BASE_URL . '/inventario/admin/index.php');
} else {
    // Redirigir al dashboard de usuario
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
}
exit;
