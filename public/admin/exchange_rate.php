<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a esta sección';
    $auth->redirect(BASE_URL . '/dashboard.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$companySettings = new CompanySettings();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exchangeRate = floatval($_POST['exchange_rate'] ?? 0);

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
                $sql = "INSERT INTO settings (company_id, setting_key, setting_value, description) VALUES (?, 'exchange_rate_usd_pen', ?, 'Tipo de cambio USD a PEN')";
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
                            $db->prepare("INSERT INTO settings (company_id, setting_key, setting_value, description) VALUES (?, 'exchange_rate_usd_pen', ?, 'Tipo de cambio USD a PEN')")->execute([$cid, (string)$exchangeRate]);
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
$currentExchangeRate = (float)($companySettings->getSetting($companyId, 'exchange_rate_usd_pen') ?? 3.80);

$pageTitle = 'Tipo de Cambio';
$activePage = 'exchange_rate';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 900px; margin: 0 auto;">

    <!-- =========================================================
         1. BREADCRUMB & HEADER
         ========================================================= -->
    <div class="d-flex flex-column gap-2 mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="text-decoration-none text-muted">
                        <i class="fas fa-home me-1"></i>Inicio
                    </a>
                </li>
                <li class="breadcrumb-item text-muted">Sistema</li>
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Tipo de Cambio</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Configuración de Tipo de Cambio
                    </h1>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Paridad cambiaria oficial USD / PEN aplicada en cotizaciones, productos y facturación.
                </p>
            </div>

            <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm px-3">
                <i class="fas fa-arrow-left me-1"></i> Panel Admin
            </a>
        </div>
    </div>

    <!-- Feedback Alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 small mb-3" role="alert">
            <i class="fas fa-check-circle"></i>
            <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 py-2 px-3 small mb-3" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- =========================================================
         2. MASTER PARITY HERO CARD
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 14px; overflow: hidden;">
        <div class="card-header bg-body-tertiary border-bottom py-2.5 px-4 d-flex justify-content-between align-items-center">
            <span class="fw-bold small text-body">
                <i class="fas fa-money-bill-transfer text-warning me-2"></i>Tipo de Cambio Activo
            </span>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                <i class="fas fa-circle-check me-1"></i>En uso
            </span>
        </div>

        <div class="card-body p-4">
            <!-- Visual Exchange Display -->
            <div class="p-4 rounded text-center mb-4" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                <div class="row align-items-center justify-content-center g-3">
                    <div class="col-auto">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-primary mb-1" style="width: 52px; height: 52px; background: rgba(59, 130, 246, 0.12);">
                            <i class="fas fa-dollar-sign fa-2x"></i>
                        </div>
                        <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono);">1.00 USD</div>
                        <div class="small text-muted">Dólar Americano</div>
                    </div>

                    <div class="col-auto px-4">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-muted" style="width: 36px; height: 36px; background: var(--color-panel); border: 1px solid var(--color-rule);">
                            <i class="fas fa-equals"></i>
                        </div>
                    </div>

                    <div class="col-auto">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-success mb-1" style="width: 52px; height: 52px; background: rgba(16, 185, 129, 0.12);">
                            <span class="fw-bold fs-4">S/</span>
                        </div>
                        <div class="fs-2 fw-bold text-success" style="font-family: var(--font-mono); letter-spacing: -0.02em;">
                            S/ <?= number_format($currentExchangeRate, 3) ?>
                        </div>
                        <div class="small text-muted">Soles Peruanos (PEN)</div>
                    </div>
                </div>
            </div>

            <!-- Form: Update Rate -->
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="exchange_rate" class="form-label small fw-bold text-body">
                        Modificar Tipo de Cambio (Soles por 1 USD) *
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-body-tertiary font-monospace fw-bold">1 USD = S/</span>
                        <input type="number"
                               class="form-control text-center fw-bold font-monospace"
                               id="exchange_rate"
                               name="exchange_rate"
                               value="<?= htmlspecialchars(number_format($currentExchangeRate, 3, '.', '')) ?>"
                               step="0.001"
                               min="0.001"
                               max="20"
                               required
                               style="font-size: 1.6rem; letter-spacing: 0.05em;">
                        <span class="input-group-text bg-body-tertiary fw-semibold">PEN</span>
                    </div>
                    <div class="form-text small text-muted mt-1">
                        Ingrese el valor con hasta 3 decimales (ejemplo: <code>3.800</code> o <code>3.745</code>). Se aplicará de inmediato a las nuevas cotizaciones.
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark shadow-sm">
                        <i class="fas fa-save me-2"></i> Guardar y Actualizar Tipo de Cambio
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         3. INTERACTIVE CONVERSION CALCULATOR & SYSTEM NOTES
         ========================================================= -->
    <div class="row g-3">
        <!-- Interactive Converter -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-calculator text-primary me-2"></i>Calculadora de Conversión Rápida
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-5">
                            <label class="form-label small text-muted mb-1">Dólares (USD)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control font-monospace fw-semibold" id="usd_amount" value="100" step="0.01" min="0">
                            </div>
                        </div>

                        <div class="col-2 text-center pt-3">
                            <i class="fas fa-arrow-right-arrow-left text-muted"></i>
                        </div>

                        <div class="col-5">
                            <label class="form-label small text-muted mb-1">Soles (PEN)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">S/</span>
                                <input type="text" class="form-control font-monospace fw-bold text-success bg-body-tertiary" id="pen_amount" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="small text-muted mt-2 text-center" style="font-size: 0.75rem;">
                        Conversión en tiempo real basada en la tasa activa: <strong>1 USD = S/ <?= number_format($currentExchangeRate, 3) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Guidelines Note -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-circle-info text-info me-2"></i>Pautas del Sistema
                    </span>
                </div>
                <div class="card-body p-3">
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small text-muted" style="font-size: 0.78rem;">
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check text-success mt-1"></i>
                            <span>Los precios de catálogo COBOL se gestionan originalmente en <strong>Dólares (USD)</strong>.</span>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check text-success mt-1"></i>
                            <span>Cuando una cotización se emite en <strong>Soles (PEN)</strong>, los precios se multiplican por este factor.</span>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check text-success mt-1"></i>
                            <span>Las cotizaciones guardadas mantienen el tipo de cambio registrado en su fecha de emisión.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rate = <?= floatval($currentExchangeRate) ?>;
    const usdInput = document.getElementById('usd_amount');
    const penOutput = document.getElementById('pen_amount');

    function calculate() {
        if (!usdInput || !penOutput) return;
        const usd = parseFloat(usdInput.value) || 0;
        const pen = usd * rate;
        penOutput.value = pen.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if (usdInput) {
        usdInput.addEventListener('input', calculate);
        calculate();
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
