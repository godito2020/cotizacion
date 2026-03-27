<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    http_response_code(403);
    exit('Access denied');
}

$companyId = $auth->getCompanyId();
$logFile = __DIR__ . '/../../logs/email_debug.log';

// Create logs directory if it doesn't exist
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Action handling
$action = $_GET['action'] ?? 'view';

if ($action === 'clear') {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    header('Location: email_debug.php');
    exit;
}

if ($action === 'test') {
    header('Content-Type: application/json');

    try {
        $companySettings = new CompanySettings();
        $emailSettings = $companySettings->getEmailSettings($companyId);

        $testResults = [
            'timestamp' => date('Y-m-d H:i:s'),
            'config_check' => [],
            'connection_test' => null
        ];

        // Configuration checks
        $testResults['config_check']['smtp_enabled'] = $emailSettings['use_smtp'];
        $testResults['config_check']['smtp_host'] = !empty($emailSettings['smtp_host']);
        $testResults['config_check']['smtp_port'] = !empty($emailSettings['smtp_port']);
        $testResults['config_check']['smtp_username'] = !empty($emailSettings['smtp_username']);
        $testResults['config_check']['smtp_password'] = !empty($emailSettings['smtp_password']);
        $testResults['config_check']['from_email'] = !empty($emailSettings['from_email']);

        // Connection test
        if ($emailSettings['use_smtp'] && !empty($emailSettings['smtp_host'])) {
            $host = $emailSettings['smtp_host'];
            $port = $emailSettings['smtp_port'];
            $encryption = $emailSettings['smtp_encryption'];

            if ($encryption === 'ssl') {
                $host = 'ssl://' . $host;
            }

            $connection = @fsockopen($host, $port, $errno, $errstr, 10);

            if ($connection) {
                $response = fgets($connection, 512);
                fclose($connection);

                $testResults['connection_test'] = [
                    'success' => true,
                    'response' => trim($response),
                    'message' => 'Connection successful'
                ];
            } else {
                $testResults['connection_test'] = [
                    'success' => false,
                    'error' => $errstr,
                    'errno' => $errno,
                    'message' => "Connection failed: $errstr ($errno)"
                ];
            }
        }

        echo json_encode($testResults, JSON_PRETTY_PRINT);
        exit;

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Read log file
$logs = '';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
}

$pageTitle = 'Email Debug Console';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-viewer {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            height: 400px;
            overflow-y: auto;
            border-radius: 5px;
        }
        .log-error { color: #f44747; }
        .log-warning { color: #ffcc02; }
        .log-info { color: #0ea5e9; }
        .log-success { color: #00d4aa; }
    </style>
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

                <a class="nav-link" href="<?= BASE_URL ?>/admin/email_settings.php">
                    <i class="fas fa-arrow-left"></i> Volver a Email Settings
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bug"></i> <?= $pageTitle ?></h1>
                    <div>
                        <button class="btn btn-info me-2" onclick="runQuickTest()">
                            <i class="fas fa-vial"></i> Test Rápido
                        </button>
                        <button class="btn btn-warning me-2" onclick="refreshLogs()">
                            <i class="fas fa-sync"></i> Actualizar
                        </button>
                        <a href="?action=clear" class="btn btn-danger" onclick="return confirm('¿Limpiar todos los logs?')">
                            <i class="fas fa-trash"></i> Limpiar Logs
                        </a>
                    </div>
                </div>

                <!-- Quick Test Results -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> Test Rápido de Configuración</h5>
                    </div>
                    <div class="card-body">
                        <div id="quick-test-results">
                            <p class="text-muted">Haz clic en "Test Rápido" para verificar la configuración básica</p>
                        </div>
                    </div>
                </div>

                <!-- Log Viewer -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Logs de Email Debug</h5>
                    </div>
                    <div class="card-body">
                        <div class="log-viewer" id="log-viewer">
                            <?= $logs ? nl2br(htmlspecialchars($logs)) : '<span class="text-muted">No hay logs disponibles</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshLogs() {
            location.reload();
        }

        function runQuickTest() {
            const resultsDiv = document.getElementById('quick-test-results');
            resultsDiv.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin"></i> Ejecutando test...</div>';

            fetch('email_debug.php?action=test')
                .then(response => response.json())
                .then(data => {
                    let html = `<h6>Test ejecutado: ${data.timestamp}</h6>`;

                    // Configuration checks
                    html += '<h6 class="mt-3">Verificación de Configuración:</h6>';
                    html += '<div class="row">';

                    Object.entries(data.config_check).forEach(([key, value]) => {
                        const label = key.replace(/_/g, ' ').toUpperCase();
                        const icon = value ? 'fa-check text-success' : 'fa-times text-danger';
                        html += `<div class="col-md-4 mb-2">
                            <i class="fas ${icon}"></i> ${label}
                        </div>`;
                    });

                    html += '</div>';

                    // Connection test
                    if (data.connection_test) {
                        html += '<h6 class="mt-3">Test de Conexión:</h6>';
                        if (data.connection_test.success) {
                            html += `<div class="alert alert-success">
                                <i class="fas fa-check"></i> ${data.connection_test.message}<br>
                                <small>Respuesta: ${data.connection_test.response}</small>
                            </div>`;
                        } else {
                            html += `<div class="alert alert-danger">
                                <i class="fas fa-times"></i> ${data.connection_test.message}
                            </div>`;
                        }
                    }

                    resultsDiv.innerHTML = html;
                })
                .catch(error => {
                    resultsDiv.innerHTML = `<div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error: ${error.message}
                    </div>`;
                });
        }

        // Auto-scroll log viewer to bottom
        const logViewer = document.getElementById('log-viewer');
        logViewer.scrollTop = logViewer.scrollHeight;

        // Auto-refresh logs every 30 seconds
        setInterval(() => {
            if (document.hidden) return; // Don't refresh if tab is not active

            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('log-viewer').innerHTML;

                    if (newContent !== logViewer.innerHTML) {
                        logViewer.innerHTML = newContent;
                        logViewer.scrollTop = logViewer.scrollHeight;
                    }
                })
                .catch(error => console.error('Auto-refresh error:', error));
        }, 30000);
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>