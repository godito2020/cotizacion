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
    $result = false;

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_company':
                $companyData = [
                    'name' => trim($_POST['company_name'] ?? ''),
                    'tax_id' => trim($_POST['tax_id'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'phone' => trim($_POST['phone'] ?? ''),
                    'email' => trim($_POST['email'] ?? ''),
                    'website' => trim($_POST['website'] ?? ''),
                    'whatsapp' => trim($_POST['whatsapp'] ?? '')
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

// Get company data
$company = $companySettings->getCompanyInfo($companyId);

// Get quotation settings
$enableDiscounts = ($companySettings->getSetting($companyId, 'quotation_enable_discounts') === '1');
$pdfHeaderColor  = $companySettings->getSetting($companyId, 'pdf_header_color') ?: '#1B3A6B';

$pageTitle = 'Configuración de Empresa';
$activePage = 'admin';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1200px; margin: 0 auto;">

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
                <li class="breadcrumb-item text-muted">Administración</li>
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Configuración de Empresa</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Configuración de Empresa
                    </h1>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Identidad corporativa, logo, membretes y personalización de cotizaciones.
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

    <div class="row g-4">
        <!-- =========================================================
             2. DATOS DE LA EMPRESA
             ========================================================= -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-building text-warning me-2"></i>Información Corporativa
                    </span>
                    <span class="badge bg-body-secondary text-body border small">Razón Social y Contacto</span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_company">

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="company_name" class="form-label small fw-semibold text-body mb-1">Nombre o Razón Social *</label>
                                <input type="text" class="form-control form-control-sm" id="company_name" name="company_name"
                                       value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-5">
                                <label for="tax_id" class="form-label small fw-semibold text-body mb-1">RUC (11 dígitos)</label>
                                <input type="text" class="form-control form-control-sm font-monospace" id="tax_id" name="tax_id"
                                       value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>" maxlength="11">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-semibold text-body mb-1">Correo Electrónico</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-semibold text-body mb-1">Teléfono Principal</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="whatsapp" class="form-label small fw-semibold text-body mb-1">WhatsApp Corporativo</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fab fa-whatsapp text-success"></i></span>
                                    <input type="tel" class="form-control" id="whatsapp" name="whatsapp"
                                           value="<?= htmlspecialchars($company['whatsapp'] ?? '') ?>" placeholder="+51 999 999 999">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="website" class="form-label small fw-semibold text-body mb-1">Sitio Web Oficial</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                    <input type="url" class="form-control" id="website" name="website"
                                           value="<?= htmlspecialchars($company['website'] ?? '') ?>" placeholder="https://www.ejemplo.com">
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label small fw-semibold text-body mb-1">Dirección Fiscal / Sede Central</label>
                                <textarea class="form-control form-control-sm" id="address" name="address" rows="2"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-2 border-top text-end">
                            <button type="submit" class="btn btn-warning btn-sm fw-bold text-dark px-4 shadow-sm">
                                <i class="fas fa-save me-1"></i> Guardar Información
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- =========================================================
                 CONFIGURACIÓN DE COTIZACIONES & COLOR PDF
                 ========================================================= -->
            <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-file-invoice text-warning me-2"></i>Reglas de Cotización y Estilo PDF
                    </span>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_quotation_settings">

                        <!-- Descuentos Switch -->
                        <div class="p-3 rounded mb-3" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="enable_discounts"
                                       name="enable_discounts" <?= $enableDiscounts ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-body small" for="enable_discounts">
                                    Habilitar módulo de descuentos en cotizaciones (%)
                                </label>
                            </div>
                            <div class="small text-muted ps-4" style="font-size: 0.78rem;">
                                Permite ingresar descuentos individuales por ítem y descuento global en el formulario de cotización.
                            </div>
                        </div>

                        <!-- Color PDF -->
                        <div class="p-3 rounded mb-3" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                            <label class="form-label small fw-bold text-body mb-1">
                                <i class="fas fa-palette text-primary me-1"></i>Color Principal del Documento PDF
                            </label>
                            <p class="text-muted small mb-2" style="font-size: 0.75rem;">
                                Se aplica a cabeceras de tabla, caja de correlativo, línea divisoria de marcas y totales.
                            </p>
                            <div class="d-flex align-items-center gap-3">
                                <input type="color" class="form-control form-control-color"
                                       id="pdf_header_color" name="pdf_header_color"
                                       value="<?= htmlspecialchars($pdfHeaderColor) ?>"
                                       style="width: 50px; height: 38px; cursor: pointer;">
                                <div class="badge px-3 py-2 font-monospace" id="colorPreviewBadge"
                                      style="background-color: <?= htmlspecialchars($pdfHeaderColor) ?>; color: #fff; font-size: 0.85rem;">
                                    <?= htmlspecialchars($pdfHeaderColor) ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold px-4">
                                <i class="fas fa-save me-1"></i> Guardar Ajustes de Cotización
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- =========================================================
             3. LOGO, FAVICON & VISTA PREVIA
             ========================================================= -->
        <div class="col-12 col-lg-5">
            <!-- Logo Upload -->
            <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-image text-warning me-2"></i>Logo de la Empresa
                    </span>
                </div>
                <div class="card-body p-3 text-center">
                    <div class="p-3 rounded mb-3 d-flex align-items-center justify-content-center"
                         style="background: var(--color-canvas); border: 1px dashed var(--color-rule); min-height: 120px;">
                        <?php if (!empty($company['logo_url'])): ?>
                            <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>"
                                 alt="Logo Corporativo" class="img-fluid" style="max-height: 100px; object-fit: contain;">
                        <?php else: ?>
                            <div class="text-muted small">
                                <i class="fas fa-image fa-2x mb-1 opacity-50 d-block"></i>
                                Sin logo cargado
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_logo">
                        <div class="input-group input-group-sm mb-2">
                            <input type="file" class="form-control" name="logo" accept="image/*" required>
                            <button type="submit" class="btn btn-outline-secondary fw-semibold">
                                <i class="fas fa-upload me-1"></i> Subir
                            </button>
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            Formatos: PNG, JPG, GIF (Máx. 2MB). Utilizado en PDFs y PWA.
                        </div>
                    </form>
                </div>
            </div>

            <!-- Favicon Upload -->
            <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-globe text-warning me-2"></i>Favicon del Navegador
                    </span>
                </div>
                <div class="card-body p-3 text-center">
                    <div class="p-2 rounded mb-3 d-inline-flex align-items-center justify-content-center"
                         style="background: var(--color-canvas); border: 1px dashed var(--color-rule); width: 64px; height: 64px;">
                        <?php if (!empty($company['favicon_url'])): ?>
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($company['favicon_url']) ?>"
                                 alt="Favicon" style="width: 42px; height: 42px; object-fit: contain;">
                        <?php else: ?>
                            <i class="fas fa-star text-muted opacity-50 fa-lg"></i>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_favicon">
                        <div class="input-group input-group-sm mb-2">
                            <input type="file" class="form-control" name="favicon" accept=".ico,.png,.jpg,.jpeg" required>
                            <button type="submit" class="btn btn-outline-secondary fw-semibold">
                                <i class="fas fa-upload me-1"></i> Subir
                            </button>
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            Formatos: ICO, PNG (64x64px recomendado).
                        </div>
                    </form>
                </div>
            </div>

            <!-- Vista Previa Membrete -->
            <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-id-card text-warning me-2"></i>Vista Previa de Membrete
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="p-3 rounded text-center" style="background-color: var(--color-canvas); border: 1px solid var(--color-rule);">
                        <?php if (!empty($company['logo_url'])): ?>
                            <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>"
                                 alt="Logo" style="max-height: 48px; object-fit: contain;" class="mb-2">
                        <?php endif; ?>
                        <h6 class="fw-bold text-body mb-0" style="font-family: var(--font-display);">
                            <?= htmlspecialchars($company['name'] ?? 'Nombre de la Empresa') ?>
                        </h6>
                        <?php if (!empty($company['tax_id'])): ?>
                            <div class="small text-muted font-monospace" style="font-size:0.75rem;">
                                RUC: <?= htmlspecialchars($company['tax_id']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($company['address'])): ?>
                            <div class="small text-muted mt-1" style="font-size:0.72rem;">
                                <?= nl2br(htmlspecialchars($company['address'])) ?>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap justify-content-center gap-2 mt-2 pt-2 border-top small text-muted" style="font-size: 0.72rem;">
                            <?php if (!empty($company['phone'])): ?>
                                <span><i class="fas fa-phone text-muted me-1"></i><?= htmlspecialchars($company['phone']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($company['email'])): ?>
                                <span><i class="fas fa-envelope text-muted me-1"></i><?= htmlspecialchars($company['email']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const colorInput = document.getElementById('pdf_header_color');
    const colorBadge = document.getElementById('colorPreviewBadge');
    if (colorInput && colorBadge) {
        colorInput.addEventListener('input', function() {
            colorBadge.style.backgroundColor = this.value;
            colorBadge.textContent = this.value.toUpperCase();
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
