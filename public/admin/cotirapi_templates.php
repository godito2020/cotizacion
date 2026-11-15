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
if (!$isSysAdmin) {
    $selectedCompanyId = $auth->getCompanyId();
}

$db = getDBConnection();

// Get all companies for dropdown (only for sysadmin)
$companies = [];
if ($isSysAdmin) {
    $companyRepo = new Company();
    $companies = $companyRepo->getAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetCompanyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : $selectedCompanyId;

    if (!$isSysAdmin && $targetCompanyId != $auth->getCompanyId()) {
        $_SESSION['error_message'] = 'No tienes permisos';
        $auth->redirect($_SERVER['REQUEST_URI']);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        try {
            $templateId = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
            $name = trim($_POST['name'] ?? '');
            $header = $_POST['template_header'] ?? '';
            $item = $_POST['template_item'] ?? '';
            $footer = $_POST['template_footer'] ?? '';
            $isDefault = isset($_POST['is_default']) ? 1 : 0;

            if ($isDefault) {
                $stmt = $db->prepare("UPDATE cotirapi_templates SET is_default = 0 WHERE company_id = ?");
                $stmt->execute([$targetCompanyId]);
            }

            if ($templateId) {
                $stmt = $db->prepare("
                    UPDATE cotirapi_templates SET
                        name = ?, template_header = ?, template_item = ?, template_footer = ?, is_default = ?
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$name, $header, $item, $footer, $isDefault, $templateId, $targetCompanyId]);
                $_SESSION['success_message'] = 'Plantilla actualizada exitosamente';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_default)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$targetCompanyId, $name, $header, $item, $footer, $isDefault]);
                $_SESSION['success_message'] = 'Plantilla creada exitosamente';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        $auth->redirect('?company_id=' . $targetCompanyId);
    }

    if ($action === 'delete_template') {
        try {
            $templateId = (int)($_POST['template_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM cotirapi_templates WHERE id = ? AND company_id = ?");
            $stmt->execute([$templateId, $targetCompanyId]);
            $_SESSION['success_message'] = 'Plantilla eliminada correctamente';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        $auth->redirect('?company_id=' . $targetCompanyId);
    }
}

// Get templates
$stmt = $db->prepare("SELECT * FROM cotirapi_templates WHERE company_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$selectedCompanyId]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$companyRepo = new Company();
$selectedCompany = $companyRepo->getById($selectedCompanyId);
$companyName = $selectedCompany['name'] ?? 'Empresa';

$pageTitle = 'Plantillas CotiRapi';
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
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Plantillas CotiRapi</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Plantillas CotiRapi (WhatsApp & Texto)
                    </h1>
                    <span class="badge rounded-pill bg-body-tertiary text-body border px-2 py-1 small fw-semibold">
                        <?= count($templates) ?> registradas
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Formatos dinámicos para compartir cotizaciones rápidamente por WhatsApp, email o mensajería de texto.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="resetModal()">
                    <i class="fas fa-plus me-1"></i> + Nueva Plantilla
                </button>
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

    <!-- Company Selector (SysAdmin only) -->
    <?php if ($isSysAdmin && !empty($companies)): ?>
        <div class="card mb-3 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
            <div class="card-body p-2 px-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-building text-warning"></i>
                        <span class="small fw-semibold text-body">Gestionar Empresa:</span>
                    </div>
                    <div style="min-width: 280px;">
                        <select class="form-select form-select-sm" onchange="window.location.href='?company_id='+this.value">
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $selectedCompanyId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comp['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         2. TEMPLATES GRID
         ========================================================= -->
    <div class="row g-3 mb-4">
        <?php if (empty($templates)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm text-center py-5 px-3" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <i class="fas fa-bolt fa-2x"></i>
                    </div>
                    <h6 class="fw-bold text-body mb-1">No hay plantillas CotiRapi registradas</h6>
                    <p class="text-muted small mb-3">Cree una plantilla personalizada para compartir cotizaciones en formato texto por WhatsApp.</p>
                    <div>
                        <button class="btn btn-warning btn-sm fw-bold text-dark px-3" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="resetModal()">
                            <i class="fas fa-plus me-1"></i> Crear Primera Plantilla
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="col-12 col-md-6">
                    <div class="card border-0 shadow-sm h-100 transition-hover" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px; overflow: hidden;">
                        <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fab fa-whatsapp text-success"></i>
                                <strong class="text-body small"><?= htmlspecialchars($template['name']) ?></strong>
                            </div>
                            <?php if (!empty($template['is_default'])): ?>
                                <span class="badge bg-warning text-dark fw-bold px-2 py-0.5" style="font-size: 0.68rem;">
                                    <i class="fas fa-star me-1"></i>Predeterminada
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="card-body p-3">
                            <div class="small text-muted mb-1.5" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                Estructura de Mensaje:
                            </div>
                            <pre class="p-2.5 rounded font-monospace text-body mb-0"
                                 style="background-color: var(--color-canvas); border: 1px solid var(--color-rule); max-height: 160px; overflow-y: auto; font-size: 0.75rem; white-space: pre-wrap; line-height: 1.4;"><?= htmlspecialchars(substr($template['template_header'] . $template['template_item'] . $template['template_footer'], 0, 320)) ?>...</pre>
                        </div>

                        <div class="card-footer bg-body-tertiary border-top py-2 px-3 d-flex justify-content-between align-items-center">
                            <span class="text-muted small" style="font-size: 0.72rem;">ID #<?= $template['id'] ?></span>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary px-2 py-0.5" style="font-size: 0.75rem;" onclick='editTemplate(<?= json_encode($template) ?>)'>
                                    <i class="fas fa-pen me-1"></i>Editar
                                </button>
                                <?php if (empty($template['is_default'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger px-2 py-0.5" style="font-size: 0.75rem;" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                        <i class="fas fa-trash-alt me-1"></i>Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- =========================================================
         3. VARIABLES REFERENCE GUIDE (Accordion / Card)
         ========================================================= -->
    <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
        <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3">
            <span class="fw-bold small text-body">
                <i class="fas fa-code text-primary me-2"></i>Guía de Variables Disponibles
            </span>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-4">
                    <span class="small fw-bold text-body d-block mb-1">Encabezado y Generales:</span>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-1 small text-muted" style="font-size: 0.78rem;">
                        <li><code class="text-primary">{CUSTOMER_NAME}</code> - Nombre o razón social del cliente</li>
                        <li><code class="text-primary">{DATE}</code> - Fecha de emisión de la cotización</li>
                        <li><code class="text-primary">{CURRENCY}</code> - Símbolo de moneda (S/ o $)</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <span class="small fw-bold text-body d-block mb-1">Por Cada Ítem (Producto):</span>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-1 small text-muted" style="font-size: 0.78rem;">
                        <li><code class="text-warning">{ITEM_NUMBER}</code> - Número de línea (1, 2, 3...)</li>
                        <li><code class="text-warning">{CODE}</code> - Código del producto</li>
                        <li><code class="text-warning">{DESCRIPTION}</code> - Nombre y medida del producto</li>
                        <li><code class="text-warning">{QUANTITY}</code> - Cantidad entera cotizada</li>
                        <li><code class="text-warning">{UNIT_PRICE}</code> - Precio unitario</li>
                        <li><code class="text-warning">{TOTAL}</code> - Importe total del producto</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <span class="small fw-bold text-body d-block mb-1">Pie de Mensaje y Totales:</span>
                    <ul class="list-unstyled mb-0 d-flex flex-column gap-1 small text-muted" style="font-size: 0.78rem;">
                        <li><code class="text-success">{SUBTOTAL}</code> - Subtotal antes de impuestos</li>
                        <li><code class="text-success">{IGV}</code> - Monto calculado del IGV</li>
                        <li><code class="text-success">{GRAND_TOTAL}</code> - Total general con IGV</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: AGREGAR / EDITAR PLANTILLA
     ========================================================= -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <form method="POST">
                <div class="modal-header border-bottom py-2.5 px-3">
                    <h6 class="modal-title fw-bold text-body mb-0" id="modalTitle">
                        <i class="fas fa-bolt text-warning me-2"></i>Nueva Plantilla CotiRapi
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_id" id="template_id">
                    <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-body mb-1">Nombre de la Plantilla *</label>
                        <input type="text" class="form-control form-control-sm" name="name" id="template_name" placeholder="Ej: Cotización Rápida WhatsApp Estándar" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-body mb-1">Encabezado de la Cotización</label>
                        <textarea class="form-control form-control-sm font-monospace" name="template_header" id="template_header" rows="3" placeholder="Hola {CUSTOMER_NAME}, le compartimos la cotización solicitada el {DATE}:"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-body mb-1">Formato de Ítem (Se repite por cada producto) *</label>
                        <textarea class="form-control form-control-sm font-monospace" name="template_item" id="template_item" rows="5" placeholder="🔹 {QUANTITY}x {DESCRIPTION} (Cód: {CODE}) - {CURRENCY} {TOTAL}" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-body mb-1">Pie de Mensaje y Resumen de Totales</label>
                        <textarea class="form-control form-control-sm font-monospace" name="template_footer" id="template_footer" rows="4" placeholder="Total a Pagar: {CURRENCY} {GRAND_TOTAL} (Incluye IGV). Válido por 7 días."></textarea>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_default" id="is_default">
                        <label class="form-check-label small fw-semibold text-body" for="is_default">
                            Establecer como plantilla predeterminada de la empresa
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                        <i class="fas fa-save me-1"></i> Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-bolt text-warning me-2"></i>Nueva Plantilla CotiRapi';
    document.getElementById('template_id').value = '';
    document.getElementById('template_name').value = '';
    document.getElementById('template_header').value = '';
    document.getElementById('template_item').value = '';
    document.getElementById('template_footer').value = '';
    document.getElementById('is_default').checked = false;
}

function editTemplate(template) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen text-warning me-2"></i>Editar Plantilla CotiRapi';
    document.getElementById('template_id').value = template.id;
    document.getElementById('template_name').value = template.name;
    document.getElementById('template_header').value = template.template_header || '';
    document.getElementById('template_item').value = template.template_item || '';
    document.getElementById('template_footer').value = template.template_footer || '';
    document.getElementById('is_default').checked = (template.is_default == 1);

    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function deleteTemplate(id) {
    if (confirm('¿Está seguro de que desea eliminar esta plantilla?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${id}">
            <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
