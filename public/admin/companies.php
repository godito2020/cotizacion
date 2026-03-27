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
$companies = $companyRepo->getAll();

$pageTitle = 'Gestión de Empresas';

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) {
    $companyId = (int)$_POST['company_id'];

    // Check if company has users
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $userCount = $stmt->fetchColumn();

    if ($userCount > 0) {
        $_SESSION['error_message'] = 'No se puede eliminar la empresa porque tiene usuarios asignados';
    } else {
        $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
        if ($stmt->execute([$companyId])) {
            $_SESSION['success_message'] = 'Empresa eliminada correctamente';
        } else {
            $_SESSION['error_message'] = 'Error al eliminar la empresa';
        }
    }
    $auth->redirect(BASE_URL . '/admin/companies.php');
}

ob_start();
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-building"></i> Gestión de Empresas</h1>
            <a href="<?= BASE_URL ?>/admin/company_form.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Nueva Empresa
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

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Empresas</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($companies)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Logo</th>
                                    <th>Nombre</th>
                                    <th>RUC</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Usuarios</th>
                                    <th style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $db = getDBConnection();
                                foreach ($companies as $company):
                                    // Count users per company
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
                                    $stmt->execute([$company['id']]);
                                    $userCount = $stmt->fetchColumn();
                                ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($company['logo_url'])): ?>
                                                <img src="<?= htmlspecialchars(upload_url($company['logo_url'])) ?>" alt="Logo" style="max-height: 40px; max-width: 50px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                                                <i class="fas fa-building text-muted" style="font-size: 24px; display: none;"></i>
                                            <?php else: ?>
                                                <i class="fas fa-building text-muted" style="font-size: 24px;"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($company['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($company['tax_id'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($company['email'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($company['phone'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $userCount ?> usuarios</span>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/admin/company_form.php?id=<?= $company['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <?php if ($userCount == 0): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta empresa?')">
                                                <input type="hidden" name="company_id" value="<?= $company['id'] ?>">
                                                <button type="submit" name="delete_company" value="1" class="btn btn-sm btn-outline-danger">
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
                    <p class="text-muted text-center py-4">No hay empresas registradas.</p>
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
