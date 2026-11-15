<?php
/**
 * public/customers/edit_mobile.php
 * Edición de Cliente — Vista Móvil Optimizada (gstack Industrial / Linear Design)
 * Consulta en tiempo real DNI/RUC, validación de duplicados y diseño responsivo sin destellos.
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = $auth->getUser();
$companyId = (int)$auth->getCompanyId();
$isAdmin = $auth->hasRole(['Admin', 'Administrador', 'Administrador del Sistema', 'Administrador de Empresa']);

$customerId = (int)($_GET['id'] ?? 0);
if (!$customerId) {
    $_SESSION['error'] = 'ID de cliente no especificado';
    header('Location: ' . BASE_URL . '/customers/index_mobile.php');
    exit;
}

$customerRepo = new Customer();
if ($isAdmin) {
    $customer = $customerRepo->getByIdGlobal($customerId);
} else {
    $customer = $customerRepo->getById($customerId, $companyId);
}

if (!$customer) {
    $_SESSION['error'] = 'Cliente no encontrado';
    header('Location: ' . BASE_URL . '/customers/index_mobile.php');
    exit;
}

$customerCompanyId = (int)$customer['company_id'];

// Get company users for owner assignment if admin
$companyUsers = [];
if ($isAdmin) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, first_name, last_name, username FROM users WHERE company_id = ? AND is_active = 1 ORDER BY first_name, last_name");
    $stmt->execute([$customerCompanyId]);
    $companyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$errors = [];
$formData = $customer;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_merge($customer, $_POST);

    // Validation
    if (empty(trim($_POST['name'] ?? ''))) {
        $errors['name'] = 'El nombre o razón social es requerido';
    }

    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El correo electrónico no es válido';
    }

    if (!empty($_POST['email_cc'])) {
        $ccEmails = array_map('trim', explode(',', $_POST['email_cc']));
        foreach ($ccEmails as $ccEmail) {
            if (!empty($ccEmail) && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email_cc'] = 'Uno o más correos CC no son válidos';
                break;
            }
        }
    }

    if (!empty($_POST['tax_id'])) {
        $taxIdDigits = preg_replace('/\D/', '', $_POST['tax_id']);
        $len = strlen($taxIdDigits);
        if ($len !== 8 && $len !== 10 && $len !== 11) {
            $errors['tax_id'] = 'El documento debe tener 8 dígitos (DNI) u 11 dígitos (RUC)';
        } else {
            $existing = $customerRepo->findByTaxId($taxIdDigits, $customerCompanyId);
            if ($existing && (int)$existing['id'] !== $customerId) {
                $errors['tax_id'] = 'Ya existe otro cliente con este documento: ' . htmlspecialchars($existing['name']);
            }
        }
    }

    if (empty($errors)) {
        $name           = trim($_POST['name']);
        $contact_person = !empty($_POST['contact_person']) ? trim($_POST['contact_person']) : null;
        $email          = !empty($_POST['email']) ? trim($_POST['email']) : null;
        $email_cc       = !empty($_POST['email_cc']) ? trim($_POST['email_cc']) : null;
        $phone          = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
        $address        = !empty($_POST['address']) ? trim($_POST['address']) : null;
        $tax_id         = !empty($_POST['tax_id']) ? preg_replace('/\D/', '', $_POST['tax_id']) : null;
        $company_status = !empty($_POST['company_status']) ? trim($_POST['company_status']) : null;
        $user_id        = ($isAdmin && isset($_POST['user_id'])) ? (int)$_POST['user_id'] : (int)($customer['user_id'] ?? $user['id']);

        $updated = $customerRepo->update(
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

        if ($updated) {
            $_SESSION['success_message'] = 'Cliente actualizado exitosamente';
            header('Location: ' . BASE_URL . '/customers/view_mobile.php?id=' . $customerId);
            exit;
        } else {
            $errors['general'] = 'No se pudo actualizar el cliente. Intente nuevamente.';
        }
    }
}

$pageTitle = 'Editar Cliente';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($customer['name']) ?></title>

    <script>
        (function () {
            var mode = 'system';
            try {
                var saved = localStorage.getItem('coti-theme');
                if (saved === 'light' || saved === 'dark') mode = saved;
            } catch (e) {}
            var theme = mode === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : mode;
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.setAttribute('data-coti-theme-mode', mode);
        })();
    </script>

    <!-- Typography: Satoshi, DM Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=satoshi@700,900&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 & Font Awesome 6 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Core App Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/style.css") ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">

    <?php if (file_exists(dirname(__DIR__) . '/../includes/pwa_head.php')) include_once dirname(__DIR__) . '/../includes/pwa_head.php'; ?>

    <style>
        :root {
            --color-canvas: #09090B;
            --color-panel: #18181B;
            --color-panel-card: #202024;
            --color-rule: #27272A;
            --color-rule-strong: #3F3F46;
            --color-ink: #FAFAFA;
            --color-ink-muted: #A1A1AA;
            --color-amber: #F59E0B;
            --color-amber-dim: rgba(245, 158, 11, 0.12);
        }

        [data-bs-theme="light"] {
            --color-canvas: #F4F4F5;
            --color-panel: #FFFFFF;
            --color-panel-card: #FAFAFA;
            --color-rule: #E4E4E7;
            --color-rule-strong: #D4D4D8;
            --color-ink: #09090B;
            --color-ink-muted: #71717A;
            --color-amber: #D97706;
            --color-amber-dim: rgba(217, 119, 6, 0.1);
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--color-canvas);
            color: var(--color-ink);
            padding-bottom: 96px;
            font-size: 14.5px;
            margin: 0;
            overscroll-behavior-y: contain;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Top Sticky Navbar */
        .mobile-top-navbar {
            background-color: var(--color-panel);
            border-bottom: 1px solid var(--color-rule);
            position: sticky;
            top: 0;
            z-index: 1020;
            padding: 12px 16px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Form Card */
        .form-card {
            background-color: var(--color-panel);
            border: 1px solid var(--color-rule);
            border-radius: 12px;
            margin-bottom: 16px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Form Inputs */
        .form-control, .form-select {
            background-color: var(--color-canvas);
            border: 1px solid var(--color-rule);
            color: var(--color-ink);
            min-height: 44px;
            font-size: 0.92rem;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            background-color: var(--color-canvas);
            border-color: var(--color-amber);
            color: var(--color-ink);
            box-shadow: 0 0 0 3px var(--color-amber-dim);
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--color-ink-muted);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-label i {
            color: var(--color-amber);
            font-size: 0.8rem;
        }

        /* Lookup Box */
        .lookup-box {
            background-color: var(--color-canvas);
            border: 1px dashed var(--color-rule-strong);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body>

    <!-- =========================================================
         1. MOBILE TOP NAVIGATION BAR
         ========================================================= -->
    <header class="mobile-top-navbar shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2" style="min-width: 0;">
                <a href="<?= BASE_URL ?>/customers/view_mobile.php?id=<?= $customerId ?>" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none" title="Volver al Detalle">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div style="min-width: 0;">
                    <h1 class="h6 fw-bold mb-0 text-body text-truncate" style="letter-spacing: -0.01em; max-width: 200px;">
                        Editar Cliente
                    </h1>
                    <span class="text-muted small" style="font-size: 0.7rem;">
                        #<?= $customerId ?> &bull; <?= htmlspecialchars($customer['name']) ?>
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Theme Toggle -->
                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 mobile-theme-toggle"
                        data-theme-toggle title="Cambiar tema (Sistema/Claro/Oscuro)">
                    <i class="fas fa-circle-half-stroke"></i>
                </button>

                <!-- Desktop switch -->
                <a href="<?= BASE_URL ?>/customers/edit.php?id=<?= $customerId ?>&desktop=1" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Vista Escritorio">
                    <i class="fas fa-desktop"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="px-3 pt-3">

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 rounded-3" style="font-size: 0.85rem;">
                <i class="fas fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($errors['general']) ?></span>
            </div>
        <?php endif; ?>

        <!-- =========================================================
             2. AUTO-LOOKUP SUNAT / RENIEC BOX
             ========================================================= -->
        <div class="lookup-box">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-bold small text-body d-flex align-items-center gap-1.5">
                    <i class="fas fa-bolt text-warning"></i> Consulta Automática SUNAT / RENIEC
                </span>
                <span class="text-muted" style="font-size: 0.7rem;">Opcional</span>
            </div>
            <div class="input-group">
                <input type="text"
                       id="lookup_input"
                       class="form-control font-mono"
                       placeholder="DNI (8 d.) o RUC (11 d.)"
                       maxlength="11"
                       inputmode="numeric">
                <button type="button" class="btn btn-warning text-dark fw-bold px-3" id="btn_do_lookup">
                    <i class="fas fa-search me-1"></i> <span id="lookup_btn_text">Consultar</span>
                </button>
            </div>
            <div id="lookup_feedback" class="small mt-1" style="font-size: 0.74rem;"></div>
        </div>

        <!-- =========================================================
             3. CUSTOMER EDIT FORM
             ========================================================= -->
        <form method="POST" action="" id="customerEditForm">
            <div class="form-card">
                <!-- Nombre / Razon Social -->
                <div class="mb-3">
                    <label class="form-label" for="name">
                        <i class="fas fa-building"></i> Nombre o Razón Social <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                           id="name"
                           name="name"
                           value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                           required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- DNI / RUC -->
                <div class="mb-3">
                    <label class="form-label" for="tax_id">
                        <i class="fas fa-id-card"></i> Documento (DNI / RUC)
                    </label>
                    <input type="text"
                           class="form-control font-mono <?= isset($errors['tax_id']) ? 'is-invalid' : '' ?>"
                           id="tax_id"
                           name="tax_id"
                           maxlength="11"
                           inputmode="numeric"
                           value="<?= htmlspecialchars($formData['tax_id'] ?? '') ?>">
                    <div id="duplicate_feedback" class="small mt-1 text-warning" style="font-size: 0.74rem; display: none;"></div>
                    <?php if (isset($errors['tax_id'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['tax_id']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Persona de Contacto -->
                <div class="mb-3">
                    <label class="form-label" for="contact_person">
                        <i class="fas fa-user-circle"></i> Persona de Contacto
                    </label>
                    <input type="text"
                           class="form-control"
                           id="contact_person"
                           name="contact_person"
                           value="<?= htmlspecialchars($formData['contact_person'] ?? '') ?>">
                </div>

                <!-- Teléfono / WhatsApp -->
                <div class="mb-3">
                    <label class="form-label" for="phone">
                        <i class="fas fa-phone"></i> Teléfono / Celular (WhatsApp)
                    </label>
                    <input type="tel"
                           class="form-control font-mono"
                           id="phone"
                           name="phone"
                           placeholder="999 999 999"
                           inputmode="tel"
                           value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
                </div>

                <!-- Email Principal -->
                <div class="mb-3">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i> Email Principal
                    </label>
                    <input type="email"
                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           id="email"
                           name="email"
                           placeholder="correo@empresa.com"
                           inputmode="email"
                           value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Email CC -->
                <div class="mb-3">
                    <label class="form-label" for="email_cc">
                        <i class="fas fa-at"></i> Emails en Copia (CC)
                    </label>
                    <input type="text"
                           class="form-control <?= isset($errors['email_cc']) ? 'is-invalid' : '' ?>"
                           id="email_cc"
                           name="email_cc"
                           placeholder="correo1@empresa.com, correo2@empresa.com"
                           value="<?= htmlspecialchars($formData['email_cc'] ?? '') ?>">
                    <span class="text-muted small" style="font-size: 0.7rem;">Múltiples correos separados por coma</span>
                    <?php if (isset($errors['email_cc'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['email_cc']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Dirección -->
                <div class="mb-3">
                    <label class="form-label" for="address">
                        <i class="fas fa-location-dot"></i> Dirección Fiscal / Entrega
                    </label>
                    <textarea class="form-control"
                              id="address"
                              name="address"
                              rows="3"
                              placeholder="Av., Calle, Nro., Distrito, Ciudad"><?= htmlspecialchars($formData['address'] ?? '') ?></textarea>
                </div>

                <!-- Estado de la Empresa -->
                <div class="mb-3">
                    <label class="form-label" for="company_status">
                        <i class="fas fa-circle-check"></i> Estado de la Empresa
                    </label>
                    <select class="form-select" id="company_status" name="company_status">
                        <option value="">-- Sin especificar --</option>
                        <option value="ACTIVO" <?= ($formData['company_status'] ?? '') === 'ACTIVO' ? 'selected' : '' ?>>ACTIVO</option>
                        <option value="INACTIVO" <?= ($formData['company_status'] ?? '') === 'INACTIVO' ? 'selected' : '' ?>>INACTIVO</option>
                        <option value="SUSPENDIDO" <?= ($formData['company_status'] ?? '') === 'SUSPENDIDO' ? 'selected' : '' ?>>SUSPENDIDO</option>
                    </select>
                </div>

                <!-- Vendedor asignado (Admin only) -->
                <?php if ($isAdmin && !empty($companyUsers)): ?>
                    <div class="mb-3">
                        <label class="form-label" for="user_id">
                            <i class="fas fa-user-tie"></i> Vendedor Asignado
                        </label>
                        <select class="form-select" id="user_id" name="user_id">
                            <?php foreach ($companyUsers as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ((int)($formData['user_id'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit and Cancel Buttons -->
            <div class="d-flex flex-column gap-2">
                <button type="submit" class="btn btn-warning text-dark fw-bold py-2.5 shadow-sm" id="btnSubmit">
                    <i class="fas fa-check-circle me-1"></i> Guardar Cambios
                </button>
                <a href="<?= BASE_URL ?>/customers/view_mobile.php?id=<?= $customerId ?>" class="btn btn-outline-secondary py-2">
                    <i class="fas fa-times me-1"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <!-- Universal Mobile Bottom Navigation Bar -->
    <?php include __DIR__ . '/../../includes/mobile_bottom_nav.php'; ?>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const currentCustomerId = <?= $customerId ?>;

        // Auto-lookup logic
        const lookupInput = document.getElementById('lookup_input');
        const btnDoLookup = document.getElementById('btn_do_lookup');
        const lookupBtnText = document.getElementById('lookup_btn_text');
        const lookupFeedback = document.getElementById('lookup_feedback');

        const nameField = document.getElementById('name');
        const taxIdField = document.getElementById('tax_id');
        const addressField = document.getElementById('address');
        const statusField = document.getElementById('company_status');
        const duplicateFeedback = document.getElementById('duplicate_feedback');
        const btnSubmit = document.getElementById('btnSubmit');

        function performLookup(doc) {
            doc = doc.replace(/\D/g, '');
            if (doc.length !== 8 && doc.length !== 11) {
                lookupFeedback.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Ingrese 8 dígitos (DNI) u 11 dígitos (RUC)</span>';
                return;
            }

            const type = (doc.length === 11) ? 'ruc' : 'dni';
            lookupBtnText.textContent = 'Buscando...';
            btnDoLookup.disabled = true;
            lookupFeedback.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Consultando base de datos oficial...</span>';

            fetch(`${BASE_URL}/api/search_customer.php?document=${encodeURIComponent(doc)}&type=${type}`)
                .then(r => r.json())
                .then(res => {
                    btnDoLookup.disabled = false;
                    lookupBtnText.textContent = 'Consultar';

                    if (res.success && res.data) {
                        const d = res.data;
                        taxIdField.value = doc;

                        if (type === 'ruc') {
                            nameField.value = d.business_name || d.razon_social || nameField.value;
                            const fullAddr = [d.address || d.direccion, d.district || d.distrito, d.province || d.provincia, d.department || d.departamento]
                                .filter(Boolean).join(', ');
                            if (fullAddr) addressField.value = fullAddr;
                            if (d.state || d.estado) {
                                const st = String(d.state || d.estado).toUpperCase();
                                if (st.includes('ACTIVO')) statusField.value = 'ACTIVO';
                                else if (st.includes('BAJA') || st.includes('INACTIVO')) statusField.value = 'INACTIVO';
                                else if (st.includes('SUSP')) statusField.value = 'SUSPENDIDO';
                            }
                        } else {
                            nameField.value = d.name || d.nombre_completo || (d.nombres + ' ' + d.apellido_paterno + ' ' + (d.apellido_materno || '')).trim();
                        }

                        lookupFeedback.innerHTML = '<span class="text-success fw-semibold"><i class="fas fa-check-circle"></i> Datos completados exitosamente</span>';
                        checkDuplicate(doc);
                    } else {
                        lookupFeedback.innerHTML = `<span class="text-danger"><i class="fas fa-triangle-exclamation"></i> ${res.message || 'No se encontraron datos'}</span>`;
                    }
                })
                .catch(err => {
                    btnDoLookup.disabled = false;
                    lookupBtnText.textContent = 'Consultar';
                    lookupFeedback.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Error de conexión con el servicio</span>';
                });
        }

        btnDoLookup.addEventListener('click', () => {
            performLookup(lookupInput.value.trim());
        });

        lookupInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performLookup(lookupInput.value.trim());
            }
        });

        // Duplicate Check with debounce
        let dupTimer = null;
        function checkDuplicate(doc) {
            clearTimeout(dupTimer);
            doc = doc.replace(/\D/g, '');
            if (doc.length !== 8 && doc.length !== 11) {
                duplicateFeedback.style.display = 'none';
                btnSubmit.disabled = false;
                return;
            }

            dupTimer = setTimeout(() => {
                fetch(`${BASE_URL}/api/check_duplicate_customer.php?tax_id=${encodeURIComponent(doc)}&exclude_id=${currentCustomerId}`)
                    .then(r => r.json())
                    .then(res => {
                        if (res.exists) {
                            duplicateFeedback.style.display = 'block';
                            duplicateFeedback.innerHTML = `<i class="fas fa-triangle-exclamation me-1"></i> Ya existe otro cliente con este documento: <strong>${res.customer_name}</strong>`;
                            taxIdField.classList.add('is-invalid');
                            btnSubmit.disabled = true;
                        } else {
                            duplicateFeedback.style.display = 'none';
                            taxIdField.classList.remove('is-invalid');
                            btnSubmit.disabled = false;
                        }
                    })
                    .catch(() => {});
            }, 400);
        }

        taxIdField.addEventListener('input', (e) => {
            checkDuplicate(e.target.value);
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>"></script>
</body>
</html>