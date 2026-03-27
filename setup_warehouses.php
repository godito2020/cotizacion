<?php
/**
 * Script para configurar almacenes desde COBOL
 *
 * Este script:
 * 1. Detecta los números de almacén que existen en vista_almacenes_anual (COBOL)
 * 2. Permite asignarles nombres descriptivos
 * 3. Los guarda en la tabla desc_almacen (BD local)
 */

require_once __DIR__ . '/includes/init.php';

$auth = new Auth();

// Solo permitir acceso a administradores o CLI
$isCLI = php_sapi_name() === 'cli';
if (!$isCLI && !$auth->isLoggedIn()) {
    die('Debe iniciar sesión');
}

$stockRepo = new Stock();
$message = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['warehouses'])) {
    foreach ($_POST['warehouses'] as $numero => $nombre) {
        $nombre = trim($nombre);
        if (!empty($nombre)) {
            $stockRepo->saveWarehouse((int)$numero, $nombre);
        }
    }
    $message = 'Almacenes guardados correctamente';
}

// Obtener almacenes de COBOL
$cobolWarehouses = $stockRepo->getCobolWarehouses();

// Obtener almacenes ya configurados
$configuredWarehouses = $stockRepo->getWarehouses();
$configuredMap = [];
foreach ($configuredWarehouses as $w) {
    $configuredMap[$w['numero_almacen']] = $w['nombre'];
}

// Mes actual
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
    4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
    10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$mesActual = $meses[(int)date('n')];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Almacenes - COBOL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-warehouse me-2"></i>
                            Configurar Almacenes desde COBOL
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Mes actual:</strong> <?= $mesActual ?> <?= date('Y') ?><br>
                            <small>El stock se lee de la columna del mes actual en <code>vista_almacenes_anual</code></small>
                        </div>

                        <?php if (empty($cobolWarehouses)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No se encontraron almacenes con stock en COBOL para el mes actual.
                                <br><small>Verifica que la vista <code>vista_almacenes_anual</code> tenga datos y la columna <code><?= strtolower($mesActual) ?></code> tenga valores > 0</small>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-4">
                                Se encontraron <strong><?= count($cobolWarehouses) ?></strong> almacenes en COBOL.
                                Asigna un nombre descriptivo a cada uno:
                            </p>

                            <form method="POST">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 120px;">Número</th>
                                            <th>Nombre del Almacén</th>
                                            <th style="width: 100px;">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cobolWarehouses as $w): ?>
                                            <?php
                                            $num = $w['numero_almacen'];
                                            $nombreActual = $configuredMap[$num] ?? '';
                                            $configurado = !empty($nombreActual);
                                            ?>
                                            <tr>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary fs-6"><?= $num ?></span>
                                                </td>
                                                <td>
                                                    <input type="text"
                                                           class="form-control"
                                                           name="warehouses[<?= $num ?>]"
                                                           value="<?= htmlspecialchars($nombreActual) ?>"
                                                           placeholder="Ej: Metro, Productores, Central..."
                                                           required>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($configurado): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> OK
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="fas fa-exclamation"></i> Pendiente
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="d-flex justify-content-between">
                                    <a href="<?= BASE_URL ?>/admin/warehouses.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-1"></i> Volver
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Guardar Nombres
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Información de Conexión</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><strong>BD COBOL:</strong></td>
                                <td><code><?= COBOL_DB_NAME ?></code> en <?= COBOL_DB_HOST ?></td>
                            </tr>
                            <tr>
                                <td><strong>Vista productos:</strong></td>
                                <td><code>vista_productos</code></td>
                            </tr>
                            <tr>
                                <td><strong>Vista stock:</strong></td>
                                <td><code>vista_almacenes_anual</code></td>
                            </tr>
                            <tr>
                                <td><strong>Columna mes:</strong></td>
                                <td><code><?= strtolower($mesActual) ?></code></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
