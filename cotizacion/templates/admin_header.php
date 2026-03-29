<?php
// admin_header.php
// auth_helper.php ya debería estar incluido por admin.php, por lo que las funciones como get_current_user_name() están disponibles.
$current_page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Determinar el título de la página dinámicamente
$page_title_text = 'Dashboard'; // Default
if ($current_page === 'empresa') $page_title_text = 'Datos de la Empresa';
elseif ($current_page === 'configuraciones') $page_title_text = 'Configuraciones del Sistema';
elseif ($current_page === 'usuarios') $page_title_text = 'Gestión de Usuarios';
elseif ($current_page === 'usuario_crear') $page_title_text = 'Crear Nuevo Usuario';
elseif ($current_page === 'usuario_editar') $page_title_text = 'Editar Usuario';
elseif ($current_page === 'clientes') $page_title_text = 'Gestión de Clientes';
elseif ($current_page === 'cliente_crear') $page_title_text = 'Crear Nuevo Cliente';
elseif ($current_page === 'cliente_editar') $page_title_text = 'Editar Cliente';
elseif ($current_page === 'almacenes') $page_title_text = 'Gestión de Almacenes';
elseif ($current_page === 'almacen_crear') $page_title_text = 'Crear Nuevo Almacén';
elseif ($current_page === 'almacen_editar') $page_title_text = 'Editar Almacén';
elseif ($current_page === 'productos') $page_title_text = 'Gestión de Productos';
elseif ($current_page === 'producto_crear') $page_title_text = 'Crear Nuevo Producto';
elseif ($current_page === 'producto_editar') $page_title_text = 'Editar Producto';
elseif ($current_page === 'producto_importar') $page_title_text = 'Importar Productos';
elseif ($current_page === 'cotizaciones') $page_title_text = 'Gestión de Cotizaciones';
elseif ($current_page === 'cotizacion_crear') $page_title_text = 'Crear Nueva Cotización';
elseif ($current_page === 'cotizacion_editar') $page_title_text = 'Editar Cotización';
elseif ($current_page === 'cotizacion_ver') $page_title_text = 'Ver Cotización';
elseif ($current_page === 'herramientas_mantenimiento') $page_title_text = 'Herramientas y Mantenimiento';
// Añadir más títulos según sea necesario para otras páginas

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title_text); ?> - Panel de Administración - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/admin_styles.css">
    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
    </script>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo BASE_URL; ?>admin.php">
                    <h2><?php echo APP_NAME; ?></h2>
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=dashboard" class="<?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">Dashboard</a></li>

                    <li class="nav-section-title">Gestión Principal</li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=cotizaciones" class="<?php echo ($current_page === 'cotizaciones' || $current_page === 'cotizacion_crear' || $current_page === 'cotizacion_editar' || $current_page === 'cotizacion_ver') ? 'active' : ''; ?>">Cotizaciones</a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=clientes" class="<?php echo ($current_page === 'clientes' || $current_page === 'cliente_crear' || $current_page === 'cliente_editar') ? 'active' : ''; ?>">Clientes</a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=productos" class="<?php echo ($current_page === 'productos' || $current_page === 'producto_crear' || $current_page === 'producto_editar' || $current_page === 'producto_importar') ? 'active' : ''; ?>">Productos</a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=almacenes" class="<?php echo ($current_page === 'almacenes' || $current_page === 'almacen_crear' || $current_page === 'almacen_editar') ? 'active' : ''; ?>">Almacenes</a></li>

                    <?php if (is_admin()): // Mostrar solo a administradores ?>
                    <li class="nav-section-title">Configuración</li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=empresa" class="<?php echo $current_page === 'empresa' ? 'active' : ''; ?>">Datos de la Empresa</a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=configuraciones" class="<?php echo $current_page === 'configuraciones' ? 'active' : ''; ?>">Configuraciones del Sistema</a></li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=usuarios" class="<?php echo ($current_page === 'usuarios' || $current_page === 'usuario_crear' || $current_page === 'usuario_editar') ? 'active' : ''; ?>">Usuarios</a></li>
                    <?php endif; ?>

                    <li class="nav-section-title">Herramientas</li>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=producto_importar" class="<?php echo $current_page === 'producto_importar' ? 'active' : ''; ?>">Importar Productos</a></li>
                    <?php if (is_admin()): ?>
                    <li><a href="<?php echo BASE_URL; ?>admin.php?page=herramientas_mantenimiento" class="<?php echo $current_page === 'herramientas_mantenimiento' ? 'active' : ''; ?>">Mantenimiento</a></li>
                    <?php endif; ?>
                    <!-- Se podría tener un menú general de "Importar/Exportar" y submenús -->
                </ul>
            </nav>
            <div class="sidebar-footer">
                 <p>Usuario: <strong><?php echo htmlspecialchars(get_current_user_name() ?? 'N/A'); ?></strong></p>
                <p>Rol: <?php echo htmlspecialchars(ucfirst(get_current_user_role() ?? 'N/A')); ?></p>
                <a href="<?php echo BASE_URL; ?>logout.php">Cerrar Sesión</a>
                <p style="margin-top:10px;">Versión <?php echo APP_VERSION; ?></p>
            </div>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <h1><?php echo htmlspecialchars($page_title_text); ?></h1>
                <div class="user-info">
                    <!-- Podría ir aquí también o en el sidebar footer como está ahora -->
                </div>
            </header>
            <div class="content-area">
                <!-- El contenido específico de cada página se cargará aquí por admin.php -->
                    ?>
                </h1>
                <!-- Aquí podrías añadir breadcrumbs o acciones rápidas -->
            </header>
            <div class="content-area">
                <!-- El contenido específico de cada página se cargará aquí -->
