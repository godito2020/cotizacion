<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Check if user is admin
$isAdmin = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']);

// Get all users in the company for owner selection (loaded after customer is fetched)
$companyUsers = [];

// Get customer ID from URL
$customerId = $_GET['id'] ?? null;

if (!$customerId) {
    $_SESSION['error_message'] = 'ID de cliente no especificado';
    $auth->redirect(BASE_URL . '/customers/index.php');
}

// Get customer data
$customerRepo = new Customer();
if ($isAdmin) {
    $customer = $customerRepo->getByIdGlobal($customerId);
} else {
    $customer = $customerRepo->getById($customerId, $companyId);
}

if (!$customer) {
    $_SESSION['error_message'] = 'Cliente no encontrado';
    $auth->redirect(BASE_URL . '/customers/index.php');
}

// Use the customer's own company_id for operations
$customerCompanyId = $customer['company_id'];

// Load users from the customer's company for owner selection
if ($isAdmin) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, first_name, last_name, username FROM users WHERE company_id = ? AND is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute([$customerCompanyId]);
    $companyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$errors = [];
$formData = $customer; // Pre-fill with existing data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;

    // Validation
    if (empty($_POST['name'])) {
        $errors['name'] = 'El nombre es requerido';
    }

    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El email no es válido';
    }

    // Validar emails CC (pueden ser múltiples separados por coma)
    if (!empty($_POST['email_cc'])) {
        $ccEmails = array_map('trim', explode(',', $_POST['email_cc']));
        foreach ($ccEmails as $ccEmail) {
            if (!empty($ccEmail) && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email_cc'] = 'Uno o más emails de CC no son válidos';
                break;
            }
        }
    }

    if (!empty($_POST['tax_id'])) {
        $taxId = preg_replace('/[^0-9]/', '', $_POST['tax_id']);
        if (strlen($taxId) != 8 && strlen($taxId) != 10 && strlen($taxId) != 11) {
            $errors['tax_id'] = 'El documento debe tener 8 dígitos (DNI), 10 u 11 dígitos (RUC)';
        } else {
            // Check if another customer with this tax_id already exists (excluding current customer)
            $existingCustomer = $customerRepo->findByTaxId($taxId, $customerCompanyId);
            if ($existingCustomer && $existingCustomer['id'] != $customerId) {
                $errors['tax_id'] = 'Ya existe otro cliente registrado con este documento: ' . htmlspecialchars($existingCustomer['name']);
            }
        }
    }

    if (empty($errors)) {
        $name = $_POST['name'];
        $contact_person = $_POST['contact_person'] ?? null;
        $email = $_POST['email'] ?? null;
        $email_cc = $_POST['email_cc'] ?? null;
        $phone = $_POST['phone'] ?? null;
        $address = $_POST['address'] ?? null;
        $tax_id = !empty($_POST['tax_id']) ? preg_replace('/[^0-9]/', '', $_POST['tax_id']) : null;
        $company_status = $_POST['company_status'] ?? null;
        $user_id = $isAdmin && isset($_POST['user_id']) ? (int)$_POST['user_id'] : $customer['user_id'];

        $result = $customerRepo->update(
            $customerId,
            $customerCompanyId,
            $user_id,
            $name,
            $contact_person,
            $email,
            $email_cc,
            $phone,
            $address,
            $tax_id,
            $company_status
        );

        if ($result) {
            $_SESSION['success_message'] = 'Cliente actualizado exitosamente';
            $auth->redirect(BASE_URL . '/customers/view.php?id=' . $customerId);
        } else {
            $errors['general'] = 'Error al actualizar el cliente';
        }
    }
}

$pageTitle = 'Editar Cliente';
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
    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            if (savedTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/customers/index.php">Clientes</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-user-edit"></i> <?= $pageTitle ?></h1>
                    <div>
                        <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customerId ?>" class="btn btn-outline-info me-2">
                            <i class="fas fa-eye"></i> Ver Cliente
                        </a>
                        <a href="<?= BASE_URL ?>/customers/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Información del Cliente</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="customer-form">
                            <div class="row">
                                <!-- Lookup Section -->
                                <div class="col-12 mb-4">
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h6 class="mb-0">
                                                <i class="fas fa-search"></i> Consulta Automática (Opcional)
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Consultar DNI</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control dni-input"
                                                               placeholder="Ingrese DNI (8 dígitos)" maxlength="8">
                                                        <button type="button" class="btn btn-outline-primary lookup-dni-btn">
                                                            <i class="fas fa-search"></i> Consultar DNI
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Consultar RUC</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control ruc-input"
                                                               placeholder="Ingrese RUC (11 dígitos)" maxlength="11">
                                                        <button type="button" class="btn btn-outline-primary lookup-ruc-btn">
                                                            <i class="fas fa-search"></i> Consultar RUC
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nombre / Razón Social *</label>
                                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                               id="name" name="name" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="contact_person" class="form-label">Persona de Contacto</label>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person"
                                               value="<?= htmlspecialchars($formData['contact_person'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Principal</label>
                                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                               id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                                               placeholder="correo@ejemplo.com">
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?= $errors['email'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_cc" class="form-label">
                                            Emails en Copia (CC)
                                            <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip"
                                               title="Correos adicionales que recibirán copia de las cotizaciones. Separe múltiples correos con coma."></i>
                                        </label>
                                        <input type="text" class="form-control <?= isset($errors['email_cc']) ? 'is-invalid' : '' ?>"
                                               id="email_cc" name="email_cc" value="<?= htmlspecialchars($formData['email_cc'] ?? '') ?>"
                                               placeholder="correo1@ejemplo.com, correo2@ejemplo.com">
                                        <?php if (isset($errors['email_cc'])): ?>
                                            <div class="invalid-feedback"><?= $errors['email_cc'] ?></div>
                                        <?php endif; ?>
                                        <small class="text-muted">Múltiples correos separados por coma</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Document and Status -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tax_id" class="form-label">DNI / RUC</label>
                                        <input type="text" class="form-control <?= isset($errors['tax_id']) ? 'is-invalid' : '' ?>"
                                               id="tax_id" name="tax_id" value="<?= htmlspecialchars($formData['tax_id'] ?? '') ?>">
                                        <?php if (isset($errors['tax_id'])): ?>
                                            <div class="invalid-feedback"><?= $errors['tax_id'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_status" class="form-label">Estado de la Empresa</label>
                                        <select class="form-select" id="company_status" name="company_status">
                                            <option value="">Seleccionar estado</option>
                                            <option value="ACTIVO" <?= ($formData['company_status'] ?? '') === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                                            <option value="INACTIVO" <?= ($formData['company_status'] ?? '') === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                                            <option value="SUSPENDIDO" <?= ($formData['company_status'] ?? '') === 'SUSPENDIDO' ? 'selected' : '' ?>>Suspendido</option>
                                        </select>
                                    </div>
                                </div>

                                 <div class="col-12">
                                     <div class="mb-3">
                                         <label for="address" class="form-label">Dirección</label>
                                         <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($formData['address'] ?? '') ?></textarea>
                                     </div>
                                 </div>

                                 <?php if ($isAdmin): ?>
                                 <div class="col-md-6">
                                     <div class="mb-3">
                                         <label for="user_id" class="form-label">Propietario del Cliente *</label>
                                         <select class="form-select" id="user_id" name="user_id" required>
                                             <option value="">Seleccionar propietario</option>
                                             <?php foreach ($companyUsers as $companyUser): ?>
                                                 <option value="<?= $companyUser['id'] ?>" <?= ($formData['user_id'] ?? $customer['user_id']) == $companyUser['id'] ? 'selected' : '' ?>>
                                                     <?= htmlspecialchars($companyUser['first_name'] . ' ' . $companyUser['last_name']) ?> (<?= htmlspecialchars($companyUser['username']) ?>)
                                                 </option>
                                             <?php endforeach; ?>
                                         </select>
                                         <small class="text-muted">Usuario que registró o es responsable de este cliente</small>
                                     </div>
                                 </div>
                                 <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= BASE_URL ?>/customers/view.php?id=<?= $customerId ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Actualizar Cliente
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- JSON Preview Modal -->
    <div class="modal fade" id="jsonPreviewModal" tabindex="-1" aria-labelledby="jsonPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="jsonPreviewModalLabel">
                        <i class="fas fa-code"></i> Datos de Consulta API
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Tipo de Consulta:</strong></label>
                        <span class="badge bg-primary" id="consultaType">-</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Documento Consultado:</strong></label>
                        <span class="badge bg-secondary" id="consultaDocument">-</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Respuesta JSON:</strong></label>
                        <pre class="bg-light p-3 rounded" id="jsonContent" style="max-height: 400px; overflow-y: auto; font-size: 12px;"></pre>
                    </div>
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Campos Utilizables:</h6>
                        <div id="usableFields"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="fillFormBtn">
                        <i class="fas fa-edit"></i> Llenar Formulario
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        const dniInput = document.querySelector('.dni-input');
        const rucInput = document.querySelector('.ruc-input');
        const lookupDniBtn = document.querySelector('.lookup-dni-btn');
        const lookupRucBtn = document.querySelector('.lookup-ruc-btn');

        const nameField = document.getElementById('name');
        const contactPersonField = document.getElementById('contact_person');
        const taxIdField = document.getElementById('tax_id');
        const addressField = document.getElementById('address');

        // Modal elements
        const jsonModal = new bootstrap.Modal(document.getElementById('jsonPreviewModal'));
        const fillFormBtn = document.getElementById('fillFormBtn');

        // Store last API response for modal
        let lastApiResponse = null;
        let lastApiAction = null;
        let lastApiDocument = null;

        // DNI Lookup
        lookupDniBtn.addEventListener('click', function() {
            const dni = dniInput.value.trim();
            if (!dni) {
                alert('Por favor ingrese un DNI');
                return;
            }
            if (dni.length !== 8) {
                alert('El DNI debe tener 8 dígitos');
                return;
            }
            performLookup('dni', dni, lookupDniBtn);
        });

        // RUC Lookup
        lookupRucBtn.addEventListener('click', function() {
            const ruc = rucInput.value.trim();
            if (!ruc) {
                alert('Por favor ingrese un RUC');
                return;
            }
            if (ruc.length !== 11) {
                alert('El RUC debe tener 11 dígitos');
                return;
            }
            performLookup('ruc', ruc, lookupRucBtn);
        });

        // Allow Enter key to trigger lookup
        dniInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupDniBtn.click();
            }
        });

        rucInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupRucBtn.click();
            }
        });

        // Only allow numbers in DNI/RUC inputs and check for duplicates
        dniInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        rucInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Check for duplicate tax_id field (exclude current customer)
        taxIdField.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            const length = this.value.length;
            if (length === 8 || length === 11) {
                const type = length === 8 ? 'DNI' : 'RUC';
                checkDuplicateDocument(this.value, type);
            } else {
                clearDuplicateMessage();
            }
        });

        function performLookup(action, document, button) {
            const originalText = button.innerHTML;
            const originalDisabled = button.disabled;

            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';
            button.disabled = true;

            fetch(`<?= BASE_URL ?>/api/lookup_document.php?action=${action}&document=${document}`)
                .then(response => response.json())
                .then(data => {
                    lastApiResponse = data;
                    lastApiAction = action;
                    lastApiDocument = document;

                    if (data.success) {
                        if (action === 'dni') {
                            fillDniData(data.data);
                        } else if (action === 'ruc') {
                            fillRucData(data.data);
                        }
                        showMessage('Datos consultados exitosamente', 'success', true);
                    } else {
                        showMessage('Error: ' + (data.message || 'No se pudo consultar el documento'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error de conexión al consultar el documento', 'danger');
                })
                .finally(() => {
                    button.innerHTML = originalText;
                    button.disabled = originalDisabled;
                });
        }

        function fillDniData(data) {
            if (data.nombre_completo) {
                nameField.value = data.nombre_completo;
            }
            if (data.dni) {
                taxIdField.value = data.dni;
            }
            rucInput.value = '';
        }

        function fillRucData(data) {
            if (data.razon_social) {
                nameField.value = data.razon_social;
            }
            if (data.ruc) {
                taxIdField.value = data.ruc;
            }

            // Combine address fields
            let fullAddress = '';
            if (data.direccion) {
                fullAddress += data.direccion;
            }
            if (data.distrito) {
                fullAddress += (fullAddress ? ', ' : '') + data.distrito;
            }
            if (data.provincia) {
                fullAddress += (fullAddress ? ', ' : '') + data.provincia;
            }
            if (data.departamento) {
                fullAddress += (fullAddress ? ', ' : '') + data.departamento;
            }

            if (fullAddress) {
                addressField.value = fullAddress;
            }

            if (data.estado) {
                const statusField = document.getElementById('company_status');
                if (statusField) {
                    statusField.value = data.estado;
                }
            }

            dniInput.value = '';
        }

        function showMessage(message, type, showJsonButton = false) {
            const existingAlerts = document.querySelectorAll('.lookup-alert');
            existingAlerts.forEach(alert => alert.remove());

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show lookup-alert`;

            let jsonButtonHtml = '';
            if (showJsonButton && type === 'success') {
                jsonButtonHtml = `
                    <button type="button" class="btn btn-outline-primary btn-sm ms-3" onclick="showJsonModal()">
                        <i class="fas fa-code"></i> Ver JSON
                    </button>
                `;
            }

            alertDiv.innerHTML = `
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                        ${message}
                    </div>
                    <div class="d-flex align-items-center">
                        ${jsonButtonHtml}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            `;

            const form = document.querySelector('.customer-form');
            form.parentNode.insertBefore(alertDiv, form);

            if (type === 'success') {
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 8000);
            }
        }

        // Function to check for duplicate documents (exclude current customer)
        function checkDuplicateDocument(document, type) {
            clearTimeout(window.duplicateCheckTimeout);

            window.duplicateCheckTimeout = setTimeout(() => {
                fetch(`<?= BASE_URL ?>/api/check_duplicate_customer.php?tax_id=${document}&exclude_id=<?= $customerId ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            showDuplicateMessage(type, document, data.customer_name);
                        } else {
                            clearDuplicateMessage();
                        }
                    })
                    .catch(error => {
                        console.error('Error checking duplicate:', error);
                    });
            }, 500);
        }

        function showDuplicateMessage(type, document, customerName) {
            clearDuplicateMessage();

            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning alert-dismissible fade show duplicate-alert';
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div>
                        <strong>¡Atención!</strong> Ya existe otro cliente con ${type} <strong>${document}</strong>
                        <br><small>Cliente: ${customerName}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            const form = document.querySelector('.customer-form');
            form.parentNode.insertBefore(alertDiv, form);

            taxIdField.classList.add('is-invalid');

            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.title = 'No se puede guardar: documento duplicado';
            }
        }

        function clearDuplicateMessage() {
            const existingAlerts = document.querySelectorAll('.duplicate-alert');
            existingAlerts.forEach(alert => alert.remove());

            taxIdField.classList.remove('is-invalid');

            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.title = '';
            }
        }

        // Global function to show JSON modal
        window.showJsonModal = function() {
            if (!lastApiResponse) {
                alert('No hay datos de consulta disponibles');
                return;
            }

            document.getElementById('consultaType').textContent =
                lastApiAction === 'dni' ? 'RENIEC (DNI)' : 'SUNAT (RUC)';
            document.getElementById('consultaDocument').textContent = lastApiDocument;
            document.getElementById('jsonContent').textContent =
                JSON.stringify(lastApiResponse, null, 2);

            const usableFieldsDiv = document.getElementById('usableFields');
            let fieldsHtml = '';

            if (lastApiAction === 'dni' && lastApiResponse.success) {
                const data = lastApiResponse.data;
                fieldsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Para formulario:</strong>
                            <ul class="mb-0">
                                <li><code>nombre_completo</code> → Nombre</li>
                                <li><code>dni</code> → DNI/RUC</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <strong>Campos disponibles:</strong>
                            <ul class="mb-0">
                                <li><code>nombres</code>: "${data.nombres || 'N/A'}"</li>
                                <li><code>apellido_paterno</code>: "${data.apellido_paterno || 'N/A'}"</li>
                                <li><code>apellido_materno</code>: "${data.apellido_materno || 'N/A'}"</li>
                            </ul>
                        </div>
                    </div>
                `;
            } else if (lastApiAction === 'ruc' && lastApiResponse.success) {
                const data = lastApiResponse.data;
                fieldsHtml = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Para formulario:</strong>
                            <ul class="mb-0">
                                <li><code>razon_social</code> → Nombre</li>
                                <li><code>ruc</code> → DNI/RUC</li>
                                <li><code>direccion + distrito + provincia + departamento</code> → Dirección</li>
                                <li><code>estado</code> → Estado de la empresa</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <strong>Campos adicionales:</strong>
                            <ul class="mb-0">
                                <li><code>estado</code>: "${data.estado || 'N/A'}"</li>
                                <li><code>condicion</code>: "${data.condicion || 'N/A'}"</li>
                                <li><code>distrito</code>: "${data.distrito || 'N/A'}"</li>
                                <li><code>provincia</code>: "${data.provincia || 'N/A'}"</li>
                                <li><code>departamento</code>: "${data.departamento || 'N/A'}"</li>
                            </ul>
                        </div>
                    </div>
                `;
            }

            usableFieldsDiv.innerHTML = fieldsHtml;
            jsonModal.show();
        };

        // Fill form from modal
        fillFormBtn.addEventListener('click', function() {
            if (lastApiResponse && lastApiResponse.success) {
                if (lastApiAction === 'dni') {
                    fillDniData(lastApiResponse.data);
                } else if (lastApiAction === 'ruc') {
                    fillRucData(lastApiResponse.data);
                }
                jsonModal.hide();
                showMessage('Formulario actualizado desde JSON', 'success');
            }
        });
    });
    </script>
</body>
</html>