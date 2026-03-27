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
    $emailConfig = [
        'smtp_host' => $_POST['smtp_host'] ?? '',
        'smtp_port' => $_POST['smtp_port'] ?? 587,
        'smtp_username' => $_POST['smtp_username'] ?? '',
        'smtp_password' => $_POST['smtp_password'] ?? '',
        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
        'from_email' => $_POST['from_email'] ?? '',
        'from_name' => $_POST['from_name'] ?? '',
        'reply_to_email' => $_POST['reply_to_email'] ?? '',
        'use_smtp' => isset($_POST['use_smtp']) ? 1 : 0
    ];

    $result = $companySettings->updateEmailSettings($companyId, $emailConfig);

    if ($result) {
        $_SESSION['success_message'] = 'Configuración de email actualizada correctamente';
    } else {
        $_SESSION['error_message'] = 'Error al actualizar la configuración de email';
    }
    $auth->redirect($_SERVER['REQUEST_URI']);
}

// Get current email settings
$emailSettings = $companySettings->getEmailSettings($companyId);

$pageTitle = 'Configuración de Email';
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
                        <a href="<?= BASE_URL ?>/admin/email_settings.php" class="list-group-item list-group-item-action active">
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
                        <a href="<?= BASE_URL ?>/admin/import_products.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-upload"></i> Importar Productos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <h1><i class="fas fa-envelope"></i> <?= $pageTitle ?></h1>

                <!-- Email Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Configuración SMTP</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="use_smtp" name="use_smtp"
                                               <?= ($emailSettings['use_smtp'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_smtp">
                                            Usar servidor SMTP personalizado
                                        </label>
                                    </div>
                                    <small class="text-muted">Si está desactivado, se usará la función mail() de PHP</small>
                                </div>
                            </div>

                            <div id="smtp-config" style="<?= ($emailSettings['use_smtp'] ?? 0) ? '' : 'display: none;' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label">Servidor SMTP *</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                   value="<?= htmlspecialchars($emailSettings['smtp_host'] ?? '') ?>"
                                                   placeholder="smtp.gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label">Puerto *</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                                   value="<?= htmlspecialchars($emailSettings['smtp_port'] ?? '587') ?>"
                                                   min="1" max="65535">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="smtp_encryption" class="form-label">Encriptación *</label>
                                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                <option value="none" <?= ($emailSettings['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>Ninguna</option>
                                                <option value="tls" <?= ($emailSettings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                <option value="ssl" <?= ($emailSettings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label">Usuario SMTP *</label>
                                            <input type="email" class="form-control" id="smtp_username" name="smtp_username"
                                                   value="<?= htmlspecialchars($emailSettings['smtp_username'] ?? '') ?>"
                                                   placeholder="tu-email@gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">Contraseña SMTP *</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                                   value="<?= htmlspecialchars($emailSettings['smtp_password'] ?? '') ?>"
                                                   placeholder="••••••••••••••••">
                                            <small class="text-muted">Para Gmail, usa una contraseña de aplicación</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="from_email" class="form-label">Email Remitente *</label>
                                        <input type="email" class="form-control" id="from_email" name="from_email"
                                               value="<?= htmlspecialchars($emailSettings['from_email'] ?? '') ?>"
                                               placeholder="noreply@tuempresa.com" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="from_name" class="form-label">Nombre Remitente *</label>
                                        <input type="text" class="form-control" id="from_name" name="from_name"
                                               value="<?= htmlspecialchars($emailSettings['from_name'] ?? '') ?>"
                                               placeholder="Tu Empresa" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="reply_to_email" class="form-label">Email de Respuesta</label>
                                        <input type="email" class="form-control" id="reply_to_email" name="reply_to_email"
                                               value="<?= htmlspecialchars($emailSettings['reply_to_email'] ?? '') ?>"
                                               placeholder="contacto@tuempresa.com">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Test Email -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Probar Configuración</h5>
                    </div>
                    <div class="card-body">
                        <form id="test-email-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="test_email" class="form-label">Email de Prueba</label>
                                        <input type="email" class="form-control" id="test_email" name="test_email"
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                               placeholder="test@ejemplo.com" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div>
                                            <button type="button" class="btn btn-info me-2" onclick="testEmail()">
                                                <i class="fas fa-paper-plane"></i> Enviar Email de Prueba
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm me-2" onclick="runDiagnostics()">
                                                <i class="fas fa-stethoscope"></i> Diagnóstico SMTP
                                            </button>
                                            <a href="<?= BASE_URL ?>/admin/email_debug.php" class="btn btn-outline-secondary btn-sm" target="_blank">
                                                <i class="fas fa-bug"></i> Debug Console
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div id="test-result"></div>
                    </div>
                </div>

                <!-- Email Templates Preview -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Plantillas de Email</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-secondary">
                                    <div class="card-header bg-secondary text-white">
                                        <h6 class="mb-0">Envío de Cotización</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Asunto:</strong> Nueva Cotización #{numero}</p>
                                        <p><strong>Contenido:</strong> Email con cotización adjunta en PDF</p>
                                        <small class="text-muted">Se envía automáticamente cuando se marca una cotización como "Enviada"</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0">Seguimiento de Cliente</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Asunto:</strong> Seguimiento - Cotización #{numero}</p>
                                        <p><strong>Contenido:</strong> Email de seguimiento personalizable</p>
                                        <small class="text-muted">Para hacer seguimiento a cotizaciones enviadas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle SMTP configuration visibility
        document.getElementById('use_smtp').addEventListener('change', function() {
            const smtpConfig = document.getElementById('smtp-config');
            if (this.checked) {
                smtpConfig.style.display = 'block';
            } else {
                smtpConfig.style.display = 'none';
            }
        });

        // Test email function
        function testEmail() {
            const testEmail = document.getElementById('test_email').value;
            const resultDiv = document.getElementById('test-result');

            if (!testEmail) {
                resultDiv.innerHTML = '<div class="alert alert-warning">Por favor ingresa un email para la prueba</div>';
                return;
            }

            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Enviando email de prueba...</div>';

            fetch('<?= BASE_URL ?>/admin/test_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `test_email=${encodeURIComponent(testEmail)}&debug=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<div class="alert alert-success"><i class="fas fa-check"></i> Email enviado exitosamente</div>';
                    if (data.debug_logs && data.debug_logs.length > 0) {
                        html += buildDebugLogHtml(data.debug_logs);
                    }
                    resultDiv.innerHTML = html;
                } else {
                    let html = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ${data.message}</div>`;
                    if (data.debug_logs && data.debug_logs.length > 0) {
                        html += buildDebugLogHtml(data.debug_logs);
                    }
                    resultDiv.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error de conexión. Revisa la consola del navegador para más detalles.</div>';
            });
        }

        function buildDebugLogHtml(logs) {
            let html = '<div class="mt-3"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugLogs" aria-expanded="false">Ver Logs de Debug</button>';
            html += '<div class="collapse mt-2" id="debugLogs">';
            html += '<div class="card"><div class="card-body" style="max-height: 300px; overflow-y: auto;">';
            html += '<pre style="font-size: 11px; margin: 0;">';

            logs.forEach(log => {
                if (log.includes('[ERROR]')) {
                    html += '<span class="text-danger">' + escapeHtml(log) + '</span>\n';
                } else if (log.includes('response:') || log.includes('Response:')) {
                    html += '<span class="text-info">' + escapeHtml(log) + '</span>\n';
                } else {
                    html += escapeHtml(log) + '\n';
                }
            });

            html += '</pre></div></div></div></div>';
            return html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // SMTP Diagnostics function
        function runDiagnostics() {
            const resultDiv = document.getElementById('test-result');

            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Ejecutando diagnóstico SMTP...</div>';

            fetch('<?= BASE_URL ?>/admin/smtp_diagnostics.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Diagnóstico completado</div>';
                        html += buildDiagnosticsHtml(data.diagnostics);
                        resultDiv.innerHTML = html;
                    } else {
                        let html = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Diagnóstico falló: ' + data.message + '</div>';
                        if (data.diagnostics) {
                            html += buildDiagnosticsHtml(data.diagnostics);
                        }
                        resultDiv.innerHTML = html;
                    }
                })
                .catch(error => {
                    console.error('Diagnostics error:', error);
                    resultDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error ejecutando diagnóstico: ' + error.message + '</div>';
                });
        }

        function buildDiagnosticsHtml(diagnostics) {
            let html = '<div class="mt-3"><div class="card"><div class="card-header"><h6>Resultados del Diagnóstico</h6></div><div class="card-body">';

            // Configuration
            if (diagnostics.config) {
                html += '<h6>Configuración:</h6>';
                html += '<ul>';
                html += '<li><strong>Host:</strong> ' + diagnostics.config.host + '</li>';
                html += '<li><strong>Puerto:</strong> ' + diagnostics.config.port + '</li>';
                html += '<li><strong>Encriptación:</strong> ' + diagnostics.config.encryption + '</li>';
                html += '<li><strong>Usuario:</strong> ' + diagnostics.config.username + '</li>';
                html += '<li><strong>Contraseña:</strong> ' + (diagnostics.config.has_password ? '✓ Configurada' : '✗ No configurada') + '</li>';
                html += '</ul>';
            }

            // TCP Test
            if (diagnostics.tcp_test) {
                html += '<h6>Test TCP:</h6>';
                const tcpClass = diagnostics.tcp_test.success ? 'text-success' : 'text-danger';
                const tcpIcon = diagnostics.tcp_test.success ? 'fa-check' : 'fa-times';
                html += '<p class="' + tcpClass + '"><i class="fas ' + tcpIcon + '"></i> ' + diagnostics.tcp_test.message + '</p>';
            }

            // SSL Test
            if (diagnostics.ssl_test) {
                html += '<h6>Test SSL:</h6>';
                const sslClass = diagnostics.ssl_test.success ? 'text-success' : 'text-danger';
                const sslIcon = diagnostics.ssl_test.success ? 'fa-check' : 'fa-times';
                html += '<p class="' + sslClass + '"><i class="fas ' + sslIcon + '"></i> ' + diagnostics.ssl_test.message + '</p>';
            }

            // SMTP Protocol Test
            if (diagnostics.smtp_test) {
                html += '<h6>Test Protocolo SMTP:</h6>';
                const smtpClass = diagnostics.smtp_test.success ? 'text-success' : 'text-danger';
                const smtpIcon = diagnostics.smtp_test.success ? 'fa-check' : 'fa-times';
                html += '<p class="' + smtpClass + '"><i class="fas ' + smtpIcon + '"></i> ' + diagnostics.smtp_test.message + '</p>';

                if (diagnostics.smtp_test.details) {
                    html += '<details><summary>Ver detalles</summary><pre style="font-size: 11px;">';
                    diagnostics.smtp_test.details.forEach(detail => {
                        html += escapeHtml(detail) + '\n';
                    });
                    html += '</pre></details>';
                }
            }

            html += '</div></div></div>';
            return html;
        }

        // Common SMTP configurations helper
        const smtpPresets = {
            gmail: {
                host: 'smtp.gmail.com',
                port: 587,
                encryption: 'tls'
            },
            outlook: {
                host: 'smtp-mail.outlook.com',
                port: 587,
                encryption: 'tls'
            },
            yahoo: {
                host: 'smtp.mail.yahoo.com',
                port: 587,
                encryption: 'tls'
            }
        };

        // Add preset buttons
        document.addEventListener('DOMContentLoaded', function() {
            const smtpHostInput = document.getElementById('smtp_host');
            const helpText = document.createElement('div');
            helpText.className = 'mt-2';
            helpText.innerHTML = `
                <small class="text-muted">Configuraciones comunes:</small>
                <div class="btn-group btn-group-sm mt-1" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="setSmtpPreset('gmail')">Gmail</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setSmtpPreset('outlook')">Outlook</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setSmtpPreset('yahoo')">Yahoo</button>
                </div>
            `;
            smtpHostInput.parentNode.appendChild(helpText);
        });

        function setSmtpPreset(provider) {
            const preset = smtpPresets[provider];
            if (preset) {
                document.getElementById('smtp_host').value = preset.host;
                document.getElementById('smtp_port').value = preset.port;
                document.getElementById('smtp_encryption').value = preset.encryption;
            }
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>