<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;

    // Validation
    if (empty($_POST['name'])) {
        $errors['name'] = 'El nombre es requerido';
    }

    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El email no es válido';
    }

    if (!empty($_POST['tax_id'])) {
        $taxId = preg_replace('/[^0-9]/', '', $_POST['tax_id']);
        if (strlen($taxId) != 8 && strlen($taxId) != 11) {
            $errors['tax_id'] = 'El documento debe tener 8 dígitos (DNI) u 11 dígitos (RUC)';
        } else {
            // Check if customer with this tax_id already exists in this company
            $customerRepo = new Customer();
            $existingCustomer = $customerRepo->findByTaxId($taxId, $companyId);
            if ($existingCustomer) {
                $errors['tax_id'] = 'Ya existe un cliente con este documento: ' . htmlspecialchars($existingCustomer['name']);
            }
        }
    }

    if (empty($errors)) {
        $customerRepo = new Customer();

        $result = $customerRepo->create(
            $companyId,
            $user['id'],
            $_POST['name'],
            $_POST['contact_person'] ?? null,
            $_POST['email'] ?? null,
            null, // email_cc
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            !empty($_POST['tax_id']) ? preg_replace('/[^0-9]/', '', $_POST['tax_id']) : null,
            $_POST['company_status'] ?? null
        );

        if ($result) {
            Notification::notifyNewCustomer($user['id'], $companyId, $result, $_POST['name']);
            $_SESSION['success'] = 'Cliente registrado exitosamente';
            header('Location: ' . BASE_URL . '/customers/view_mobile.php?id=' . $result);
            exit;
        } else {
            $errors['general'] = 'Error al registrar el cliente';
        }
    }
}

$pageTitle = 'Nuevo Cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d6efd">
    <title><?= $pageTitle ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--gray-100);
            padding-bottom: 80px;
            font-size: 16px;
            margin: 0;
        }

        /* Mobile Header */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            color: white;
            padding: 16px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .mobile-header h1 {
            font-size: 20px;
            margin: 0;
            font-weight: 600;
        }

        /* Container */
        .container-mobile {
            padding: 16px;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #495057;
            display: block;
        }

        .form-control, .form-select {
            min-height: 48px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 12px;
            width: 100%;
            margin-bottom: 16px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            outline: none;
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: 13px;
            margin-top: -12px;
            margin-bottom: 12px;
            display: block;
        }

        /* Document Type Detection */
        .doc-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }

        .doc-type-badge.dni {
            background: #d1ecf1;
            color: #0c5460;
        }

        .doc-type-badge.ruc {
            background: #d4edda;
            color: #155724;
        }

        /* Buttons */
        .btn-primary, .btn-secondary {
            min-height: 48px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            padding: 12px 24px;
            width: 100%;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Section Title */
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #212529;
            margin: 20px 0 12px 0;
        }

        /* Helper Text */
        .helper-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: -12px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1><i class="fas fa-user-plus"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-mobile">
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="customerForm">
            <div class="form-card">
                <div class="section-title">Información Básica</div>

                <!-- Tax ID / Document Number -->
                <label class="form-label">
                    <i class="fas fa-id-card"></i> Documento (DNI/RUC)
                </label>
                <input type="text"
                       class="form-control <?= isset($errors['tax_id']) ? 'is-invalid' : '' ?>"
                       name="tax_id"
                       id="tax_id"
                       placeholder="Ingrese DNI (8) o RUC (11 dígitos)"
                       value="<?= htmlspecialchars($formData['tax_id'] ?? '') ?>"
                       maxlength="11"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <div id="doc-type-indicator"></div>
                <?php if (isset($errors['tax_id'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['tax_id']) ?></div>
                <?php endif; ?>

                <!-- Name -->
                <label class="form-label">
                    <i class="fas fa-user"></i> Nombre / Razón Social *
                </label>
                <input type="text"
                       class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                       name="name"
                       placeholder="Nombre completo o razón social"
                       value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                       required>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                <?php endif; ?>

                <!-- Contact Person -->
                <label class="form-label">
                    <i class="fas fa-user-tie"></i> Persona de Contacto
                </label>
                <input type="text"
                       class="form-control"
                       name="contact_person"
                       placeholder="Nombre del contacto"
                       value="<?= htmlspecialchars($formData['contact_person'] ?? '') ?>">

                <!-- Email -->
                <label class="form-label">
                    <i class="fas fa-envelope"></i> Email
                </label>
                <input type="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       name="email"
                       placeholder="correo@ejemplo.com"
                       value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                       inputmode="email">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                <?php endif; ?>

                <!-- Phone -->
                <label class="form-label">
                    <i class="fas fa-phone"></i> Teléfono
                </label>
                <input type="tel"
                       class="form-control"
                       name="phone"
                       placeholder="999 999 999"
                       value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                       inputmode="tel">

                <!-- Address -->
                <label class="form-label">
                    <i class="fas fa-map-marker-alt"></i> Dirección
                </label>
                <textarea class="form-control"
                          name="address"
                          rows="3"
                          placeholder="Dirección completa"
                          style="min-height: 80px;"><?= htmlspecialchars($formData['address'] ?? '') ?></textarea>
            </div>

            <!-- Submit Buttons -->
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cliente
            </button>

            <a href="<?= BASE_URL ?>/customers/index_mobile.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const BASE_URL = '<?= BASE_URL ?>';

    // Document type detection and auto-lookup
    document.addEventListener('DOMContentLoaded', function() {
        const taxIdInput = document.getElementById('tax_id');
        const docTypeIndicator = document.getElementById('doc-type-indicator');
        const nameInput = document.querySelector('input[name="name"]');
        const addressInput = document.querySelector('textarea[name="address"]');

        let lookupTimeout = null;

        if (taxIdInput) {
            // Only allow numbers
            taxIdInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');

                // Clear previous timeout
                if (lookupTimeout) {
                    clearTimeout(lookupTimeout);
                }

                const length = this.value.length;

                // Auto-lookup when complete
                if (length === 8 || length === 11) {
                    lookupTimeout = setTimeout(() => {
                        autoLookupDocument(this.value, length);
                    }, 500); // Wait 500ms after user stops typing
                }

                updateDocTypeIndicator();
            });

            // Lookup when user presses Enter
            taxIdInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const length = this.value.length;
                    if (length === 8 || length === 11) {
                        if (lookupTimeout) clearTimeout(lookupTimeout);
                        autoLookupDocument(this.value, length);
                    }
                }
            });

            // Lookup when user leaves the field (blur)
            taxIdInput.addEventListener('blur', function() {
                const length = this.value.length;
                if (length === 8 || length === 11) {
                    if (lookupTimeout) clearTimeout(lookupTimeout);
                    autoLookupDocument(this.value, length);
                }
            });

            // Detect and show document type
            function updateDocTypeIndicator() {
                const length = taxIdInput.value.length;

                if (length === 8) {
                    docTypeIndicator.innerHTML = '<span class="doc-type-badge dni"><i class="fas fa-id-card"></i> DNI detectado</span>';
                } else if (length === 11) {
                    docTypeIndicator.innerHTML = '<span class="doc-type-badge ruc"><i class="fas fa-building"></i> RUC detectado</span>';
                } else if (length > 0) {
                    docTypeIndicator.innerHTML = '<span class="helper-text">DNI: 8 dígitos | RUC: 11 dígitos</span>';
                } else {
                    docTypeIndicator.innerHTML = '';
                }
            }

            // Auto lookup document
            async function autoLookupDocument(document, length) {
                const type = length === 8 ? 'dni' : 'ruc';

                docTypeIndicator.innerHTML = `<span class="doc-type-badge ${type === 'dni' ? 'dni' : 'ruc'}">
                    <i class="fas fa-spinner fa-spin"></i> Consultando ${type.toUpperCase()}...
                </span>`;

                try {
                    const response = await fetch(`${BASE_URL}/api/lookup_document.php?document=${document}&type=${type}`);
                    const data = await response.json();

                    if (data.success && data.data) {
                        // Fill form with data from API
                        if (type === 'dni') {
                            // DNI response
                            const nombreCompleto = data.data.nombre_completo ||
                                (data.data.nombres && data.data.apellido_paterno ?
                                 `${data.data.nombres} ${data.data.apellido_paterno} ${data.data.apellido_materno || ''}`.trim() : '');

                            if (nombreCompleto && nameInput) {
                                nameInput.value = nombreCompleto;
                                nameInput.classList.add('is-valid');
                            }
                        } else {
                            // RUC response
                            const razonSocial = data.data.razon_social || data.data.nombre_o_razon_social || '';

                            if (razonSocial && nameInput) {
                                nameInput.value = razonSocial;
                                nameInput.classList.add('is-valid');
                            }

                            // Build address from components
                            let fullAddress = '';
                            if (data.data.direccion) fullAddress += data.data.direccion;
                            if (data.data.distrito) fullAddress += (fullAddress ? ', ' : '') + data.data.distrito;
                            if (data.data.provincia) fullAddress += (fullAddress ? ', ' : '') + data.data.provincia;
                            if (data.data.departamento) fullAddress += (fullAddress ? ', ' : '') + data.data.departamento;

                            if (fullAddress && addressInput) {
                                addressInput.value = fullAddress;
                            }
                        }

                        // Show success message
                        docTypeIndicator.innerHTML = `<span class="doc-type-badge ${type === 'dni' ? 'dni' : 'ruc'}">
                            <i class="fas fa-check-circle"></i> ✓ Datos encontrados
                        </span>`;

                        // Remove valid class after 3 seconds
                        setTimeout(() => {
                            if (nameInput) nameInput.classList.remove('is-valid');
                        }, 3000);
                    } else {
                        // Show not found message
                        docTypeIndicator.innerHTML = `<span class="doc-type-badge ${type === 'dni' ? 'dni' : 'ruc'}">
                            <i class="fas fa-exclamation-triangle"></i> No encontrado - Complete manualmente
                        </span>`;
                    }
                } catch (error) {
                    console.error('Error al consultar documento:', error);
                    docTypeIndicator.innerHTML = `<span class="doc-type-badge ${type === 'dni' ? 'dni' : 'ruc'}">
                        <i class="fas fa-exclamation-triangle"></i> Error - Complete manualmente
                    </span>`;
                }
            }

            // Initial check
            updateDocTypeIndicator();
        }
    });
    </script>
</body>
</html>
