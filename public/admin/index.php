<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../config/permissions.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

// Verificar acceso al Admin Panel
if (!Permissions::canAccessAdminPanel($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder al Panel de Administración';
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$user = $auth->getUser();
$userPermissions = Permissions::getUserPermissions($auth);

$pageTitle = 'Panel de Administración';
$pageTitle = 'Panel de Administración';
$activePage = 'admin';
require_once __DIR__ . '/../../includes/app_header.php';
?>
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-tools"></i> <?= $pageTitle ?></h1>
                <p class="text-muted">Gestiona y configura el sistema</p>
            </div>
        </div>

        <?php if ($auth->hasRole('Administrador del Sistema')): ?>
            <!-- Sistema (Solo Administrador del Sistema) -->
            <h3 class="section-title">
                <i class="fas fa-cog"></i> Configuración del Sistema
            </h3>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card admin-card bg-danger text-white">
                        <div class="card-body">
                            <i class="fas fa-building"></i>
                            <h5 class="card-title">Empresas</h5>
                            <p class="card-text">Gestionar empresas del sistema</p>
                            <a href="<?= BASE_URL ?>/admin/companies.php" class="btn btn-light">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card admin-card bg-warning text-dark">
                        <div class="card-body">
                            <i class="fas fa-plug"></i>
                            <h5 class="card-title">Configuración API</h5>
                            <p class="card-text">APIs externas y webhooks</p>
                            <a href="<?= BASE_URL ?>/admin/api_settings.php" class="btn btn-dark">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card admin-card bg-info text-white">
                        <div class="card-body">
                            <i class="fas fa-envelope"></i>
                            <h5 class="card-title">Configuración Email</h5>
                            <p class="card-text">SMTP y correos electrónicos</p>
                            <a href="<?= BASE_URL ?>/admin/email_settings.php" class="btn btn-light">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Empresa (Administrador del Sistema y Administrador de Empresa) -->
        <?php if ($auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
            <h3 class="section-title">
                <i class="fas fa-briefcase"></i> Gestión de Empresa
            </h3>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card admin-card border-primary">
                        <div class="card-body">
                            <i class="fas fa-users text-primary"></i>
                            <h5 class="card-title">Usuarios</h5>
                            <p class="card-text">Gestionar usuarios y roles</p>
                            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card admin-card border-success">
                        <div class="card-body">
                            <i class="fas fa-cog text-success"></i>
                            <h5 class="card-title">Configuración</h5>
                            <p class="card-text">Ajustes de la empresa</p>
                            <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-success">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card admin-card border-warning">
                        <div class="card-body">
                            <i class="fas fa-exchange-alt text-warning"></i>
                            <h5 class="card-title">Tipo de Cambio</h5>
                            <p class="card-text">Configurar tipo de cambio</p>
                            <a href="<?= BASE_URL ?>/admin/exchange_rate.php" class="btn btn-warning">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card admin-card border-info">
                        <div class="card-body">
                            <i class="fas fa-university text-info"></i>
                            <h5 class="card-title">Cuentas Bancarias</h5>
                            <p class="card-text">Gestionar cuentas bancarias</p>
                            <a href="<?= BASE_URL ?>/admin/bank_accounts.php" class="btn btn-info">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card admin-card border-warning">
                        <div class="card-body">
                            <i class="fas fa-tags text-warning"></i>
                            <h5 class="card-title">Logos de Marcas</h5>
                            <p class="card-text">Marcas que aparecen en el PDF</p>
                            <a href="<?= BASE_URL ?>/admin/brand_logos.php" class="btn btn-warning">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card admin-card border-secondary">
                        <div class="card-body">
                            <i class="fas fa-address-book text-secondary"></i>
                            <h5 class="card-title">Clientes</h5>
                            <p class="card-text">Gestionar clientes</p>
                            <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card admin-card border-warning" style="border-width: 2px !important;">
                        <div class="card-body">
                            <i class="fas fa-bolt text-warning"></i>
                            <h5 class="card-title">Plantillas CotiRapi</h5>
                            <p class="card-text">Gestionar plantillas de cotización rápida</p>
                            <a href="<?= BASE_URL ?>/admin/cotirapi_templates.php" class="btn btn-warning">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="section-title">
                <i class="fas fa-tasks"></i> Flujos de Trabajo
            </h3>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card admin-card border-info">
                        <div class="card-body">
                            <i class="fas fa-credit-card text-info"></i>
                            <h5 class="card-title">Créditos y Cobranzas</h5>
                            <p class="card-text">Aprobar solicitudes de crédito</p>
                            <a href="<?= BASE_URL ?>/credits/pending.php" class="btn btn-info">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card admin-card border-success">
                        <div class="card-body">
                            <i class="fas fa-file-invoice-dollar text-success"></i>
                            <h5 class="card-title">Facturación</h5>
                            <p class="card-text">Procesar solicitudes de facturación</p>
                            <a href="<?= BASE_URL ?>/billing/pending.php" class="btn btn-success">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="section-title">
                <i class="fas fa-boxes"></i> Inventario y Productos
            </h3>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card admin-card border-warning">
                        <div class="card-body">
                            <i class="fas fa-box text-warning"></i>
                            <h5 class="card-title">Productos</h5>
                            <p class="card-text">Catálogo de productos</p>
                            <a href="<?= BASE_URL ?>/admin/products.php" class="btn btn-warning">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card admin-card border-dark">
                        <div class="card-body">
                            <i class="fas fa-warehouse text-dark"></i>
                            <h5 class="card-title">Almacenes</h5>
                            <p class="card-text">Gestionar almacenes</p>
                            <a href="<?= BASE_URL ?>/admin/warehouses.php" class="btn btn-dark">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card admin-card border-danger">
                        <div class="card-body">
                            <i class="fas fa-clipboard-list text-danger"></i>
                            <h5 class="card-title">Stock</h5>
                            <p class="card-text">Gestión de inventario</p>
                            <a href="<?= BASE_URL ?>/admin/stock_management.php" class="btn btn-danger">
                                <i class="fas fa-arrow-right"></i> Acceder
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Botón de regreso -->
        <div class="row mt-5">
            <div class="col-12 text-center">
                <a href="<?= BASE_URL ?>/dashboard_simple.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-home"></i> Volver al Dashboard
                </a>
            </div>
        </div>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
