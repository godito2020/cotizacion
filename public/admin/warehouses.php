<?php
/**
 * Gestión de Almacenes - Admin
 * Administra la tabla desc_almacen que mapea números de almacén a nombres
 */
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) {
    $_SESSION['error_message'] = "No tiene permisos para gestionar almacenes.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$loggedInUser = $auth->getUser();
$stockRepo = new Stock();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $numero = (int)($_POST['numero_almacen'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        if ($numero > 0 && !empty($nombre)) {
            if ($stockRepo->saveWarehouse($numero, $nombre, $direccion, $telefono)) {
                $_SESSION['message'] = "Almacén guardado correctamente.";
            } else {
                $_SESSION['error_message'] = "Error al guardar almacén.";
            }
        } else {
            $_SESSION['error_message'] = "Número y nombre son requeridos.";
        }
    } elseif ($action === 'delete') {
        $numero = (int)($_POST['numero_almacen'] ?? 0);
        if ($numero > 0) {
            if ($stockRepo->deactivateWarehouse($numero)) {
                $_SESSION['message'] = "Almacén desactivado.";
            } else {
                $_SESSION['error_message'] = "Error al desactivar almacén.";
            }
        }
    }

    header('Location: warehouses.php');
    exit;
}

// Obtener almacenes
$warehouses = $stockRepo->getWarehouses();
$stockSummary = $stockRepo->getWarehouseStockSummary();

// Crear mapa de resumen por número de almacén
$summaryMap = [];
foreach ($stockSummary as $s) {
    $summaryMap[$s['numero_almacen']] = $s;
}

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Almacenes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/admin/index.php">
                <i class="fas fa-cogs me-2"></i>Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/index.php">
                            <i class="fas fa-home me-1"></i>Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/admin/products.php">
                            <i class="fas fa-boxes me-1"></i>Productos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= BASE_URL ?>/admin/warehouses.php">
                            <i class="fas fa-warehouse me-1"></i>Almacenes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/dashboard_simple.php">
                            <i class="fas fa-chart-line me-1"></i>Dashboard
                        </a>
                    </li>
                </ul>
                <div class="navbar-text text-white">
                    <i class="fas fa-user me-1"></i>
                    <?= htmlspecialchars($loggedInUser['username'] ?? 'Usuario') ?>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-light btn-sm ms-2">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-warehouse me-2"></i>Gestión de Almacenes</h2>
                <p class="text-muted">
                    Configura los nombres de los almacenes que corresponden a los números en el sistema COBOL.
                </p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulario -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Agregar/Editar Almacén</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="warehouseForm">
                            <input type="hidden" name="action" value="save">

                            <div class="mb-3">
                                <label class="form-label">Número de Almacén *</label>
                                <input type="number" class="form-control" name="numero_almacen" id="numero_almacen"
                                       required min="1" placeholder="Ej: 1, 2, 3...">
                                <div class="form-text">Número asignado en sistema COBOL</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre" id="nombre"
                                       required placeholder="Ej: Metro, Productores...">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <input type="text" class="form-control" name="direccion" id="direccion"
                                       placeholder="Dirección del almacén">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" id="telefono"
                                       placeholder="Teléfono de contacto">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de almacenes -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Almacenes Configurados</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">Número</th>
                                    <th>Nombre</th>
                                    <th>Dirección</th>
                                    <th style="width: 120px;">Productos</th>
                                    <th style="width: 120px;">Stock Total</th>
                                    <th style="width: 100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($warehouses)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-warehouse fa-3x mb-3 d-block"></i>
                                            No hay almacenes configurados
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($warehouses as $w): ?>
                                        <?php $summary = $summaryMap[$w['numero_almacen']] ?? null; ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary"><?= $w['numero_almacen'] ?></span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($w['nombre']) ?></strong>
                                                <?php if (!empty($w['telefono'])): ?>
                                                    <br><small class="text-muted">
                                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($w['telefono']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($w['direccion'])): ?>
                                                    <small><?= htmlspecialchars($w['direccion']) ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($summary): ?>
                                                    <span class="badge bg-info">
                                                        <?= number_format($summary['total_productos']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($summary): ?>
                                                    <?= number_format($summary['stock_total'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">0.00</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        onclick="editWarehouse(<?= htmlspecialchars(json_encode($w)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline"
                                                      onsubmit="return confirm('¿Desactivar este almacén?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="numero_almacen" value="<?= $w['numero_almacen'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editWarehouse(data) {
            document.getElementById('numero_almacen').value = data.numero_almacen;
            document.getElementById('nombre').value = data.nombre;
            document.getElementById('direccion').value = data.direccion || '';
            document.getElementById('telefono').value = data.telefono || '';
            document.getElementById('numero_almacen').focus();
        }
    </script>
</body>
</html>
