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
                $_SESSION['success_message'] = 'Logo PWA actualizado y tamaños PWA generados correctamente';
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
        $brandId   = (int)$_POST['brand_id'];
        $brandName = trim($_POST['brand_name'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($companySettings->updateBrandLogo($targetCompanyId, $brandId, $brandName, $sortOrder, $isActive)) {
            $_SESSION['success_message'] = 'Marca actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar la marca';
        }

    } elseif ($action === 'delete_brand') {
        $brandId = (int)$_POST['brand_id'];
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
require_once __DIR__ . '/../../includes/init.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php include __DIR__ . '/../../includes/notification_bell.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 bg-light border-end min-vh-100 py-3">
            <h5 class="mb-0 px-2"><i class="fas fa-cog"></i> Configuración</h5>
            <hr>
            <div class="list-group list-group-flush">
                <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-building"></i> Empresa
                </a>
                <a href="<?= BASE_URL ?>/admin/exchange_rate.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-dollar-sign"></i> Tipo de Cambio
                </a>
                <a href="<?= BASE_URL ?>/admin/email_settings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-envelope"></i> Correo Electrónico
                </a>
                <a href="<?= BASE_URL ?>/admin/bank_accounts.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-university"></i> Cuentas Bancarias
                </a>
                <a href="<?= BASE_URL ?>/admin/brand_logos.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-tags"></i> Logos de Marcas
                </a>
                <a href="<?= BASE_URL ?>/admin/api_settings.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-plug"></i> API / Integraciones
                </a>
                <a href="<?= BASE_URL ?>/admin/users.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users"></i> Usuarios
                </a>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9 col-lg-10 py-4 px-4">
            <div class="mb-3">
                <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Volver al Panel Admin
                </a>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i>Logos de Marcas</h4>
                <?php if ($isSysAdmin && !empty($companies)): ?>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label class="mb-0 text-muted small">Empresa:</label>
                    <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $selectedCompanyId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
            </div>

            <p class="text-muted">Los logos de marcas se muestran como una fila de imágenes en el encabezado del PDF de cotización.</p>

            <?php if ($isSysAdmin): ?>
            <?php
                $sessionCompanyId   = $auth->getCompanyId();
                $sessionCompanyLogo = $companySettings->getSetting($sessionCompanyId, 'company_logo_url');
                $sessionCompanyName = $companySettings->getSetting($sessionCompanyId, 'company_name') ?: 'Tu empresa';
            ?>
            <div class="alert alert-info py-2 small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                <strong>Ícono PWA del manifest:</strong> el ícono que se instala en el celular corresponde a la empresa de tu sesión
                (<strong><?= htmlspecialchars($sessionCompanyName) ?></strong> — ID <?= $sessionCompanyId ?>).
                <?php if ($sessionCompanyLogo): ?>
                    <span class="text-success"><i class="fas fa-check-circle me-1"></i>Tiene logo cargado.</span>
                <?php else: ?>
                    <span class="text-danger"><i class="fas fa-times-circle me-1"></i>Sin logo — selecciona esa empresa en el panel y súbele un logo.</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- PWA Icon Setting -->
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="fas fa-mobile-alt me-1"></i>Ícono de la aplicación (PWA)</span>
                    <a href="<?= BASE_URL ?>/manifest.php" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-eye me-1"></i>Ver manifest actual
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">El <strong>Favicon</strong> se usa como ícono PWA (es cuadrado y queda bien en celulares). El <strong>Logo</strong> es solo respaldo si no hay favicon.</p>
                    <div class="row g-3">
                        <!-- Logo (fallback) -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 text-center h-100">
                                <div class="fw-semibold mb-2"><i class="fas fa-image me-1"></i>Logo de empresa <span class="badge bg-secondary">Respaldo PDF / portada</span></div>
                                <?php if ($pwaLogoUrl): ?>
                                    <img src="<?= htmlspecialchars(upload_url($pwaLogoUrl)) ?>"
                                         alt="Logo" class="img-fluid mb-2" style="max-height:80px;object-fit:contain;">
                                <?php else: ?>
                                    <div class="bg-light p-3 mb-2 rounded">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                        <p class="text-muted small mt-1 mb-0">Sin logo</p>
                                    </div>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_pwa_logo">
                                    <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                                    <input type="file" class="form-control form-control-sm mb-2" name="pwa_logo" accept="image/*" required>
                                    <small class="text-muted d-block mb-2">PNG/JPG/WebP. Recomendado: 512×512px o mayor.</small>
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <i class="fas fa-upload me-1"></i>Subir Logo
                                    </button>
                                </form>
                            </div>
                        </div>
                        <!-- Favicon (browser tab) -->
                        <div class="col-md-6">
                            <div class="border rounded p-3 text-center h-100">
                                <div class="fw-semibold mb-2"><i class="fas fa-star me-1"></i>Favicon <span class="badge bg-success">Ícono PWA + Pestaña</span></div>
                                <?php if ($pwaFaviconUrl): ?>
                                    <img src="<?= htmlspecialchars(upload_url($pwaFaviconUrl)) ?>"
                                         alt="Favicon" class="mb-2" style="width:48px;height:48px;object-fit:contain;">
                                <?php else: ?>
                                    <div class="bg-light p-3 mb-2 rounded">
                                        <i class="fas fa-star fa-2x text-muted"></i>
                                        <p class="text-muted small mt-1 mb-0">Sin favicon</p>
                                    </div>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_pwa_favicon">
                                    <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                                    <input type="file" class="form-control form-control-sm mb-2" name="pwa_favicon" accept=".ico,.png,.jpg,.jpeg,.webp" required>
                                    <small class="text-muted d-block mb-2">ICO/PNG. Recomendado: 32×32px o 64×64px.</small>
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <i class="fas fa-upload me-1"></i>Subir Favicon
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PDF Color Setting -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-palette me-1"></i>Color principal del PDF de cotización</div>
                <div class="card-body">
                    <form method="post" class="d-flex align-items-end gap-3 flex-wrap">
                        <input type="hidden" name="action" value="save_pdf_color">
                        <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                        <div>
                            <label class="form-label mb-1 d-block">Color</label>
                            <input type="color" class="form-control form-control-color"
                                   name="pdf_header_color" id="pdfColorPicker"
                                   value="<?= htmlspecialchars($pdfHeaderColor) ?>"
                                   style="width:60px;height:38px;padding:2px;">
                        </div>
                        <div>
                            <label class="form-label mb-1 d-block">Hex</label>
                            <input type="text" class="form-control form-control-sm font-monospace"
                                   id="pdfColorHex" value="<?= htmlspecialchars($pdfHeaderColor) ?>"
                                   maxlength="7" style="width:90px;"
                                   oninput="document.getElementById('pdfColorPicker').value=this.value">
                        </div>
                        <div>
                            <label class="form-label mb-1 d-block">Vista previa</label>
                            <span id="pdfColorPreview"
                                  style="display:inline-block;padding:4px 18px;border-radius:4px;font-weight:bold;font-size:13px;color:#fff;background:<?= htmlspecialchars($pdfHeaderColor) ?>;">
                                COTIZACIÓN
                            </span>
                        </div>
                        <div class="mt-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save me-1"></i>Guardar color
                            </button>
                        </div>
                    </form>
                    <small class="text-muted mt-2 d-block">Afecta: encabezado "COTIZACIÓN", cabecera de tabla de items y fila "TOTAL IMPORTE".</small>
                </div>
            </div>

            <div class="row">
                <!-- Add brand form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-plus-circle me-1"></i>Agregar Marca</div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_brand">
                                <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                                <div class="mb-3">
                                    <label class="form-label">Nombre de la Marca <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="brand_name" placeholder="Ej: Continental" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Logo <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="logo" accept="image/*" required>
                                    <small class="text-muted">PNG, JPG, SVG o WebP. Recomendado: fondo transparente (PNG), máx 1MB.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Orden</label>
                                    <input type="number" class="form-control" name="sort_order" value="0" min="0">
                                    <small class="text-muted">Menor número = aparece primero.</small>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-upload me-1"></i>Agregar Logo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Brands list -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list me-1"></i>Marcas Registradas</span>
                            <span class="badge bg-secondary"><?= count($brands) ?> marca(s)</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($brands)): ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-tags fa-2x mb-2 d-block"></i>
                                    No hay logos de marcas registrados.
                                </div>
                            <?php else: ?>
                                <!-- Preview row -->
                                <div class="p-3 bg-light border-bottom d-flex align-items-center gap-3 flex-wrap">
                                    <small class="text-muted fw-bold">Vista previa PDF:</small>
                                    <?php foreach ($brands as $b): ?>
                                        <?php if ($b['is_active']): ?>
                                        <img src="<?= htmlspecialchars(upload_url($b['logo_url'])) ?>"
                                             alt="<?= htmlspecialchars($b['brand_name']) ?>"
                                             style="height:32px; object-fit:contain;"
                                             title="<?= htmlspecialchars($b['brand_name']) ?>">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Logo</th>
                                            <th>Nombre</th>
                                            <th class="text-center">Orden</th>
                                            <th class="text-center">Activo</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($brands as $b): ?>
                                        <tr>
                                            <td>
                                                <img src="<?= htmlspecialchars(upload_url($b['logo_url'])) ?>"
                                                     alt="<?= htmlspecialchars($b['brand_name']) ?>"
                                                     style="height:36px; max-width:80px; object-fit:contain;">
                                            </td>
                                            <td class="align-middle"><?= htmlspecialchars($b['brand_name']) ?></td>
                                            <td class="text-center align-middle"><?= $b['sort_order'] ?></td>
                                            <td class="text-center align-middle">
                                                <?php if ($b['is_active']): ?>
                                                    <span class="badge bg-success">Sí</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center align-middle">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        onclick="editBrand(<?= htmlspecialchars(json_encode($b)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" class="d-inline"
                                                      onsubmit="return confirm('¿Eliminar este logo?')">
                                                    <input type="hidden" name="action" value="delete_brand">
                                                    <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                                                    <input type="hidden" name="brand_id" value="<?= $b['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="editBrandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-1"></i>Editar Marca</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_brand">
                    <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
                    <input type="hidden" name="brand_id" id="edit_brand_id">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="brand_name" id="edit_brand_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" class="form-control" name="sort_order" id="edit_sort_order" min="0">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <label class="form-check-label" for="edit_is_active">Activo (visible en PDF)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editBrand(brand) {
    document.getElementById('edit_brand_id').value    = brand.id;
    document.getElementById('edit_brand_name').value  = brand.brand_name;
    document.getElementById('edit_sort_order').value  = brand.sort_order;
    document.getElementById('edit_is_active').checked = brand.is_active == 1;
    new bootstrap.Modal(document.getElementById('editBrandModal')).show();
}

// Sync color picker ↔ hex input ↔ preview
document.getElementById('pdfColorPicker').addEventListener('input', function() {
    document.getElementById('pdfColorHex').value = this.value;
    document.getElementById('pdfColorPreview').style.background = this.value;
});
document.getElementById('pdfColorHex').addEventListener('input', function() {
    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        document.getElementById('pdfColorPicker').value = this.value;
        document.getElementById('pdfColorPreview').style.background = this.value;
    }
});
</script>
</body>
</html>
