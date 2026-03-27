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

// Get selected company ID
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

    // Security check
    if (!$isSysAdmin && $targetCompanyId != $auth->getCompanyId()) {
        $_SESSION['error_message'] = 'No tienes permisos';
        $auth->redirect($_SERVER['REQUEST_URI']);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        try {
            $templateId = $_POST['template_id'] ?? null;
            $name = trim($_POST['name']);
            $header = $_POST['template_header'];
            $item = $_POST['template_item'];
            $footer = $_POST['template_footer'];
            $isDefault = isset($_POST['is_default']) ? 1 : 0;

            // If setting as default, unset other defaults
            if ($isDefault) {
                $stmt = $db->prepare("UPDATE cotirapi_templates SET is_default = 0 WHERE company_id = ?");
                $stmt->execute([$targetCompanyId]);
            }

            if ($templateId) {
                // Update
                $stmt = $db->prepare("
                    UPDATE cotirapi_templates SET
                        name = ?, template_header = ?, template_item = ?, template_footer = ?, is_default = ?
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$name, $header, $item, $footer, $isDefault, $templateId, $targetCompanyId]);
                $_SESSION['success_message'] = 'Plantilla actualizada correctamente';
            } else {
                // Insert
                $stmt = $db->prepare("
                    INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_default)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$targetCompanyId, $name, $header, $item, $footer, $isDefault]);
                $_SESSION['success_message'] = 'Plantilla creada correctamente';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        $auth->redirect('?company_id=' . $targetCompanyId);
    }

    if ($action === 'delete_template') {
        try {
            $templateId = $_POST['template_id'];
            $stmt = $db->prepare("DELETE FROM cotirapi_templates WHERE id = ? AND company_id = ?");
            $stmt->execute([$templateId, $targetCompanyId]);
            $_SESSION['success_message'] = 'Plantilla eliminada correctamente';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        $auth->redirect('?company_id=' . $targetCompanyId);
    }
}

// Get templates for selected company
$stmt = $db->prepare("SELECT * FROM cotirapi_templates WHERE company_id = ? ORDER BY is_default DESC, name ASC");
$stmt->execute([$selectedCompanyId]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company name
$companyRepo = new Company();
$selectedCompany = $companyRepo->getById($selectedCompanyId);
$companyName = $selectedCompany['name'] ?? 'Empresa';

$pageTitle = 'Plantillas CotiRapi';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand text-white" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Configuración</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> Empresa
                        </a>
                        <a href="<?= BASE_URL ?>/admin/cotirapi_templates.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-bolt"></i> Plantillas CotiRapi
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bolt"></i> <?= $pageTitle ?></h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="resetModal()">
                        <i class="fas fa-plus"></i> Nueva Plantilla
                    </button>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible">
                        <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Company Selector (only for sysadmin) -->
                <?php if ($isSysAdmin && !empty($companies)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <label class="col-form-label"><strong>Empresa:</strong></label>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" onchange="window.location.href='?company_id='+this.value">
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?= $company['id'] ?>" <?= $company['id'] == $selectedCompanyId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($company['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Templates List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Plantillas Registradas (<?= count($templates) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($templates)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5>No hay plantillas registradas</h5>
                                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="resetModal()">
                                    <i class="fas fa-plus"></i> Crear Primera Plantilla
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($templates as $template): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 <?= $template['is_default'] ? 'border-primary' : '' ?>">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <strong><?= htmlspecialchars($template['name']) ?></strong>
                                                <?php if ($template['is_default']): ?>
                                                    <span class="badge bg-primary">Predeterminada</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-2">
                                                    <small class="text-muted">Vista previa:</small>
                                                    <pre class="bg-light p-2 small" style="max-height: 150px; overflow-y: auto; font-size: 0.75rem;"><?= htmlspecialchars(substr($template['template_header'] . $template['template_item'] . $template['template_footer'], 0, 200)) ?>...</pre>
                                                </div>
                                            </div>
                                            <div class="card-footer">
                                                <button class="btn btn-sm btn-outline-primary" onclick='editTemplate(<?= json_encode($template) ?>)'>
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                                <?php if (!$template['is_default']): ?>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                                        <i class="fas fa-trash"></i> Eliminar
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Variables Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Variables Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Generales:</h6>
                                <ul class="small">
                                    <li><code>{CUSTOMER_NAME}</code> - Nombre del cliente</li>
                                    <li><code>{DATE}</code> - Fecha actual</li>
                                    <li><code>{CURRENCY}</code> - Símbolo de moneda (S/ o $)</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Por Item:</h6>
                                <ul class="small">
                                    <li><code>{ITEM_NUMBER}</code> - Número de item</li>
                                    <li><code>{CODE}</code> - Código del producto</li>
                                    <li><code>{CODE_LINE}</code> - Línea completa de código (con formato)</li>
                                    <li><code>{DESCRIPTION}</code> - Descripción</li>
                                    <li><code>{QUANTITY}</code> - Cantidad</li>
                                    <li><code>{UNIT_PRICE}</code> - Precio unitario</li>
                                    <li><code>{DISCOUNT_LINE}</code> - Línea de descuento (si aplica)</li>
                                    <li><code>{TOTAL}</code> - Total del item</li>
                                    <li><code>{IMAGE_URL}</code> - URL de imagen (si existe)</li>
                                    <li><code>{IMAGE_LINE}</code> - Línea con URL de imagen (si existe)</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Totales:</h6>
                                <ul class="small">
                                    <li><code>{SUBTOTAL}</code> - Subtotal</li>
                                    <li><code>{IGV}</code> - Monto de IGV</li>
                                    <li><code>{GRAND_TOTAL}</code> - Total general</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Template Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Nueva Plantilla</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="template_id" id="template_id">
                        <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">

                        <div class="mb-3">
                            <label class="form-label">Nombre de la Plantilla *</label>
                            <input type="text" class="form-control" name="name" id="template_name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Encabezado</label>
                            <textarea class="form-control" name="template_header" id="template_header" rows="4" style="font-family: monospace; font-size: 0.9rem;"></textarea>
                            <small class="text-muted">Texto que aparece al inicio. Variables: {CUSTOMER_NAME}, {DATE}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Formato de Item *</label>
                            <textarea class="form-control" name="template_item" id="template_item" rows="6" style="font-family: monospace; font-size: 0.9rem;" required></textarea>
                            <small class="text-muted">Se repite por cada producto. Variables: {ITEM_NUMBER}, {CODE}, {CODE_LINE}, {DESCRIPTION}, {QUANTITY}, {UNIT_PRICE}, {DISCOUNT_LINE}, {TOTAL}, {CURRENCY}</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pie de Página</label>
                            <textarea class="form-control" name="template_footer" id="template_footer" rows="6" style="font-family: monospace; font-size: 0.9rem;"></textarea>
                            <small class="text-muted">Texto que aparece al final. Variables: {SUBTOTAL}, {IGV}, {GRAND_TOTAL}, {CURRENCY}</small>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
                            <label class="form-check-label" for="is_default">
                                Establecer como plantilla predeterminada
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetModal() {
            document.getElementById('modalTitle').textContent = 'Nueva Plantilla';
            document.getElementById('template_id').value = '';
            document.getElementById('template_name').value = '';
            document.getElementById('template_header').value = '';
            document.getElementById('template_item').value = '';
            document.getElementById('template_footer').value = '';
            document.getElementById('is_default').checked = false;
        }

        function editTemplate(template) {
            document.getElementById('modalTitle').textContent = 'Editar Plantilla';
            document.getElementById('template_id').value = template.id;
            document.getElementById('template_name').value = template.name;
            document.getElementById('template_header').value = template.template_header;
            document.getElementById('template_item').value = template.template_item;
            document.getElementById('template_footer').value = template.template_footer;
            document.getElementById('is_default').checked = template.is_default == 1;

            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        function deleteTemplate(id) {
            if (confirm('¿Estás seguro de eliminar esta plantilla?')) {
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
</body>
</html>
