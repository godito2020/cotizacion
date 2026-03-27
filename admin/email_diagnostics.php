<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['System Admin', 'Company Admin'])) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a esta sección';
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();
$companySettings = new CompanySettings();

// Get current email settings
$emailSettings = $companySettings->getEmailSettings($companyId);

$pageTitle = 'Diagnóstico de Configuración de Email';
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
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard_simple.php">
                <i class="fas fa-chart-line"></i> Sistema de Cotizaciones
            </a>
            <div class="navbar-nav ms-auto">
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
                        <a href="<?= BASE_URL ?>/admin/email_diagnostics.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-stethoscope"></i> Diagnóstico Email
                        </a>
                        <a href="<?= BASE_URL ?>/admin/bank_accounts.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-university"></i> Cuentas Bancarias
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <h1><i class="fas fa-stethoscope"></i> <?= $pageTitle ?></h1>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Configuración Actual</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Configuración General:</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Usar SMTP:</strong> <?= $emailSettings['use_smtp'] ? '<span class="text-success">Sí</span>' : '<span class="text-warning">No (usará mail() de PHP)</span>' ?></li>
                                    <li><strong>Email Remitente:</strong> <?= htmlspecialchars($emailSettings['from_email'] ?: '<span class="text-danger">No configurado</span>') ?></li>
                                    <li><strong>Nombre Remitente:</strong> <?= htmlspecialchars($emailSettings['from_name'] ?: '<span class="text-danger">No configurado</span>') ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Configuración SMTP:</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Servidor:</strong> <?= htmlspecialchars($emailSettings['smtp_host'] ?: '<span class="text-danger">No configurado</span>') ?></li>
                                    <li><strong>Puerto:</strong> <?= htmlspecialchars($emailSettings['smtp_port'] ?: '<span class="text-danger">No configurado</span>') ?></li>
                                    <li><strong>Usuario:</strong> <?= htmlspecialchars($emailSettings['smtp_username'] ?: '<span class="text-danger">No configurado</span>') ?></li>
                                    <li><strong>Contraseña:</strong> <?= !empty($emailSettings['smtp_password']) ? '<span class="text-success">Configurada</span>' : '<span class="text-danger">No configurada</span>' ?></li>
                                    <li><strong>Encriptación:</strong> <?= htmlspecialchars($emailSettings['smtp_encryption'] ?: 'No configurado') ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Validación de Configuración</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $errors = [];
                        $warnings = [];

                        // Check basic settings
                        if (empty($emailSettings['from_email'])) {
                            $errors[] = "Email remitente no configurado";
                        }
                        if (empty($emailSettings['from_name'])) {
                            $warnings[] = "Nombre remitente no configurado";
                        }

                        // Check SMTP settings if enabled
                        if ($emailSettings['use_smtp']) {
                            if (empty($emailSettings['smtp_host'])) $errors[] = "Servidor SMTP no configurado";
                            if (empty($emailSettings['smtp_username'])) $errors[] = "Usuario SMTP no configurado";
                            if (empty($emailSettings['smtp_password'])) $errors[] = "Contraseña SMTP no configurada";
                            if (empty($emailSettings['smtp_port'])) $errors[] = "Puerto SMTP no configurado";
                        }

                        if (!empty($errors)) {
                            echo '<div class="alert alert-danger"><h6>Errores encontrados:</h6><ul>';
                            foreach ($errors as $error) {
                                echo "<li>$error</li>";
                            }
                            echo '</ul></div>';
                        }

                        if (!empty($warnings)) {
                            echo '<div class="alert alert-warning"><h6>Advertencias:</h6><ul>';
                            foreach ($warnings as $warning) {
                                echo "<li>$warning</li>";
                            }
                            echo '</ul></div>';
                        }

                        if (empty($errors) && empty($warnings)) {
                            echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> La configuración parece estar completa y correcta.</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Configuración en Base de Datos</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Esta tabla muestra los valores crudos almacenados en la base de datos (la contraseña aparece encriptada por seguridad):</p>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Clave</th>
                                        <th>Valor</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $allSettings = $companySettings->getAllSettings($companyId);
                                    $emailKeys = [
                                        'smtp_enabled' => 'SMTP Habilitado',
                                        'smtp_host' => 'Servidor SMTP',
                                        'smtp_port' => 'Puerto SMTP',
                                        'smtp_username' => 'Usuario SMTP',
                                        'smtp_password' => 'Contraseña SMTP',
                                        'smtp_encryption' => 'Encriptación SMTP',
                                        'email_from' => 'Email Remitente',
                                        'email_from_name' => 'Nombre Remitente',
                                        'email_reply_to' => 'Email de Respuesta'
                                    ];

                                    foreach ($emailKeys as $key => $label) {
                                        $value = $allSettings[$key] ?? '';
                                        $status = '';

                                        if ($key === 'smtp_password') {
                                            $status = !empty($value) ? '<span class="badge bg-success">Encriptada</span>' : '<span class="badge bg-danger">Vacía</span>';
                                        } elseif ($key === 'smtp_enabled') {
                                            $status = $value ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-warning">No</span>';
                                        } elseif (in_array($key, ['smtp_host', 'smtp_username', 'email_from', 'email_from_name'])) {
                                            $status = !empty($value) ? '<span class="badge bg-success">Configurado</span>' : '<span class="badge bg-danger">Falta</span>';
                                        }

                                        echo "<tr>
                                                <td><code>$key</code></td>
                                                <td>" . htmlspecialchars($value) . "</td>
                                                <td>$status</td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>