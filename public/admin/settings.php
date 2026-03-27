<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a esta sección';
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$companySettings = new CompanySettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = false;

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_company':
                $companyData = [
                    'name' => $_POST['company_name'],
                    'tax_id' => $_POST['tax_id'],
                    'address' => $_POST['address'],
                    'phone' => $_POST['phone'],
                    'email' => $_POST['email'],
                    'website' => $_POST['website'],
                    'whatsapp' => $_POST['whatsapp']
                ];
                $result = $companySettings->updateCompanyInfo($companyId, $companyData);
                break;

            case 'upload_logo':
                if (!empty($_FILES['logo']['name'])) {
                    $uploadDir = PUBLIC_PATH . '/uploads/company';
                    $uploadResult = $companySettings->handleFileUpload($_FILES['logo'], $uploadDir);
                    if ($uploadResult['success']) {
                        $logoPath = 'uploads/company/' . $uploadResult['filename'];
                        $result = $companySettings->updateLogo($companyId, $logoPath);
                    } else {
                        $_SESSION['error_message'] = 'Error al subir logo: ' . $uploadResult['message'];
                        $auth->redirect($_SERVER['REQUEST_URI']);
                    }
                } else {
                    $_SESSION['error_message'] = 'No se seleccionó ningún archivo para el logo';
                    $auth->redirect($_SERVER['REQUEST_URI']);
                }
                break;

            case 'upload_favicon':
                if (!empty($_FILES['favicon']['name'])) {
                    $uploadDir = PUBLIC_PATH . '/uploads/company';
                    $uploadResult = $companySettings->handleFileUpload($_FILES['favicon'], $uploadDir, ['ico', 'png', 'jpg', 'jpeg']);
                    if ($uploadResult['success']) {
                        $faviconPath = 'uploads/company/' . $uploadResult['filename'];
                        $result = $companySettings->updateFavicon($companyId, $faviconPath);
                    } else {
                        $_SESSION['error_message'] = 'Error al subir favicon: ' . $uploadResult['message'];
                        $auth->redirect($_SERVER['REQUEST_URI']);
                    }
                } else {
                    $_SESSION['error_message'] = 'No se seleccionó ningún archivo para el favicon';
                    $auth->redirect($_SERVER['REQUEST_URI']);
                }
                break;

            case 'update_quotation_settings':
                $enableDiscounts = isset($_POST['enable_discounts']) ? '1' : '0';
                $result = $companySettings->updateSetting($companyId, 'quotation_enable_discounts', $enableDiscounts);
                // PDF header color
                $pdfColor = $_POST['pdf_header_color'] ?? '#1B3A6B';
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $pdfColor)) {
                    $companySettings->updateSetting($companyId, 'pdf_header_color', $pdfColor);
                }
                break;

        }

        if ($result) {
            $_SESSION['success_message'] = 'Configuración actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar la configuración';
        }
        $auth->redirect($_SERVER['REQUEST_URI']);
    }
}

// Get company data from settings table
$company = $companySettings->getCompanyInfo($companyId);

// Get quotation settings
$enableDiscounts = $companySettings->getSetting($companyId, 'quotation_enable_discounts');
$enableDiscounts = $enableDiscounts === '1';
$pdfHeaderColor  = $companySettings->getSetting($companyId, 'pdf_header_color') ?: '#1B3A6B';

$pageTitle = 'Configuración de Empresa';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
    /* EMERGENCY LIGHT THEME ENFORCEMENT - Override ANY dark styles */
    html, body {
        background-color: #ffffff !important;
        color: #212529 !important;
    }

    html[data-theme="dark"] body {
        background-color: #121212 !important;
        color: #e0e0e0 !important;
    }

    /* Force all components to light theme unless in dark mode */
    body:not([data-theme="dark"]) * {
        --bs-body-bg: #ffffff !important;
        --bs-body-color: #212529 !important;
        --bs-border-color: #dee2e6 !important;
    }

    /* Ultra-specific overrides for stubborn dark elements */
    body:not([data-theme="dark"]) .card,
    body:not([data-theme="dark"]) .modal-content,
    body:not([data-theme="dark"]) .form-control,
    body:not([data-theme="dark"]) .form-select,
    body:not([data-theme="dark"]) .table,
    body:not([data-theme="dark"]) .table td,
    body:not([data-theme="dark"]) .table th,
    body:not([data-theme="dark"]) .dropdown-menu,
    body:not([data-theme="dark"]) .list-group-item,
    body:not([data-theme="dark"]) .page-link,
    body:not([data-theme="dark"]) .breadcrumb,
    body:not([data-theme="dark"]) .accordion-item,
    body:not([data-theme="dark"]) .offcanvas,
    body:not([data-theme="dark"]) .toast {
        background-color: #ffffff !important;
        color: #212529 !important;
        border-color: #dee2e6 !important;
    }

    /* Force navbar to be blue with white text */
    .navbar,
    .navbar-dark,
    .navbar-light {
        background-color: #0d6efd !important;
    }

    .navbar .navbar-brand,
    .navbar .navbar-nav .nav-link,
    .navbar-dark .navbar-brand,
    .navbar-dark .navbar-nav .nav-link,
    .navbar-light .navbar-brand,
    .navbar-light .navbar-nav .nav-link {
        color: #ffffff !important;
    }
    </style>

    <script>
    // Emergency theme enforcement
    (function() {
        // Remove any dark theme attributes on page load
        document.documentElement.removeAttribute('data-theme');

        // Set light theme in localStorage if not explicitly dark
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme !== 'dark') {
            localStorage.setItem('theme', 'light');
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Force body styles
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'light') {
                document.body.style.backgroundColor = '#ffffff';
                document.body.style.color = '#212529';
            }
        });
    })();
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>
            <div class="navbar-nav ms-auto">
                <button class="theme-toggle me-3" id="themeToggle" title="Cambiar tema">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/dashboard_simple.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">Panel Admin</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Configuración</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-building"></i> Empresa
                        </a>
                        <a href="<?= BASE_URL ?>/admin/exchange_rate.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-exchange-alt"></i> Tipo de Cambio
                        </a>
                        <a href="<?= BASE_URL ?>/admin/email_settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                        <a href="<?= BASE_URL ?>/admin/bank_accounts.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-university"></i> Cuentas Bancarias
                        </a>
                        <a href="<?= BASE_URL ?>/admin/brand_logos.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tags"></i> Logos de Marcas
                        </a>
                        <a href="<?= BASE_URL ?>/admin/api_settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plug"></i> APIs
                        </a>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users"></i> Usuarios
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <h1><i class="fas fa-building"></i> <?= $pageTitle ?></h1>

                <!-- Company Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Información de la Empresa</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_company">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Nombre de la Empresa *</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name"
                                               value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tax_id" class="form-label">RUC</label>
                                        <input type="text" class="form-control" id="tax_id" name="tax_id"
                                               value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>" maxlength="11">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="website" class="form-label">Sitio Web</label>
                                        <input type="url" class="form-control" id="website" name="website"
                                               value="<?= htmlspecialchars($company['website'] ?? '') ?>"
                                               placeholder="https://www.ejemplo.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="whatsapp" class="form-label">WhatsApp</label>
                                        <input type="tel" class="form-control" id="whatsapp" name="whatsapp"
                                               value="<?= htmlspecialchars($company['whatsapp'] ?? '') ?>"
                                               placeholder="+51 999 999 999">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Información
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Logo and Favicon -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Logo de la Empresa</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!empty($company['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>"
                                         alt="Logo" class="img-fluid mb-3" style="max-height: 150px;">
                                <?php else: ?>
                                    <div class="bg-light p-4 mb-3">
                                        <i class="fas fa-image fa-3x text-muted"></i>
                                        <p class="text-muted mt-2">No hay logo cargado</p>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_logo">
                                    <div class="mb-3">
                                        <input type="file" class="form-control" name="logo" accept="image/*" required>
                                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 2MB. Se usa como ícono de la PWA al instalar la app.</small>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-upload"></i> Subir Logo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Favicon</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!empty($company['favicon_url'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($company['favicon_url']) ?>"
                                         alt="Favicon" class="mb-3" style="width: 64px; height: 64px;">
                                <?php else: ?>
                                    <div class="bg-light p-4 mb-3">
                                        <i class="fas fa-star fa-2x text-muted"></i>
                                        <p class="text-muted mt-2">No hay favicon cargado</p>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_favicon">
                                    <div class="mb-3">
                                        <input type="file" class="form-control" name="favicon" accept=".ico,.png,.jpg,.jpeg" required>
                                        <small class="text-muted">Formatos: ICO, PNG, JPG. Recomendado: PNG 64×64px o mayor. Se usa como favicon del navegador.</small>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-upload"></i> Subir Favicon
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quotation Settings -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Configuración de Cotizaciones</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_quotation_settings">

                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Columnas en Cotizaciones</h6>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_discounts"
                                               name="enable_discounts" <?= $enableDiscounts ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_discounts">
                                            Habilitar columna de descuento (%)
                                        </label>
                                    </div>
                                    <small class="text-muted">
                                        Permite agregar descuentos por producto y descuento global en las cotizaciones.
                                    </small>
                                </div>

                                <div class="col-md-6">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Información</h6>
                                        <p class="mb-1"><strong>Estado actual:</strong> <?= $enableDiscounts ? 'Activado' : 'Desactivado' ?></p>
                                        <p class="mb-0"><small>Esta configuración afecta todas las cotizaciones nuevas.</small></p>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-palette me-1"></i>Color del PDF</h6>
                                    <p class="text-muted small mb-2">Color principal del encabezado de tabla, totales y separadores en el PDF de cotización.</p>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" class="form-control form-control-color"
                                               id="pdf_header_color" name="pdf_header_color"
                                               value="<?= htmlspecialchars($pdfHeaderColor) ?>"
                                               style="width:60px; height:40px; cursor:pointer;">
                                        <div>
                                            <span class="badge" id="colorPreviewBadge"
                                                  style="background-color:<?= htmlspecialchars($pdfHeaderColor) ?>; font-size:0.85rem; padding:6px 14px;">
                                                <?= htmlspecialchars($pdfHeaderColor) ?>
                                            </span>
                                            <br><small class="text-muted">Vista previa del color</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-light border mt-2">
                                        <small><i class="fas fa-info-circle text-primary me-1"></i>
                                        Este color se aplica a: cabeceras de tabla, caja de número de cotización, fila TOTAL IMPORTE y línea separadora de marcas en el PDF.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Configuración
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Company Preview -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Vista Previa</h5>
                    </div>
                    <div class="card-body">
                        <div class="border p-4 text-center bg-light">
                            <?php if (!empty($company['logo_url'])): ?>
                                <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>"
                                     alt="Logo" style="max-height: 60px;" class="mb-2">
                            <?php endif; ?>
                            <h4><?= htmlspecialchars($company['name'] ?? 'Nombre de la Empresa') ?></h4>
                            <?php if ($company['tax_id']): ?>
                                <p class="mb-1"><strong>RUC:</strong> <?= htmlspecialchars($company['tax_id']) ?></p>
                            <?php endif; ?>
                            <?php if ($company['address']): ?>
                                <p class="mb-1"><?= nl2br(htmlspecialchars($company['address'])) ?></p>
                            <?php endif; ?>
                            <div class="row justify-content-center">
                                <?php if ($company['email']): ?>
                                    <div class="col-auto">
                                        <small><i class="fas fa-envelope"></i> <?= htmlspecialchars($company['email']) ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($company['phone']): ?>
                                    <div class="col-auto">
                                        <small><i class="fas fa-phone"></i> <?= htmlspecialchars($company['phone']) ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($company['website']): ?>
                                    <div class="col-auto">
                                        <small><i class="fas fa-globe"></i> <?= htmlspecialchars($company['website']) ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($company['whatsapp']) && $company['whatsapp']): ?>
                                    <div class="col-auto">
                                        <small><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($company['whatsapp']) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create uploads directory if needed
        <?php
        $uploadDir = PUBLIC_PATH . '/uploads/company';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        ?>
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <script>
    const colorInput = document.getElementById('pdf_header_color');
    const colorBadge = document.getElementById('colorPreviewBadge');
    if (colorInput && colorBadge) {
        colorInput.addEventListener('input', function() {
            colorBadge.style.backgroundColor = this.value;
            colorBadge.textContent = this.value.toUpperCase();
        });
    }
    </script>
</body>
</html>