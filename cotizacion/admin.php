<?php
// admin.php - Punto de entrada principal para el panel de administración

// Cabeceras de Seguridad
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
}

require_once 'config/config.php'; // Siempre primero para constantes como BASE_URL
require_once 'utils/auth_helper.php';
require_once 'utils/db_helper.php';

// Verificar si el usuario está logueado. Si no, redirigir a login.php
check_login(); // auth_helper.php se encarga de la redirección

// Obtener la página solicitada, por defecto 'dashboard'
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? ''; // Para acciones como crear, editar, eliminar
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

// Incluir el header del panel de administración
include_once 'templates/admin_header.php'; // admin_header.php puede usar funciones de auth_helper

// Protección de rutas específicas para administradores
$admin_only_pages = ['empresa', 'configuraciones', 'usuarios', 'usuario_crear', 'usuario_editar', 'usuario_eliminar'];
// Las páginas de gestión de clientes, productos, etc., podrían no ser admin-only si hay roles como 'vendedor' que pueden gestionarlos.
// Por ahora, asumiremos que la gestión completa de clientes es para usuarios logueados, y si se requiere restricción de rol, se hará dentro de la vista/controlador específico.
// $client_management_pages = ['clientes', 'cliente_crear', 'cliente_editar']; // Ejemplo
// if (in_array($page, $client_management_pages) && !is_logged_in()) { /* Redirigir o denegar */ }


if (in_array($page, $admin_only_pages) && !is_admin()) {
    // Si no es admin y intenta acceder a una página protegida de CONFIGURACIÓN/USUARIOS
    echo '<div class="alert alert-danger">Acceso denegado. Esta sección requiere privilegios de administrador.</div>';
} else {
    // Enrutamiento del contenido principal
    switch ($page) {
        case 'dashboard':
            include_once 'app/views/admin/dashboard.php';
            break;

        // Configuración (requiere admin)
        case 'empresa':
            include_once 'app/views/admin/empresa_config.php';
            break;
        case 'configuraciones':
            include_once 'app/views/admin/sistema_config.php';
            break;

        // Gestión de Usuarios (requiere admin)
        case 'usuarios':
            include_once 'app/views/admin/usuarios_list.php';
            break;
        case 'usuario_crear':
        case 'usuario_editar':
            include_once 'app/views/admin/usuario_form.php';
            break;

        // Gestión de Clientes (accesible por usuarios logueados, permisos más granulares dentro de la vista si es necesario)
        case 'clientes':
            include_once 'app/views/admin/clientes_list.php'; // Se creará
            break;
        case 'cliente_crear':
        case 'cliente_editar':
            include_once 'app/views/admin/cliente_form.php';
            break;

        // Gestión de Almacenes
        case 'almacenes':
            include_once 'app/views/admin/almacenes_list.php'; // Se creará
            break;
        case 'almacen_crear':
        case 'almacen_editar':
            include_once 'app/views/admin/almacen_form.php';
            break;

        // Gestión de Productos
        case 'productos':
            include_once 'app/views/admin/productos_list.php'; // Se creará
            break;
        case 'producto_crear':
        case 'producto_editar':
            include_once 'app/views/admin/producto_form.php'; // Se creará
            break;
        case 'producto_importar':
            include_once 'app/views/admin/producto_importar.php';
            break;

        // Gestión de Cotizaciones
        case 'cotizaciones':
            include_once 'app/views/admin/cotizaciones_list.php'; // Se creará
            break;
        case 'cotizacion_crear':
        case 'cotizacion_editar': // Edición de borradores
            include_once 'app/views/admin/cotizacion_form.php'; // Se creará
            break;
        case 'cotizacion_ver':
            include_once 'app/views/admin/cotizacion_ver.php';
            break;

        // Herramientas
        case 'herramientas_mantenimiento': // Incluirá Backup BD y otras futuras
            if (is_admin()) { // Solo admin para herramientas sensibles
                include_once 'app/views/admin/herramientas_mantenimiento.php'; // Se creará
            } else {
                echo '<div class="alert alert-danger">Acceso denegado. Requiere privilegios de administrador.</div>';
            }
            break;

        // TODO: Añadir casos para importar (general) podría ir aquí también

        default:
            // Si la página no se encuentra, mostrar dashboard o un error 404
            echo '<div class="alert alert-warning">Página no encontrada.</div>';
            include_once 'app/views/admin/dashboard.php';
            break;
    }
}

// Incluir el footer del panel de administración
include_once 'templates/admin_footer.php';
?>
