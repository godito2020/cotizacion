<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Get filter parameters
$filterType = $_GET['type'] ?? 'all';
$filterUser = $_GET['user'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Initialize repository
$activityRepo = new ActivityLog();

// Get activities
$activities = $activityRepo->getActivities($companyId, $filterType, $filterUser, $limit, $offset);
$totalActivities = $activityRepo->getActivitiesCount($companyId, $filterType, $filterUser);
$totalPages = ceil($totalActivities / $limit);

// Get users for filter
$userRepo = new User();
$users = $userRepo->getByCompany($companyId);

$pageTitle = 'Registro de Actividades';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tema profesional COTI (no-flash + marca) -->
    <script>(function(){var t=localStorage.getItem('coti-theme')||(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.setAttribute('data-bs-theme',t);})();</script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-pro.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(APP_ROOT . "/public/assets/css/style.css") ?>">
    <style>
        .activity-item {
            border-left: 4px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        .activity-icon.create { background-color: #28a745; }
        .activity-icon.update { background-color: #17a2b8; }
        .activity-icon.delete { background-color: #dc3545; }
        .activity-icon.view { background-color: #6c757d; }
        .activity-icon.import { background-color: #ffc107; }
        .activity-icon.export { background-color: #6f42c1; }
        .activity-icon.login { background-color: #007bff; }
        .activity-icon.logout { background-color: #fd7e14; }
    </style>

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
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/quotations/index.php">Cotizaciones</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-history"></i> <?= $pageTitle ?></h1>
                    <a href="<?= BASE_URL ?>/dashboard_simple.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row align-items-end">
                            <div class="col-md-4">
                                <label for="type" class="form-label">Tipo de Actividad</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Todas las actividades</option>
                                    <option value="quotation" <?= $filterType === 'quotation' ? 'selected' : '' ?>>Cotizaciones</option>
                                    <option value="customer" <?= $filterType === 'customer' ? 'selected' : '' ?>>Clientes</option>
                                    <option value="product" <?= $filterType === 'product' ? 'selected' : '' ?>>Productos</option>
                                    <option value="user" <?= $filterType === 'user' ? 'selected' : '' ?>>Usuarios</option>
                                    <option value="system" <?= $filterType === 'system' ? 'selected' : '' ?>>Sistema</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="user" class="form-label">Usuario</label>
                                <select class="form-select" id="user" name="user">
                                    <option value="all" <?= $filterUser === 'all' ? 'selected' : '' ?>>Todos los usuarios</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filtrar
                                </button>
                                <a href="<?= BASE_URL ?>/activities/index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activities List -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Actividades Recientes</h5>
                            <small class="text-muted">
                                Mostrando <?= count($activities) ?> de <?= $totalActivities ?> actividades
                            </small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activities)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No hay actividades registradas</h5>
                                <p class="text-muted">Las actividades del sistema aparecerán aquí.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item p-3 border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <?php
                                            $iconClass = 'view';
                                            $icon = 'fa-eye';

                                            switch ($activity['action']) {
                                                case 'create':
                                                    $iconClass = 'create';
                                                    $icon = 'fa-plus';
                                                    break;
                                                case 'update':
                                                    $iconClass = 'update';
                                                    $icon = 'fa-edit';
                                                    break;
                                                case 'delete':
                                                    $iconClass = 'delete';
                                                    $icon = 'fa-trash';
                                                    break;
                                                case 'import':
                                                    $iconClass = 'import';
                                                    $icon = 'fa-upload';
                                                    break;
                                                case 'export':
                                                    $iconClass = 'export';
                                                    $icon = 'fa-download';
                                                    break;
                                                case 'login':
                                                    $iconClass = 'login';
                                                    $icon = 'fa-sign-in-alt';
                                                    break;
                                                case 'logout':
                                                    $iconClass = 'logout';
                                                    $icon = 'fa-sign-out-alt';
                                                    break;
                                            }
                                            ?>
                                            <div class="activity-icon <?= $iconClass ?>">
                                                <i class="fas <?= $icon ?>"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?= htmlspecialchars($activity['description']) ?>
                                                    </h6>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-user"></i>
                                                        <?= htmlspecialchars($activity['user_name']) ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-clock"></i>
                                                        <?= date('d/m/Y H:i:s', strtotime($activity['created_at'])) ?>
                                                        <span class="mx-2">•</span>
                                                        <i class="fas fa-desktop"></i>
                                                        <?= htmlspecialchars($activity['ip_address'] ?? 'N/A') ?>
                                                    </div>
                                                    <?php if ($activity['details']): ?>
                                                        <div class="mt-2">
                                                            <button class="btn btn-sm btn-outline-secondary"
                                                                    data-bs-toggle="collapse"
                                                                    data-bs-target="#details-<?= $activity['id'] ?>">
                                                                <i class="fas fa-info-circle"></i> Ver detalles
                                                            </button>
                                                            <div class="collapse mt-2" id="details-<?= $activity['id'] ?>">
                                                                <div class="bg-light p-2 rounded">
                                                                    <pre class="mb-0 small"><?= htmlspecialchars($activity['details']) ?></pre>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ms-3">
                                                    <span class="badge bg-<?= $iconClass === 'create' ? 'success' : ($iconClass === 'delete' ? 'danger' : 'secondary') ?>">
                                                        <?= ucfirst($activity['action']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= $filterType ?>&user=<?= $filterUser ?>">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&type=<?= $filterType ?>&user=<?= $filterUser ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= $filterType ?>&user=<?= $filterUser ?>">
                                        Siguiente <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

                <!-- Activity Types Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Tipos de Actividades Registradas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-file-invoice text-info"></i> Cotizaciones</h6>
                                <ul class="small">
                                    <li>Creación de nuevas cotizaciones</li>
                                    <li>Modificación de cotizaciones existentes</li>
                                    <li>Cambios de estado</li>
                                    <li>Duplicación de cotizaciones</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-users text-success"></i> Clientes</h6>
                                <ul class="small">
                                    <li>Registro de nuevos clientes</li>
                                    <li>Actualización de datos</li>
                                    <li>Consultas API SUNAT/RENIEC</li>
                                </ul>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6><i class="fas fa-box text-warning"></i> Productos</h6>
                                <ul class="small">
                                    <li>Importación masiva de productos</li>
                                    <li>Actualización de stock</li>
                                    <li>Modificación de precios</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-cog text-secondary"></i> Sistema</h6>
                                <ul class="small">
                                    <li>Inicio y cierre de sesión</li>
                                    <li>Cambios de configuración</li>
                                    <li>Exportación de reportes</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js"></script>
</body>
</html>