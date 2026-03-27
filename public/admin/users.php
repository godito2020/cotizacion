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
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$user = $auth->getUser();
$currentCompanyId = $auth->getCompanyId();
$db = getDBConnection();

$pageTitle = 'Gestión de Usuarios';

// Handle permission update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permission'])) {
    $targetUserId = $_POST['user_id'];
    $canViewAll = isset($_POST['can_view_all_quotations']) ? 1 : 0;

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    if ($stmt->fetch()) {
        $updateStmt = $db->prepare("UPDATE users SET can_view_all_quotations = ? WHERE id = ?");
        $updateStmt->execute([$canViewAll, $targetUserId]);
        $_SESSION['success_message'] = 'Permiso actualizado correctamente';
    } else {
        $_SESSION['error_message'] = 'Usuario no encontrado';
    }
    $auth->redirect(BASE_URL . '/admin/users.php');
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $targetUserId = $_POST['user_id'];

    if ($targetUserId == $user['id']) {
        $_SESSION['error_message'] = 'No puedes eliminar tu propio usuario';
        $auth->redirect(BASE_URL . '/admin/users.php');
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$targetUserId]);
    if ($stmt->fetch()) {
        // Delete user roles first
        $db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$targetUserId]);
        // Delete user
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetUserId]);
        $_SESSION['success_message'] = 'Usuario eliminado correctamente';
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

ob_start();
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-users"></i> Gestión de Usuarios</h1>
            <a href="<?= BASE_URL ?>/admin/add_user.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Agregar Usuario
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if ($isSystemAdmin && !empty($companies)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="company_id" class="col-form-label">Filtrar por Empresa:</label>
                    </div>
                    <div class="col-auto">
                        <select class="form-select" id="company_id" name="company_id" onchange="this.form.submit()">
                            <option value="">-- Todas las empresas --</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>" <?= ($filterCompanyId == $company['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($company['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($filterCompanyId)): ?>
                    <div class="col-auto">
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Limpiar filtro
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <?php if ($isSystemAdmin): ?>
                        Usuarios del Sistema
                        <?php if (!empty($filterCompanyId)): ?>
                            <span class="badge bg-info ms-2">
                                <?= htmlspecialchars($companies[array_search($filterCompanyId, array_column($companies, 'id'))]['name'] ?? '') ?>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        Usuarios de la Empresa
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <?php if ($isSystemAdmin): ?>
                                    <th>Empresa</th>
                                    <?php endif; ?>
                                    <th>Usuario</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th><i class="fab fa-whatsapp text-success"></i> WhatsApp</th>
                                    <th>Roles</th>
                                    <th>Ver T. Coti.</th>
                                    <th style="width: 160px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <?php if ($isSystemAdmin): ?>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($u['company_name'] ?? 'Sin empresa') ?></span>
                                        </td>
                                        <?php endif; ?>
                                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <?php if (!empty($u['phone'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $u['phone']) ?>" target="_blank" class="text-success text-decoration-none">
                                                    <i class="fab fa-whatsapp me-1"></i><?= htmlspecialchars($u['phone']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['roles']): ?>
                                                <?php
                                                $roles = explode(', ', $u['roles']);
                                                foreach ($roles as $role):
                                                    $badgeClass = 'bg-secondary';
                                                    if ($role === 'Administrador del Sistema') $badgeClass = 'bg-danger';
                                                    elseif ($role === 'Administrador de Empresa') $badgeClass = 'bg-primary';
                                                    elseif ($role === 'Facturación') $badgeClass = 'bg-success';
                                                    elseif ($role === 'Vendedor') $badgeClass = 'bg-info';
                                                ?>
                                                    <span class="badge <?= $badgeClass ?> me-1"><?= htmlspecialchars($role) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sin roles</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="form-check d-inline">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="can_view_all_quotations" value="1"
                                                           <?= $u['can_view_all_quotations'] ? 'checked' : '' ?>
                                                           onchange="this.form.submit()">
                                                </div>
                                                <input type="hidden" name="update_permission" value="1">
                                            </form>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/admin/edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($u['id'] != $user['id']): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?')">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" name="delete_user" value="1" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No hay usuarios registrados.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
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
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
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
        <?= $content ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
