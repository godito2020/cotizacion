<?php
// cotizacion/public/admin/index.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $redirect_url = $_SERVER['REQUEST_URI'];
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($redirect_url));
}
// This page is a generic admin dashboard, specific role checks for content sections
// For the page itself, let's require at least one of the admin-level roles.
if (!$auth->hasRole(['System Admin', 'Company Admin', 'Salesperson'])) {
    $_SESSION['error_message'] = "No tiene autorización para acceder al panel de administración.";
    $auth->redirect(BASE_URL . '/dashboard.php');
}

$loggedInUser = $auth->getUser(); // Renamed from $user to avoid conflict with $userRepo
$userRepo = new User();

$page_title = "Panel de Administración - " . APP_NAME;
require_once TEMPLATES_PATH . '/header.php';
?>

<nav aria-label="breadcrumb">
  <ul>
    <li><a href="<?php echo BASE_URL; ?>/dashboard.php">Dashboard</a></li>
    <li>Panel de Administración</li>
  </ul>
</nav>

<hgroup>
    <h1>Panel de Administración</h1>
    <p>Bienvenido, <?php echo htmlspecialchars($loggedInUser['first_name'] ?: $loggedInUser['username']); ?>!</p>
</hgroup>

<!-- Admin specific navigation can go here as a secondary nav if needed -->
<nav class="admin-secondary-nav">
  <ul>
    <?php if ($auth->hasRole('System Admin')): ?>
        <li><a href="<?php echo BASE_URL; ?>/admin/companies.php" role="button" class="outline">Empresas</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/system_settings.php" role="button" class="outline">Conf. Sistema</a></li>
    <?php endif; ?>
    <?php if ($auth->hasRole(['Company Admin', 'Salesperson'])): ?>
         <li><a href="<?php echo BASE_URL; ?>/admin/customers.php" role="button" class="outline">Clientes</a></li>
         <li><a href="<?php echo BASE_URL; ?>/admin/quotations.php" role="button" class="outline">Cotizaciones</a></li>
    <?php endif; ?>
    <?php if ($auth->hasRole('Company Admin')): // Company Admin specific links can be grouped ?>
        <li><a href="<?php echo BASE_URL; ?>/admin/products.php" role="button" class="outline">Productos</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/product_import.php" role="button" class="outline">Importar Productos</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/warehouses.php" role="button" class="outline">Almacenes</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/warehouse_import.php" role="button" class="outline">Importar Almacenes</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/stock_management.php" role="button" class="outline">Stock</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/stock_import.php" role="button" class="outline">Importar Stock</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/company_profile.php" role="button" class="outline">Perfil Empresa</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/company_settings_specific.php" role="button" class="outline">Conf. Empresa</a></li>
        <li><a href="<?php echo BASE_URL; ?>/admin/reports.php" role="button" class="outline">Reportes</a></li>
    <?php endif; ?>
  </ul>
</nav>
<br>


<article>
    <header>
        <h2>Accesos Rápidos</h2>
    </header>
    <div class="grid">
        <?php if ($auth->hasRole('System Admin')): ?>
            <div><a href="<?php echo BASE_URL; ?>/admin/companies.php">Gestionar Empresas</a></div>
            <div><a href="<?php echo BASE_URL; ?>/admin/system_settings.php">Configuraciones del Sistema</a></div>
        <?php endif; ?>

        <?php if ($auth->hasRole(['Company Admin', 'Salesperson'])): ?>
            <div><a href="<?php echo BASE_URL; ?>/admin/customers.php">Gestionar Clientes</a> (para su compañía)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/quotations.php">Gestionar Cotizaciones</a> (para su compañía)</div>
        <?php endif; ?>

        <?php if ($auth->hasRole('Company Admin')): ?>
            <div><a href="<?php echo BASE_URL; ?>/admin/products.php">Gestionar Productos</a> (para su compañía)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/product_import.php">Importar Productos</a> (CSV)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Gestionar Almacenes</a> (para su compañía)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/warehouse_import.php">Importar Almacenes</a> (CSV)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Gestionar Stock</a> (para su compañía)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/stock_import.php">Importar Stock</a> (CSV)</div>
            <div><a href="<?php echo BASE_URL; ?>/admin/company_profile.php">Perfil de Empresa</a></div>
            <div><a href="<?php echo BASE_URL; ?>/admin/company_settings_specific.php">Configuraciones de Empresa</a></div>
            <a href="<?php echo BASE_URL; ?>/admin/reports.php">Reportes</a>
        <?php endif; ?>

        <?php if ($auth->hasRole(['System Admin', 'Company Admin'])): ?>
             <!-- Example: Link to User Management if it existed -->
             <!-- <li><a href="<?php echo BASE_URL; ?>/admin/users.php">Gestionar Usuarios</a></li> -->
        <?php endif; ?>
    </div>
    <footer>
        <p>Utilice los enlaces de navegación o los accesos rápidos para gestionar la aplicación.</p>
    </footer>
</article>


<?php
require_once TEMPLATES_PATH . '/footer.php';
?>
