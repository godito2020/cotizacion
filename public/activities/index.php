<?php
/**
 * Registro de Actividades / Auditoría
 * Muestra el historial completo de acciones, accesos y cambios en el sistema.
 * Integrado con Master App Shell y diseño Notion / Linear
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Helper de tiempo relativo amigable
if (!function_exists('formatActivityTime')) {
    function formatActivityTime($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        if ($diff < 60) return 'Hace un momento';
        if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
        if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' h';
        if ($diff < 172800) return 'Ayer ' . date('H:i', $timestamp);
        return date('d/m/Y H:i', $timestamp);
    }
}

// Parámetros de filtrado
$filterType = $_GET['type'] ?? 'all';
$filterUser = $_GET['user'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Repositorios
$activityRepo = new ActivityLog();
$userRepo = new User();

// Obtener actividades y totales
$activities = $activityRepo->getActivities($companyId, $filterType, $filterUser, $limit, $offset);
$totalActivities = $activityRepo->getActivitiesCount($companyId, $filterType, $filterUser);
$totalPages = ceil($totalActivities / $limit);

// Lista de usuarios para el filtro
$users = $userRepo->getByCompany($companyId);

// Métricas KPI rápidas
$db = getDBConnection();
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN entity_type = 'quotation' THEN 1 ELSE 0 END) as total_quotations,
        SUM(CASE WHEN entity_type = 'customer' THEN 1 ELSE 0 END) as total_customers,
        SUM(CASE WHEN action IN ('login', 'logout') THEN 1 ELSE 0 END) as total_sessions
    FROM activity_logs
    WHERE company_id = ?
");
$statsStmt->execute([$companyId]);
$kpiStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total' => $totalActivities,
    'total_quotations' => 0,
    'total_customers' => 0,
    'total_sessions' => 0
];

$pageTitle = 'Registro de Actividades';
$activePage = 'activities';
require_once __DIR__ . '/../../includes/app_header.php';
?>

<style>
    .activity-card-row {
        background: var(--color-panel, #18181B);
        border: 1px solid var(--color-rule, #27272A);
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 10px;
        transition: all 0.15s ease;
    }
    .activity-card-row:hover {
        border-color: var(--color-amber, #F59E0B);
        background: var(--color-panel-card, #202024);
        transform: translateY(-1px);
    }
    .activity-badge-circle {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .activity-badge-circle.create { background: rgba(16, 185, 129, 0.15); color: #10B981; border: 1px solid rgba(16, 185, 129, 0.3); }
    .activity-badge-circle.update { background: rgba(59, 130, 246, 0.15); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3); }
    .activity-badge-circle.delete { background: rgba(239, 68, 68, 0.15); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3); }
    .activity-badge-circle.login  { background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); }
    .activity-badge-circle.logout { background: rgba(249, 115, 22, 0.15); color: #F97316; border: 1px solid rgba(249, 115, 22, 0.3); }
    .activity-badge-circle.import { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; border: 1px solid rgba(139, 92, 246, 0.3); }
    .activity-badge-circle.export { background: rgba(168, 85, 247, 0.15); color: #A855F7; border: 1px solid rgba(168, 85, 247, 0.3); }
    .activity-badge-circle.view   { background: rgba(113, 113, 122, 0.15); color: #A1A1AA; border: 1px solid rgba(113, 113, 122, 0.3); }

    .activity-details-box {
        background: var(--color-canvas, #09090B) !important;
        border: 1px solid var(--color-rule, #27272A) !important;
        border-radius: 8px;
        padding: 10px 14px;
        color: var(--color-amber, #F59E0B);
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.8rem;
        max-height: 250px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }

    .kpi-card {
        background: var(--color-panel, #18181B);
        border: 1px solid var(--color-rule, #27272A);
        border-radius: 12px;
        padding: 14px 16px;
        transition: all 0.15s ease;
    }
    .kpi-card:hover {
        border-color: var(--color-rule-strong, #3F3F46);
    }

    .filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        border: 1px solid var(--color-rule, #27272A);
        background: var(--color-panel, #18181B);
        color: var(--color-ink-muted, #A1A1AA);
        font-size: 0.82rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .filter-pill:hover {
        background: var(--color-panel-card, #202024);
        color: var(--color-ink, #FAFAFA);
        border-color: var(--color-rule-strong, #3F3F46);
    }
    .filter-pill.active {
        background: rgba(245, 158, 11, 0.15);
        color: var(--color-amber, #F59E0B);
        border-color: var(--color-amber, #F59E0B);
        font-weight: 600;
    }
</style>

<div class="content-body" style="padding: 1.25rem 1.5rem; max-width: 1400px; margin: 0 auto; padding-bottom: 95px;">

    <!-- =========================================================
         1. HERO HEADER
         ========================================================= -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 600; font-size: 0.75rem;">
                    <i class="fas fa-shield-alt me-1"></i> Auditoría & Seguridad
                </span>
                <span class="text-muted small">Trazabilidad en tiempo real</span>
            </div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--color-ink, #09090B); letter-spacing: -0.02em;">
                Registro de Actividades
            </h1>
            <p class="text-muted small mb-0">
                Historial completo de accesos, cotizaciones creadas, clientes y operaciones registradas en la empresa.
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5 shadow-sm"
                    onclick="exportActivitiesCSV()" style="font-size: 0.85rem; border-radius: 8px;">
                <i class="fas fa-file-csv text-success"></i>
                <span>Exportar CSV</span>
            </button>
            <a href="<?= BASE_URL ?>/dashboard_simple.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1.5"
               style="font-size: 0.85rem; border-radius: 8px;">
                <i class="fas fa-arrow-left"></i>
                <span>Volver</span>
            </a>
        </div>
    </div>

    <!-- =========================================================
         2. KPI METRICS (4 CARDS)
         ========================================================= -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="kpi-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Eventos Totales</span>
                    <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.12); color: #F59E0B;"><i class="fas fa-history"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 font-mono" style="color: var(--color-ink);"><?= number_format($kpiStats['total'] ?? 0) ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">Acciones auditadas</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Cotizaciones</span>
                    <span class="badge rounded-pill" style="background: rgba(59, 130, 246, 0.12); color: #3B82F6;"><i class="fas fa-file-invoice-dollar"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 font-mono text-primary"><?= number_format($kpiStats['total_quotations'] ?? 0) ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">Creación y cambios</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Clientes</span>
                    <span class="badge rounded-pill" style="background: rgba(16, 185, 129, 0.12); color: #10B981;"><i class="fas fa-users"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 font-mono text-success"><?= number_format($kpiStats['total_customers'] ?? 0) ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">Registros y consultas</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Sesiones</span>
                    <span class="badge rounded-pill" style="background: rgba(139, 92, 246, 0.12); color: #8B5CF6;"><i class="fas fa-user-check"></i></span>
                </div>
                <div class="h3 fw-bold mb-0 font-mono" style="color: #A855F7;"><?= number_format($kpiStats['total_sessions'] ?? 0) ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">Inicios y salidas</div>
            </div>
        </div>
    </div>

    <!-- =========================================================
         3. QUICK FILTER PILLS
         ========================================================= -->
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="text-muted small fw-semibold me-1"><i class="fas fa-filter"></i> Filtro rápido:</span>
        <a href="?type=all&user=<?= urlencode($filterUser) ?>" class="filter-pill <?= $filterType === 'all' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> Todas
        </a>
        <a href="?type=quotation&user=<?= urlencode($filterUser) ?>" class="filter-pill <?= $filterType === 'quotation' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> Cotizaciones
        </a>
        <a href="?type=customer&user=<?= urlencode($filterUser) ?>" class="filter-pill <?= $filterType === 'customer' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Clientes
        </a>
        <a href="?type=product&user=<?= urlencode($filterUser) ?>" class="filter-pill <?= $filterType === 'product' ? 'active' : '' ?>">
            <i class="fas fa-box"></i> Productos
        </a>
        <a href="?type=user&user=<?= urlencode($filterUser) ?>" class="filter-pill <?= $filterType === 'user' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i> Usuarios
        </a>
        <a href="?type=system&user=<?= urlencode($filterUser) ?>" class="filter-pill <?= $filterType === 'system' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i> Sistema
        </a>
    </div>

    <!-- =========================================================
         4. DETAILED FILTER BAR
         ========================================================= -->
    <div class="card mb-4" style="background: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 12px;">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="type" class="form-label small fw-semibold text-muted mb-1">Tipo de Actividad</label>
                    <select class="form-select" id="type" name="type" style="background: var(--color-canvas); border-color: var(--color-rule); color: var(--color-ink);">
                        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>Todas las actividades</option>
                        <option value="quotation" <?= $filterType === 'quotation' ? 'selected' : '' ?>>Cotizaciones</option>
                        <option value="customer" <?= $filterType === 'customer' ? 'selected' : '' ?>>Clientes</option>
                        <option value="product" <?= $filterType === 'product' ? 'selected' : '' ?>>Productos</option>
                        <option value="user" <?= $filterType === 'user' ? 'selected' : '' ?>>Usuarios</option>
                        <option value="system" <?= $filterType === 'system' ? 'selected' : '' ?>>Sistema</option>
                    </select>
                </div>

                <div class="col-12 col-md-5">
                    <label for="user" class="form-label small fw-semibold text-muted mb-1">Usuario</label>
                    <select class="form-select" id="user" name="user" style="background: var(--color-canvas); border-color: var(--color-rule); color: var(--color-ink);">
                        <option value="all" <?= $filterUser === 'all' ? 'selected' : '' ?>>Todos los usuarios</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (<?= htmlspecialchars($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-warning text-dark fw-semibold flex-grow-1" style="border-radius: 8px;">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="<?= BASE_URL ?>/activities/index.php" class="btn btn-outline-secondary" title="Limpiar filtros" style="border-radius: 8px;">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================
         5. ACTIVITIES TIMELINE / LIST
         ========================================================= -->
    <div class="card mb-4" style="background: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 12px;">
        <div class="card-header py-3 px-3 d-flex justify-content-between align-items-center"
             style="background: var(--color-panel-card); border-bottom: 1px solid var(--color-rule); border-radius: 12px 12px 0 0;">
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;">
                    <i class="fas fa-stream"></i>
                </span>
                <h6 class="mb-0 fw-bold" style="color: var(--color-ink);">Eventos Auditados</h6>
            </div>
            <span class="text-muted small">
                Mostrando <?= count($activities) ?> de <?= number_format($totalActivities) ?> eventos
            </span>
        </div>

        <div class="card-body p-3">
            <?php if (empty($activities)): ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-history fa-3x" style="color: var(--color-rule-strong, #3F3F46);"></i>
                    </div>
                    <h5 class="fw-bold mb-1" style="color: var(--color-ink);">No hay actividades registradas</h5>
                    <p class="text-muted small mb-3">No se encontraron eventos coincidentes con los filtros seleccionados.</p>
                    <a href="<?= BASE_URL ?>/activities/index.php" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-sync-alt me-1"></i> Restablecer Filtros
                    </a>
                </div>
            <?php else: ?>
                <div class="activity-timeline">
                    <?php foreach ($activities as $activity): ?>
                        <?php
                        $iconClass = 'view';
                        $icon = 'fa-eye';
                        $actionLabel = ucfirst($activity['action'] ?? 'actividad');

                        switch ($activity['action']) {
                            case 'create':
                                $iconClass = 'create';
                                $icon = 'fa-plus';
                                $actionLabel = 'Creación';
                                break;
                            case 'update':
                                $iconClass = 'update';
                                $icon = 'fa-edit';
                                $actionLabel = 'Modificación';
                                break;
                            case 'delete':
                                $iconClass = 'delete';
                                $icon = 'fa-trash-alt';
                                $actionLabel = 'Eliminación';
                                break;
                            case 'import':
                                $iconClass = 'import';
                                $icon = 'fa-file-import';
                                $actionLabel = 'Importación';
                                break;
                            case 'export':
                                $iconClass = 'export';
                                $icon = 'fa-file-export';
                                $actionLabel = 'Exportación';
                                break;
                            case 'login':
                                $iconClass = 'login';
                                $icon = 'fa-right-to-bracket';
                                $actionLabel = 'Login';
                                break;
                            case 'logout':
                                $iconClass = 'logout';
                                $icon = 'fa-right-from-bracket';
                                $actionLabel = 'Logout';
                                break;
                        }

                        // Entity info
                        $entityLabel = ucfirst($activity['entity_type'] ?? 'sistema');
                        $entityColor = '#71717A';
                        $entityIcon = 'fa-tag';
                        if ($activity['entity_type'] === 'quotation') {
                            $entityLabel = 'Cotización';
                            $entityColor = '#F59E0B';
                            $entityIcon = 'fa-file-invoice-dollar';
                        } elseif ($activity['entity_type'] === 'customer') {
                            $entityLabel = 'Cliente';
                            $entityColor = '#3B82F6';
                            $entityIcon = 'fa-users';
                        } elseif ($activity['entity_type'] === 'product') {
                            $entityLabel = 'Producto';
                            $entityColor = '#10B981';
                            $entityIcon = 'fa-box';
                        } elseif ($activity['entity_type'] === 'user') {
                            $entityLabel = 'Usuario';
                            $entityColor = '#8B5CF6';
                            $entityIcon = 'fa-user-shield';
                        }
                        ?>

                        <div class="activity-card-row"
                             data-date="<?= date('Y-m-d', strtotime($activity['created_at'])) ?>"
                             data-time="<?= date('H:i:s', strtotime($activity['created_at'])) ?>"
                             data-user="<?= htmlspecialchars($activity['user_name'] ?? 'Sistema') ?>"
                             data-action="<?= htmlspecialchars($actionLabel) ?>"
                             data-entity="<?= htmlspecialchars($entityLabel) ?>"
                             data-desc="<?= htmlspecialchars($activity['description'] ?? '') ?>"
                             data-ip="<?= htmlspecialchars($activity['ip_address'] ?? 'N/A') ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="activity-badge-circle <?= $iconClass ?>" title="<?= $actionLabel ?>">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>

                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <div class="d-flex flex-wrap align-items-center gap-1.5">
                                            <span class="badge" style="background: rgba(255, 255, 255, 0.06); color: <?= $entityColor ?>; border: 1px solid rgba(255, 255, 255, 0.1); font-size: 0.72rem;">
                                                <i class="fas <?= $entityIcon ?> me-1"></i><?= $entityLabel ?>
                                            </span>
                                            <span class="badge" style="background: rgba(255, 255, 255, 0.04); color: var(--color-ink-muted); font-size: 0.72rem;">
                                                <?= $actionLabel ?>
                                            </span>
                                        </div>
                                        <span class="text-muted small font-mono" style="font-size: 0.75rem;" title="<?= date('d/m/Y H:i:s', strtotime($activity['created_at'])) ?>">
                                            <i class="far fa-clock me-1"></i><?= formatActivityTime($activity['created_at']) ?>
                                        </span>
                                    </div>

                                    <div class="fw-semibold mb-2" style="color: var(--color-ink); font-size: 0.92rem; line-height: 1.35;">
                                        <?= htmlspecialchars($activity['description']) ?>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-3 text-muted small" style="font-size: 0.75rem;">
                                        <span>
                                            <i class="fas fa-user me-1 text-primary"></i>
                                            <strong style="color: var(--color-ink);"><?= htmlspecialchars($activity['user_name'] ?: 'Sistema') ?></strong>
                                        </span>
                                        <span>
                                            <i class="fas fa-calendar-alt me-1"></i>
                                            <?= date('d/m/Y H:i:s', strtotime($activity['created_at'])) ?>
                                        </span>
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <span class="font-mono">
                                                <i class="fas fa-laptop me-1"></i>
                                                <?= htmlspecialchars($activity['ip_address']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($activity['details'])): ?>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                    style="font-size: 0.75rem; border-radius: 6px;"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#details-<?= $activity['id'] ?>">
                                                <i class="fas fa-code me-1"></i> Ver Datos JSON
                                            </button>
                                            <div class="collapse mt-2" id="details-<?= $activity['id'] ?>">
                                                <pre class="activity-details-box mb-0"><?php
                                                    $json = json_decode($activity['details']);
                                                    echo htmlspecialchars($json ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $activity['details']);
                                                ?></pre>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- =========================================================
         6. PAGINATION
         ========================================================= -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mb-4">
            <ul class="pagination justify-content-center flex-wrap gap-1">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&type=<?= urlencode($filterType) ?>&user=<?= urlencode($filterUser) ?>"
                           style="background: var(--color-panel); border-color: var(--color-rule); color: var(--color-ink); border-radius: 8px;">
                            <i class="fas fa-chevron-left me-1"></i> Anterior
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link font-mono" href="?page=<?= $i ?>&type=<?= urlencode($filterType) ?>&user=<?= urlencode($filterUser) ?>"
                           style="<?= $i === $page ? 'background: #F59E0B; border-color: #F59E0B; color: #09090B; font-weight: 700;' : 'background: var(--color-panel); border-color: var(--color-rule); color: var(--color-ink);' ?> border-radius: 8px;">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&type=<?= urlencode($filterType) ?>&user=<?= urlencode($filterUser) ?>"
                           style="background: var(--color-panel); border-color: var(--color-rule); color: var(--color-ink); border-radius: 8px;">
                            Siguiente <i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- =========================================================
         7. ACTIVITY TYPES GUIDE
         ========================================================= -->
    <div class="card" style="background: var(--color-panel); border: 1px solid var(--color-rule); border-radius: 12px;">
        <div class="card-header py-2.5 px-3" style="background: var(--color-panel-card); border-bottom: 1px solid var(--color-rule); border-radius: 12px 12px 0 0;">
            <h6 class="mb-0 fw-bold small text-muted text-uppercase" style="letter-spacing: 0.05em;">
                <i class="fas fa-info-circle me-1 text-warning"></i> Cobertura del Sistema de Auditoría
            </h6>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="p-2.5 rounded h-100" style="background: var(--color-panel-card); border: 1px solid var(--color-rule);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #F59E0B;"><i class="fas fa-file-invoice-dollar"></i></span>
                            <span class="fw-bold small" style="color: var(--color-ink);">Cotizaciones</span>
                        </div>
                        <ul class="small text-muted mb-0 ps-3" style="font-size: 0.78rem;">
                            <li>Creación y emisión de cotizaciones</li>
                            <li>Modificación de items y precios</li>
                            <li>Cambios de estado (Borrador, Enviada, Aprobada)</li>
                            <li>Duplicación y descargas de PDF</li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="p-2.5 rounded h-100" style="background: var(--color-panel-card); border: 1px solid var(--color-rule);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge" style="background: rgba(59, 130, 246, 0.15); color: #3B82F6;"><i class="fas fa-users"></i></span>
                            <span class="fw-bold small" style="color: var(--color-ink);">Clientes</span>
                        </div>
                        <ul class="small text-muted mb-0 ps-3" style="font-size: 0.78rem;">
                            <li>Alta y edición de clientes</li>
                            <li>Consultas a API SUNAT (RUC) y RENIEC (DNI)</li>
                            <li>Asignación de créditos y condiciones</li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="p-2.5 rounded h-100" style="background: var(--color-panel-card); border: 1px solid var(--color-rule);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10B981;"><i class="fas fa-box"></i></span>
                            <span class="fw-bold small" style="color: var(--color-ink);">Productos & Stock</span>
                        </div>
                        <ul class="small text-muted mb-0 ps-3" style="font-size: 0.78rem;">
                            <li>Importación masiva Excel/CSV</li>
                            <li>Actualizaciones de stock por almacén COBOL</li>
                            <li>Mapeo de sedes y números de almacén</li>
                        </ul>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <div class="p-2.5 rounded h-100" style="background: var(--color-panel-card); border: 1px solid var(--color-rule);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #8B5CF6;"><i class="fas fa-server"></i></span>
                            <span class="fw-bold small" style="color: var(--color-ink);">Seguridad & Sesiones</span>
                        </div>
                        <ul class="small text-muted mb-0 ps-3" style="font-size: 0.78rem;">
                            <li>Inicios y cierres de sesión de usuarios</li>
                            <li>Direcciones IP y agentes de usuario</li>
                            <li>Cambios de configuración y permisos</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportActivitiesCSV() {
    const rows = document.querySelectorAll('.activity-card-row');
    if (!rows.length) {
        alert('No hay actividades para exportar.');
        return;
    }
    let csv = "\uFEFFFecha,Hora,Usuario,Acción,Tipo,Descripción,IP\n";
    rows.forEach(r => {
        const date = r.dataset.date || '';
        const time = r.dataset.time || '';
        const user = (r.dataset.user || '').replace(/"/g, '""');
        const action = (r.dataset.action || '').replace(/"/g, '""');
        const entity = (r.dataset.entity || '').replace(/"/g, '""');
        const desc = (r.dataset.desc || '').replace(/"/g, '""');
        const ip = (r.dataset.ip || '').replace(/"/g, '""');
        csv += `"${date}","${time}","${user}","${action}","${entity}","${desc}","${ip}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `actividades_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
require_once __DIR__ . '/../../includes/app_footer.php';
?>
