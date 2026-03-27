<?php
/**
 * Gestión de Zonas de Almacén
 */

require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para administrar zonas';
    header('Location: ' . BASE_URL . '/inventario/admin/index.php');
    exit;
}

$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

// Obtener almacenes disponibles (de sesiones activas o todas)
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);

// Obtener lista de almacenes configurados en el sistema
$stock = new Stock();
$warehouses = $stock->getWarehouses();

// Obtener almacén seleccionado
$selectedWarehouse = (int)($_GET['warehouse'] ?? ($warehouses[0]['numero_almacen'] ?? 1));

// Obtener zonas del almacén seleccionado
$zone = new InventoryZone();
$zones = $zone->getByWarehouse($companyId, $selectedWarehouse, false);

$pageTitle = 'Gestión de Zonas';
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
        .zone-card {
            border-left: 4px solid #6c757d;
            transition: transform 0.2s;
        }
        .zone-card:hover {
            transform: translateX(5px);
        }
        .zone-inactive {
            opacity: 0.6;
        }
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 4px;
            display: inline-block;
            vertical-align: middle;
        }
        .zone-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            font-size: 0.9em;
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
                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pageTitle) ?>
            </span>
        </div>
    </nav>

    <main class="container-fluid py-4">
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

        <!-- Selector de almacén -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Seleccionar Almacén:</label>
                        <select id="warehouseSelect" class="form-select" onchange="changeWarehouse(this.value)">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['numero_almacen'] ?>" <?= $wh['numero_almacen'] == $selectedWarehouse ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['nombre']) ?> (#<?= $wh['numero_almacen'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-primary btn-lg" onclick="showAddZoneModal()">
                            <i class="fas fa-plus"></i> Nueva Zona
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de zonas -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-layer-group"></i> Zonas del Almacén <?= $selectedWarehouse ?></h5>
                <span class="badge bg-info fs-6"><?= count($zones) ?> zonas</span>
            </div>
            <div class="card-body">
                <?php if (empty($zones)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
                        <p>No hay zonas configuradas para este almacén.</p>
                        <button class="btn btn-primary" onclick="showAddZoneModal()">
                            <i class="fas fa-plus"></i> Crear primera zona
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row" id="zonesList">
                        <?php foreach ($zones as $z): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card zone-card <?= !$z['is_active'] ? 'zone-inactive' : '' ?>"
                                     style="border-left-color: <?= htmlspecialchars($z['color']) ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="zone-badge" style="background-color: <?= htmlspecialchars($z['color']) ?>">
                                                    <?= htmlspecialchars($z['name']) ?>
                                                </span>
                                                <?php if (!$z['is_active']): ?>
                                                    <span class="badge bg-secondary ms-2">Inactiva</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editZone(<?= htmlspecialchars(json_encode($z)) ?>)">
                                                            <i class="fas fa-edit text-primary"></i> Editar
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="toggleZoneActive(<?= $z['id'] ?>, <?= $z['is_active'] ? 'false' : 'true' ?>)">
                                                            <?php if ($z['is_active']): ?>
                                                                <i class="fas fa-eye-slash text-warning"></i> Desactivar
                                                            <?php else: ?>
                                                                <i class="fas fa-eye text-success"></i> Activar
                                                            <?php endif; ?>
                                                        </a>
                                                    </li>
                                                    <?php if ($z['entry_count'] == 0): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" onclick="deleteZone(<?= $z['id'] ?>, '<?= htmlspecialchars($z['name']) ?>')">
                                                                <i class="fas fa-trash"></i> Eliminar
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <?php if ($z['description']): ?>
                                            <p class="text-muted small mt-2 mb-0"><?= htmlspecialchars($z['description']) ?></p>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-boxes"></i> <?= (int)$z['entry_count'] ?> registros
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información -->
        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle"></i>
            <strong>¿Cómo funcionan las zonas?</strong>
            <ul class="mb-0 mt-2">
                <li>Las zonas permiten dividir un almacén en áreas específicas (Zona A, Zona B, Pasillo 1, etc.)</li>
                <li>Los usuarios podrán seleccionar en qué zona(s) van a realizar el inventario</li>
                <li>Al registrar un producto, se indicará en qué zona fue encontrado</li>
                <li>Los reportes mostrarán la distribución de productos por zona</li>
            </ul>
        </div>
    </main>

    <!-- Modal para agregar/editar zona -->
    <div class="modal fade" id="zoneModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="zoneModalTitle">
                        <i class="fas fa-map-marker-alt"></i> Nueva Zona
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="zoneForm">
                    <div class="modal-body">
                        <input type="hidden" id="zoneId" name="zone_id">
                        <input type="hidden" id="warehouseNumber" name="warehouse_number" value="<?= $selectedWarehouse ?>">

                        <div class="mb-3">
                            <label for="zoneName" class="form-label fw-bold">Nombre de la Zona *</label>
                            <input type="text" id="zoneName" name="name" class="form-control form-control-lg"
                                   placeholder="Ej: Zona A, Pasillo 1, Estante Norte" required maxlength="50">
                        </div>

                        <div class="mb-3">
                            <label for="zoneDescription" class="form-label">Descripción (opcional)</label>
                            <textarea id="zoneDescription" name="description" class="form-control" rows="2"
                                      placeholder="Descripción o ubicación de la zona" maxlength="255"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="zoneColor" class="form-label">Color identificador</label>
                            <div class="d-flex gap-2">
                                <input type="color" id="zoneColor" name="color" class="form-control form-control-color" value="#0d6efd">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="setColor('#0d6efd')" style="background:#0d6efd;color:white">A</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setColor('#198754')" style="background:#198754;color:white">B</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setColor('#dc3545')" style="background:#dc3545;color:white">C</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setColor('#ffc107')" style="background:#ffc107;color:black">D</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setColor('#6f42c1')" style="background:#6f42c1;color:white">E</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="setColor('#fd7e14')" style="background:#fd7e14;color:white">F</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveZone">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const warehouseNumber = <?= $selectedWarehouse ?>;
        const zoneModal = new bootstrap.Modal(document.getElementById('zoneModal'));

        function changeWarehouse(warehouse) {
            window.location.href = `${BASE_URL}/inventario/admin/zones.php?warehouse=${warehouse}`;
        }

        function setColor(color) {
            document.getElementById('zoneColor').value = color;
        }

        function showAddZoneModal() {
            document.getElementById('zoneModalTitle').innerHTML = '<i class="fas fa-map-marker-alt"></i> Nueva Zona';
            document.getElementById('zoneForm').reset();
            document.getElementById('zoneId').value = '';
            document.getElementById('warehouseNumber').value = warehouseNumber;
            document.getElementById('zoneColor').value = '#0d6efd';
            zoneModal.show();
        }

        function editZone(zone) {
            document.getElementById('zoneModalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Zona';
            document.getElementById('zoneId').value = zone.id;
            document.getElementById('zoneName').value = zone.name;
            document.getElementById('zoneDescription').value = zone.description || '';
            document.getElementById('zoneColor').value = zone.color || '#6c757d';
            zoneModal.show();
        }

        document.getElementById('zoneForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = {
                zone_id: document.getElementById('zoneId').value,
                warehouse_number: document.getElementById('warehouseNumber').value,
                name: document.getElementById('zoneName').value.trim(),
                description: document.getElementById('zoneDescription').value.trim(),
                color: document.getElementById('zoneColor').value
            };

            const action = formData.zone_id ? 'update' : 'create';

            try {
                const response = await fetch(`${BASE_URL}/inventario/api/zones.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action, ...formData })
                });

                const data = await response.json();

                if (data.success) {
                    zoneModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Error al guardar la zona');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        });

        async function toggleZoneActive(zoneId, isActive) {
            try {
                const response = await fetch(`${BASE_URL}/inventario/api/zones.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'toggle', zone_id: zoneId, is_active: isActive })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error al cambiar estado');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        }

        async function deleteZone(zoneId, zoneName) {
            if (!confirm(`¿Estás seguro de eliminar la zona "${zoneName}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }

            try {
                const response = await fetch(`${BASE_URL}/inventario/api/zones.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', zone_id: zoneId })
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Error al eliminar la zona');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        }
    </script>
</body>
</html>
