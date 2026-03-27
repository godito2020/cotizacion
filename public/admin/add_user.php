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

$pageTitle = 'Agregar Usuario';

// Get all companies (for system admin)
$companies = [];
if ($isSystemAdmin) {
    $stmt = $db->query("SELECT id, name FROM companies ORDER BY name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all roles
$rolesStmt = $db->query("SELECT id, role_name, description FROM roles ORDER BY id");
$allRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $canViewAll = isset($_POST['can_view_all_quotations']) ? 1 : 0;
    $selectedRoles = $_POST['roles'] ?? [];

    // Company selection: system admin can choose, company admin uses their own
    if ($isSystemAdmin && isset($_POST['company_id'])) {
        $companyId = (int)$_POST['company_id'];
    } else {
        $companyId = $currentCompanyId;
    }

    $errors = [];

    if (empty($username)) $errors[] = 'El nombre de usuario es obligatorio';
    if (empty($email)) $errors[] = 'El email es obligatorio';
    if (empty($firstName)) $errors[] = 'El nombre es obligatorio';
    if (empty($lastName)) $errors[] = 'El apellido es obligatorio';
    if (empty($password)) $errors[] = 'La contraseña es obligatoria';
    if ($password !== $confirmPassword) $errors[] = 'Las contraseñas no coinciden';
    if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    if (empty($selectedRoles)) $errors[] = 'Debe seleccionar al menos un rol';
    if ($isSystemAdmin && empty($companyId)) $errors[] = 'Debe seleccionar una empresa';

    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'El nombre de usuario o email ya existe';
        } else {
            try {
                $db->beginTransaction();

                // Create user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Get next available ID (in case AUTO_INCREMENT is not set)
                $maxIdStmt = $db->query("SELECT COALESCE(MAX(id), 0) + 1 as next_id FROM users");
                $nextId = $maxIdStmt->fetch(PDO::FETCH_ASSOC)['next_id'];

                $insertStmt = $db->prepare("INSERT INTO users (id, company_id, username, email, password_hash, first_name, last_name, phone, can_view_all_quotations, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $insertStmt->execute([$nextId, $companyId, $username, $email, $hashedPassword, $firstName, $lastName, $phone, $canViewAll]);

                // Insert roles
                $insertRoleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($selectedRoles as $roleId) {
                    $insertRoleStmt->execute([$nextId, $roleId]);
                }

                $db->commit();
                $_SESSION['success_message'] = 'Usuario creado correctamente';
                $auth->redirect(BASE_URL . '/admin/users.php');
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al crear el usuario: ' . $e->getMessage();
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-user-plus"></i> Agregar Usuario
                </h4>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <form method="post">
                    <?php if ($isSystemAdmin && !empty($companies)): ?>
                    <div class="mb-3">
                        <label for="company_id" class="form-label">Empresa <span class="text-danger">*</span></label>
                        <select class="form-select" id="company_id" name="company_id" required>
                            <option value="">-- Seleccione una empresa --</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>" <?= (isset($_POST['company_id']) && $_POST['company_id'] == $company['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($company['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Seleccione la empresa a la que pertenecerá el usuario</small>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">
                            <i class="fab fa-whatsapp text-success me-1"></i>WhatsApp / Celular
                        </label>
                        <input type="text" class="form-control" id="phone" name="phone"
                               placeholder="Ej: +51 987 654 321"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <small class="text-muted">Se mostrará en la firma del PDF para contacto directo con el cliente.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>Roles del Usuario</strong> <span class="text-danger">*</span></label>
                        <div class="card">
                            <div class="card-body">
                                <?php
                                $selectedRoles = $_POST['roles'] ?? [];
                                foreach ($allRoles as $role):
                                    // Only system admin can assign system admin role
                                    if ($role['role_name'] === 'Administrador del Sistema' && !$isSystemAdmin) continue;
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="role_<?= $role['id'] ?>"
                                               name="roles[]"
                                               value="<?= $role['id'] ?>"
                                               <?= in_array($role['id'], $selectedRoles) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                            <strong><?= htmlspecialchars($role['role_name']) ?></strong>
                                            <?php if ($role['description']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($role['description']) ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <?php if ($role !== end($allRoles)): ?>
                                        <hr class="my-2">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted">Seleccione al menos un rol para el usuario</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="can_view_all_quotations" name="can_view_all_quotations" value="1">
                            <label class="form-check-label" for="can_view_all_quotations">
                                Ver todas las cotizaciones de la empresa
                            </label>
                        </div>
                        <small class="text-muted">Si no está marcado, el usuario solo verá sus propias cotizaciones</small>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Crear Usuario
                        </button>
                    </div>
                </form>
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
