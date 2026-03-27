<?php
/**
 * Dashboard del Usuario de Inventario - PWA Mobile First
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
$user = $auth->getUser();
$username = $user['first_name'] ?? $user['username'];

// Verificar sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);

if (!$activeSession) {
    header('Location: ' . BASE_URL . '/inventario/select_warehouse.php');
    exit;
}

// Verificar almacén seleccionado
if (!isset($_SESSION['inventory_warehouse'])) {
    header('Location: ' . BASE_URL . '/inventario/select_warehouse.php');
    exit;
}

$warehouseNumber = (int)$_SESSION['inventory_warehouse'];
$warehouseName = $_SESSION['inventory_warehouse_name'] ?? 'Almacén ' . $warehouseNumber;

// Obtener estadísticas del usuario
$entry = new InventoryEntry();
$userStats = $entry->getUserStats($activeSession['id'], $userId);

// Obtener zonas disponibles para el almacén
$zoneManager = new InventoryZone();
$availableZones = $zoneManager->getByWarehouse($companyId, $warehouseNumber, true);
$userZones = $zoneManager->getUserZones($activeSession['id'], $userId);
$hasZones = !empty($availableZones);

// Obtener icono PWA personalizado
$customIconPath = __DIR__ . '/../uploads/pwa_icons/inventory_icon_' . $companyId . '.png';
$pwaIconUrl = file_exists($customIconPath)
    ? BASE_URL . '/uploads/pwa_icons/inventory_icon_' . $companyId . '.png'
    : BASE_URL . '/assets/icons/icon-192x192.png';

$pageTitle = 'Inventario';
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
    <meta name="format-detection" content="telephone=no">
    <meta name="screen-orientation" content="portrait">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?= BASE_URL ?>/inventario/manifest.php">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($pwaIconUrl) ?>">
    <link rel="apple-touch-icon" sizes="152x152" href="<?= htmlspecialchars($pwaIconUrl) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($pwaIconUrl) ?>">
    <link rel="icon" href="<?= htmlspecialchars($pwaIconUrl) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($pwaIconUrl) ?>">

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

        /* Fullscreen mode */
        @media all and (display-mode: fullscreen) {
            body {
                padding-top: calc(44px + var(--safe-area-top));
            }
        }

        @media all and (display-mode: standalone) {
            body {
                padding-top: calc(44px + var(--safe-area-top));
            }
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
            justify-content: space-between;
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

        .title-bar .warehouse-badge {
            background: rgba(255,255,255,0.2);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .title-bar-actions {
            display: flex;
            gap: 8px;
        }

        .title-bar-btn {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }

        .title-bar-btn:active {
            background: rgba(255,255,255,0.25);
        }

        /* Contenido principal */
        .app-content {
            padding: 16px;
        }

        /* Tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            margin-bottom: 10px;
            justify-content: center;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 7px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: #6c757d;
        }

        .stat-card.primary .stat-value { color: var(--primary-color); }
        .stat-card.success .stat-value { color: var(--success-color); }
        .stat-card.danger .stat-value { color: var(--danger-color); }
        .stat-card.warning .stat-value { color: #cc9a06; }

        /* Sesión activa */
        .session-banner {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            color: white;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .session-banner i {
            font-size: 24px;
        }

        .session-banner .session-info {
            flex: 1;
        }

        .session-banner .session-name {
            font-weight: 600;
            font-size: 14px;
        }

        .session-banner .session-status {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Búsqueda */
        .search-container {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .search-header {
            padding: 16px;
            border-bottom: 1px solid #e9ecef;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .search-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 18px;
        }

        .search-results {
            max-height: 50vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .product-item {
            padding: 16px;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .product-item:active {
            background: #f8f9fa;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info {
            flex: 1;
            min-width: 0;
        }

        .product-code {
            font-weight: 600;
            font-size: 15px;
            color: #212529;
        }

        .product-desc {
            font-size: 13px;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-stock {
            text-align: right;
            margin-left: 12px;
        }

        .stock-badge {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .last-entry-badge {
            font-size: 11px;
            color: var(--primary-color);
            margin-top: 4px;
        }

        /* Filtro de stock */
        .filter-toggle {
            display: flex;
            justify-content: center;
        }

        .filter-toggle .btn-group {
            width: 100%;
        }

        .filter-toggle .btn {
            flex: 1;
            font-size: 13px;
            padding: 8px 12px;
        }

        .filter-toggle .btn-check:checked + .btn-outline-primary {
            background-color: var(--primary-color);
            color: white;
        }

        /* Empty state */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
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

        /* Modal de registro */
        .modal-fullscreen-sm-down {
            padding: 0 !important;
        }

        @media (max-width: 575.98px) {
            .modal-fullscreen-sm-down .modal-dialog {
                width: 100%;
                max-width: none;
                height: 100%;
                margin: 0;
            }

            .modal-fullscreen-sm-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            padding: 16px;
        }

        .modal-body {
            padding: 20px;
        }

        .form-label {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-control {
            padding: 14px 16px;
            font-size: 16px;
            border-radius: 12px;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }

        .quantity-input {
            font-size: 28px !important;
            font-weight: 700;
            text-align: center;
            height: 70px;
        }

        .product-display {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .product-display .code {
            font-weight: 700;
            font-size: 18px;
            color: var(--primary-color);
        }

        .product-display .desc {
            color: #6c757d;
            font-size: 14px;
        }

        .stock-display {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .stock-display .stock-item {
            flex: 1;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }

        .stock-display .stock-value {
            font-size: 20px;
            font-weight: 700;
        }

        .stock-display .stock-label {
            font-size: 12px;
            color: #6c757d;
        }

        .btn-save {
            width: 100%;
            padding: 16px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 12px;
        }

        /* Toast personalizado */
        .custom-toast {
            position: fixed;
            bottom: calc(80px + var(--safe-area-bottom));
            left: 16px;
            right: 16px;
            background: #323232;
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .custom-toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .custom-toast.success {
            background: var(--success-color);
        }

        .custom-toast.error {
            background: var(--danger-color);
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* PWA Install prompt */
        .install-prompt {
            display: none;
            position: fixed;
            bottom: calc(70px + var(--safe-area-bottom));
            left: 16px;
            right: 16px;
            background: white;
            padding: 16px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1500;
        }

        .install-prompt.show {
            display: block;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Selector de zonas */
        .zone-selector {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .zone-selector-title {
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 12px;
        }

        .zone-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .zone-chip {
            padding: 5px 5px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
            border: 2px solid #e9ecef;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .zone-chip.selected {
            border-color: currentColor;
            color: white !important;
        }

        .zone-chip:active {
            transform: scale(0.95);
        }

        .current-zone-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Barra de título compacta -->
    <header class="title-bar">
        <h1>
            <i class="fas fa-clipboard-check"></i> Inventario
            <span class="warehouse-badge"><?= htmlspecialchars($warehouseName) ?></span>
        </h1>
        <div class="title-bar-actions">
            <button class="title-bar-btn" onclick="toggleMenu()">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="app-content">
        <!-- Banner de sesión -->
        <div class="session-banner">
            <i class="fas fa-clipboard-list"></i>
            <div class="session-info">
                <div class="session-name"><?= htmlspecialchars($activeSession['name']) ?></div>
                <div class="session-status"><i class="fas fa-circle"></i> Sesión Activa</div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-value"><?= (int)($userStats['total_entries'] ?? 0) ?></div>
                <div class="stat-label">Registros</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value"><?= (int)($userStats['matching_count'] ?? 0) ?></div>
                <div class="stat-label">Coinciden</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-value"><?= (int)($userStats['faltantes_count'] ?? 0) ?></div>
                <div class="stat-label">Faltantes</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?= (int)($userStats['sobrantes_count'] ?? 0) ?></div>
                <div class="stat-label">Sobrantes</div>
            </div>
        </div>

        <?php if ($hasZones): ?>
        <!-- Selector de Zona -->
        <div class="zone-selector">
            <div class="zone-selector-title">
                <i class="fas fa-map-marker-alt"></i> Zona de trabajo:
                <?php if (!empty($userZones)): ?>
                    <span class="current-zone-badge" id="currentZoneBadge" style="background: <?= htmlspecialchars($userZones[0]['color']) ?>">
                        <?= htmlspecialchars($userZones[0]['name']) ?>
                    </span>
                <?php else: ?>
                    <span class="text-danger" id="noZoneWarning">Selecciona una zona</span>
                <?php endif; ?>
            </div>
            <div class="zone-chips" id="zoneChips">
                <?php foreach ($availableZones as $zone): ?>
                    <?php
                    $isSelected = in_array($zone['id'], array_column($userZones, 'id'));
                    ?>
                    <div class="zone-chip <?= $isSelected ? 'selected' : '' ?>"
                         data-zone-id="<?= $zone['id'] ?>"
                         data-zone-name="<?= htmlspecialchars($zone['name']) ?>"
                         data-zone-color="<?= htmlspecialchars($zone['color']) ?>"
                         style="<?= $isSelected ? "background-color: {$zone['color']}; border-color: {$zone['color']}; color: white;" : "color: {$zone['color']};" ?>"
                         onclick="selectZone(this)">
                        <?= htmlspecialchars($zone['name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Búsqueda de productos -->
        <div class="search-container">
            <div class="search-header">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input"
                           placeholder="Buscar producto..." autocomplete="off">
                </div>
                <!-- Filtro de stock -->
                <div class="filter-toggle mt-2">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="stockFilter" id="filterWithStock" value="1" checked>
                        <label class="btn btn-outline-primary btn-sm" for="filterWithStock">
                            <i class="fas fa-boxes"></i> Con Saldos
                        </label>
                        <input type="radio" class="btn-check" name="stockFilter" id="filterAll" value="0">
                        <label class="btn btn-outline-primary btn-sm" for="filterAll">
                            <i class="fas fa-list"></i> Todos
                        </label>
                    </div>
                </div>
            </div>
            <div id="searchResults" class="search-results">
                <div class="empty-state">
                    <i class="fas fa-barcode"></i>
                    <p>Escribe para buscar por código o descripción</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer fijo con navegación -->
    <footer class="app-footer">
        <a href="<?= BASE_URL ?>/inventario/dashboard.php" class="footer-btn active">
            <i class="fas fa-search"></i>
            <span>Buscar</span>
        </a>
        <a href="<?= BASE_URL ?>/inventario/my_entries.php" class="footer-btn">
            <i class="fas fa-list-alt"></i>
            <span>Registros</span>
        </a>
        <a href="<?= BASE_URL ?>/inventario/select_warehouse.php" class="footer-btn">
            <i class="fas fa-warehouse"></i>
            <span>Almacén</span>
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="footer-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Salir</span>
        </a>
    </footer>

    <!-- Modal de registro -->
    <div class="modal fade modal-fullscreen-sm-down" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Registrar Conteo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="registerForm">
                    <div class="modal-body">
                        <div class="product-display">
                            <div class="code" id="productCode"></div>
                            <div class="desc" id="productDesc"></div>
                        </div>
                        <input type="hidden" id="productCodeInput" name="product_code">

                        <div class="stock-display">
                            <div class="stock-item">
                                <div class="stock-value" id="systemStock">0</div>
                                <div class="stock-label">Stock Sistema</div>
                            </div>
                            <div class="stock-item">
                                <div class="stock-value" id="lastEntry">-</div>
                                <div class="stock-label">Ya Contado</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="countedQuantity" class="form-label">Cantidad Encontrada</label>
                            <input type="number" id="countedQuantity" name="counted_quantity"
                                   class="form-control quantity-input" step="0.01" min="0"
                                   inputmode="decimal" required placeholder="0">
                        </div>

                        <?php if ($hasZones): ?>
                        <div class="mb-4">
                            <label for="entryZone" class="form-label">Zona donde se encontró</label>
                            <select id="entryZone" name="zone_id" class="form-select" style="font-size: 16px; padding: 14px;">
                                <option value="">-- Seleccionar zona --</option>
                                <?php foreach ($availableZones as $zone): ?>
                                    <option value="<?= $zone['id'] ?>" data-color="<?= htmlspecialchars($zone['color']) ?>">
                                        <?= htmlspecialchars($zone['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label for="comments" class="form-label">Comentarios (opcional)</label>
                            <textarea id="comments" name="comments" class="form-control"
                                      rows="2" placeholder="Observaciones..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-save">
                            <i class="fas fa-save"></i> Guardar Conteo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Menu Dropdown -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="menuOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menú</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="list-group list-group-flush">
                <a href="<?= BASE_URL ?>/inventario/my_entries.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-list-check me-3"></i> Mis Registros
                </a>
                <a href="<?= BASE_URL ?>/inventario/export.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-file-excel me-3"></i> Exportar a Excel
                </a>
                <a href="<?= BASE_URL ?>/inventario/select_warehouse.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-exchange-alt me-3"></i> Cambiar Almacén
                </a>
                <hr>
                <a href="<?= BASE_URL ?>/logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="fas fa-sign-out-alt me-3"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="custom-toast"></div>

    <!-- Loading -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <p class="mt-3">Procesando...</p>
    </div>

    <!-- PWA Install Prompt -->
    <div id="installPrompt" class="install-prompt">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1">
                <strong>Instalar App</strong>
                <p class="mb-0 small text-muted">Accede más rápido desde tu pantalla de inicio</p>
            </div>
            <button class="btn btn-primary btn-sm" onclick="installPWA()">Instalar</button>
            <button class="btn btn-link btn-sm" onclick="dismissInstallPrompt()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const warehouseNumber = <?= $warehouseNumber ?>;
        const sessionId = <?= $activeSession['id'] ?>;
        const hasZones = <?= $hasZones ? 'true' : 'false' ?>;
        let searchTimeout = null;
        let deferredPrompt = null;
        let selectedZoneId = <?= !empty($userZones) ? $userZones[0]['id'] : 'null' ?>;

        const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
        const menuOffcanvas = new bootstrap.Offcanvas(document.getElementById('menuOffcanvas'));

        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= BASE_URL ?>/inventario/sw.js')
                .then(reg => console.log('Service Worker registrado'))
                .catch(err => console.log('Error SW:', err));
        }

        // PWA Install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            // Mostrar prompt después de un momento
            setTimeout(() => {
                if (!localStorage.getItem('pwa-prompt-dismissed')) {
                    document.getElementById('installPrompt').classList.add('show');
                }
            }, 3000);
        });

        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choice) => {
                    if (choice.outcome === 'accepted') {
                        showToast('¡App instalada correctamente!', 'success');
                    }
                    deferredPrompt = null;
                    document.getElementById('installPrompt').classList.remove('show');
                });
            }
        }

        function dismissInstallPrompt() {
            document.getElementById('installPrompt').classList.remove('show');
            localStorage.setItem('pwa-prompt-dismissed', 'true');
        }

        function toggleMenu() {
            menuOffcanvas.toggle();
        }

        // Selección de zona
        async function selectZone(element) {
            const zoneId = element.dataset.zoneId;
            const zoneName = element.dataset.zoneName;
            const zoneColor = element.dataset.zoneColor;

            // Deseleccionar todas
            document.querySelectorAll('.zone-chip').forEach(chip => {
                chip.classList.remove('selected');
                chip.style.backgroundColor = '';
                chip.style.borderColor = '#e9ecef';
                chip.style.color = chip.dataset.zoneColor;
            });

            // Seleccionar la actual
            element.classList.add('selected');
            element.style.backgroundColor = zoneColor;
            element.style.borderColor = zoneColor;
            element.style.color = 'white';

            selectedZoneId = zoneId;

            // Actualizar badge
            const badge = document.getElementById('currentZoneBadge');
            const warning = document.getElementById('noZoneWarning');
            if (badge) {
                badge.textContent = zoneName;
                badge.style.background = zoneColor;
            } else if (warning) {
                warning.outerHTML = `<span class="current-zone-badge" id="currentZoneBadge" style="background: ${zoneColor}">${zoneName}</span>`;
            }

            // Guardar en servidor
            try {
                await fetch(`${BASE_URL}/inventario/api/zones.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'save_user_zones',
                        session_id: sessionId,
                        zone_ids: [zoneId]
                    })
                });
            } catch (error) {
                console.error('Error guardando zona:', error);
            }
        }

        // Búsqueda con debounce
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-barcode"></i>
                        <p>Escribe para buscar por código o descripción</p>
                    </div>
                `;
                return;
            }

            searchTimeout = setTimeout(() => searchProducts(query), 300);
        });

        // Cambio de filtro - re-ejecutar búsqueda
        document.querySelectorAll('input[name="stockFilter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const query = document.getElementById('searchInput').value.trim();
                if (query.length >= 2) {
                    searchProducts(query);
                }
            });
        });

        async function searchProducts(query) {
            document.getElementById('searchResults').innerHTML = `
                <div class="empty-state">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Buscando...</p>
                </div>
            `;

            try {
                const withStock = document.querySelector('input[name="stockFilter"]:checked').value;
                const response = await fetch(`${BASE_URL}/inventario/api/search_products.php?query=${encodeURIComponent(query)}&warehouse=${warehouseNumber}&with_stock=${withStock}`);
                const data = await response.json();

                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(product => {
                        let entryBadge = '';
                        if (product.user_entry_summary) {
                            const s = product.user_entry_summary;
                            if (s.entry_count > 1) {
                                entryBadge = `<div class="last-entry-badge"><i class="fas fa-layer-group"></i> ${s.total_counted} (${s.entry_count}x)</div>`;
                            } else {
                                entryBadge = `<div class="last-entry-badge">Contado: ${s.total_counted}</div>`;
                            }
                        }

                        html += `
                            <div class="product-item" onclick='selectProduct(${JSON.stringify(product)})'>
                                <div class="product-info">
                                    <div class="product-code">${product.codigo}</div>
                                    <div class="product-desc">${product.descripcion || ''}</div>
                                </div>
                                <div class="product-stock">
                                    <div class="stock-badge">${product.stock_actual}</div>
                                    ${entryBadge}
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById('searchResults').innerHTML = html;
                } else {
                    document.getElementById('searchResults').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <p>No se encontraron productos</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('searchResults').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        <p>Error al buscar productos</p>
                    </div>
                `;
            }
        }

        function selectProduct(product) {
            document.getElementById('productCode').textContent = product.codigo;
            document.getElementById('productDesc').textContent = product.descripcion || '';
            document.getElementById('productCodeInput').value = product.codigo;
            document.getElementById('systemStock').textContent = product.stock_actual;

            // Mostrar resumen de conteos anteriores (suma total)
            const summary = product.user_entry_summary;
            if (summary) {
                if (summary.entry_count > 1) {
                    document.getElementById('lastEntry').innerHTML = `${summary.total_counted} <small>(${summary.entry_count} registros)</small>`;
                } else {
                    document.getElementById('lastEntry').textContent = summary.total_counted;
                }
            } else {
                document.getElementById('lastEntry').textContent = '-';
            }

            document.getElementById('countedQuantity').value = '';
            document.getElementById('comments').value = '';

            // Pre-seleccionar la zona activa
            if (hasZones && selectedZoneId) {
                const zoneSelect = document.getElementById('entryZone');
                if (zoneSelect) {
                    zoneSelect.value = selectedZoneId;
                }
            }

            registerModal.show();
            setTimeout(() => document.getElementById('countedQuantity').focus(), 500);
        }

        // Enviar formulario
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            showLoading(true);

            const formData = {
                product_code: document.getElementById('productCodeInput').value,
                counted_quantity: parseFloat(document.getElementById('countedQuantity').value),
                comments: document.getElementById('comments').value,
                warehouse_number: warehouseNumber,
                zone_id: hasZones ? (document.getElementById('entryZone')?.value || null) : null
            };

            try {
                const response = await fetch(`${BASE_URL}/inventario/api/register_entry.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();
                showLoading(false);

                if (data.success) {
                    registerModal.hide();
                    showToast('¡Conteo registrado correctamente!', 'success');

                    // Limpiar y mostrar mensaje de éxito
                    document.getElementById('searchInput').value = '';
                    document.getElementById('searchResults').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-check-circle text-success"></i>
                            <p>¡Guardado! Busca otro producto</p>
                        </div>
                    `;

                    // Recargar para actualizar estadísticas
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message || 'Error al guardar', 'error');
                }
            } catch (error) {
                showLoading(false);
                console.error('Error:', error);
                showToast('Error de conexión', 'error');
            }
        });

        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'custom-toast ' + type + ' show';

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (show) {
                overlay.classList.add('show');
            } else {
                overlay.classList.remove('show');
            }
        }

        // Focus automático en búsqueda al cargar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').focus();
        });
    </script>
</body>
</html>
