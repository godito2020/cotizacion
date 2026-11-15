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
$isSysAdmin = $auth->hasRole('Administrador del Sistema');
$selectedCompanyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : $auth->getCompanyId();
if (!$isSysAdmin) $selectedCompanyId = $auth->getCompanyId();

$companySettings = new CompanySettings();
$companies = [];
if ($isSysAdmin) {
    $companyRepo = new Company();
    $companies = $companyRepo->getAll();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetCompanyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : $selectedCompanyId;
    if (!$isSysAdmin && $targetCompanyId != $auth->getCompanyId()) {
        $_SESSION['error_message'] = 'Sin permisos para gestionar otras empresas';
        $auth->redirect($_SERVER['REQUEST_URI']);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upload_pwa_logo') {
        if (!empty($_FILES['pwa_logo']['name']) && $_FILES['pwa_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = PUBLIC_PATH . '/uploads/company/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $uploadResult = $companySettings->handleFileUpload($_FILES['pwa_logo'], $uploadDir, ['jpg','jpeg','png','gif','webp','svg']);
            if ($uploadResult['success']) {
                $companySettings->updateLogo($targetCompanyId, 'uploads/company/' . $uploadResult['filename']);
                $companySettings->generatePwaIcons($uploadResult['path'], $targetCompanyId);
                $_SESSION['success_message'] = 'Logo PWA actualizado y tamaños generados correctamente';
            } else {
                $_SESSION['error_message'] = 'Error al subir imagen: ' . $uploadResult['message'];
            }
        } else {
            $_SESSION['error_message'] = 'Selecciona un archivo de imagen';
        }

    } elseif ($action === 'upload_pwa_favicon') {
        if (!empty($_FILES['pwa_favicon']['name']) && $_FILES['pwa_favicon']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = PUBLIC_PATH . '/uploads/company/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $uploadResult = $companySettings->handleFileUpload($_FILES['pwa_favicon'], $uploadDir, ['ico','png','jpg','jpeg','webp']);
            if ($uploadResult['success']) {
                $companySettings->updateFavicon($targetCompanyId, 'uploads/company/' . $uploadResult['filename']);
                $companySettings->generatePwaIcons($uploadResult['path'], $targetCompanyId);
                $_SESSION['success_message'] = 'Favicon actualizado y tamaños PWA generados correctamente';
            } else {
                $_SESSION['error_message'] = 'Error al subir imagen: ' . $uploadResult['message'];
            }
        } else {
            $_SESSION['error_message'] = 'Selecciona un archivo de imagen';
        }

    } elseif ($action === 'save_pdf_color') {
        $color = trim($_POST['pdf_header_color'] ?? '#1B3A6B');
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $companySettings->updateSetting($targetCompanyId, 'pdf_header_color', $color);
            $_SESSION['success_message'] = 'Color del PDF actualizado correctamente';
        } else {
            $_SESSION['error_message'] = 'Color inválido';
        }

    } elseif ($action === 'add_brand') {
        $brandName  = trim($_POST['brand_name'] ?? '');
        $sortOrder  = (int)($_POST['sort_order'] ?? 0);

        if (empty($brandName)) {
            $_SESSION['error_message'] = 'El nombre de la marca es requerido';
        } elseif (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error_message'] = 'Debes seleccionar un archivo de logo';
        } else {
            $uploadDir = PUBLIC_PATH . '/uploads/company/brands/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $uploadResult = $companySettings->handleFileUpload($_FILES['logo'], $uploadDir, ['jpg','jpeg','png','gif','svg','webp']);
            if ($uploadResult['success']) {
                $logoPath = 'uploads/company/brands/' . $uploadResult['filename'];
                if ($companySettings->addBrandLogo($targetCompanyId, $brandName, $logoPath, $sortOrder)) {
                    $_SESSION['success_message'] = 'Logo de marca agregado correctamente';
                } else {
                    $_SESSION['error_message'] = 'Error al guardar en la base de datos';
                }
            } else {
                $_SESSION['error_message'] = 'Error al subir imagen: ' . $uploadResult['message'];
            }
        }

    } elseif ($action === 'update_brand') {
        $brandId   = (int)($_POST['brand_id'] ?? 0);
        $brandName = trim($_POST['brand_name'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($companySettings->updateBrandLogo($targetCompanyId, $brandId, $brandName, $sortOrder, $isActive)) {
            $_SESSION['success_message'] = 'Marca actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar la marca';
        }

    } elseif ($action === 'delete_brand') {
        $brandId = (int)($_POST['brand_id'] ?? 0);
        if ($companySettings->deleteBrandLogo($targetCompanyId, $brandId)) {
            $_SESSION['success_message'] = 'Marca eliminada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al eliminar la marca';
        }
    }

    $redirectUrl = BASE_URL . '/admin/brand_logos.php';
    if ($isSysAdmin && $targetCompanyId) $redirectUrl .= '?company_id=' . $targetCompanyId;
    $auth->redirect($redirectUrl);
}

$brands = $companySettings->getBrandLogos($selectedCompanyId);
$pdfHeaderColor  = $companySettings->getSetting($selectedCompanyId, 'pdf_header_color') ?: '#1B3A6B';

$pwaLogoUrl    = $companySettings->getSetting($selectedCompanyId, 'company_logo_url') ?: null;
$pwaFaviconUrl = $companySettings->getSetting($selectedCompanyId, 'company_favicon_url') ?: null;

$pageTitle = 'Logos de Marcas';
$activePage = 'admin';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1300px; margin: 0 auto;">

    <!-- =========================================================
         1. BREADCRUMB & HEADER
         ========================================================= -->
    <div class="d-flex flex-column gap-2 mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="text-decoration-none text-muted">
                        <i class="fas fa-home me-1"></i>Inicio
                    </a>
                </li>
                <li class="breadcrumb-item text-muted">Administración</li>
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Logos de Marcas</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Logos de Marcas y Cabecera PDF
                    </h1>
                    <span class="badge rounded-pill bg-body-tertiary text-body border px-2 py-1 small fw-semibold">
                        <?= count($brands) ?> marcas
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Marcas comerciales que se imprimen horizontalmente en el encabezado de las cotizaciones en PDF.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isSysAdmin && !empty($companies)): ?>
                    <form method="get" class="d-flex align-items-center gap-1.5 m-0">
                        <span class="small text-muted text-nowrap">Empresa:</span>
                        <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 200px;">
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $selectedCompanyId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm px-3">
                    <i class="fas fa-arrow-left me-1"></i> Panel Admin
                </a>
            </div>
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

    <div class="row g-3">
        <!-- =========================================================
             2. MARCAS REGISTRADAS & VISTA PREVIA PDF (Izquierda 8 Col)
             ========================================================= -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm mb-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-tags text-warning me-2"></i>Marcas de Neumáticos Registradas
                    </span>
                    <span class="badge bg-body-secondary text-body border">
                        <?= count($brands) ?> activas
                    </span>
                </div>

                <div class="card-body p-0">
                    <!-- Live PDF Header Preview Row -->
                    <?php if (!empty($brands)): ?>
                        <div class="p-3 border-bottom" style="background-color: var(--color-canvas);">
                            <div class="small text-muted fw-semibold mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                                <i class="fas fa-eye text-primary me-1"></i>Vista Previa de Fila de Logos en el PDF:
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap p-2 rounded border" style="background: #ffffff; min-height: 48px;">
                                <?php foreach ($brands as $b): ?>
                                    <?php if ($b['is_active']): ?>
                                        <img src="<?= htmlspecialchars(upload_url($b['logo_url'])) ?>"
                                             alt="<?= htmlspecialchars($b['brand_name']) ?>"
                                             style="height: 28px; max-width: 80px; object-fit: contain;"
                                             title="<?= htmlspecialchars($b['brand_name']) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="width: 100%;">
                                <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                                    <tr>
                                        <th class="ps-3 py-2 text-muted" style="width: 90px;">Logo</th>
                                        <th class="py-2 text-muted">Nombre de la Marca</th>
                                        <th class="py-2 text-muted text-center" style="width: 80px;">Orden</th>
                                        <th class="py-2 text-muted text-center" style="width: 90px;">Estado</th>
                                        <th class="pe-3 py-2 text-end text-muted" style="width: 100px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <?php foreach ($brands as $b): ?>
                                        <tr class="transition-hover">
                                            <td class="ps-3 py-2 text-center">
                                                <div class="p-1 rounded d-inline-flex align-items-center justify-content-center" style="background: #ffffff; border: 1px solid var(--color-rule); width: 70px; height: 38px;">
                                                    <img src="<?= htmlspecialchars(upload_url($b['logo_url'])) ?>"
                                                         alt="<?= htmlspecialchars($b['brand_name']) ?>"
                                                         style="max-height: 28px; max-width: 60px; object-fit: contain;">
                                                </div>
                                            </td>
                                            <td class="py-2">
                                                <strong class="text-body" style="font-size: 0.85rem;"><?= htmlspecialchars($b['brand_name']) ?></strong>
                                            </td>
                                            <td class="py-2 text-center font-monospace small">
                                                <?= (int)$b['sort_order'] ?>
                                            </td>
                                            <td class="py-2 text-center">
                                                <?php if ($b['is_active']): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size: 0.7rem;">
                                                        Visible
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-body-secondary text-muted border px-2 py-0.5" style="font-size: 0.7rem;">
                                                        Oculto
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-3 py-2 text-end text-nowrap">
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1"
                                                            style="font-size: 0.75rem;"
                                                            onclick="editBrand(<?= htmlspecialchars(json_encode($b)) ?>)"
                                                            title="Editar Marca">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <form method="post" class="d-inline m-0" onsubmit="return confirm('¿Está seguro de eliminar el logo de <?= htmlspecialchars($b['brand_name']) ?>?')">
                                                        <input type="hidden" name="action" value="delete_brand">
                                                        <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                                                        <input type="hidden" name="brand_id" value="<?= $b['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger px-2 py-1"
                                                                style="font-size: 0.75rem;"
                                                                title="Eliminar">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 px-3">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                                <i class="fas fa-tags fa-2x"></i>
                            </div>
                            <h6 class="fw-bold text-body mb-1">Sin marcas registradas</h6>
                            <p class="text-muted small mb-0">Use el formulario a la derecha para agregar logos de marcas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PDF Header Color Setting -->
            <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-palette text-primary me-2"></i>Color Oficial del Encabezado PDF
                    </span>
                </div>
                <div class="card-body p-3">
                    <form method="post" class="d-flex align-items-end gap-3 flex-wrap m-0">
                        <input type="hidden" name="action" value="save_pdf_color">
                        <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                        <div>
                            <label class="form-label small text-muted mb-1">Selector</label>
                            <input type="color" class="form-control form-control-color"
                                   name="pdf_header_color" id="pdfColorPicker"
                                   value="<?= htmlspecialchars($pdfHeaderColor) ?>"
                                   style="width: 48px; height: 34px; cursor: pointer;">
                        </div>
                        <div>
                            <label class="form-label small text-muted mb-1">Código HEX</label>
                            <input type="text" class="form-control form-control-sm font-monospace"
                                   id="pdfColorHex" value="<?= htmlspecialchars($pdfHeaderColor) ?>"
                                   maxlength="7" style="width: 95px;"
                                   oninput="document.getElementById('pdfColorPicker').value=this.value; document.getElementById('pdfColorPreview').style.background=this.value;">
                        </div>
                        <div>
                            <label class="form-label small text-muted mb-1">Vista Previa</label>
                            <span id="pdfColorPreview" class="badge px-3 py-2 font-monospace"
                                  style="font-size: 0.8rem; background: <?= htmlspecialchars($pdfHeaderColor) ?>; color: #fff;">
                                COTIZACIÓN
                            </span>
                        </div>
                        <div class="ms-auto">
                            <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3">
                                <i class="fas fa-save me-1"></i> Guardar Color
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- =========================================================
             3. AGREGAR MARCA & PWA ICONS (Derecha 4 Col)
             ========================================================= -->
        <div class="col-12 col-lg-4">
            <!-- Add Brand Card -->
            <div class="card border-0 shadow-sm mb-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-plus-circle text-warning me-2"></i>Agregar Nueva Marca
                    </span>
                </div>
                <div class="card-body p-3">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_brand">
                        <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">

                        <div class="mb-2.5">
                            <label class="form-label small fw-semibold text-body mb-1">Nombre de la Marca *</label>
                            <input type="text" class="form-control form-control-sm" name="brand_name" placeholder="Ej: Continental, Pirelli, Triangle" required>
                        </div>

                        <div class="mb-2.5">
                            <label class="form-label small fw-semibold text-body mb-1">Archivo de Logo (PNG transparente) *</label>
                            <input type="file" class="form-control form-control-sm" name="logo" accept="image/*" required>
                            <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                Recomendado: formato PNG transparente, altura proporcional.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-body mb-1">Orden de Visualización</label>
                            <input type="number" class="form-control form-control-sm" name="sort_order" value="0" min="0">
                            <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                0 aparece primero, seguido de 1, 2, 3...
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold text-dark shadow-sm">
                            <i class="fas fa-upload me-1"></i> Subir y Registrar Logo
                        </button>
                    </form>
                </div>
            </div>

            <!-- PWA Icon Card -->
            <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold small text-body">
                        <i class="fas fa-mobile-screen text-info me-2"></i>Ícono de la App (PWA)
                    </span>
                    <a href="<?= BASE_URL ?>/manifest.php" target="_blank" class="badge bg-body-secondary text-body border text-decoration-none" style="font-size: 0.7rem;">
                        manifest.json <i class="fas fa-external-link-alt ms-1"></i>
                    </a>
                </div>
                <div class="card-body p-3">
                    <div class="text-center p-2 rounded mb-2" style="background: var(--color-canvas); border: 1px dashed var(--color-rule);">
                        <?php if ($pwaFaviconUrl): ?>
                            <img src="<?= htmlspecialchars(upload_url($pwaFaviconUrl)) ?>"
                                 alt="Favicon" style="width: 44px; height: 44px; object-fit: contain;">
                        <?php else: ?>
                            <i class="fas fa-star text-muted fa-2x opacity-50"></i>
                        <?php endif; ?>
                    </div>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_pwa_favicon">
                        <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                        <div class="input-group input-group-sm mb-1.5">
                            <input type="file" class="form-control" name="pwa_favicon" accept=".ico,.png,.jpg,.jpeg,.webp" required>
                            <button type="submit" class="btn btn-outline-secondary fw-semibold">Subir</button>
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            Genera automáticamente los tamaños PWA requeridos por Android / iOS.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: EDITAR MARCA
     ========================================================= -->
<div class="modal fade" id="editBrandModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <div class="modal-header border-bottom py-2.5 px-3">
                <h6 class="modal-title fw-bold text-body mb-0">
                    <i class="fas fa-pen text-warning me-2"></i>Editar Marca
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post">
                <div class="modal-body p-3">
                    <input type="hidden" name="action" value="update_brand">
                    <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                    <input type="hidden" name="brand_id" id="edit_brand_id">

                    <div class="mb-2.5">
                        <label class="form-label small fw-semibold text-body mb-1">Nombre</label>
                        <input type="text" class="form-control form-control-sm" name="brand_name" id="edit_brand_name" required>
                    </div>

                    <div class="mb-2.5">
                        <label class="form-label small fw-semibold text-body mb-1">Orden</label>
                        <input type="number" class="form-control form-control-sm" name="sort_order" id="edit_sort_order" min="0">
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label small fw-semibold text-body" for="edit_is_active">
                            Activo (visible en PDF)
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBrand(brand) {
    document.getElementById('edit_brand_id').value    = brand.id;
    document.getElementById('edit_brand_name').value  = brand.brand_name;
    document.getElementById('edit_sort_order').value  = brand.sort_order;
    document.getElementById('edit_is_active').checked = (brand.is_active == 1);
    new bootstrap.Modal(document.getElementById('editBrandModal')).show();
}

document.getElementById('pdfColorPicker').addEventListener('input', function() {
    document.getElementById('pdfColorHex').value = this.value;
    document.getElementById('pdfColorPreview').style.background = this.value;
});
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
