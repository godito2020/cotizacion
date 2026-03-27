<?php
/**
 * Mis Registros de Inventario - Mobile First con Footer fijo
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
$username = $auth->getUser()['username'];

// Verificar sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);

if (!$activeSession) {
    header('Location: ' . BASE_URL . '/inventario/select_warehouse.php');
    exit;
}

$warehouseNumber = $_SESSION['inventory_warehouse'] ?? 0;
$warehouseName = $_SESSION['inventory_warehouse_name'] ?? 'Almacén';

// Obtener entradas del usuario (todas para búsqueda local)
$entry = new InventoryEntry();
$entries = $entry->getByUser($activeSession['id'], $userId, 1, 500);
$totalEntries = count($entries);

$pageTitle = 'Mis Registros';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --safe-area-top: env(safe-area-inset-top, 0px);
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            padding-top: var(--safe-area-top);
        }

        /* Título compacto */
        .page-title {
            background: white;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
        }

        .page-title h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }

        .page-title .badge {
            font-size: 13px;
        }

        /* Buscador */
        .search-bar {
            background: white;
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
        }

        .search-bar input {
            border-radius: 20px;
            padding: 10px 16px;
            font-size: 15px;
            border: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .search-bar input:focus {
            background: white;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }

        /* Contenido scrolleable */
        .content-area {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: calc(70px + var(--safe-area-bottom));
        }

        /* Cards de registros */
        .entry-card {
            background: white;
            margin: 8px 12px;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .entry-card .product-code {
            font-weight: 700;
            font-size: 15px;
            color: #333;
        }

        .entry-card .product-desc {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .entry-card .entry-data {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }

        .entry-card .data-item {
            text-align: center;
        }

        .entry-card .data-label {
            font-size: 10px;
            color: #999;
            text-transform: uppercase;
        }

        .entry-card .data-value {
            font-size: 16px;
            font-weight: 600;
        }

        .entry-card .diff-positive { color: #ffc107; }
        .entry-card .diff-negative { color: #dc3545; }
        .entry-card .diff-zero { color: #198754; }

        .entry-card .edit-btn {
            background: #f8f9fa;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: #0d6efd;
            font-size: 14px;
        }

        .entry-card .entry-time {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
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
            background: none;
            border: none;
            color: #6c757d;
            font-size: 11px;
            padding: 8px 16px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-btn i {
            font-size: 22px;
            margin-bottom: 4px;
        }

        .footer-btn.active {
            color: #0d6efd;
        }

        .footer-btn:active {
            transform: scale(0.95);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Zone badge */
        .zone-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            color: white;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <!-- Título -->
    <div class="page-title">
        <h1><i class="fas fa-list-alt"></i> <?= $pageTitle ?></h1>
        <span class="badge bg-primary" id="recordCount"><?= $totalEntries ?> registros</span>
    </div>

    <!-- Buscador -->
    <div class="search-bar">
        <input type="text" id="searchInput" class="form-control"
               placeholder="Buscar por código o descripción..." autocomplete="off">
    </div>

    <!-- Contenido -->
    <div class="content-area">
        <?php if (empty($entries)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No tienes registros aún</p>
                <small>Comienza a contar desde el inicio</small>
            </div>
        <?php else: ?>
            <div id="entriesList">
                <?php foreach ($entries as $e): ?>
                    <?php
                    $diff = (float)$e['difference'];
                    $diffClass = $diff == 0 ? 'diff-zero' : ($diff < 0 ? 'diff-negative' : 'diff-positive');
                    ?>
                    <div class="entry-card" data-code="<?= htmlspecialchars(strtolower($e['product_code'])) ?>"
                         data-desc="<?= htmlspecialchars(strtolower($e['product_description'] ?? '')) ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex:1; min-width:0;">
                                <div class="product-code">
                                    <?= htmlspecialchars($e['product_code']) ?>
                                    <?php if (!empty($e['zone_name'])): ?>
                                        <span class="zone-badge" style="background:<?= htmlspecialchars($e['zone_color'] ?? '#6c757d') ?>">
                                            <?= htmlspecialchars($e['zone_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-desc"><?= htmlspecialchars($e['product_description'] ?? '-') ?></div>
                            </div>
                            <button class="edit-btn" onclick='editEntry(<?= json_encode($e) ?>)'>
                                <i class="fas fa-pen"></i>
                            </button>
                        </div>
                        <div class="entry-data">
                            <div class="data-item">
                                <div class="data-label">Stock</div>
                                <div class="data-value"><?= number_format($e['system_stock'], 2) ?></div>
                            </div>
                            <div class="data-item">
                                <div class="data-label">Cantidad</div>
                                <div class="data-value"><?= number_format($e['counted_quantity'], 2) ?></div>
                            </div>
                            <div class="data-item">
                                <div class="data-label">Diferencia</div>
                                <div class="data-value <?= $diffClass ?>"><?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?></div>
                            </div>
                        </div>
                        <div class="entry-time">
                            <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer fijo -->
    <footer class="app-footer">
        <a href="<?= BASE_URL ?>/inventario/dashboard.php" class="footer-btn">
            <i class="fas fa-search"></i>
            <span>Buscar</span>
        </a>
        <a href="<?= BASE_URL ?>/inventario/my_entries.php" class="footer-btn active">
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

    <!-- Modal para editar -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Registro</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" id="editEntryId" name="entry_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Producto:</label>
                            <div id="editProductInfo" class="p-3 bg-light rounded"></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label small text-muted">Stock Sistema</label>
                                <input type="text" id="editSystemStock" class="form-control" readonly>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Nueva Cantidad *</label>
                                <input type="number" id="editCountedQuantity" name="counted_quantity"
                                       class="form-control form-control-lg" step="0.01" min="0" required
                                       inputmode="decimal" style="font-size:20px;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editComments" class="form-label">Comentarios:</label>
                            <textarea id="editComments" name="comments" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
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
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        const totalEntries = <?= $totalEntries ?>;

        function editEntry(entry) {
            document.getElementById('editEntryId').value = entry.id;
            document.getElementById('editProductInfo').innerHTML = `
                <strong>${entry.product_code}</strong><br>
                <small class="text-muted">${entry.product_description || '-'}</small>
            `;
            document.getElementById('editSystemStock').value = entry.system_stock;
            document.getElementById('editCountedQuantity').value = entry.counted_quantity;
            document.getElementById('editComments').value = entry.comments || '';
            editModal.show();
            setTimeout(() => document.getElementById('editCountedQuantity').select(), 300);
        }

        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = {
                entry_id: document.getElementById('editEntryId').value,
                counted_quantity: parseFloat(document.getElementById('editCountedQuantity').value),
                comments: document.getElementById('editComments').value
            };

            try {
                const response = await fetch(`${BASE_URL}/inventario/api/update_entry.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    editModal.hide();
                    location.reload();
                } else {
                    alert(data.message || 'Error al actualizar');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión');
            }
        });

        // Buscador en tiempo real
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.entry-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const code = card.dataset.code || '';
                const desc = card.dataset.desc || '';

                if (code.includes(searchTerm) || desc.includes(searchTerm)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const countEl = document.getElementById('recordCount');
            if (searchTerm) {
                countEl.textContent = visibleCount + ' de ' + totalEntries + ' registros';
            } else {
                countEl.textContent = totalEntries + ' registros';
            }
        });
    </script>
</body>
</html>
