<?php
/**
 * Monitor en Tiempo Real de Inventario
 */

require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para administrar inventarios';
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
    exit;
}

$companyId = $auth->getCompanyId();

// Verificar sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);

if (!$activeSession) {
    $_SESSION['warning_message'] = 'No hay sesión de inventario activa';
    header('Location: ' . BASE_URL . '/inventario/admin/index.php');
    exit;
}

$pageTitle = 'Monitor en Tiempo Real';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .stat-number { font-size: 2.5rem; font-weight: bold; }
        .recent-entry {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .diff-positive { color: var(--bs-warning); }
        .diff-negative { color: var(--bs-danger); }
        .diff-zero { color: var(--bs-success); }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .stat-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .entries-table {
            max-height: 400px;
            overflow-y: auto;
        }
        .entries-table th {
            position: sticky;
            top: 0;
            background: #212529;
            z-index: 1;
        }
        /* Tabs con texto blanco */
        #detailsTabs {
            background: #0d6efd;
            border-radius: 5px 5px 0 0;
            padding: 5px 10px 0;
        }
        #detailsTabs .nav-link {
            color: rgba(255,255,255,0.8);
            border: none;
            margin-right: 5px;
        }
        #detailsTabs .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }
        #detailsTabs .nav-link.active {
            color: #0d6efd;
            background: #fff;
            font-weight: bold;
        }
        #detailsTabs .nav-link .badge {
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/inventario/admin/index.php">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-desktop"></i> Monitor en Tiempo Real
                <span class="badge bg-success pulse ms-2">
                    <i class="fas fa-circle"></i> EN VIVO
                </span>
            </span>
            <span class="navbar-text text-white-50" id="lastUpdate">
                Actualizando...
            </span>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <!-- Info de sesión -->
        <div class="alert alert-info mb-4">
            <strong><i class="fas fa-clipboard-list"></i> Sesión:</strong>
            <?= htmlspecialchars($activeSession['name']) ?>
            <span class="float-end">
                Iniciada: <?= date('d/m/Y H:i', strtotime($activeSession['opened_at'])) ?>
            </span>
        </div>

        <!-- Estadísticas principales (clickeables) -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body text-center">
                        <div class="stat-number" id="totalEntries">0</div>
                        <div>Registros Totales</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card bg-success text-white h-100 stat-card" role="button" onclick="showTab('matching')">
                    <div class="card-body text-center">
                        <div class="stat-number" id="matchingCount">0</div>
                        <div>Coinciden</div>
                        <small><i class="fas fa-hand-pointer"></i> Ver detalle</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card bg-danger text-white h-100 stat-card" role="button" onclick="showTab('faltantes')">
                    <div class="card-body text-center">
                        <div class="stat-number" id="faltantesCount">0</div>
                        <div>Faltantes</div>
                        <small><i class="fas fa-hand-pointer"></i> Ver detalle</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card bg-warning text-dark h-100 stat-card" role="button" onclick="showTab('sobrantes')">
                    <div class="card-body text-center">
                        <div class="stat-number" id="sobrantesCount">0</div>
                        <div>Sobrantes</div>
                        <small><i class="fas fa-hand-pointer"></i> Ver detalle</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Progreso por almacén -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-warehouse"></i> Progreso por Almacén</h5>
                    </div>
                    <div class="card-body" id="warehouseProgress">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border"></div>
                            <p>Cargando...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Entradas recientes -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-stream"></i> Últimos Registros</h5>
                        <span class="badge bg-info" id="activeUsers">0 usuarios activos</span>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <div class="list-group list-group-flush" id="recentEntries">
                            <div class="list-group-item text-center text-muted py-4">
                                <div class="spinner-border"></div>
                                <p>Cargando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs de detalles -->
        <div class="card" id="detailsCard">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="detailsTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tabMatching" onclick="loadTabData('matching')">
                            <i class="fas fa-check-circle text-success"></i> Coinciden
                            <span class="badge bg-success" id="tabMatchingCount">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabFaltantes" onclick="loadTabData('faltantes')">
                            <i class="fas fa-arrow-down text-danger"></i> Faltantes
                            <span class="badge bg-danger" id="tabFaltantesCount">0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tabSobrantes" onclick="loadTabData('sobrantes')">
                            <i class="fas fa-arrow-up text-warning"></i> Sobrantes
                            <span class="badge bg-warning text-dark" id="tabSobrantesCount">0</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                <div class="tab-pane fade show active" id="tabMatching">
                    <div class="entries-table">
                        <table class="table table-sm table-hover" id="tableMatching">
                            <thead class="table-dark">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Almacén</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Contado</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyMatching">
                                <tr><td colspan="6" class="text-center text-muted py-3">Haz clic para cargar datos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/inventario/admin/reports.php" class="btn btn-outline-success">
                            <i class="fas fa-chart-bar"></i> Ver reportes completos
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tabFaltantes">
                    <div class="entries-table">
                        <table class="table table-sm table-hover" id="tableFaltantes">
                            <thead class="table-dark">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Almacén</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Contado</th>
                                    <th class="text-center">Diferencia</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyFaltantes">
                                <tr><td colspan="7" class="text-center text-muted py-3">Haz clic para cargar datos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/inventario/admin/reports.php" class="btn btn-outline-danger">
                            <i class="fas fa-chart-bar"></i> Ver reportes completos
                        </a>
                    </div>
                </div>
                <div class="tab-pane fade" id="tabSobrantes">
                    <div class="entries-table">
                        <table class="table table-sm table-hover" id="tableSobrantes">
                            <thead class="table-dark">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th class="text-center">Almacén</th>
                                    <th class="text-center">Stock</th>
                                    <th class="text-center">Contado</th>
                                    <th class="text-center">Diferencia</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody id="tbodySobrantes">
                                <tr><td colspan="7" class="text-center text-muted py-3">Haz clic para cargar datos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/inventario/admin/reports.php" class="btn btn-outline-warning">
                            <i class="fas fa-chart-bar"></i> Ver reportes completos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const sessionId = <?= $activeSession['id'] ?>;
        let pollInterval;

        function startPolling() {
            fetchData();
            pollInterval = setInterval(fetchData, 3000);
        }

        async function fetchData() {
            try {
                const response = await fetch(`${BASE_URL}/inventario/api/get_realtime_data.php?session_id=${sessionId}`);
                const result = await response.json();

                if (result.success && result.data) {
                    updateUI(result.data);
                }

                document.getElementById('lastUpdate').textContent =
                    'Última actualización: ' + new Date().toLocaleTimeString();

            } catch (error) {
                console.error('Error fetching data:', error);
            }
        }

        function updateUI(data) {
            // Estadísticas principales
            document.getElementById('totalEntries').textContent = data.progress?.total_entries || 0;
            document.getElementById('matchingCount').textContent = data.discrepancies?.matching || 0;
            document.getElementById('faltantesCount').textContent = data.discrepancies?.faltantes || 0;
            document.getElementById('sobrantesCount').textContent = data.discrepancies?.sobrantes || 0;

            // Badges en tabs
            document.getElementById('tabMatchingCount').textContent = data.discrepancies?.matching || 0;
            document.getElementById('tabFaltantesCount').textContent = data.discrepancies?.faltantes || 0;
            document.getElementById('tabSobrantesCount').textContent = data.discrepancies?.sobrantes || 0;

            // Usuarios activos
            document.getElementById('activeUsers').textContent = (data.active_users || 0) + ' usuarios activos';

            // Progreso por almacén
            if (data.by_warehouse && data.by_warehouse.length > 0) {
                let html = '';
                data.by_warehouse.forEach(wh => {
                    const total = wh.total_entries || 0;
                    const matching = wh.matching || 0;
                    const faltantes = wh.faltantes || 0;
                    const sobrantes = wh.sobrantes || 0;

                    html += `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>${wh.warehouse_name}</strong>
                                <span>${total} registros</span>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" style="width: ${total > 0 ? (matching/total*100) : 0}%"
                                     title="Coinciden: ${matching}">
                                    ${matching}
                                </div>
                                <div class="progress-bar bg-danger" style="width: ${total > 0 ? (faltantes/total*100) : 0}%"
                                     title="Faltantes: ${faltantes}">
                                    ${faltantes}
                                </div>
                                <div class="progress-bar bg-warning" style="width: ${total > 0 ? (sobrantes/total*100) : 0}%"
                                     title="Sobrantes: ${sobrantes}">
                                    ${sobrantes}
                                </div>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('warehouseProgress').innerHTML = html;
            }

            // Entradas recientes
            if (data.recent_entries && data.recent_entries.length > 0) {
                let html = '';
                data.recent_entries.forEach(entry => {
                    const diff = parseFloat(entry.difference);
                    let diffClass = 'diff-zero';
                    let diffIcon = 'check-circle';

                    if (diff < 0) {
                        diffClass = 'diff-negative';
                        diffIcon = 'arrow-down';
                    } else if (diff > 0) {
                        diffClass = 'diff-positive';
                        diffIcon = 'arrow-up';
                    }

                    const time = new Date(entry.created_at).toLocaleTimeString();

                    html += `
                        <div class="list-group-item recent-entry">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${entry.product_code}</strong>
                                    <small class="text-muted d-block">${entry.product_description || ''}</small>
                                </div>
                                <div class="text-end">
                                    <span class="${diffClass}">
                                        <i class="fas fa-${diffIcon}"></i> ${diff.toFixed(2)}
                                    </span>
                                    <small class="text-muted d-block">${entry.username} - ${time}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('recentEntries').innerHTML = html;
            }
        }

        // Cache para evitar recargas innecesarias
        const tabDataLoaded = { matching: false, faltantes: false, sobrantes: false };

        // Mostrar tab y cargar datos
        function showTab(type) {
            // Scroll al card de detalles
            document.getElementById('detailsCard').scrollIntoView({ behavior: 'smooth' });

            // Activar el tab correcto
            const tabId = type.charAt(0).toUpperCase() + type.slice(1);
            const tabLink = document.querySelector(`a[href="#tab${tabId}"]`);
            if (tabLink) {
                const tab = new bootstrap.Tab(tabLink);
                tab.show();
            }

            // Cargar datos
            loadTabData(type);
        }

        // Cargar datos de un tab específico
        async function loadTabData(type) {
            const tbody = document.getElementById(`tbody${type.charAt(0).toUpperCase() + type.slice(1)}`);
            if (!tbody) return;

            // Mostrar loading
            const colSpan = type === 'matching' ? 6 : 7;
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-3">
                <div class="spinner-border spinner-border-sm"></div> Cargando...
            </td></tr>`;

            try {
                const response = await fetch(`${BASE_URL}/inventario/api/get_entries.php?session_id=${sessionId}&type=${type}&per_page=100`);
                const result = await response.json();

                if (result.success && result.data) {
                    renderTableData(type, result.data);
                    tabDataLoaded[type] = true;
                } else {
                    tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-muted py-3">
                        No hay datos disponibles
                    </td></tr>`;
                }
            } catch (error) {
                console.error('Error loading tab data:', error);
                tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-danger py-3">
                    Error al cargar datos
                </td></tr>`;
            }
        }

        // Renderizar datos en tabla
        function renderTableData(type, data) {
            const tbody = document.getElementById(`tbody${type.charAt(0).toUpperCase() + type.slice(1)}`);
            if (!tbody) return;

            if (data.length === 0) {
                const colSpan = type === 'matching' ? 6 : 7;
                tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-muted py-3">
                    No hay registros en esta categoría
                </td></tr>`;
                return;
            }

            let html = '';
            data.forEach(entry => {
                const diff = parseFloat(entry.difference);

                if (type === 'matching') {
                    html += `
                        <tr>
                            <td><strong>${entry.product_code}</strong></td>
                            <td>${entry.product_description || ''}</td>
                            <td class="text-center">${entry.warehouse_number}</td>
                            <td class="text-center">${parseFloat(entry.system_stock).toFixed(2)}</td>
                            <td class="text-center">${parseFloat(entry.counted_quantity).toFixed(2)}</td>
                            <td><small>${entry.username}</small></td>
                        </tr>
                    `;
                } else {
                    const diffClass = diff < 0 ? 'text-danger' : 'text-warning';
                    html += `
                        <tr>
                            <td><strong>${entry.product_code}</strong></td>
                            <td>${entry.product_description || ''}</td>
                            <td class="text-center">${entry.warehouse_number}</td>
                            <td class="text-center">${parseFloat(entry.system_stock).toFixed(2)}</td>
                            <td class="text-center">${parseFloat(entry.counted_quantity).toFixed(2)}</td>
                            <td class="text-center ${diffClass}"><strong>${diff.toFixed(2)}</strong></td>
                            <td><small>${entry.username}</small></td>
                        </tr>
                    `;
                }
            });

            tbody.innerHTML = html;
        }

        // Iniciar polling
        startPolling();

        // Cargar datos del tab inicial
        loadTabData('matching');

        // Limpiar al salir
        window.addEventListener('beforeunload', () => {
            clearInterval(pollInterval);
        });
    </script>
</body>
</html>
