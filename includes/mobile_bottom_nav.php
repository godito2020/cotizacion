<?php
/**
 * includes/mobile_bottom_nav.php
 * Menú de Navegación Inferior Móvil Universal (Notion / Linear Clean Mobile Bar)
 * Se incluye en todas las páginas móviles y a través de app_footer.php
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/init.php';
}

$currentUri = $_SERVER['REQUEST_URI'] ?? '';

// Active states detection
$isHomeActive = strpos($currentUri, 'dashboard_mobile.php') !== false
    || (strpos($currentUri, 'dashboard') !== false && strpos($currentUri, 'quotations') === false);

$isQuotationsActive = (strpos($currentUri, '/quotations/') !== false || strpos($currentUri, 'quotations') !== false)
    && strpos($currentUri, 'create') === false;

$isCreateActive = strpos($currentUri, '/quotations/create') !== false
    || strpos($currentUri, 'create_mobile.php') !== false;

$isCustomersActive = strpos($currentUri, '/customers/') !== false
    || strpos($currentUri, 'customers') !== false;

$isProductsActive = strpos($currentUri, '/products/') !== false
    || strpos($currentUri, '/admin/products') !== false
    || strpos($currentUri, '/cost-analysis/') !== false
    || strpos($currentUri, '/inventario/') !== false;
?>

<!-- Universal Mobile Bottom Navigation Bar -->
<nav class="app-mobile-bottom-nav" aria-label="Navegación móvil">
    <!-- 1. Inicio -->
    <a href="<?= BASE_URL ?>/dashboard_mobile.php" class="<?= $isHomeActive ? 'is-active' : '' ?>" title="Ir a Inicio">
        <i class="fas fa-house"></i>
        <span>Inicio</span>
    </a>

    <!-- 2. Cotizaciones -->
    <a href="<?= BASE_URL ?>/quotations/index_mobile.php" class="<?= $isQuotationsActive ? 'is-active' : '' ?>" title="Listado de Cotizaciones">
        <i class="fas fa-file-invoice"></i>
        <span>Cotizaciones</span>
    </a>

    <!-- 3. Cotizar (Acción Destacada Central) -->
    <a href="<?= BASE_URL ?>/quotations/create.php" class="nav-center-action <?= $isCreateActive ? 'is-active' : '' ?>" title="Nueva Cotización">
        <div class="nav-center-pill">
            <i class="fas fa-plus"></i>
        </div>
        <span>Cotizar</span>
    </a>

    <!-- 4. Clientes -->
    <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="<?= $isCustomersActive ? 'is-active' : '' ?>" title="Cartera de Clientes">
        <i class="fas fa-users"></i>
        <span>Clientes</span>
    </a>

    <!-- 5. Productos / Inventario -->
    <a href="<?= BASE_URL ?>/products/index.php" class="<?= $isProductsActive ? 'is-active' : '' ?>" title="Catálogo de Productos y Stock">
        <i class="fas fa-boxes-stacked"></i>
        <span>Productos</span>
    </a>
</nav>
