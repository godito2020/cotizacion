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

// Redirect to the new version with progress bar
header('Location: ' . BASE_URL . '/admin/import_products_with_progress.php');
exit;

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$importResult = null;

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['excel_file']['tmp_name'])) {
    try {
        $importer = new ExcelImporter();

        // Validate file
        $validation = $importer->validateExcelFile($_FILES['excel_file']['tmp_name']);

        if (!$validation['valid']) {
            $importResult = [
                'success' => false,
                'message' => 'Archivo Excel inválido: ' . ($validation['error'] ?? 'Faltan columnas requeridas'),
                'missing_columns' => $validation['missing_columns'] ?? []
            ];
        } else {
            // Import products
            $importType = $_POST['import_type'] ?? 'products';
            $importCurrency = $_POST['import_currency'] ?? 'USD';

            if ($importType === 'products') {
                $importResult = $importer->importProducts($_FILES['excel_file']['tmp_name'], $companyId, $importCurrency);
            } else if ($importType === 'stock') {
                $importResult = $importer->importWarehouseStock($_FILES['excel_file']['tmp_name'], $companyId);
            }
        }

    } catch (Exception $e) {
        $importResult = [
            'success' => false,
            'message' => 'Error al procesar el archivo: ' . $e->getMessage()
        ];
    }
}

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
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-upload"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver al Panel
                    </a>
                </div>

                <!-- Import Result -->
                <?php if ($importResult): ?>
                    <div class="card mb-4">
                        <div class="card-header <?= $importResult['success'] ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                            <h5 class="mb-0">
                                <i class="fas <?= $importResult['success'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                                Resultado de la Importación
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($importResult['success']): ?>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h3 text-success"><?= $importResult['imported'] ?? 0 ?></div>
                                            <small>Productos Nuevos</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h3 text-info"><?= $importResult['updated'] ?? 0 ?></div>
                                            <small>Productos Actualizados</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h3 text-primary"><?= $importResult['total_rows'] ?? 0 ?></div>
                                            <small>Filas Procesadas</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h3 <?= empty($importResult['errors']) ? 'text-success' : 'text-warning' ?>">
                                                <?= count($importResult['errors'] ?? []) ?>
                                            </div>
                                            <small>Errores</small>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($importResult['errors'])): ?>
                                    <div class="mt-4">
                                        <h6>Errores encontrados:</h6>
                                        <ul class="list-unstyled">
                                            <?php foreach (array_slice($importResult['errors'], 0, 10) as $error): ?>
                                                <li class="text-warning"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($importResult['errors']) > 10): ?>
                                                <li class="text-muted">... y <?= count($importResult['errors']) - 10 ?> errores más</li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <strong>Error:</strong> <?= htmlspecialchars($importResult['message']) ?>
                                </div>
                                <?php if (!empty($importResult['missing_columns'])): ?>
                                    <p><strong>Columnas faltantes:</strong></p>
                                    <ul>
                                        <?php foreach ($importResult['missing_columns'] as $column): ?>
                                            <li><?= htmlspecialchars($column) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Import Form -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Cargar Archivo Excel</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="import_type" class="form-label">Tipo de Importación</label>
                                        <select class="form-select" id="import_type" name="import_type" required>
                                            <option value="products">Productos (Precios y datos básicos)</option>
                                            <option value="stock">Stock por Almacén</option>
                                        </select>
                                    </div>

                                    <div class="mb-3" id="currency_section">
                                        <label for="import_currency" class="form-label">Moneda de los Precios</label>
                                        <select class="form-select" id="import_currency" name="import_currency">
                                            <option value="USD">Dólares (USD)</option>
                                            <option value="PEN">Soles (PEN)</option>
                                        </select>
                                        <div class="form-text">Indica en qué moneda están los precios en el archivo Excel</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="excel_file" class="form-label">Archivo Excel (.xlsx, .xls)</label>
                                        <input type="file" class="form-control" id="excel_file" name="excel_file"
                                               accept=".xlsx,.xls" required>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="confirm_import" required>
                                            <label class="form-check-label" for="confirm_import">
                                                Confirmo que el archivo tiene el formato correcto y entiendo que
                                                los productos existentes serán actualizados.
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-upload"></i> Importar Archivo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Format Instructions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Formato de Productos</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Columnas requeridas:</strong></p>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> <strong>CODIGO</strong> - Código del producto</li>
                                    <li><i class="fas fa-check text-success"></i> <strong>DESCRIPCION</strong> - Nombre del producto</li>
                                </ul>

                                <p><strong>Columnas opcionales:</strong></p>
                                <ul class="list-unstyled small">
                                    <li>• MARCA</li>
                                    <li>• <strong>SALDO</strong> (stock/cantidad del producto)</li>
                                    <li>• PREMIUM (precio premium)</li>
                                    <li>• PRECIO (precio regular)</li>
                                    <li>• ULTCOSTO (último costo)</li>
                                    <li>• FECULTCOS (fecha último costo)</li>
                                    <li>• IMAGEN (URL imagen)</li>
                                </ul>

                                <div class="alert alert-warning mt-2">
                                    <small>
                                        <strong>Nota:</strong> El SALDO se guardará automáticamente como stock en el almacén "GENERAL" y aparecerá en la vista de productos y stock.
                                    </small>
                                </div>

                                <div class="alert alert-info mt-3">
                                    <small>
                                        <strong>Límites:</strong><br>
                                        • Máximo 5000 filas<br>
                                        • Archivo máximo 20MB<br>
                                        • Procesamiento por lotes de 50 filas
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">Formato de Stock</h5>
                            </div>
                            <div class="card-body">
                                <p><strong>Columnas requeridas:</strong></p>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> <strong>Articulo</strong> - Código del producto</li>
                                </ul>

                                <p><strong>Columnas de almacenes:</strong></p>
                                <ul class="list-unstyled small">
                                    <li>• YP, PRODUCTORES</li>
                                    <li>• AR1902, PRO I</li>
                                    <li>• UNIVERSITARIA, PALAO 2</li>
                                    <li>• AR1940, PTE. PIEDRA</li>
                                    <li>• PIURA, METRO, ZAPALLAL</li>
                                    <li>• Y todos los almacenes configurados</li>
                                </ul>

                                <div class="alert alert-info mt-3">
                                    <small>
                                        <strong>Límites:</strong><br>
                                        • Máximo 5000 filas<br>
                                        • Archivo máximo 20MB<br>
                                        • Procesamiento por lotes de 50 filas
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Download Template -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">Plantillas</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="<?= BASE_URL ?>/admin/download_template.php?type=products" class="btn btn-outline-primary">
                                        <i class="fas fa-download"></i> Plantilla Productos
                                    </a>
                                    <a href="<?= BASE_URL ?>/admin/download_template.php?type=stock" class="btn btn-outline-info">
                                        <i class="fas fa-download"></i> Plantilla Stock
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import History -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Historial de Importaciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-history fa-2x mb-3"></i>
                            <p>Las importaciones realizadas aparecerán aquí</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File validation
        document.getElementById('excel_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                   'application/vnd.ms-excel'];

                if (!validTypes.includes(file.type)) {
                    alert('Por favor selecciona un archivo Excel válido (.xlsx o .xls)');
                    e.target.value = '';
                    return;
                }

                if (file.size > 20 * 1024 * 1024) { // 20MB
                    alert('El archivo es demasiado grande. Máximo 20MB permitido.');
                    e.target.value = '';
                    return;
                }
            }
        });

        // Form submission with loading
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        });

        // Show/hide currency selection based on import type
        document.getElementById('import_type').addEventListener('change', function() {
            const currencySection = document.getElementById('currency_section');
            if (this.value === 'products') {
                currencySection.style.display = 'block';
            } else {
                currencySection.style.display = 'none';
            }
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>