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
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_sunat_api':
                $sunatConfig = [
                    'sunat_api_enabled' => isset($_POST['sunat_api_enabled']) ? 1 : 0,
                    'sunat_api_url' => $_POST['sunat_api_url'] ?? '',
                    'sunat_api_token' => $_POST['sunat_api_token'] ?? '',
                    'sunat_timeout' => $_POST['sunat_timeout'] ?? 30
                ];
                $result = $companySettings->updateApiSettings($companyId, 'sunat', $sunatConfig);
                break;

            case 'update_reniec_api':
                $reniecConfig = [
                    'reniec_api_enabled' => isset($_POST['reniec_api_enabled']) ? 1 : 0,
                    'reniec_api_url' => $_POST['reniec_api_url'] ?? '',
                    'reniec_api_token' => $_POST['reniec_api_token'] ?? '',
                    'reniec_timeout' => $_POST['reniec_timeout'] ?? 30
                ];
                $result = $companySettings->updateApiSettings($companyId, 'reniec', $reniecConfig);
                break;

            case 'update_whatsapp':
                $whatsappConfig = [
                    'whatsapp_api_enabled' => isset($_POST['whatsapp_api_enabled']) ? 1 : 0,
                    'whatsapp_api_url' => $_POST['whatsapp_api_url'] ?? '',
                    'whatsapp_api_token' => $_POST['whatsapp_api_token'] ?? '',
                    'whatsapp_phone' => $_POST['whatsapp_phone'] ?? ''
                ];
                $result = $companySettings->updateApiSettings($companyId, 'whatsapp', $whatsappConfig);
                break;
        }

        if ($result ?? false) {
            $_SESSION['success_message'] = 'Configuración de API actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar la configuración de API';
        }
        $auth->redirect($_SERVER['REQUEST_URI']);
    }
}

// Get current API settings
$sunatSettings = $companySettings->getApiSettings($companyId, 'sunat');
$reniecSettings = $companySettings->getApiSettings($companyId, 'reniec');
$whatsappSettings = $companySettings->getApiSettings($companyId, 'whatsapp');

$pageTitle = 'Configuración de APIs';
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
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> Empresa
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
                        <a href="<?= BASE_URL ?>/admin/api_settings.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-plug"></i> APIs
                        </a>
                        <a href="<?= BASE_URL ?>/admin/import_products.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-upload"></i> Importar Productos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <h1><i class="fas fa-plug"></i> <?= $pageTitle ?></h1>

                <!-- SUNAT API Configuration -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-building"></i> API SUNAT - Consulta RUC</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_sunat_api">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sunat_api_enabled" name="sunat_api_enabled"
                                               <?= ($sunatSettings['sunat_api_enabled'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="sunat_api_enabled">
                                            Habilitar consultas automáticas de RUC
                                        </label>
                                    </div>
                                    <small class="text-muted">Permite validar y obtener datos de empresas por RUC</small>
                                </div>
                            </div>

                            <div id="sunat-config" style="<?= ($sunatSettings['sunat_api_enabled'] ?? 0) ? '' : 'display: none;' ?>">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="sunat_api_url" class="form-label">URL del API *</label>
                                            <input type="url" class="form-control" id="sunat_api_url" name="sunat_api_url"
                                                   value="<?= htmlspecialchars($sunatSettings['sunat_api_url'] ?? 'https://dniruc.apisperu.com/api/v1/ruc/{ruc}') ?>"
                                                   placeholder="https://api.sunat.gob.pe/v1/contribuyente/ruc/{ruc}">
                                            <small class="text-muted">Usa {ruc} como placeholder para el número de RUC</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="sunat_timeout" class="form-label">Timeout (segundos)</label>
                                            <input type="number" class="form-control" id="sunat_timeout" name="sunat_timeout"
                                                   value="<?= htmlspecialchars($sunatSettings['sunat_timeout'] ?? '30') ?>"
                                                   min="5" max="120">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="sunat_api_token" class="form-label">Token de API</label>
                                    <input type="password" class="form-control" id="sunat_api_token" name="sunat_api_token"
                                           value="<?= htmlspecialchars($sunatSettings['sunat_api_token'] ?? '') ?>"
                                           placeholder="Bearer token o API key">
                                    <small class="text-muted">Dejar vacío si el API no requiere autenticación</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Configuración SUNAT
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="testSunatApi()">
                                <i class="fas fa-test-tube"></i> Probar API
                            </button>
                        </form>
                        <div id="sunat-test-result" class="mt-3"></div>
                    </div>
                </div>

                <!-- RENIEC API Configuration -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-id-card"></i> API RENIEC - Consulta DNI</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_reniec_api">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="reniec_api_enabled" name="reniec_api_enabled"
                                               <?= ($reniecSettings['reniec_api_enabled'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="reniec_api_enabled">
                                            Habilitar consultas automáticas de DNI
                                        </label>
                                    </div>
                                    <small class="text-muted">Permite validar y obtener datos de personas por DNI</small>
                                </div>
                            </div>

                            <div id="reniec-config" style="<?= ($reniecSettings['reniec_api_enabled'] ?? 0) ? '' : 'display: none;' ?>">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="reniec_api_url" class="form-label">URL del API *</label>
                                            <input type="url" class="form-control" id="reniec_api_url" name="reniec_api_url"
                                                   value="<?= htmlspecialchars($reniecSettings['reniec_api_url'] ?? 'https://dniruc.apisperu.com/api/v1/dni/{dni}') ?>"
                                                   placeholder="https://api.reniec.gob.pe/v1/persona/dni/{dni}">
                                            <small class="text-muted">Usa {dni} como placeholder para el número de DNI</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="reniec_timeout" class="form-label">Timeout (segundos)</label>
                                            <input type="number" class="form-control" id="reniec_timeout" name="reniec_timeout"
                                                   value="<?= htmlspecialchars($reniecSettings['reniec_timeout'] ?? '30') ?>"
                                                   min="5" max="120">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="reniec_api_token" class="form-label">Token de API</label>
                                    <input type="password" class="form-control" id="reniec_api_token" name="reniec_api_token"
                                           value="<?= htmlspecialchars($reniecSettings['reniec_api_token'] ?? '') ?>"
                                           placeholder="Bearer token o API key">
                                    <small class="text-muted">Dejar vacío si el API no requiere autenticación</small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración RENIEC
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="testReniecApi()">
                                <i class="fas fa-test-tube"></i> Probar API
                            </button>
                        </form>
                        <div id="reniec-test-result" class="mt-3"></div>
                    </div>
                </div>

                <!-- WhatsApp API Configuration -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fab fa-whatsapp"></i> API WhatsApp - Envío de Mensajes</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_whatsapp">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="whatsapp_api_enabled" name="whatsapp_api_enabled"
                                               <?= ($whatsappSettings['whatsapp_api_enabled'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="whatsapp_api_enabled">
                                            Habilitar envío de cotizaciones por WhatsApp
                                        </label>
                                    </div>
                                    <small class="text-muted">Permite enviar cotizaciones directamente por WhatsApp</small>
                                </div>
                            </div>

                            <div id="whatsapp-config" style="<?= ($whatsappSettings['whatsapp_api_enabled'] ?? 0) ? '' : 'display: none;' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="whatsapp_api_url" class="form-label">URL del API *</label>
                                            <input type="url" class="form-control" id="whatsapp_api_url" name="whatsapp_api_url"
                                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_api_url'] ?? '') ?>"
                                                   placeholder="https://api.whatsapp.com/send">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="whatsapp_phone" class="form-label">Número de WhatsApp</label>
                                            <input type="tel" class="form-control" id="whatsapp_phone" name="whatsapp_phone"
                                                   value="<?= htmlspecialchars($whatsappSettings['whatsapp_phone'] ?? '') ?>"
                                                   placeholder="+51987654321">
                                            <small class="text-muted">Número con código de país (ej: +51987654321)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="whatsapp_api_token" class="form-label">Token de API</label>
                                    <input type="password" class="form-control" id="whatsapp_api_token" name="whatsapp_api_token"
                                           value="<?= htmlspecialchars($whatsappSettings['whatsapp_api_token'] ?? '') ?>"
                                           placeholder="Token de WhatsApp Business API">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Guardar Configuración WhatsApp
                            </button>
                        </form>
                    </div>
                </div>

                <!-- API Status and Usage -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Estado y Uso de APIs</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="mb-2">
                                        <i class="fas fa-building fa-2x <?= ($sunatSettings['sunat_api_enabled'] ?? 0) ? 'text-success' : 'text-muted' ?>"></i>
                                    </div>
                                    <h6>SUNAT API</h6>
                                    <span class="badge <?= ($sunatSettings['sunat_api_enabled'] ?? 0) ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ($sunatSettings['sunat_api_enabled'] ?? 0) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="mb-2">
                                        <i class="fas fa-id-card fa-2x <?= ($reniecSettings['reniec_api_enabled'] ?? 0) ? 'text-primary' : 'text-muted' ?>"></i>
                                    </div>
                                    <h6>RENIEC API</h6>
                                    <span class="badge <?= ($reniecSettings['reniec_api_enabled'] ?? 0) ? 'bg-primary' : 'bg-secondary' ?>">
                                        <?= ($reniecSettings['reniec_api_enabled'] ?? 0) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="mb-2">
                                        <i class="fab fa-whatsapp fa-2x <?= ($whatsappSettings['whatsapp_api_enabled'] ?? 0) ? 'text-success' : 'text-muted' ?>"></i>
                                    </div>
                                    <h6>WhatsApp API</h6>
                                    <span class="badge <?= ($whatsappSettings['whatsapp_api_enabled'] ?? 0) ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ($whatsappSettings['whatsapp_api_enabled'] ?? 0) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-12">
                                <h6>¿Cómo funcionan las APIs?</h6>
                                <ul class="small">
                                    <li><strong>SUNAT:</strong> Al crear/editar clientes, si ingresas un RUC, se consultará automáticamente la información de la empresa</li>
                                    <li><strong>RENIEC:</strong> Al crear/editar clientes, si ingresas un DNI, se consultará automáticamente el nombre de la persona</li>
                                    <li><strong>WhatsApp:</strong> En las cotizaciones aparecerá un botón para enviar directamente por WhatsApp</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle API configurations visibility
        document.getElementById('sunat_api_enabled').addEventListener('change', function() {
            const config = document.getElementById('sunat-config');
            config.style.display = this.checked ? 'block' : 'none';
        });

        document.getElementById('reniec_api_enabled').addEventListener('change', function() {
            const config = document.getElementById('reniec-config');
            config.style.display = this.checked ? 'block' : 'none';
        });

        document.getElementById('whatsapp_api_enabled').addEventListener('change', function() {
            const config = document.getElementById('whatsapp-config');
            config.style.display = this.checked ? 'block' : 'none';
        });

        // Test SUNAT API
        function testSunatApi() {
            const resultDiv = document.getElementById('sunat-test-result');
            const testRuc = prompt('Ingresa un RUC para probar (ej: 20100070970):');

            if (!testRuc || testRuc.length !== 11) {
                resultDiv.innerHTML = '<div class="alert alert-warning">Por favor ingresa un RUC válido de 11 dígitos</div>';
                return;
            }

            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Probando conexión con SUNAT...</div>';

            fetch('<?= BASE_URL ?>/admin/test_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=sunat&document=${testRuc}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check"></i> <strong>Conexión exitosa</strong><br>
                            <strong>Empresa:</strong> ${data.data.razonSocial || 'N/A'}<br>
                            <strong>Estado:</strong> ${data.data.estado || 'N/A'}
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error de conexión</div>';
            });
        }

        // Test RENIEC API
        function testReniecApi() {
            const resultDiv = document.getElementById('reniec-test-result');
            const testDni = prompt('Ingresa un DNI para probar (ej: 12345678):');

            if (!testDni || testDni.length !== 8) {
                resultDiv.innerHTML = '<div class="alert alert-warning">Por favor ingresa un DNI válido de 8 dígitos</div>';
                return;
            }

            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Probando conexión con RENIEC...</div>';

            fetch('<?= BASE_URL ?>/admin/test_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=reniec&document=${testDni}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check"></i> <strong>Conexión exitosa</strong><br>
                            <strong>Nombres:</strong> ${data.data.nombres || 'N/A'}<br>
                            <strong>Apellidos:</strong> ${data.data.apellidoPaterno || 'N/A'} ${data.data.apellidoMaterno || 'N/A'}
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error de conexión</div>';
            });
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>