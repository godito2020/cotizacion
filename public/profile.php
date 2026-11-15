<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$pageTitle = 'Mi Perfil';

// Handle signature upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['signature'])) {
    $uploadDir = __DIR__ . '/uploads/signatures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $file = $_FILES['signature'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'signature_' . $user['id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old signature if exists
            if (!empty($user['signature_url'])) {
                $oldFile = __DIR__ . '/' . $user['signature_url'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            // Update database
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE users SET signature_url = ? WHERE id = ?");
            $stmt->execute(['uploads/signatures/' . $filename, $user['id']]);

            $_SESSION['success_message'] = 'Firma actualizada correctamente';
            $auth->redirect(BASE_URL . '/profile.php');
        } else {
            $_SESSION['error_message'] = 'Error al subir la firma';
        }
    } else {
        $_SESSION['error_message'] = 'Tipo de archivo no permitido o tamaño excedido (máx. 2MB)';
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $canViewAll = isset($_POST['can_view_all_quotations']) ? 1 : 0;

    if (empty($firstName) || empty($lastName) || empty($email)) {
        $_SESSION['error_message'] = 'Todos los campos son obligatorios';
    } else {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, can_view_all_quotations = ? WHERE id = ?");
        $stmt->execute([$firstName, $lastName, $email, $canViewAll, $user['id']]);

        $_SESSION['success_message'] = 'Perfil actualizado correctamente';
        $auth->redirect(BASE_URL . '/profile.php');
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['error_message'] = 'Todos los campos de contraseña son obligatorios';
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['error_message'] = 'La nueva contraseña y su confirmación no coinciden';
    } elseif (strlen($newPassword) < 6) {
        $_SESSION['error_message'] = 'La nueva contraseña debe tener al menos 6 caracteres';
    } else {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($currentPassword, $row['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            $_SESSION['success_message'] = 'Contraseña actualizada correctamente';
        } else {
            $_SESSION['error_message'] = 'La contraseña actual es incorrecta';
        }
    }
    $auth->redirect(BASE_URL . '/profile.php');
}

$pageTitle = 'Mi Perfil';
$activePage = 'profile';
require_once __DIR__ . '/../includes/app_header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="fas fa-user"></i> Mi Perfil
                </h4>
            </div>
            <div class="card-body">
                <!-- Profile Information -->
                <h5>Información Personal</h5>
                <form method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Apellido</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    <?php if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])): ?>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="can_view_all_quotations" name="can_view_all_quotations"
                                   value="1" <?= ($user['can_view_all_quotations'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="can_view_all_quotations">
                                Ver todas las cotizaciones de la empresa
                            </label>
                        </div>
                        <div class="form-text">
                            Si está marcado, podrás ver todas las cotizaciones. De lo contrario, solo las tuyas.
                        </div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Perfil
                    </button>
                </form>

                <hr>

                <!-- Signature Upload -->
                <h5>Firma Digital</h5>
                <p class="text-muted">Sube una imagen de tu firma para incluirla en las cotizaciones.</p>

                <?php if (!empty($user['signature_url']) && file_exists(__DIR__ . '/' . $user['signature_url'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Firma Actual:</label>
                        <div>
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($user['signature_url']) ?>"
                                 alt="Firma" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd;">
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="signature" class="form-label">Seleccionar Nueva Firma</label>
                        <input type="file" class="form-control" id="signature" name="signature"
                               accept="image/*" required>
                        <div class="form-text">
                            Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> Subir Firma
                    </button>
                </form>

                <hr>

                <!-- Theme Preference -->
                <div class="mb-4">
                    <h5><i class="fas fa-palette"></i> Apariencia y Tema</h5>
                    <p class="text-muted small">Selecciona el modo visual preferido para tu espacio de trabajo.</p>
                    <div class="theme-selector-group">
                        <div class="theme-choice-card" data-choice-mode="system" role="radio" aria-checked="false" tabindex="0">
                            <i class="fas fa-circle-half-stroke"></i>
                            <strong>Sistema</strong>
                            <small>Automático según tu dispositivo</small>
                        </div>
                        <div class="theme-choice-card" data-choice-mode="light" role="radio" aria-checked="false" tabindex="0">
                            <i class="fas fa-sun"></i>
                            <strong>Claro</strong>
                            <small>Fondo blanco con tinta nítida</small>
                        </div>
                        <div class="theme-choice-card" data-choice-mode="dark" role="radio" aria-checked="false" tabindex="0">
                            <i class="fas fa-moon"></i>
                            <strong>Oscuro</strong>
                            <small>Grafito gstack industrial anti-fatiga</small>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Change Password -->
                <h5><i class="fas fa-lock"></i> Cambiar Contraseña</h5>
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
                <form method="post">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                        <div class="form-text">Mínimo 6 caracteres.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/app_footer.php';
?>
