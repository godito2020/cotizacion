<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'Sistema de Cotizaciones' ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <?php if (isset($customCSS)): ?>
        <?php foreach ($customCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Favicon -->
    <?php if (!empty($companyData['favicon_url'])): ?>
        <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/<?= $companyData['favicon_url'] ?>">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <!-- Logo and Brand -->
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/dashboard.php">
                <?php if (!empty($companyData['logo_url'])): ?>
                    <img src="<?= BASE_URL ?>/<?= $companyData['logo_url'] ?>" alt="Logo" height="40" class="me-2">
                <?php endif; ?>
                <span><?= $companyData['name'] ?? 'Cotizaciones' ?></span>
            </a>

            <!-- Mobile menu toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($auth->isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>

                        <!-- User Panel Navigation -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="quotationDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-file-invoice"></i> Cotizaciones
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/create.php">Nueva Cotización</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/index.php">Mis Cotizaciones</a></li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/customers/index.php">
                                <i class="fas fa-users"></i> Clientes
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/products/index.php">
                                <i class="fas fa-box"></i> Productos
                            </a>
                        </li>

                        <!-- Admin Panel Navigation -->
                        <?php if ($auth->hasRole(['System Admin', 'Company Admin'])): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog"></i> Administración
                                </a>
                                <ul class="dropdown-menu">
                                    <?php if ($auth->hasRole('System Admin')): ?>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/companies.php">Empresas</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/settings.php">Configuración</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/email_settings.php">Correo</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/bank_accounts.php">Cuentas Bancarias</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/api_settings.php">APIs</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/import_products.php">Importar Productos</a></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/stock_management.php">Stock</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <!-- User menu -->
                <?php if ($auth->isLoggedIn()): ?>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($userData['username'] ?? 'Usuario') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">
                                    <i class="fas fa-user-edit"></i> Mi Perfil
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid py-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['warning_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>

        <!-- Page Content -->
        <?php if (isset($content)): ?>
            <?= $content ?>
        <?php else: ?>
            <!-- Content will be included here -->
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-light border-top mt-5 py-4">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        © <?= date('Y') ?> <?= $companyData['name'] ?? 'Sistema de Cotizaciones' ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-muted">
                        <small>Desarrollado con ❤️ para tu empresa</small>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>

    <?php if (isset($customJS)): ?>
        <?php foreach ($customJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Page specific scripts -->
    <?php if (isset($pageScripts)): ?>
        <script>
            <?= $pageScripts ?>
        </script>
    <?php endif; ?>
</body>
</html>