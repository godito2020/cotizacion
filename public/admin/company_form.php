<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole('Administrador del Sistema')) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a esta sección';
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$user = $auth->getUser();
$companyRepo = new Company();
$db = getDBConnection();

$companyId = $_GET['id'] ?? null;
$isEditMode = false;
$company = [
    'id' => null,
    'name' => '',
    'tax_id' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'logo_url' => ''
];
$pageTitle = 'Nueva Empresa';
$errors = [];

if ($companyId) {
    $company = $companyRepo->getById((int)$companyId);
    if ($company) {
        $isEditMode = true;
        $pageTitle = 'Editar Empresa: ' . htmlspecialchars($company['name']);
    } else {
        $_SESSION['error_message'] = 'Empresa no encontrada';
        $auth->redirect(BASE_URL . '/admin/companies.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company['name'] = trim($_POST['name'] ?? '');
    $company['tax_id'] = trim($_POST['tax_id'] ?? '');
    $company['address'] = trim($_POST['address'] ?? '');
    $company['phone'] = trim($_POST['phone'] ?? '');
    $company['email'] = trim($_POST['email'] ?? '');

    // Validation
    if (empty($company['name'])) {
        $errors[] = 'El nombre de la empresa es obligatorio';
    }
    if (!empty($company['email']) && !filter_var($company['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El formato del email no es válido';
    }

    // Handle logo upload
    $logoUrl = $_POST['current_logo_url'] ?? '';
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite de upload_max_filesize del servidor',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite MAX_FILE_SIZE del formulario',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_TMP_DIR => 'No se encontró el directorio temporal del servidor',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco (permisos)',
                UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP bloqueó la subida',
            ];
            $errors[] = 'Error al subir el logo: ' . ($uploadErrors[$_FILES['logo']['error']] ?? 'Error desconocido (código ' . $_FILES['logo']['error'] . ')');
        }
    }
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['logo']['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'El logo debe ser una imagen (JPG, PNG, GIF o WebP)';
        } elseif ($_FILES['logo']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'El logo no puede superar los 5MB';
        } else {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $filename = uniqid() . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                // Use relative path from public folder (without leading slash for consistency)
                $logoUrl = 'uploads/company/' . $filename;

                // Delete old logo if exists
                if (!empty($_POST['current_logo_url'])) {
                    $oldLogoPath = $_POST['current_logo_url'];
                    // Handle both relative and absolute URLs
                    if (strpos($oldLogoPath, '/uploads/') !== false) {
                        $oldFile = __DIR__ . '/..' . substr($oldLogoPath, strpos($oldLogoPath, '/uploads/'));
                        if (file_exists($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                }
            } else {
                $errors[] = 'Error al subir el logo';
            }
        }
    }

    if (empty($errors)) {
        $targetCompanyId = $companyId;

        if ($isEditMode) {
            // Update existing company
            $stmt = $db->prepare("UPDATE companies SET name = ?, tax_id = ?, address = ?, phone = ?, email = ?, logo_url = ? WHERE id = ?");
            $success = $stmt->execute([
                $company['name'],
                $company['tax_id'] ?: null,
                $company['address'] ?: null,
                $company['phone'] ?: null,
                $company['email'] ?: null,
                $logoUrl ?: null,
                $companyId
            ]);

            if ($success) {
                $_SESSION['success_message'] = 'Empresa actualizada correctamente';
            } else {
                $_SESSION['error_message'] = 'Error al actualizar la empresa';
            }
        } else {
            // Create new company - AUTO_INCREMENT handles id
            $stmt = $db->prepare("INSERT INTO companies (name, tax_id, address, phone, email, logo_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $success = $stmt->execute([
                $company['name'],
                $company['tax_id'] ?: null,
                $company['address'] ?: null,
                $company['phone'] ?: null,
                $company['email'] ?: null,
                $logoUrl ?: null
            ]);

            if ($success) {
                $targetCompanyId = $db->lastInsertId();
                $_SESSION['success_message'] = 'Empresa creada correctamente';
            } else {
                $_SESSION['error_message'] = 'Error al crear la empresa';
            }
        }

        // Sincronizar con la tabla settings para que aparezca en las cotizaciones/PDF
        if ($success && $targetCompanyId) {
            $settingsToSync = [
                'company_name' => $company['name'],
                'company_tax_id' => $company['tax_id'] ?: '',
                'company_address' => $company['address'] ?: '',
                'company_phone' => $company['phone'] ?: '',
                'company_email' => $company['email'] ?: '',
                'company_logo_url' => $logoUrl ? ltrim($logoUrl, '/') : ''
            ];

            foreach ($settingsToSync as $key => $value) {
                // Check if setting exists
                $checkStmt = $db->prepare("SELECT id FROM settings WHERE company_id = ? AND setting_key = ?");
                $checkStmt->execute([$targetCompanyId, $key]);

                if ($checkStmt->fetch()) {
                    // Update existing
                    $updateStmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE company_id = ? AND setting_key = ?");
                    $updateStmt->execute([$value, $targetCompanyId, $key]);
                } else {
                    // Insert new
                    $insertStmt = $db->prepare("INSERT INTO settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)");
                    $insertStmt->execute([$targetCompanyId, $key, $value]);
                }
            }
        }

        // For new companies: copy API settings from an existing configured company
        if (!$isEditMode && $success && $targetCompanyId) {
            // Copy exchange rate from any existing company
            $erSrc = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'exchange_rate_usd_pen' AND setting_value != '' ORDER BY company_id ASC LIMIT 1")->fetchColumn();
            if ($erSrc !== false) {
                $db->prepare("INSERT IGNORE INTO settings (company_id, setting_key, setting_value, setting_type, description) VALUES (?, 'exchange_rate_usd_pen', ?, 'number', 'Tipo de cambio USD a PEN')")->execute([$targetCompanyId, $erSrc]);
            }

            $apiNames = ['sunat', 'reniec'];
            foreach ($apiNames as $apiName) {
                $srcStmt = $db->prepare("SELECT * FROM api_settings WHERE api_name = ? AND is_active = 1 ORDER BY company_id ASC LIMIT 1");
                $srcStmt->execute([$apiName]);
                $src = $srcStmt->fetch(PDO::FETCH_ASSOC);
                if ($src) {
                    $insStmt = $db->prepare(
                        "INSERT IGNORE INTO api_settings (company_id, api_name, api_url, api_key, is_active, additional_config, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
                    );
                    $insStmt->execute([
                        $targetCompanyId,
                        $apiName,
                        $src['api_url'],
                        $src['api_key'],
                        $src['is_active'],
                        $src['additional_config']
                    ]);
                }
            }
        }

        $auth->redirect(BASE_URL . '/admin/companies.php');
    }
}

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-building"></i> <?= $pageTitle ?></h1>
            <a href="<?= BASE_URL ?>/admin/companies.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Por favor corrija los siguientes errores:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Datos de la Empresa</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="current_logo_url" value="<?= htmlspecialchars($company['logo_url'] ?? '') ?>">

                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre de la Empresa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($company['name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="tax_id" class="form-label">RUC</label>
                                <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= htmlspecialchars($company['tax_id'] ?? '') ?>" maxlength="11">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($company['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Dirección</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="logo" class="form-label">Logo de la Empresa</label>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                <small class="text-muted">Formatos: JPG, PNG, GIF, WebP. Máximo 5MB.</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <?php if (!empty($company['logo_url'])): ?>
                                    <?php
                                    ?>
                                    <div class="mt-2">
                                        <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>" alt="Logo actual" style="max-height: 80px; max-width: 150px;" class="border rounded p-1">
                                        <div class="small text-muted mt-1">Logo actual</div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted mt-2">
                                        <i class="fas fa-image" style="font-size: 40px;"></i>
                                        <div class="small">Sin logo</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/admin/companies.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $isEditMode ? 'Actualizar Empresa' : 'Crear Empresa' ?>
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
