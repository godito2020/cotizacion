<?php
// Increase memory limit for Excel processing
ini_set('memory_limit', '1024M'); // 1GB
ini_set('max_execution_time', 600); // 10 minutes

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

$pageTitle = 'Importar Productos desde Excel';
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
        .progress-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .progress-modal {
            background: white;
            padding: 30px;
            border-radius: 15px;
            min-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .progress-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }

        .progress {
            height: 30px;
            margin: 20px 0;
            border-radius: 15px;
            overflow: hidden;
        }

        .progress-bar {
            font-size: 14px;
            font-weight: bold;
            line-height: 30px;
            transition: width 0.3s ease;
        }

        .progress-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin: 25px 0;
        }

        .progress-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .progress-stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }

        .progress-stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-status {
            font-size: 16px;
            color: #666;
            margin: 15px 0;
            min-height: 20px;
        }

        .upload-zone {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-zone:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .upload-zone.dragover {
            border-color: #28a745;
            background-color: #d4edda;
        }

        .file-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .import-type-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
        }

        .import-type-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .import-type-card.selected {
            border-color: #007bff;
            background-color: #f8f9fa;
        }

        .import-type-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .completed-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .completed-stat {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }

        .completed-stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .completed-stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
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

    <!-- Progress Modal -->
    <div class="progress-container" id="progressContainer">
        <div class="progress-modal">
            <div class="progress-title">
                <i class="fas fa-upload text-primary"></i>
                Importando Archivo Excel
            </div>

            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                     id="progressBar"
                     role="progressbar"
                     style="width: 0%"
                     aria-valuenow="0"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    0%
                </div>
            </div>

            <div class="progress-stats">
                <div class="progress-stat">
                    <div class="progress-stat-value" id="currentRow">0</div>
                    <div class="progress-stat-label">Procesadas</div>
                </div>
                <div class="progress-stat">
                    <div class="progress-stat-value" id="totalRows">0</div>
                    <div class="progress-stat-label">Total Filas</div>
                </div>
                <div class="progress-stat">
                    <div class="progress-stat-value" id="estimatedTime">--</div>
                    <div class="progress-stat-label">Tiempo restante</div>
                </div>
            </div>

            <div class="progress-status" id="progressStatus">
                Iniciando importación...
            </div>

            <button type="button" class="btn btn-outline-secondary" id="cancelImport" style="display: none;">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>

    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-upload"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                </div>

                <!-- Import Results -->
                <div class="card mb-4" id="resultsCard" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle"></i>
                            Importación Completada
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="completed-stats" id="completedStats">
                            <!-- Results will be populated here -->
                        </div>

                        <div class="mt-4">
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL ?>/products/index.php" class="btn btn-primary">
                                    <i class="fas fa-boxes"></i> Ver Productos
                                </a>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetImport()">
                                    <i class="fas fa-upload"></i> Importar Otro Archivo
                                </button>
                            </div>
                        </div>

                        <div class="mt-3" id="errorsList" style="display: none;">
                            <h6>Errores encontrados:</h6>
                            <div class="alert alert-warning">
                                <ul class="mb-0" id="errorsContent">
                                    <!-- Errors will be populated here -->
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Form -->
                <div class="row" id="importForm">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-file-excel"></i> Seleccionar Archivo</h5>
                            </div>
                            <div class="card-body">
                                <form id="uploadForm" enctype="multipart/form-data">
                                    <div class="upload-zone" id="uploadZone">
                                        <div class="file-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <h5>Arrastra tu archivo Excel aquí</h5>
                                        <p class="text-muted">o haz clic para seleccionar</p>
                                        <input type="file"
                                               class="form-control"
                                               id="excel_file"
                                               name="excel_file"
                                               accept=".xlsx,.xls"
                                               style="display: none;"
                                               required>
                                    </div>

                                    <div class="mt-3" id="selectedFile" style="display: none;">
                                        <div class="alert alert-info">
                                            <i class="fas fa-file-excel"></i>
                                            <strong>Archivo seleccionado:</strong> <span id="fileName"></span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="clearFile()">
                                                <i class="fas fa-times"></i> Cambiar
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Tipo de Importación</label>
                                            <select class="form-select" name="import_type" id="import_type" required>
                                                <option value="products">Productos (con precios)</option>
                                                <option value="stock">Solo Stock por Almacén</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Moneda (solo para productos)</label>
                                            <select class="form-select" name="import_currency" id="import_currency">
                                                <option value="USD">USD (Dólares)</option>
                                                <option value="PEN">PEN (Soles)</option>
                                                <option value="EUR">EUR (Euros)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg" id="importBtn" disabled>
                                            <i class="fas fa-upload"></i> Iniciar Importación
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información</h5>
                            </div>
                            <div class="card-body">
                                <h6>Formatos soportados:</h6>
                                <ul>
                                    <li>.xlsx (Excel 2007+)</li>
                                    <li>.xls (Excel 97-2003)</li>
                                </ul>

                                <h6 class="mt-4">Columnas requeridas para productos:</h6>
                                <ul>
                                    <li><strong>CODIGO</strong> - Código único del producto</li>
                                    <li><strong>DESCRIPCION</strong> - Nombre del producto</li>
                                    <li>MARCA - Marca del producto</li>
                                    <li>PRECIO - Precio regular</li>
                                    <li>PREMIUM - Precio premium</li>
                                    <li>IMAGEN - URL de la imagen</li>
                                </ul>

                                <h6 class="mt-4">Columnas para stock:</h6>
                                <ul>
                                    <li><strong>Columna 1:</strong> Código de producto</li>
                                    <li><strong>Otras columnas:</strong> Nombres de almacenes con cantidades</li>
                                </ul>

                                <div class="alert alert-warning mt-4">
                                    <small><i class="fas fa-exclamation-triangle"></i>
                                    Los archivos grandes pueden tomar varios minutos en procesar.
                                    La barra de progreso te mostrará el avance en tiempo real.</small>
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
        let importInProgress = false;
        let progressInterval = null;

        document.addEventListener('DOMContentLoaded', function() {
            setupFileUpload();
            setupForm();
        });

        function setupFileUpload() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('excel_file');
            const importBtn = document.getElementById('importBtn');

            // Click to select file
            uploadZone.addEventListener('click', () => {
                if (!importInProgress) {
                    fileInput.click();
                }
            });

            // File selection change
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    handleFileSelection(this.files[0]);
                }
            });

            // Drag and drop
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!importInProgress) {
                    this.classList.add('dragover');
                }
            });

            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');

                if (!importInProgress && e.dataTransfer.files && e.dataTransfer.files[0]) {
                    const file = e.dataTransfer.files[0];
                    if (file.type.includes('sheet') || file.name.endsWith('.xlsx') || file.name.endsWith('.xls')) {
                        fileInput.files = e.dataTransfer.files;
                        handleFileSelection(file);
                    } else {
                        alert('Por favor selecciona un archivo Excel válido (.xlsx o .xls)');
                    }
                }
            });
        }

        function handleFileSelection(file) {
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('selectedFile').style.display = 'block';
            document.getElementById('importBtn').disabled = false;
        }

        function clearFile() {
            document.getElementById('excel_file').value = '';
            document.getElementById('selectedFile').style.display = 'none';
            document.getElementById('importBtn').disabled = true;
        }

        function setupForm() {
            document.getElementById('uploadForm').addEventListener('submit', function(e) {
                e.preventDefault();

                if (importInProgress) {
                    return;
                }

                const fileInput = document.getElementById('excel_file');
                if (!fileInput.files || !fileInput.files[0]) {
                    alert('Por favor selecciona un archivo Excel');
                    return;
                }

                startImport();
            });
        }

        async function startImport() {
            importInProgress = true;
            document.getElementById('progressContainer').style.display = 'flex';

            // Reset progress
            updateProgress(0, 0, 0, 'Iniciando importación...', 0);

            const formData = new FormData();
            formData.append('excel_file', document.getElementById('excel_file').files[0]);
            formData.append('import_type', document.getElementById('import_type').value);
            formData.append('import_currency', document.getElementById('import_currency').value);
            formData.append('action', 'import');

            try {
                // Start progress monitoring
                progressInterval = setInterval(checkProgress, 1000);

                // Start import
                const response = await fetch('<?= BASE_URL ?>/api/import_progress.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }

                const result = await response.json();

                // Stop progress monitoring
                clearInterval(progressInterval);

                if (result.error) {
                    throw new Error(result.error);
                }

                // Show completion
                showResults(result);

            } catch (error) {
                clearInterval(progressInterval);
                updateProgress(0, 0, 0, 'Error: ' + error.message, 0);

                setTimeout(() => {
                    document.getElementById('progressContainer').style.display = 'none';
                    alert('Error en la importación: ' + error.message);
                    importInProgress = false;
                }, 3000);
            }
        }

        async function checkProgress() {
            try {
                const response = await fetch('<?= BASE_URL ?>/api/import_progress.php?action=progress');
                const progress = await response.json();

                updateProgress(
                    progress.progress,
                    progress.current_row,
                    progress.total,
                    progress.status,
                    progress.estimated_time || 0
                );

                if (progress.completed) {
                    clearInterval(progressInterval);
                }

            } catch (error) {
                console.error('Error checking progress:', error);
            }
        }

        function updateProgress(progress, currentRow, totalRows, status, estimatedTime) {
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = progress + '%';
            progressBar.textContent = Math.round(progress) + '%';

            document.getElementById('currentRow').textContent = currentRow;
            document.getElementById('totalRows').textContent = totalRows;
            document.getElementById('progressStatus').textContent = status;

            if (estimatedTime > 0) {
                const minutes = Math.floor(estimatedTime / 60);
                const seconds = estimatedTime % 60;
                document.getElementById('estimatedTime').textContent =
                    minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
            } else {
                document.getElementById('estimatedTime').textContent = '--';
            }
        }

        function showResults(result) {
            document.getElementById('progressContainer').style.display = 'none';

            // Build results
            const statsHtml = `
                <div class="completed-stat">
                    <div class="completed-stat-value">${result.processed || 0}</div>
                    <div class="completed-stat-label">Filas Procesadas</div>
                </div>
                <div class="completed-stat">
                    <div class="completed-stat-value">${result.inserted || 0}</div>
                    <div class="completed-stat-label">Productos Nuevos</div>
                </div>
                <div class="completed-stat">
                    <div class="completed-stat-value">${result.updated || 0}</div>
                    <div class="completed-stat-label">Productos Actualizados</div>
                </div>
                <div class="completed-stat">
                    <div class="completed-stat-value">${(result.errors || []).length}</div>
                    <div class="completed-stat-label">Errores</div>
                </div>
            `;

            document.getElementById('completedStats').innerHTML = statsHtml;

            // Show errors if any
            if (result.errors && result.errors.length > 0) {
                const errorsHtml = result.errors.slice(0, 10).map(error =>
                    `<li>${error}</li>`
                ).join('');

                document.getElementById('errorsContent').innerHTML = errorsHtml;
                document.getElementById('errorsList').style.display = 'block';
            }

            document.getElementById('resultsCard').style.display = 'block';
            document.getElementById('importForm').style.display = 'none';

            importInProgress = false;
        }

        function resetImport() {
            // Reset form
            document.getElementById('uploadForm').reset();
            clearFile();

            // Hide results
            document.getElementById('resultsCard').style.display = 'none';
            document.getElementById('importForm').style.display = 'block';

            // Reset progress session
            fetch('<?= BASE_URL ?>/api/import_progress.php?action=reset');

            importInProgress = false;
        }
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>