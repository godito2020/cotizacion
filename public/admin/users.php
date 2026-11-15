<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$isSystemAdmin = $auth->hasRole('Administrador del Sistema');
$isCompanyAdmin = $auth->hasRole('Administrador de Empresa');

if (!$isSystemAdmin && !$isCompanyAdmin) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a esta sección';
    $auth->redirect(BASE_URL . '/dashboard.php');
}

$user = $auth->getUser();
$currentCompanyId = $auth->getCompanyId();
$db = getDBConnection();

$pageTitle = 'Gestión de Usuarios';
$activePage = 'admin';

// Handle permission update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permission'])) {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $canViewAll = isset($_POST['can_view_all_quotations']) ? 1 : 0;

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    if ($stmt->fetch()) {
        $updateStmt = $db->prepare("UPDATE users SET can_view_all_quotations = ? WHERE id = ?");
        $updateStmt->execute([$canViewAll, $targetUserId]);
        $_SESSION['success_message'] = 'Permiso de visualización actualizado correctamente';
    } else {
        $_SESSION['error_message'] = 'Usuario no encontrado';
    }
    $auth->redirect(BASE_URL . '/admin/users.php' . (!empty($_GET['company_id']) ? '?company_id=' . urlencode($_GET['company_id']) : ''));
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId === (int)$user['id']) {
        $_SESSION['error_message'] = 'No puedes eliminar tu propia cuenta de usuario';
        $auth->redirect(BASE_URL . '/admin/users.php');
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    if ($stmt->fetch()) {
        // Delete user roles first
        $db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$targetUserId]);
        // Delete user
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetUserId]);
        $_SESSION['success_message'] = 'Usuario eliminado exitosamente';
    } else {
        $_SESSION['error_message'] = 'Usuario no encontrado';
    }
    $auth->redirect(BASE_URL . '/admin/users.php');
}

// Get companies for filter (system admin only)
$companies = [];
if ($isSystemAdmin) {
    $stmt = $db->query("SELECT id, name FROM companies ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Filter by company
$filterCompanyId = $_GET['company_id'] ?? '';

// Get users with their roles
if ($isSystemAdmin) {
    if (!empty($filterCompanyId)) {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.phone, u.can_view_all_quotations, u.company_id,
                   c.name as company_name,
                   GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.company_id = ?
            GROUP BY u.id
            ORDER BY c.name, u.username
        ");
        $stmt->execute([$filterCompanyId]);
    } else {
        $stmt = $db->query("
            SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.phone, u.can_view_all_quotations, u.company_id,
                   c.name as company_name,
                   GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            GROUP BY u.id
            ORDER BY c.name, u.username
        ");
    }
} else {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.phone, u.can_view_all_quotations, u.company_id,
               c.name as company_name,
               GROUP_CONCAT(r.role_name SEPARATOR ', ') as roles
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.company_id = ?
        GROUP BY u.id
        ORDER BY u.username
    ");
    $stmt->execute([$currentCompanyId]);
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$totalUsers = count($users);
$adminCount = 0;
$sellerCount = 0;
$billingCount = 0;
foreach ($users as $u) {
    $r = $u['roles'] ?? '';
    if (stripos($r, 'Administrador') !== false) $adminCount++;
    if (stripos($r, 'Vendedor') !== false) $sellerCount++;
    if (stripos($r, 'Facturación') !== false) $billingCount++;
}

require_once __DIR__ . '/../../includes/app_header.php';
?>

<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1400px; margin: 0 auto;">

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
                <li class="breadcrumb-item active text-body fw-semibold" aria-current="page">Gestión de Usuarios</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="status-pulse-dot" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#F59E0B; box-shadow:0 0 8px rgba(245, 158, 11, 0.6);"></span>
                    <h1 class="h4 fw-bold mb-0 text-body" style="font-family: var(--font-display); letter-spacing: -0.02em;">
                        Gestión de Usuarios y Accesos
                    </h1>
                    <span class="badge rounded-pill bg-body-tertiary text-body border px-2 py-1 small fw-semibold">
                        <?= $totalUsers ?> <?= $totalUsers === 1 ? 'usuario' : 'usuarios' ?>
                    </span>
                </div>
                <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.82rem;">
                    Control de cuentas comerciales, roles, empresas asignadas y privilegios de cotización.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/admin/add_user.php" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm px-3">
                    <i class="fas fa-user-plus me-1"></i> + Nuevo Usuario
                </a>
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

    <!-- =========================================================
         2. KPI SUMMARY METRIC STRIP (Slim)
         ========================================================= -->
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Total Usuarios</div>
                        <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= $totalUsers ?></div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59, 130, 246, 0.12); color: #3B82F6;">
                        <i class="fas fa-users" style="font-size: 0.75rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Administradores</div>
                        <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= $adminCount ?></div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(239, 68, 68, 0.12); color: #EF4444;">
                        <i class="fas fa-user-shield" style="font-size: 0.75rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Vendedores</div>
                        <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= $sellerCount ?></div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(245, 158, 11, 0.12); color: #F59E0B;">
                        <i class="fas fa-user-tie" style="font-size: 0.75rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
                <div class="card-body p-2 px-3 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.05em;">Facturación / Ops</div>
                        <div class="fs-5 fw-bold text-body" style="font-family: var(--font-mono); line-height: 1.2;"><?= $billingCount ?></div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(16, 185, 129, 0.12); color: #10B981;">
                        <i class="fas fa-file-invoice-dollar" style="font-size: 0.75rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. SEARCH & COMPANY FILTER BAR
         ========================================================= -->
    <div class="card mb-3 border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px;">
        <div class="card-body p-2 px-3">
            <div class="row g-2 align-items-center">
                <!-- Search Box -->
                <div class="col-12 col-md-6 col-lg-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-body-tertiary border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text"
                               class="form-control border-start-0 ps-0"
                               id="searchUsers"
                               placeholder="Buscar por usuario, nombre, email, teléfono o rol..."
                               autocomplete="off">
                    </div>
                </div>

                <!-- Company Filter (System Admin only) -->
                <?php if ($isSystemAdmin && !empty($companies)): ?>
                <div class="col-12 col-md-6 col-lg-5">
                    <form method="get" class="d-flex align-items-center justify-content-md-end gap-2 m-0">
                        <span class="small text-muted text-nowrap">Empresa:</span>
                        <select class="form-select form-select-sm" id="company_id" name="company_id" onchange="this.form.submit()">
                            <option value="">-- Todas las empresas --</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['id'] ?>" <?= ($filterCompanyId == $comp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($comp['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($filterCompanyId)): ?>
                            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-sm btn-outline-secondary text-nowrap" title="Limpiar filtro">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- =========================================================
         4. MODERN USERS TABLE
         ========================================================= -->
    <div class="card border-0 shadow-sm" style="background-color: var(--color-panel); border: 1px solid var(--color-rule) !important; border-radius: 10px; overflow: hidden;">
        <div class="card-header bg-body-tertiary border-bottom d-flex justify-content-between align-items-center py-2 px-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-users-gear text-warning"></i>
                <span class="fw-semibold text-body small">Lista de Usuarios Registrados</span>
            </div>
            <span class="badge bg-body-secondary text-body border" id="usersCounter">
                Mostrando <?= $totalUsers ?> usuarios
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="usersTable" style="width: 100%;">
                        <thead style="background-color: var(--color-canvas); font-size: 0.72rem; letter-spacing: 0.05em; text-transform: uppercase;">
                            <tr>
                                <th class="ps-3 py-2 text-muted">Usuario & Nombre</th>
                                <?php if ($isSystemAdmin): ?>
                                    <th class="py-2 text-muted">Empresa</th>
                                <?php endif; ?>
                                <th class="py-2 text-muted">Contacto & WhatsApp</th>
                                <th class="py-2 text-muted">Roles Asignados</th>
                                <th class="py-2 text-center text-muted" title="Permite al usuario ver las cotizaciones de toda su empresa">
                                    Ver Todo <i class="fas fa-info-circle ms-1" style="font-size:0.68rem;"></i>
                                </th>
                                <th class="pe-3 py-2 text-end text-muted" style="width: 120px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($users as $u): ?>
                                <?php
                                $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                $initials = strtoupper(substr($u['first_name'] ?? ($u['username'] ?? 'U'), 0, 1) . substr($u['last_name'] ?? '', 0, 1));
                                if (empty($initials)) $initials = strtoupper(substr($u['username'] ?? 'U', 0, 2));

                                $cleanPhone = preg_replace('/\D/', '', $u['phone'] ?? '');
                                $waUrl = '';
                                if (strlen($cleanPhone) >= 9) {
                                    if (substr($cleanPhone, 0, 2) !== '51' && strlen($cleanPhone) === 9) {
                                        $cleanPhone = '51' . $cleanPhone;
                                    }
                                    $waUrl = 'https://wa.me/' . $cleanPhone;
                                }
                                ?>
                                <tr class="user-row">
                                    <!-- Usuario & Nombre -->
                                    <td class="ps-3 py-2.5">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                                 style="width: 34px; height: 34px; font-size: 0.75rem; background: linear-gradient(135deg, #4B5563, #1F2937);">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                                    <span class="badge font-monospace bg-body-secondary text-body border px-1.5 py-0.5" style="font-size:0.72rem;">
                                                        <?= htmlspecialchars($u['username']) ?>
                                                    </span>
                                                    <span class="fw-bold text-body" style="font-size:0.85rem;">
                                                        <?= htmlspecialchars($fullName ?: $u['username']) ?>
                                                    </span>
                                                    <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                                        <span class="badge bg-warning text-dark px-1.5 py-0.5" style="font-size:0.65rem;">TÚ</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Empresa (Admin only) -->
                                    <?php if ($isSystemAdmin): ?>
                                        <td class="py-2.5">
                                            <span class="badge bg-secondary-subtle text-body border px-2 py-0.5" style="font-size:0.72rem;">
                                                <i class="fas fa-building me-1 text-muted"></i><?= htmlspecialchars($u['company_name'] ?? 'Sin empresa') ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Contacto & WhatsApp -->
                                    <td class="py-2.5">
                                        <div class="d-flex flex-column gap-0.5">
                                            <?php if (!empty($u['email'])): ?>
                                                <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="small text-decoration-none text-body" style="font-size:0.78rem;">
                                                    <i class="fas fa-envelope text-primary me-1"></i><?= htmlspecialchars($u['email']) ?>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($u['phone'])): ?>
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="tel:<?= htmlspecialchars($u['phone']) ?>" class="badge bg-body-tertiary text-body border text-decoration-none px-1.5 py-0.5" style="font-size:0.7rem;">
                                                        <i class="fas fa-phone-alt text-muted me-1"></i><?= htmlspecialchars($u['phone']) ?>
                                                    </a>
                                                    <?php if ($waUrl): ?>
                                                        <a href="<?= $waUrl ?>" target="_blank" rel="noopener" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none px-1 py-0.5" title="WhatsApp">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Roles -->
                                    <td class="py-2.5">
                                        <?php if (!empty($u['roles'])): ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php
                                                $rolesList = explode(', ', $u['roles']);
                                                foreach ($rolesList as $role):
                                                    $bClass = 'bg-secondary-subtle text-body border';
                                                    if ($role === 'Administrador del Sistema') $bClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                                    elseif ($role === 'Administrador de Empresa') $bClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                                                    elseif ($role === 'Facturación') $bClass = 'bg-success-subtle text-success border border-success-subtle';
                                                    elseif ($role === 'Vendedor') $bClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                                    elseif ($role === 'Créditos y Cobranzas') $bClass = 'bg-info-subtle text-info border border-info-subtle';
                                                ?>
                                                    <span class="badge <?= $bClass ?> px-2 py-0.5" style="font-size: 0.7rem;">
                                                        <?= htmlspecialchars($role) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin roles</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Switch Ver Todo -->
                                    <td class="py-2.5 text-center">
                                        <form method="post" action="" class="m-0 d-inline-block">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="update_permission" value="1">
                                            <div class="form-check form-switch d-inline-block m-0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       name="can_view_all_quotations" value="1"
                                                       <?= !empty($u['can_view_all_quotations']) ? 'checked' : '' ?>
                                                       onchange="this.form.submit()"
                                                       title="Activar para permitir ver cotizaciones de todos los vendedores de la empresa">
                                            </div>
                                        </form>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="pe-3 py-2.5 text-end text-nowrap">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="<?= BASE_URL ?>/admin/edit_user.php?id=<?= $u['id'] ?>"
                                               class="btn btn-sm btn-outline-primary px-2 py-1"
                                               style="font-size: 0.75rem;"
                                               title="Editar Usuario">
                                                <i class="fas fa-pen"></i>
                                            </a>

                                            <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                                <form method="post" action="" class="m-0 d-inline" onsubmit="return confirm('¿Está seguro de eliminar al usuario <?= htmlspecialchars($u['username']) ?>? Esta acción no se puede deshacer.')">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" name="delete_user" value="1"
                                                            class="btn btn-sm btn-outline-danger px-2 py-1"
                                                            style="font-size: 0.75rem;"
                                                            title="Eliminar Usuario">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
                        <i class="fas fa-users-slash fa-2x"></i>
                    </div>
                    <h6 class="fw-bold text-body mb-1">No hay usuarios registrados</h6>
                    <p class="text-muted small mb-3">No se encontraron usuarios en la empresa seleccionada.</p>
                    <a href="<?= BASE_URL ?>/admin/add_user.php" class="btn btn-warning btn-sm fw-bold text-dark">
                        <i class="fas fa-plus me-1"></i> Registrar Primer Usuario
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchUsers');
    const table = document.getElementById('usersTable');
    const counter = document.getElementById('usersCounter');

    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr.user-row');
            let visible = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const match = !query || text.includes(query);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (counter) {
                counter.textContent = `Mostrando ${visible} de ${rows.length} usuarios`;
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
