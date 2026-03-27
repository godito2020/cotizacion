<?php
/**
 * Selección de Almacén para Inventario - PWA Mobile First
 * El usuario debe seleccionar un almacén antes de registrar
 */

require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canAccessInventoryPanel($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder al módulo de inventario';
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

// Verificar si hay sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);

if (!$activeSession) {
    $_SESSION['warning_message'] = 'No hay una sesión de inventario activa en este momento';
}

// Procesar selección de almacén
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedWarehouse = (int)($_POST['warehouse'] ?? 0);

    if ($selectedWarehouse > 0 && $activeSession) {
        // Verificar que el almacén está en la sesión
        $warehouses = $session->getSessionWarehouses($activeSession['id']);
        $valid = false;
        foreach ($warehouses as $wh) {
            if ((int)$wh['warehouse_number'] === $selectedWarehouse) {
                $valid = true;
                $_SESSION['inventory_warehouse'] = $selectedWarehouse;
                $_SESSION['inventory_warehouse_name'] = $wh['warehouse_name'];
                break;
            }
        }

        if ($valid) {
            header('Location: ' . BASE_URL . '/inventario/dashboard.php');
            exit;
        } else {
            $_SESSION['error_message'] = 'El almacén seleccionado no está habilitado para esta sesión';
        }
    }
}

// Obtener almacenes disponibles para la sesión
$warehouses = $activeSession ? $session->getSessionWarehouses($activeSession['id']) : [];

// Almacén actualmente seleccionado
$currentWarehouse = $_SESSION['inventory_warehouse'] ?? null;

// Obtener icono PWA personalizado
$customIconPath = __DIR__ . '/../uploads/pwa_icons/inventory_icon_' . $companyId . '.png';
$pwaIconUrl = file_exists($customIconPath)
    ? BASE_URL . '/uploads/pwa_icons/inventory_icon_' . $companyId . '.png'
    : BASE_URL . '/assets/icons/icon-192x192.png';

$pageTitle = 'Seleccionar Almacén';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Inventario">
    <meta name="mobile-web-app-capable" content="yes">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= BASE_URL ?>/inventario/manifest.php">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($pwaIconUrl) ?>">
    <link rel="icon" href="<?= htmlspecialchars($pwaIconUrl) ?>">

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --safe-area-top: env(safe-area-inset-top, 0px);
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
            --safe-area-left: env(safe-area-inset-left, 0px);
            --safe-area-right: env(safe-area-inset-right, 0px);
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            padding-top: calc(44px + var(--safe-area-top));
            padding-bottom: calc(65px + var(--safe-area-bottom));
            padding-left: var(--safe-area-left);
            padding-right: var(--safe-area-right);
            min-height: 100vh;
            min-height: 100dvh;
            overscroll-behavior: contain;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Barra de título compacta */
        .title-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: calc(44px + var(--safe-area-top));
            padding-top: var(--safe-area-top);
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-left: 16px;
            padding-right: 16px;
            z-index: 1000;
        }

        .title-bar h1 {
            font-size: 17px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Contenido principal */
        .app-content {
            padding: 16px;
            max-width: 500px;
            margin: 0 auto;
        }

        /* Alertas */
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .alert-card.info {
            border-left: 4px solid var(--primary-color);
        }

        .alert-card.warning {
            border-left: 4px solid var(--warning-color);
        }

        .alert-card.danger {
            border-left: 4px solid var(--danger-color);
        }

        .alert-card .alert-icon {
            font-size: 20px;
            margin-right: 12px;
        }

        .alert-card.info .alert-icon { color: var(--primary-color); }
        .alert-card.warning .alert-icon { color: var(--warning-color); }
        .alert-card.danger .alert-icon { color: var(--danger-color); }

        /* Lista de almacenes */
        .warehouse-list {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .warehouse-list-header {
            padding: 16px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            font-size: 14px;
            color: #6c757d;
        }

        .warehouse-item {
            display: flex;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid #f0f2f5;
            cursor: pointer;
            transition: background 0.2s;
        }

        .warehouse-item:last-child {
            border-bottom: none;
        }

        .warehouse-item:active {
            background: #f8f9fa;
        }

        .warehouse-item.selected {
            background: #e8f4ff;
        }

        .warehouse-item input[type="radio"] {
            width: 22px;
            height: 22px;
            margin-right: 14px;
            accent-color: var(--primary-color);
        }

        .warehouse-info {
            flex: 1;
        }

        .warehouse-name {
            font-weight: 600;
            font-size: 15px;
            color: #212529;
        }

        .warehouse-number {
            font-size: 13px;
            color: #6c757d;
        }

        .warehouse-check {
            color: var(--success-color);
            font-size: 20px;
            display: none;
        }

        .warehouse-item.selected .warehouse-check {
            display: block;
        }

        /* Botón de continuar */
        .btn-continue {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            margin-top: 20px;
        }

        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #495057;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 20px;
        }

        /* Footer fijo */
        .app-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(65px + var(--safe-area-bottom));
            padding-bottom: var(--safe-area-bottom);
            background: white;
            display: flex;
            justify-content: space-around;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .footer-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            color: #6c757d;
            text-decoration: none;
            font-size: 10px;
            transition: color 0.2s;
            min-width: 64px;
        }

        .footer-btn i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .footer-btn.active {
            color: var(--primary-color);
        }

        .footer-btn:active {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Barra de título compacta -->
    <header class="title-bar">
        <h1>
            <i class="fas fa-warehouse"></i> <?= htmlspecialchars($pageTitle) ?>
        </h1>
    </header>

    <!-- Contenido principal -->
    <main class="app-content">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert-card danger">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert-card warning">
                <i class="fas fa-exclamation-triangle alert-icon"></i>
                <?= htmlspecialchars($_SESSION['warning_message']) ?>
            </div>
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>

        <?php if ($activeSession): ?>
            <!-- Sesión activa -->
            <div class="alert-card info">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clipboard-list alert-icon"></i>
                    <div>
                        <strong>Sesión activa</strong>
                        <div class="small text-muted"><?= htmlspecialchars($activeSession['name']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Lista de almacenes -->
            <form method="POST" id="warehouseForm">
                <div class="warehouse-list">
                    <div class="warehouse-list-header">
                        <i class="fas fa-box"></i> Selecciona un almacén
                    </div>
                    <?php foreach ($warehouses as $wh): ?>
                        <?php $isSelected = $currentWarehouse && (int)$wh['warehouse_number'] === $currentWarehouse; ?>
                        <label class="warehouse-item <?= $isSelected ? 'selected' : '' ?>">
                            <input type="radio" name="warehouse" value="<?= $wh['warehouse_number'] ?>"
                                   <?= $isSelected ? 'checked' : '' ?> required
                                   onchange="this.closest('.warehouse-item').classList.add('selected'); document.querySelectorAll('.warehouse-item').forEach(el => { if(el !== this.closest('.warehouse-item')) el.classList.remove('selected'); });">
                            <div class="warehouse-info">
                                <div class="warehouse-name"><?= htmlspecialchars($wh['warehouse_name']) ?></div>
                                <div class="warehouse-number">Almacén #<?= $wh['warehouse_number'] ?></div>
                            </div>
                            <i class="fas fa-check-circle warehouse-check"></i>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-continue">
                    <i class="fas fa-arrow-right"></i> Continuar
                </button>
            </form>
        <?php else: ?>
            <!-- No hay sesión activa -->
            <div class="empty-state">
                <i class="fas fa-pause-circle"></i>
                <h3>No hay inventario activo</h3>
                <p>El supervisor debe iniciar una sesión de inventario para que puedas comenzar a registrar.</p>
                <button onclick="location.reload()" class="btn btn-outline-primary">
                    <i class="fas fa-sync"></i> Verificar de nuevo
                </button>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer fijo con navegación -->
    <footer class="app-footer">
        <a href="<?= BASE_URL ?>/inventario/dashboard.php" class="footer-btn">
            <i class="fas fa-search"></i>
            <span>Buscar</span>
        </a>
        <a href="<?= BASE_URL ?>/inventario/my_entries.php" class="footer-btn">
            <i class="fas fa-list-alt"></i>
            <span>Registros</span>
        </a>
        <a href="<?= BASE_URL ?>/inventario/select_warehouse.php" class="footer-btn active">
            <i class="fas fa-warehouse"></i>
            <span>Almacén</span>
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="footer-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Salir</span>
        </a>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= BASE_URL ?>/inventario/sw.js')
                .then(reg => console.log('Service Worker registrado'))
                .catch(err => console.log('Error SW:', err));
        }
    </script>
</body>
</html>
