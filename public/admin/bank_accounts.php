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

// Get selected company ID from query string or use auth company
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

    // Security check: non-sysadmin can only manage their own company
    if (!$isSysAdmin && $targetCompanyId != $auth->getCompanyId()) {
        $_SESSION['error_message'] = 'No tienes permisos para gestionar cuentas de otras empresas';
        $auth->redirect($_SERVER['REQUEST_URI']);
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bank_account':
                $accountData = [
                    'bank_name' => $_POST['bank_name'],
                    'account_type' => $_POST['account_type'],
                    'account_number' => $_POST['account_number'],
                    'account_holder' => $_POST['account_holder'],
                    'currency' => $_POST['currency'],
                    'cci' => $_POST['cci'] ?? null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'is_default' => isset($_POST['is_default']) ? 1 : 0
                ];
                $result = $companySettings->addBankAccount($targetCompanyId, $accountData);
                break;

            case 'update_bank_account':
                $accountId = $_POST['account_id'];
                $accountData = [
                    'bank_name' => $_POST['bank_name'],
                    'account_type' => $_POST['account_type'],
                    'account_number' => $_POST['account_number'],
                    'account_holder' => $_POST['account_holder'],
                    'currency' => $_POST['currency'],
                    'cci' => $_POST['cci'] ?? null,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                    'is_default' => isset($_POST['is_default']) ? 1 : 0
                ];
                $result = $companySettings->updateBankAccount($targetCompanyId, $accountId, $accountData);
                break;

            case 'delete_bank_account':
                $accountId = $_POST['account_id'];
                $result = $companySettings->deleteBankAccount($targetCompanyId, $accountId);
                break;
        }

        if ($result) {
            $_SESSION['success_message'] = 'Cuenta bancaria actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al actualizar la cuenta bancaria';
        }
        $auth->redirect('?company_id=' . $targetCompanyId);
    }
}

// Get bank accounts for selected company
$bankAccounts = $companySettings->getBankAccounts($selectedCompanyId);

// Debug: Log the count
error_log("bank_accounts.php - Company ID: $selectedCompanyId, Accounts found: " . count($bankAccounts));

// Get company name
$companyRepo = new Company();
$selectedCompany = $companyRepo->getById($selectedCompanyId);
$companyName = $selectedCompany['name'] ?? 'Empresa';

$pageTitle = 'Cuentas Bancarias';
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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/index.php">Panel Admin</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> Configuración</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> Empresa
                        </a>
                        <a href="<?= BASE_URL ?>/admin/email_settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                        <a href="<?= BASE_URL ?>/admin/bank_accounts.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-university"></i> Cuentas Bancarias
                        </a>
                        <a href="<?= BASE_URL ?>/admin/brand_logos.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tags"></i> Logos de Marcas
                        </a>
                        <a href="<?= BASE_URL ?>/admin/api_settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plug"></i> APIs
                        </a>
                        <a href="<?= BASE_URL ?>/admin/import_products.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-upload"></i> Importar Productos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-university"></i> <?= $pageTitle ?></h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal">
                        <i class="fas fa-plus"></i> Agregar Cuenta
                    </button>
                </div>

                <!-- Company Selector (only for sysadmin) -->
                <?php if ($isSysAdmin && !empty($companies)): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <label class="col-form-label"><strong>Empresa:</strong></label>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" id="companySelector" onchange="changeCompany(this.value)">
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?= $company['id'] ?>"
                                                    <?= $company['id'] == $selectedCompanyId ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($company['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-building"></i> Gestionando cuentas bancarias para: <strong><?= htmlspecialchars($companyName) ?></strong>
                    </div>
                <?php endif; ?>

                <!-- Bank Accounts List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Cuentas Registradas (<?= count($bankAccounts) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <!-- DEBUG: Version 1.1 - <?= date('Y-m-d H:i:s') ?> -->
                        <!-- DEBUG: Selected Company ID = <?= $selectedCompanyId ?> -->
                        <!-- DEBUG: Bank Accounts Count = <?= count($bankAccounts) ?> -->
                        <?php if (empty($bankAccounts)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-university fa-3x mb-3"></i>
                                <h5>No hay cuentas bancarias registradas</h5>
                                <p>Agrega una cuenta bancaria para incluirla en las cotizaciones</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal">
                                    <i class="fas fa-plus"></i> Agregar Primera Cuenta
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Banco</th>
                                            <th>Tipo</th>
                                            <th>Número de Cuenta</th>
                                            <th>Titular</th>
                                            <th>Moneda</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bankAccounts as $account): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($account['bank_name']) ?></strong>
                                                    <?php if ($account['is_default']): ?>
                                                        <span class="badge bg-primary ms-2">Predeterminada</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= ucfirst(htmlspecialchars($account['account_type'])) ?></td>
                                                <td>
                                                    <code><?= htmlspecialchars($account['account_number']) ?></code>
                                                    <?php if ($account['cci']): ?>
                                                        <br><small class="text-muted">CCI: <?= htmlspecialchars($account['cci']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($account['account_holder']) ?></td>
                                                 <td>
                                                     <?php $symbol = $account['currency'] == 'PEN' ? 'S/' : $account['currency']; ?>
                                                     <span class="badge bg-info"><?= htmlspecialchars($symbol) ?></span>
                                                 </td>
                                                <td>
                                                    <?php if ($account['is_active']): ?>
                                                        <span class="badge bg-success">Activa</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactiva</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary"
                                                                onclick="editBankAccount(<?= htmlspecialchars(json_encode($account)) ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger"
                                                                onclick="deleteBankAccount(<?= $account['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Usage Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">¿Cómo se usan las cuentas bancarias?</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-file-invoice text-primary"></i> En Cotizaciones</h6>
                                <ul class="small">
                                    <li>Las cuentas activas aparecen al final de cada cotización</li>
                                    <li>La cuenta predeterminada se muestra primero</li>
                                    <li>Se incluye número de cuenta y CCI si está disponible</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-cog text-info"></i> Configuración</h6>
                                <ul class="small">
                                    <li>Solo puede haber una cuenta predeterminada por moneda</li>
                                    <li>Las cuentas inactivas no se muestran en documentos</li>
                                    <li>Puedes tener múltiples cuentas en diferentes monedas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bank Account Modal -->
    <div class="modal fade" id="bankAccountModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="bankAccountForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Agregar Cuenta Bancaria</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add_bank_account">
                        <input type="hidden" name="account_id" id="accountId">
                        <input type="hidden" name="company_id" id="formCompanyId" value="<?= $selectedCompanyId ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bank_name" class="form-label">Banco *</label>
                                    <select class="form-select" id="bank_name" name="bank_name" required>
                                        <option value="">Seleccionar banco</option>
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
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="account_type" class="form-label">Tipo de Cuenta *</label>
                                    <select class="form-select" id="account_type" name="account_type" required>
                                        <option value="">Seleccionar tipo</option>
                                        <option value="corriente">Cuenta Corriente</option>
                                        <option value="ahorros">Cuenta de Ahorros</option>
                                        <option value="detraccion">Cuenta de Detracción</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="account_number" class="form-label">Número de Cuenta *</label>
                                    <input type="text" class="form-control" id="account_number" name="account_number"
                                           placeholder="194-1234567890" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cci" class="form-label">CCI (Código de Cuenta Interbancaria)</label>
                                    <input type="text" class="form-control" id="cci" name="cci"
                                           placeholder="00219400123456789012" maxlength="20">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="account_holder" class="form-label">Titular de la Cuenta *</label>
                                    <input type="text" class="form-control" id="account_holder" name="account_holder"
                                           placeholder="EMPRESA EJEMPLO S.A.C." required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Moneda *</label>
                                    <select class="form-select" id="currency" name="currency" required>
                                        <option value="">Seleccionar</option>
                                         <option value="PEN">Soles (S/)</option>
                                         <option value="USD">Dólares ($)</option>
                                         <option value="EUR">Euros (€)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Cuenta activa
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_default" name="is_default">
                                    <label class="form-check-label" for="is_default">
                                        Cuenta predeterminada para esta moneda
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeCompany(companyId) {
            window.location.href = '?company_id=' + companyId;
        }

        function editBankAccount(account) {
            document.getElementById('modalTitle').textContent = 'Editar Cuenta Bancaria';
            document.getElementById('formAction').value = 'update_bank_account';
            document.getElementById('accountId').value = account.id;

            // Fill form fields
            document.getElementById('bank_name').value = account.bank_name;
            document.getElementById('account_type').value = account.account_type;
            document.getElementById('account_number').value = account.account_number;
            document.getElementById('account_holder').value = account.account_holder;
            document.getElementById('currency').value = account.currency;
            document.getElementById('cci').value = account.cci || '';
            document.getElementById('is_active').checked = account.is_active == 1;
            document.getElementById('is_default').checked = account.is_default == 1;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('bankAccountModal'));
            modal.show();
        }

        function deleteBankAccount(accountId) {
            if (confirm('¿Estás seguro de que deseas eliminar esta cuenta bancaria?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_bank_account">
                    <input type="hidden" name="account_id" value="${accountId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Reset form when modal is hidden
        document.getElementById('bankAccountModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('modalTitle').textContent = 'Agregar Cuenta Bancaria';
            document.getElementById('formAction').value = 'add_bank_account';
            document.getElementById('accountId').value = '';
            document.getElementById('bankAccountForm').reset();
            document.getElementById('is_active').checked = true;
        });

        // Handle "Otro" bank selection
        document.getElementById('bank_name').addEventListener('change', function() {
            if (this.value === 'Otro') {
                const customBank = prompt('Ingresa el nombre del banco:');
                if (customBank) {
                    this.value = customBank;
                } else {
                    this.value = '';
                }
            }
        });

        // CCI validation
        document.getElementById('cci').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, ''); // Only numbers
            if (this.value.length > 20) {
                this.value = this.value.substring(0, 20);
            }
        });

        // Account number formatting
        document.getElementById('account_number').addEventListener('input', function() {
            // Remove any non-digit or dash characters
            let value = this.value.replace(/[^\d-]/g, '');
            this.value = value;
        });
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js"></script>
</body>
</html>