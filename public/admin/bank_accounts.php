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

// Get selected company ID
$selectedCompanyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : $auth->getCompanyId();

// If not sysadmin, force their own company
if (!$isSysAdmin) {
    $selectedCompanyId = $auth->getCompanyId();
}

$companySettings = new CompanySettings();

// Get all companies for dropdown (only for sysadmin)
$companies = [];
if ($isSysAdmin) {
    $companyRepo = new Company();
    $companies = $companyRepo->getAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = false;
    $targetCompanyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : $selectedCompanyId;

    if (!$isSysAdmin && $targetCompanyId != $auth->getCompanyId()) {
        $_SESSION['error_message'] = 'No tienes permisos para gestionar cuentas de otras empresas';
        $auth->redirect($_SERVER['REQUEST_URI']);
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bank_account':
                $accountData = [
                    'bank_name' => trim($_POST['bank_name'] ?? ''),
                    'account_type' => trim($_POST['account_type'] ?? ''),
                    'account_number' => trim($_POST['account_number'] ?? ''),
                    'account_holder' => trim($_POST['account_holder'] ?? ''),
                    'currency' => trim($_POST['currency'] ?? 'PEN'),
                    'cci' => !empty($_POST['cci']) ? trim($_POST['cci']) : null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'is_default' => isset($_POST['is_default']) ? 1 : 0
                ];
                $result = $companySettings->addBankAccount($targetCompanyId, $accountData);
                break;

            case 'update_bank_account':
                $accountId = (int)($_POST['account_id'] ?? 0);
                $accountData = [
                    'bank_name' => trim($_POST['bank_name'] ?? ''),
                    'account_type' => trim($_POST['account_type'] ?? ''),
                    'account_number' => trim($_POST['account_number'] ?? ''),
                    'account_holder' => trim($_POST['account_holder'] ?? ''),
                    'currency' => trim($_POST['currency'] ?? 'PEN'),
                    'cci' => !empty($_POST['cci']) ? trim($_POST['cci']) : null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'is_default' => isset($_POST['is_default']) ? 1 : 0
                ];
                $result = $companySettings->updateBankAccount($targetCompanyId, $accountId, $accountData);
                break;

            case 'delete_bank_account':
                $accountId = (int)($_POST['account_id'] ?? 0);
                $result = $companySettings->deleteBankAccount($targetCompanyId, $accountId);
                break;
        }

        if ($result) {
            $_SESSION['success_message'] = 'Cuenta bancaria guardada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al procesar la cuenta bancaria';
        }
        $auth->redirect('?company_id=' . $targetCompanyId);
    }
}

// Get bank accounts for selected company
$bankAccounts = $companySettings->getBankAccounts($selectedCompanyId);

// Get company name
$companyRepo = new Company();
$selectedCompany = $companyRepo->getById($selectedCompanyId);
$companyName = $selectedCompany['name'] ?? 'Empresa';

$pageTitle = 'Cuentas Bancarias';
$activePage = 'admin';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1200px; margin: 0 auto;">

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
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Cuentas Bancarias</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Cuentas Bancarias Registradas
                    </h1>
                    <span class="badge rounded-pill bg-body-tertiary text-body border px-2 py-1 small fw-semibold">
                        <?= count($bankAccounts) ?> <?= count($bankAccounts) === 1 ? 'cuenta' : 'cuentas' ?>
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Cuentas de depósito y transferencias que se imprimen al pie de las cotizaciones y facturas.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#bankAccountModal">
                    <i class="fas fa-plus me-1"></i> + Nueva Cuenta
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
                        <select class="form-select form-select-sm" id="companySelector" onchange="window.location.href='?company_id=' + this.value">
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
    <?php else: ?>
        <div class="card mb-3 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
            <div class="card-body p-2 px-3 d-flex align-items-center gap-2 small text-muted">
                <i class="fas fa-building text-primary"></i>
                <span>Cuentas vinculadas a: <strong class="text-body"><?= htmlspecialchars($companyName) ?></strong></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- =========================================================
         2. BANK ACCOUNTS LIST TABLE
         ========================================================= -->
    <div class="card border-0 shadow-sm mb-4" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-body-tertiary border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold small text-body">
                <i class="fas fa-university text-warning me-2"></i>Cuentas Bancarias Activas e Inactivas
            </span>
            <span class="badge bg-body-secondary text-body border">
                <?= count($bankAccounts) ?> registradas
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($bankAccounts)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="width: 100%;">
                        <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                            <tr>
                                <th class="ps-3 py-2 text-muted">Banco & Tipo</th>
                                <th class="py-2 text-muted">Número de Cuenta & CCI</th>
                                <th class="py-2 text-muted">Titular</th>
                                <th class="py-2 text-muted">Moneda</th>
                                <th class="py-2 text-muted text-center">Estado</th>
                                <th class="pe-3 py-2 text-end text-muted" style="width: 100px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($bankAccounts as $account): ?>
                                <?php
                                $isPen = ($account['currency'] === 'PEN');
                                ?>
                                <tr class="transition-hover">
                                    <!-- Banco & Tipo -->
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-primary flex-shrink-0"
                                                 style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.12);">
                                                <i class="fas fa-building-columns" style="font-size: 0.85rem;"></i>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                    <strong class="text-body" style="font-size: 0.85rem;"><?= htmlspecialchars($account['bank_name']) ?></strong>
                                                    <?php if (!empty($account['is_default'])): ?>
                                                        <span class="badge bg-warning-subtle text-dark border border-warning px-1.5 py-0.5" style="font-size: 0.65rem;">
                                                            <i class="fas fa-star text-warning me-1"></i>Predeterminada
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted" style="font-size: 0.72rem;">
                                                    Cuenta <?= ucfirst(htmlspecialchars($account['account_type'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Número & CCI -->
                                    <td class="py-2.5">
                                        <div class="font-monospace fw-bold text-body" style="font-size: 0.83rem;">
                                            <?= htmlspecialchars($account['account_number']) ?>
                                        </div>
                                        <?php if (!empty($account['cci'])): ?>
                                            <div class="small text-muted font-monospace" style="font-size: 0.72rem;">
                                                CCI: <?= htmlspecialchars($account['cci']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Titular -->
                                    <td class="py-2.5">
                                        <span class="small fw-semibold text-body" style="font-size: 0.8rem;">
                                            <?= htmlspecialchars($account['account_holder']) ?>
                                        </span>
                                    </td>

                                    <!-- Moneda -->
                                    <td class="py-2.5">
                                        <span class="badge <?= $isPen ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle' ?> font-monospace px-2 py-1" style="font-size: 0.75rem;">
                                            <?= $isPen ? 'S/ Soles (PEN)' : '$ Dólares (USD)' ?>
                                        </span>
                                    </td>

                                    <!-- Estado -->
                                    <td class="py-2.5 text-center">
                                        <?php if (!empty($account['is_active'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5" style="font-size: 0.7rem;">
                                                <i class="fas fa-check-circle me-1"></i>Activa
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-body-secondary text-muted border px-2 py-0.5" style="font-size: 0.7rem;">
                                                Inactiva
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="pe-3 py-2.5 text-end text-nowrap">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1"
                                                    style="font-size: 0.75rem;"
                                                    onclick="editBankAccount(<?= htmlspecialchars(json_encode($account)) ?>)"
                                                    title="Editar Cuenta">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1"
                                                    style="font-size: 0.75rem;"
                                                    onclick="deleteBankAccount(<?= $account['id'] ?>)"
                                                    title="Eliminar Cuenta">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 px-3">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <i class="fas fa-university fa-2x"></i>
                    </div>
                    <h6 class="fw-bold text-body mb-1">No hay cuentas bancarias registradas</h6>
                    <p class="text-muted small mb-3">Agregue las cuentas bancarias para que se muestren automáticamente al final de las cotizaciones.</p>
                    <button type="button" class="btn btn-warning btn-sm fw-bold text-dark px-3" data-bs-toggle="modal" data-bs-target="#bankAccountModal">
                        <i class="fas fa-plus me-1"></i> Registrar Primera Cuenta
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Usage Guide Card -->
    <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 12px;">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-start gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-primary mt-1" style="width: 24px; height: 24px; background: rgba(59, 130, 246, 0.12); flex-shrink: 0;">
                            <i class="fas fa-file-invoice" style="font-size: 0.7rem;"></i>
                        </div>
                        <div class="small text-muted" style="font-size: 0.78rem;">
                            <strong class="text-body d-block">Aparición en Cotizaciones:</strong>
                            Las cuentas bancarias activas se listan ordenadamente en el cuadro final de las cotizaciones web y en el pie de página del PDF oficial.
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-start gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-warning mt-1" style="width: 24px; height: 24px; background: rgba(245, 158, 11, 0.12); flex-shrink: 0;">
                            <i class="fas fa-star" style="font-size: 0.7rem;"></i>
                        </div>
                        <div class="small text-muted" style="font-size: 0.78rem;">
                            <strong class="text-body d-block">Cuentas Predeterminadas:</strong>
                            Puede marcar una cuenta por defecto en Soles y otra en Dólares para priorizarlas en la presentación al cliente.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: AGREGAR / EDITAR CUENTA BANCARIA
     ========================================================= -->
<div class="modal fade" id="bankAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background-color: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 14px;">
            <form id="bankAccountForm" method="POST">
                <div class="modal-header border-bottom py-2.5 px-3">
                    <h6 class="modal-title fw-bold text-body mb-0" id="modalTitle">
                        <i class="fas fa-university text-warning me-2"></i>Agregar Cuenta Bancaria
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <input type="hidden" name="action" id="formAction" value="add_bank_account">
                    <input type="hidden" name="account_id" id="accountId">
                    <input type="hidden" name="company_id" id="formCompanyId" value="<?= $selectedCompanyId ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="bank_name" class="form-label small fw-semibold text-body mb-1">Entidad Bancaria *</label>
                            <select class="form-select form-select-sm" id="bank_name" name="bank_name" required>
                                <option value="">Seleccionar banco...</option>
                                <option value="BCP - Banco de Crédito del Perú">BCP - Banco de Crédito del Perú</option>
                                <option value="BBVA - Banco Continental">BBVA - Banco Continental</option>
                                <option value="Interbank">Interbank</option>
                                <option value="Scotiabank Perú">Scotiabank Perú</option>
                                <option value="Banco de la Nación">Banco de la Nación</option>
                                <option value="Banbif">Banbif</option>
                                <option value="Banco Pichincha">Banco Pichincha</option>
                                <option value="Banco Falabella">Banco Falabella</option>
                                <option value="Banco Ripley">Banco Ripley</option>
                                <option value="Mi Banco">Mi Banco</option>
                                <option value="Banco Azteca">Banco Azteca</option>
                                <option value="Otro">Otro (especificar)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="account_type" class="form-label small fw-semibold text-body mb-1">Tipo de Cuenta *</label>
                            <select class="form-select form-select-sm" id="account_type" name="account_type" required>
                                <option value="">Seleccionar tipo...</option>
                                <option value="corriente">Cuenta Corriente</option>
                                <option value="ahorros">Cuenta de Ahorros</option>
                                <option value="detraccion">Cuenta de Detracciones</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="account_number" class="form-label small fw-semibold text-body mb-1">Número de Cuenta *</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="account_number" name="account_number"
                                   placeholder="194-1234567890" required>
                        </div>

                        <div class="col-md-6">
                            <label for="cci" class="form-label small fw-semibold text-body mb-1">Código Interbancario (CCI)</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="cci" name="cci"
                                   placeholder="00219400123456789012" maxlength="20">
                        </div>

                        <div class="col-md-8">
                            <label for="account_holder" class="form-label small fw-semibold text-body mb-1">Titular de la Cuenta *</label>
                            <input type="text" class="form-control form-control-sm" id="account_holder" name="account_holder"
                                   placeholder="Razón Social o Nombre Completo" required>
                        </div>

                        <div class="col-md-4">
                            <label for="currency" class="form-label small fw-semibold text-body mb-1">Moneda *</label>
                            <select class="form-select form-select-sm" id="currency" name="currency" required>
                                <option value="PEN">Soles (S/)</option>
                                <option value="USD">Dólares ($)</option>
                                <option value="EUR">Euros (€)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" checked>
                                <label class="form-check-label small fw-semibold text-body" for="is_active">
                                    Cuenta Activa (visible en cotizaciones)
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_default" name="is_default">
                                <label class="form-check-label small fw-semibold text-body" for="is_default">
                                    Cuenta Predeterminada para esta moneda
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">
                        <i class="fas fa-save me-1"></i> Guardar Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBankAccount(account) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen text-warning me-2"></i>Editar Cuenta Bancaria';
    document.getElementById('formAction').value = 'update_bank_account';
    document.getElementById('accountId').value = account.id;

    document.getElementById('bank_name').value = account.bank_name;
    document.getElementById('account_type').value = account.account_type;
    document.getElementById('account_number').value = account.account_number;
    document.getElementById('account_holder').value = account.account_holder;
    document.getElementById('currency').value = account.currency;
    document.getElementById('cci').value = account.cci || '';
    document.getElementById('is_active').checked = (account.is_active == 1);
    document.getElementById('is_default').checked = (account.is_default == 1);

    new bootstrap.Modal(document.getElementById('bankAccountModal')).show();
}

function deleteBankAccount(accountId) {
    if (confirm('¿Está seguro de que desea eliminar esta cuenta bancaria?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_bank_account">
            <input type="hidden" name="account_id" value="${accountId}">
            <input type="hidden" name="company_id" value="<?= $selectedCompanyId ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

document.getElementById('bankAccountModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-university text-warning me-2"></i>Agregar Cuenta Bancaria';
    document.getElementById('formAction').value = 'add_bank_account';
    document.getElementById('accountId').value = '';
    document.getElementById('bankAccountForm').reset();
    document.getElementById('is_active').checked = true;
});

// Handle 'Otro' bank
document.getElementById('bank_name').addEventListener('change', function() {
    if (this.value === 'Otro') {
        const custom = prompt('Ingrese el nombre del banco o caja:');
        if (custom) {
            this.value = custom;
        } else {
            this.value = '';
        }
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
