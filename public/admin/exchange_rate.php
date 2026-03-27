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
    $exchangeRate = floatval($_POST['exchange_rate']);

    if (!$companyId) {
        $_SESSION['error_message'] = 'Error: No se pudo identificar la empresa del usuario';
    } elseif ($exchangeRate <= 0) {
        $_SESSION['error_message'] = 'El tipo de cambio debe ser mayor a 0';
    } else {
        try {
            $db = getDBConnection();

            // First check if setting exists
            $checkSql = "SELECT id FROM settings WHERE company_id = ? AND setting_key = 'exchange_rate_usd_pen'";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([$companyId]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                // Update existing
                $sql = "UPDATE settings SET setting_value = ? WHERE company_id = ? AND setting_key = 'exchange_rate_usd_pen'";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([(string)$exchangeRate, $companyId]);
            } else {
                // Insert new
                $sql = "INSERT INTO settings (company_id, setting_key, setting_value, setting_type, description) VALUES (?, 'exchange_rate_usd_pen', ?, 'number', 'Tipo de cambio USD a PEN')";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$companyId, (string)$exchangeRate]);
            }

            if ($result) {
                // If System Admin, propagate to all companies that don't have their own rate
                if ($auth->hasRole('Administrador del Sistema')) {
                    $allCompanies = $db->query("SELECT id FROM companies")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($allCompanies as $cid) {
                        if ($cid == $companyId) continue;
                        $chkStmt = $db->prepare("SELECT id FROM settings WHERE company_id = ? AND setting_key = 'exchange_rate_usd_pen'");
                        $chkStmt->execute([$cid]);
                        if ($chkStmt->fetch()) {
                            $db->prepare("UPDATE settings SET setting_value = ? WHERE company_id = ? AND setting_key = 'exchange_rate_usd_pen'")->execute([(string)$exchangeRate, $cid]);
                        } else {
                            $db->prepare("INSERT INTO settings (company_id, setting_key, setting_value, setting_type, description) VALUES (?, 'exchange_rate_usd_pen', ?, 'number', 'Tipo de cambio USD a PEN')")->execute([$cid, (string)$exchangeRate]);
                        }
                    }
                }
                $_SESSION['success_message'] = 'Tipo de cambio actualizado correctamente a S/ ' . number_format($exchangeRate, 3);
            } else {
                $_SESSION['error_message'] = 'Error al actualizar el tipo de cambio en la base de datos';
            }
        } catch (Exception $e) {
            error_log("Error updating exchange rate: " . $e->getMessage());
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
    $auth->redirect($_SERVER['REQUEST_URI']);
}

// Get current exchange rate
$currentExchangeRate = $companySettings->getSetting($companyId, 'exchange_rate_usd_pen') ?? '3.80';

$pageTitle = 'Tipo de Cambio';
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
        .exchange-card {
            max-width: 600px;
            margin: 0 auto;
        }
        .exchange-display {
            font-size: 3rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .currency-icon {
            font-size: 4rem;
            color: #198754;
        }
    </style>
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

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-exchange-alt"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

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

        <div class="card exchange-card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-dollar-sign"></i> Configurar Tipo de Cambio USD a PEN</h5>
            </div>
            <div class="card-body">
                <!-- Current Exchange Rate Display -->
                <div class="text-center mb-4 p-4 bg-light rounded">
                    <div class="row align-items-center justify-content-center">
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign currency-icon"></i>
                            <div class="h5 mb-0">1 USD</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-arrow-right fa-2x text-muted"></i>
                        </div>
                        <div class="col-auto">
                            <div class="exchange-display">S/ <?= number_format($currentExchangeRate, 3) ?></div>
                            <div class="h5 mb-0 text-muted">PEN</div>
                        </div>
                    </div>
                </div>

                <!-- Update Form -->
                <form method="POST">
                    <div class="mb-4">
                        <label for="exchange_rate" class="form-label fw-bold">Nuevo Tipo de Cambio</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">1 USD =</span>
                            <input type="number" class="form-control form-control-lg text-center"
                                   id="exchange_rate" name="exchange_rate"
                                   value="<?= htmlspecialchars($currentExchangeRate) ?>"
                                   step="0.001" min="0.001" max="20" required
                                   style="font-size: 1.5rem; font-weight: bold;">
                            <span class="input-group-text">PEN</span>
                        </div>
                        <small class="text-muted">Ingrese el valor del dólar en soles (ejemplo: 3.800)</small>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-save"></i> Actualizar Tipo de Cambio
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <div class="alert alert-info mb-0">
                    <h6><i class="fas fa-info-circle"></i> Información</h6>
                    <ul class="mb-0">
                        <li>Este tipo de cambio se utiliza para convertir automáticamente los precios en USD a PEN en las cotizaciones.</li>
                        <li>Los productos de COBOL vienen con precio en USD.</li>
                        <li>Cuando una cotización es en Soles (PEN), los precios se multiplican por este factor.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Conversion Calculator -->
        <div class="card exchange-card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calculator"></i> Calculadora de Conversión</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <label class="form-label">USD (Dólares)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="usd_amount" value="100" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end justify-content-center">
                        <i class="fas fa-exchange-alt fa-2x text-muted mb-2"></i>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">PEN (Soles)</label>
                        <div class="input-group">
                            <span class="input-group-text">S/</span>
                            <input type="text" class="form-control" id="pen_amount" readonly
                                   style="font-weight: bold; background-color: #f8f9fa;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
    <script>
        // Conversion calculator
        const exchangeRate = <?= floatval($currentExchangeRate) ?>;
        const usdInput = document.getElementById('usd_amount');
        const penOutput = document.getElementById('pen_amount');

        function updateConversion() {
            const usd = parseFloat(usdInput.value) || 0;
            const pen = usd * exchangeRate;
            penOutput.value = pen.toFixed(2);
        }

        usdInput.addEventListener('input', updateConversion);
        updateConversion(); // Initial calculation
    </script>
</body>
</html>
