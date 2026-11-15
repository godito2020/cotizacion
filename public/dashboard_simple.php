<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Verificar roles exclusivos
$userRepo = new User();
$userRoles = $userRepo->getRoles($userId);

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Facturación') {
    header('Location: ' . BASE_URL . '/billing/pending.php');
    exit;
}

if (count($userRoles) === 1 && $userRoles[0]['role_name'] === 'Créditos y Cobranzas') {
    header('Location: ' . BASE_URL . '/credits/pending.php');
    exit;
}

// Determinar roles del usuario
$isSystemAdmin = $auth->hasRole('Administrador del Sistema');
$isCompanyAdmin = $auth->hasRole('Administrador de Empresa');
$isAdmin = $isSystemAdmin || $isCompanyAdmin;
$isSeller = $auth->hasRole('Vendedor');
$isBilling = $auth->hasRole('Facturación');
$isCredits = $auth->hasRole('Créditos y Cobranzas');

$db = getDBConnection();

// --- Datos básicos ---
$totalQuotations = 0;
$totalCustomers = 0;
$totalProducts = 0;
$unreadNotifications = 0;
$recentNotifications = [];

// --- Datos para alertas ---
$statusCounts = [];
$billingStats = ['pending' => 0, 'in_process' => 0, 'invoiced' => 0, 'rejected' => 0];
$creditStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$expiringQuotations = [];
$expiredQuotations = 0;
$recentQuotations = [];
$conversionRate = 0;
$pendingDrafts = 0;

try {
    // Filtro por usuario: admins ven todo, el resto solo lo suyo
    $userFilter = $isAdmin ? null : $userId;

    // Totales básicos filtrados por usuario
    if ($userFilter) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM quotations WHERE company_id = ? AND user_id = ?");
        $stmt->execute([$companyId, $userFilter]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM quotations WHERE company_id = ?");
        $stmt->execute([$companyId]);
    }
    $totalQuotations = $stmt->fetchColumn();

    if ($userFilter) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM customers c INNER JOIN quotations q ON c.id = q.customer_id WHERE q.company_id = ? AND q.user_id = ?");
        $stmt->execute([$companyId, $userFilter]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ?");
        $stmt->execute([$companyId]);
    }
    $totalCustomers = $stmt->fetchColumn();

    if ($userFilter) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT qi.product_id) FROM quotation_items qi INNER JOIN quotations q ON qi.quotation_id = q.id WHERE q.company_id = ? AND q.user_id = ?");
        $stmt->execute([$companyId, $userFilter]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE company_id = ?");
        $stmt->execute([$companyId]);
    }
    $totalProducts = $stmt->fetchColumn();

    // Notificaciones
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND company_id = ? AND read_at IS NULL");
    $stmt->execute([$userId, $companyId]);
    $unreadNotifications = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND company_id = ? AND read_at IS NULL ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId, $companyId]);
    $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Conteo por estado de cotización
    $quotation = new Quotation();
    $statusData = $quotation->getCountByStatus($companyId, null, null, $userFilter);
    foreach ($statusData as $row) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }

    // Tasa de conversión
    $conversionRate = $quotation->getConversionRate($companyId, null, null, $userFilter);

    // Cotizaciones recientes
    $recentQuotations = $quotation->getRecent($companyId, 5, null, null, $userFilter);

    // Borradores pendientes
    $pendingDrafts = $statusCounts['Draft'] ?? 0;

    // Cotizaciones por vencer (próximos 3 días) y vencidas
    $stmt = $db->prepare("
        SELECT q.id, q.quotation_number, q.valid_until, q.total, q.currency, c.name as customer_name
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.company_id = ? AND q.status IN ('Draft', 'Sent')
        AND q.valid_until IS NOT NULL AND q.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        " . ($userFilter ? " AND q.user_id = ?" : "") . "
        ORDER BY q.valid_until ASC LIMIT 10
    ");
    $userFilter ? $stmt->execute([$companyId, $userFilter]) : $stmt->execute([$companyId]);
    $expiringQuotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cotizaciones ya vencidas (enviadas o borrador)
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM quotations
        WHERE company_id = ? AND status IN ('Draft', 'Sent')
        AND valid_until IS NOT NULL AND valid_until < CURDATE()
        " . ($userFilter ? " AND user_id = ?" : "")
    );
    $userFilter ? $stmt->execute([$companyId, $userFilter]) : $stmt->execute([$companyId]);
    $expiredQuotations = (int)$stmt->fetchColumn();

    // Stats de facturación
    try {
        $billingManager = new BillingManager();
        $billingRole = $isAdmin ? null : ($isBilling ? 'billing' : 'seller');
        $billingUserId = $isAdmin ? null : $userId;
        $billingStats = $billingManager->getBillingStats($companyId, $billingUserId, $billingRole);
    } catch (Exception $e) {
        // Tabla puede no existir
    }

    // Stats de créditos
    try {
        $creditManager = new CreditManager();
        $creditRole = $isAdmin ? null : ($isCredits ? 'credit' : 'seller');
        $creditUserId = $isAdmin ? null : $userId;
        $creditStats = $creditManager->getCreditStats($companyId, $creditUserId, $creditRole);
    } catch (Exception $e) {
        // Tabla puede no existir
    }

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Construir lista de alertas
$alerts = [];

// Cada alerta tiene un 'key' único: tipo + valor. Si el valor cambia, reaparece.
// 1. Cotizaciones vencidas
if ($expiredQuotations > 0) {
    $alerts[] = [
        'key' => "expired_{$expiredQuotations}",
        'type' => 'danger', 'icon' => 'fa-calendar-xmark',
        'title' => "$expiredQuotations cotización(es) vencida(s)",
        'message' => 'Tienen fecha de validez pasada y siguen en Borrador o Enviada.',
        'url' => BASE_URL . '/quotations/index.php',
    ];
}

// 2. Cotizaciones por vencer
if (!empty($expiringQuotations)) {
    $count = count($expiringQuotations);
    $alerts[] = [
        'key' => "expiring_{$count}",
        'type' => 'warning', 'icon' => 'fa-clock',
        'title' => "$count cotización(es) por vencer en 3 días",
        'message' => 'Requieren atención antes de que expiren.',
        'url' => BASE_URL . '/quotations/index.php',
        'details' => $expiringQuotations
    ];
}

// 3. Facturación pendiente
if ($billingStats['pending'] > 0 && ($isAdmin || $isBilling || $isSeller)) {
    $alerts[] = [
        'key' => "bill_pending_{$billingStats['pending']}",
        'type' => 'info', 'icon' => 'fa-file-invoice-dollar',
        'title' => $billingStats['pending'] . " solicitud(es) de facturación pendiente(s)",
        'message' => $isBilling ? 'Tienes solicitudes esperando tu aprobación.' : 'Solicitudes de facturación en espera.',
        'url' => BASE_URL . '/billing/pending.php',
    ];
}

// 4. Facturación rechazada
if ($billingStats['rejected'] > 0 && ($isAdmin || $isSeller)) {
    $alerts[] = [
        'key' => "bill_rejected_{$billingStats['rejected']}",
        'type' => 'danger', 'icon' => 'fa-ban',
        'title' => $billingStats['rejected'] . " solicitud(es) de facturación rechazada(s)",
        'message' => 'Revisa el motivo del rechazo y corrige si es necesario.',
        'url' => BASE_URL . '/billing/pending.php',
    ];
}

// 5. Créditos pendientes
if ($creditStats['pending'] > 0 && ($isAdmin || $isCredits || $isSeller)) {
    $alerts[] = [
        'key' => "credit_pending_{$creditStats['pending']}",
        'type' => 'warning', 'icon' => 'fa-credit-card',
        'title' => $creditStats['pending'] . " solicitud(es) de crédito pendiente(s)",
        'message' => $isCredits ? 'Tienes solicitudes de crédito esperando evaluación.' : 'Solicitudes de crédito en espera de aprobación.',
        'url' => BASE_URL . '/credits/pending.php',
    ];
}

// 6. Créditos rechazados
if ($creditStats['rejected'] > 0 && ($isAdmin || $isSeller)) {
    $alerts[] = [
        'key' => "credit_rejected_{$creditStats['rejected']}",
        'type' => 'danger', 'icon' => 'fa-times-circle',
        'title' => $creditStats['rejected'] . " solicitud(es) de crédito rechazada(s)",
        'message' => 'Revisa los motivos y considera alternativas de pago.',
        'url' => BASE_URL . '/credits/pending.php',
    ];
}

// 7. Borradores sin enviar
if ($pendingDrafts > 5) {
    $alerts[] = [
        'key' => "drafts_{$pendingDrafts}",
        'type' => 'secondary', 'icon' => 'fa-file-pen',
        'title' => "$pendingDrafts cotizaciones en borrador",
        'message' => 'Tienes borradores acumulados. Revisa si alguno debe enviarse o eliminarse.',
        'url' => BASE_URL . '/quotations/index.php',
    ];
}

// 8. Cotizaciones aceptadas sin facturar
$acceptedCount = $statusCounts['Accepted'] ?? 0;
if ($acceptedCount > 0 && ($isAdmin || $isSeller)) {
    $alerts[] = [
        'key' => "accepted_{$acceptedCount}",
        'type' => 'success', 'icon' => 'fa-check-circle',
        'title' => "$acceptedCount cotización(es) aceptada(s) pendiente(s) de facturación",
        'message' => 'El cliente aceptó. Solicita la facturación para concretar la venta.',
        'url' => BASE_URL . '/quotations/index.php',
    ];
}

// 9. Facturación en proceso
if ($billingStats['in_process'] > 0) {
    $alerts[] = [
        'key' => "bill_process_{$billingStats['in_process']}",
        'type' => 'primary', 'icon' => 'fa-spinner',
        'title' => $billingStats['in_process'] . " facturación(es) en proceso",
        'message' => 'Se están procesando solicitudes de facturación.',
        'url' => BASE_URL . '/billing/pending.php',
    ];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'Hace menos de 1 minuto';
    if ($time < 3600) { $m = floor($time / 60); return "Hace $m minuto" . ($m > 1 ? 's' : ''); }
    if ($time < 86400) { $h = floor($time / 3600); return "Hace $h hora" . ($h > 1 ? 's' : ''); }
    if ($time < 2592000) { $d = floor($time / 86400); return "Hace $d día" . ($d > 1 ? 's' : ''); }
    return date('d/m/Y H:i', strtotime($datetime));
}

function statusLabel($status) {
    $map = [
        'Draft' => ['Borrador', 'bg-secondary'],
        'Sent' => ['Enviada', 'bg-primary'],
        'Accepted' => ['Aceptada', 'bg-success'],
        'Rejected' => ['Rechazada', 'bg-danger'],
        'Invoiced' => ['Facturada', 'bg-info'],
    ];
    $s = $map[$status] ?? [$status, 'bg-secondary'];
    return '<span class="badge ' . $s[1] . '">' . $s[0] . '</span>';
}
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/app_header.php';
?>
                <!-- Hero Action Bar -->
                <div class="dashboard-hero">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="live-pulse-dot" title="Operativo en tiempo real"></span>
                            <h1 class="h2 mb-0 fw-bold">Dashboard</h1>
                        </div>
                        <p class="text-muted mb-0">Bienvenido, <?= htmlspecialchars(($user['first_name'] ?: $user['username']) ?: 'Usuario') ?> · Espacio comercial y operativo en tiempo real</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (Permissions::userCan($auth, 'manage_quotations')): ?>
                        <a href="<?= BASE_URL ?>/quotations/create.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus"></i>
                            <span>Nueva Cotización</span>
                        </a>
                        <?php endif; ?>
                        <?php if (Permissions::userCan($auth, 'manage_customers')): ?>
                        <a href="<?= BASE_URL ?>/customers/create.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i>
                            <span class="d-none d-sm-inline">Nuevo Cliente</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 4 Bento KPI Cards (gstack Industrial Spec) -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-card-header">
                                <span class="kpi-label"><?= $isAdmin ? 'Cotizaciones' : 'Mis Cotizaciones' ?></span>
                                <span class="kpi-icon-pill amber">
                                    <i class="fas fa-file-invoice"></i>
                                </span>
                            </div>
                            <div class="kpi-value"><?= number_format($totalQuotations) ?></div>
                            <div class="kpi-footer">
                                <i class="fas fa-database text-muted"></i>
                                <span>Registros totales</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-lg-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-card-header">
                                <span class="kpi-label"><?= $isAdmin ? 'Clientes' : 'Mis Clientes' ?></span>
                                <span class="kpi-icon-pill green">
                                    <i class="fas fa-users"></i>
                                </span>
                            </div>
                            <div class="kpi-value"><?= number_format($totalCustomers) ?></div>
                            <div class="kpi-footer">
                                <i class="fas fa-address-book text-muted"></i>
                                <span>Cartera comercial</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-lg-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-card-header">
                                <span class="kpi-label"><?= $isAdmin ? 'Catálogo' : 'Mis Productos' ?></span>
                                <span class="kpi-icon-pill blue">
                                    <i class="fas fa-boxes-stacked"></i>
                                </span>
                            </div>
                            <div class="kpi-value"><?= number_format($totalProducts) ?></div>
                            <div class="kpi-footer">
                                <i class="fas fa-cubes text-muted"></i>
                                <span>Ítems disponibles</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-lg-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-card-header">
                                <span class="kpi-label"><?= $isAdmin ? 'Conversión' : 'Mi Conversión' ?></span>
                                <span class="kpi-icon-pill slate">
                                    <i class="fas fa-chart-line"></i>
                                </span>
                            </div>
                            <div class="kpi-value"><?= number_format($conversionRate, 1) ?>%</div>
                            <div class="kpi-footer">
                                <i class="fas fa-arrow-trend-up text-muted"></i>
                                <span>Ratio cotizado / aceptado</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pipeline de Cotizaciones + Funnel Banner -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-diagram-project text-warning me-2"></i> Pipeline de Ventas</span>
                        <span class="badge bg-light text-muted border"><?= number_format($totalQuotations) ?> procesadas</span>
                    </div>
                    <div class="card-body">
                        <div class="notion-pipeline mb-3">
                            <div class="pipeline-step">
                                <div class="step-count text-secondary"><?= $statusCounts['Draft'] ?? 0 ?></div>
                                <div class="step-name">Borrador</div>
                            </div>
                            <i class="fas fa-chevron-right pipeline-chevron d-none d-sm-block"></i>
                            <div class="pipeline-step">
                                <div class="step-count text-primary"><?= $statusCounts['Sent'] ?? 0 ?></div>
                                <div class="step-name">Enviada</div>
                            </div>
                            <i class="fas fa-chevron-right pipeline-chevron d-none d-sm-block"></i>
                            <div class="pipeline-step">
                                <div class="step-count text-success"><?= $statusCounts['Accepted'] ?? 0 ?></div>
                                <div class="step-name">Aceptada</div>
                            </div>
                            <i class="fas fa-chevron-right pipeline-chevron d-none d-sm-block"></i>
                            <div class="pipeline-step">
                                <div class="step-count text-info"><?= $statusCounts['Invoiced'] ?? 0 ?></div>
                                <div class="step-name">Facturada</div>
                            </div>
                        </div>

                        <?php if (($statusCounts['Rejected'] ?? 0) > 0): ?>
                        <div class="text-center mb-2">
                            <span class="badge bg-danger"><i class="fas fa-circle-xmark me-1"></i> <?= $statusCounts['Rejected'] ?> rechazada(s)</span>
                        </div>
                        <?php endif; ?>

                        <?php if ($billingStats['total'] > 0 || $creditStats['total'] > 0): ?>
                        <div class="p-3 bg-light rounded border mt-3">
                            <div class="row g-3">
                                <?php if ($billingStats['total'] > 0 && ($isAdmin || $isBilling || $isSeller)): ?>
                                <div class="col-md-6">
                                    <div class="small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing:0.04em;">
                                        <i class="fas fa-file-invoice-dollar text-primary me-1"></i> Facturación
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($billingStats['pending'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> <?= $billingStats['pending'] ?> pendiente(s)</span>
                                        <?php endif; ?>
                                        <?php if ($billingStats['in_process'] > 0): ?>
                                            <span class="badge bg-primary"><i class="fas fa-spinner fa-spin me-1"></i> <?= $billingStats['in_process'] ?> en proceso</span>
                                        <?php endif; ?>
                                        <?php if ($billingStats['invoiced'] > 0): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> <?= $billingStats['invoiced'] ?> facturada(s)</span>
                                        <?php endif; ?>
                                        <?php if ($billingStats['rejected'] > 0): ?>
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> <?= $billingStats['rejected'] ?> rechazada(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if ($creditStats['total'] > 0 && ($isAdmin || $isCredits || $isSeller)): ?>
                                <div class="col-md-6">
                                    <div class="small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing:0.04em;">
                                        <i class="fas fa-credit-card text-success me-1"></i> Créditos
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($creditStats['pending'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> <?= $creditStats['pending'] ?> pendiente(s)</span>
                                        <?php endif; ?>
                                        <?php if ($creditStats['approved'] > 0): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> <?= $creditStats['approved'] ?> aprobado(s)</span>
                                        <?php endif; ?>
                                        <?php if ($creditStats['rejected'] > 0): ?>
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> <?= $creditStats['rejected'] ?> rechazado(s)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2-Column Split: Cotizaciones Recientes (8 cols) + Acciones Rápidas & Alertas (4 cols) -->
                <div class="row g-3">
                    <!-- Left: Últimas Cotizaciones -->
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold"><i class="fas fa-history text-warning me-2"></i> Últimas Cotizaciones</span>
                                <a href="<?= BASE_URL ?>/quotations/index.php" class="btn btn-sm btn-outline-secondary">
                                    <span>Ver todas</span> <i class="fas fa-arrow-right small ms-1"></i>
                                </a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentQuotations)): ?>
                                    <div class="empty-state py-5 text-center">
                                        <i class="fas fa-file-circle-question fa-3x text-muted mb-3"></i>
                                        <p class="text-muted mb-0">No hay cotizaciones registradas aún.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive border-0 rounded-0 shadow-none mb-0">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Número / Cliente</th>
                                                    <th class="text-center">Estado</th>
                                                    <th class="text-end">Total</th>
                                                    <th class="text-end">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentQuotations as $q): ?>
                                                <tr>
                                                    <td>
                                                        <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $q['id'] ?>" class="fw-bold text-decoration-none d-block">
                                                            <?= htmlspecialchars($q['quotation_number']) ?>
                                                        </a>
                                                        <span class="text-muted small text-truncate d-inline-block" style="max-width:240px;">
                                                            <?= htmlspecialchars($q['customer_name'] ?? 'Sin cliente') ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?= statusLabel($q['status']) ?>
                                                    </td>
                                                    <td class="text-end">
                                                        <div class="fw-bold tabular-nums" style="font-family:var(--font-mono);">
                                                            <?= $q['currency'] === 'USD' ? '$' : 'S/' ?> <?= number_format($q['total'], 2) ?>
                                                        </div>
                                                        <div class="text-muted" style="font-size:0.72rem;"><?= date('d/m/Y', strtotime($q['created_at'] ?? 'now')) ?></div>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-light p-1 px-2" title="Ver cotización">
                                                            <i class="fas fa-arrow-right"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Acciones Rápidas & Alertas -->
                    <div class="col-lg-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <span class="fw-bold"><i class="fas fa-bolt text-warning me-2"></i> Acciones Rápidas</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <?php if (Permissions::userCan($auth, 'manage_quotations')): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/quotations/create.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-plus"></i></span>
                                            <span class="qa-label">Cotizar</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (Permissions::userCan($auth, 'manage_customers')): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/customers/create.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-user-plus"></i></span>
                                            <span class="qa-label">Cliente</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (Permissions::userCan($auth, 'view_products') || Permissions::userCan($auth, 'manage_products')): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/products/index.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-search"></i></span>
                                            <span class="qa-label">Stock</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (Permissions::userCan($auth, 'billing_panel') || Permissions::userCan($auth, 'process_billing')): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/billing/pending.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                                            <span class="qa-label">Facturas</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (Permissions::userCan($auth, 'credits_panel') || Permissions::userCan($auth, 'process_credits')): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/credits/pending.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-credit-card"></i></span>
                                            <span class="qa-label">Créditos</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (Permissions::canAccessInventoryPanel($auth)): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/inventario/index.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-warehouse"></i></span>
                                            <span class="qa-label">Inventario</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (Permissions::canAccessCostAnalysis($auth)): ?>
                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/cost-analysis/index.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-calculator"></i></span>
                                            <span class="qa-label">Costos</span>
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <div class="col-6">
                                        <a href="<?= BASE_URL ?>/reports/index.php" class="quick-action">
                                            <span class="qa-icon"><i class="fas fa-chart-pie"></i></span>
                                            <span class="qa-label">Reportes</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
ob_start();
?>
<script>
    function getDismissedAlerts() {
        try { return JSON.parse(localStorage.getItem('dismissed_alerts') || '{}'); }
        catch(e) { return {}; }
    }

    function dismissAlert(key, btn) {
        var dismissed = getDismissedAlerts();
        dismissed[key] = Date.now();
        localStorage.setItem('dismissed_alerts', JSON.stringify(dismissed));
        var li = btn.closest('.alert-item');
        if (li) li.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        var dismissed = getDismissedAlerts();
        document.querySelectorAll('.alert-item').forEach(function(li) {
            if (dismissed[li.dataset.alertKey]) {
                li.style.display = 'none';
            }
        });
    });
</script>
<?php
$extraScripts = ob_get_clean();
require_once __DIR__ . '/../includes/app_footer.php';
?>