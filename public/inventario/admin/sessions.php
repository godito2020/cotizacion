<?php
/**
 * Historial de Sesiones de Inventario
 */

require_once __DIR__ . '/../../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canManageInventorySessions($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para ver el historial de inventarios';
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
    exit;
}

$companyId = $auth->getCompanyId();

// Paginación
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Obtener sesiones
$session = new InventorySession();
$sessions = $session->getSessions($companyId, null, $page, $perPage);
$totalSessions = $session->countSessions($companyId);
$totalPages = ceil($totalSessions / $perPage);

$pageTitle = 'Historial de Sesiones';
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
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-dark navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/inventario/admin/index.php">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-history"></i> <?= $pageTitle ?>
            </span>
        </div>
    </nav>

    <main class="container-fluid py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sesiones de Inventario</h5>
                <span class="badge bg-secondary"><?= $totalSessions ?> sesiones</span>
            </div>
            <div class="card-body">
                <?php if (empty($sessions)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <p>No hay sesiones de inventario registradas</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Cierre</th>
                                    <th class="text-center">Registros</th>
                                    <th class="text-center">Usuarios</th>
                                    <th>Creado por</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $s): ?>
                                    <?php
                                    $statusClass = match($s['status']) {
                                        'Open' => 'success',
                                        'Closed' => 'secondary',
                                        'Cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusText = match($s['status']) {
                                        'Open' => 'Abierta',
                                        'Closed' => 'Cerrada',
                                        'Cancelled' => 'Cancelada',
                                        default => $s['status']
                                    };
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                            <?php if (!empty($s['reopened_at'])): ?>
                                                <span class="badge bg-warning text-dark ms-1"
                                                      data-bs-toggle="tooltip"
                                                      title="Reactivada el <?= date('d/m/Y H:i', strtotime($s['reopened_at'])) ?> por <?= htmlspecialchars($s['reopened_by_username'] ?? 'N/A') ?>">
                                                    <i class="fas fa-undo"></i>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($s['opened_at'])) ?></td>
                                        <td>
                                            <?= $s['closed_at'] ? date('d/m/Y H:i', strtotime($s['closed_at'])) : '-' ?>
                                        </td>
                                        <td class="text-center"><?= (int)($s['total_entries'] ?? 0) ?></td>
                                        <td class="text-center"><?= (int)($s['total_users'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars($s['created_by_username'] ?? '') ?></td>
                                        <td class="text-center">
                                            <a href="<?= BASE_URL ?>/inventario/admin/reports.php?session_id=<?= $s['id'] ?>"
                                               class="btn btn-sm btn-outline-info" title="Ver reportes">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/inventario/api/export_excel.php?session_id=<?= $s['id'] ?>&type=complete"
                                               class="btn btn-sm btn-outline-success" title="Exportar">
                                                <i class="fas fa-file-excel"></i>
                                            </a>
                                            <?php if ($s['status'] === 'Closed'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                        title="Reactivar sesión"
                                                        onclick="openReopenModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>">Anterior</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>">Siguiente</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal para Reactivar Sesión -->
    <div class="modal fade" id="reopenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-undo"></i> Reactivar Sesión
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="reopenForm">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Atención:</strong> Está a punto de reactivar la sesión:
                            <br><strong id="sessionNameDisplay"></strong>
                        </div>

                        <input type="hidden" id="reopenSessionId" name="session_id">

                        <div class="mb-3">
                            <label for="reopenReason" class="form-label">
                                <i class="fas fa-comment"></i> Motivo de la reactivación <span class="text-danger">*</span>
                            </label>
                            <textarea id="reopenReason" name="reason" class="form-control" rows="3"
                                      placeholder="Explique por qué necesita reactivar esta sesión (mínimo 10 caracteres)"
                                      required minlength="10"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="reopenPassword" class="form-label">
                                <i class="fas fa-lock"></i> Su contraseña <span class="text-danger">*</span>
                            </label>
                            <input type="password" id="reopenPassword" name="password" class="form-control"
                                   placeholder="Ingrese su contraseña para confirmar" required>
                            <div class="form-text">Se requiere su contraseña para autorizar esta acción.</div>
                        </div>

                        <div id="reopenError" class="alert alert-danger d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning" id="reopenSubmitBtn">
                            <i class="fas fa-undo"></i> Reactivar Sesión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        let reopenModal;

        document.addEventListener('DOMContentLoaded', function() {
            reopenModal = new bootstrap.Modal(document.getElementById('reopenModal'));

            // Inicializar tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
        });

        function openReopenModal(sessionId, sessionName) {
            document.getElementById('reopenSessionId').value = sessionId;
            document.getElementById('sessionNameDisplay').textContent = sessionName;
            document.getElementById('reopenReason').value = '';
            document.getElementById('reopenPassword').value = '';
            document.getElementById('reopenError').classList.add('d-none');
            reopenModal.show();
        }

        document.getElementById('reopenForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const sessionId = document.getElementById('reopenSessionId').value;
            const reason = document.getElementById('reopenReason').value.trim();
            const password = document.getElementById('reopenPassword').value;
            const errorDiv = document.getElementById('reopenError');
            const submitBtn = document.getElementById('reopenSubmitBtn');

            // Validación básica
            if (reason.length < 10) {
                errorDiv.textContent = 'El motivo debe tener al menos 10 caracteres';
                errorDiv.classList.remove('d-none');
                return;
            }

            if (!password) {
                errorDiv.textContent = 'Ingrese su contraseña';
                errorDiv.classList.remove('d-none');
                return;
            }

            // Deshabilitar botón mientras procesa
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
            errorDiv.classList.add('d-none');

            try {
                const response = await fetch(`${BASE_URL}/inventario/api/reopen_session.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: parseInt(sessionId),
                        reason: reason,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    reopenModal.hide();
                    // Recargar la página para ver los cambios
                    location.reload();
                } else {
                    errorDiv.textContent = data.message || 'Error al reactivar la sesión';
                    errorDiv.classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error:', error);
                errorDiv.textContent = 'Error de conexión. Intente de nuevo.';
                errorDiv.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-undo"></i> Reactivar Sesión';
            }
        });
    </script>
</body>
</html>
