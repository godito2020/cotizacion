<!-- Products Report -->
<div class="row">
    <!-- Summary Cards -->
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-box fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['total']) ?></div>
                    <div class="small">Total Productos</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['with_stock']) ?></div>
                    <div class="small">Con Stock</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card warning">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['low_stock']) ?></div>
                    <div class="small">Stock Bajo</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Most Quoted Products -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top 20 Productos Más Cotizados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['most_quoted'])): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-box fa-3x mb-3"></i>
                        <p>No hay productos cotizados en el período seleccionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Producto</th>
                                    <th>Marca</th>
                                    <th class="text-center">Veces Cotizado</th>
                                    <th class="text-center">Cantidad Total</th>
                                    <th class="text-end">Stock Actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['most_quoted'] as $index => $product): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?>">
                                                <?= $index + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($product['description']) ?></strong>
                                                <?php if ($product['code']): ?>
                                                    <br><small class="text-muted">Código: <?= htmlspecialchars($product['code']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($product['brand']): ?>
                                                <span class="badge bg-light text-dark"><?= htmlspecialchars($product['brand']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $product['times_quoted'] ?? 0 ?></span>
                                        </td>
                                        <td class="text-center">
                                            <strong><?= number_format($product['total_quantity'], 2) ?></strong>
                                        </td>
                                        <td class="text-end">
                                            <?php
                                            $stockClass = 'text-success';
                                            if ($product['total_stock'] <= 10) $stockClass = 'text-danger';
                                            elseif ($product['total_stock'] <= 50) $stockClass = 'text-warning';
                                            ?>
                                            <span class="<?= $stockClass ?>"><?= number_format($product['total_stock'], 2) ?></span>
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

    <!-- Products by Brand -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Productos por Marca</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['by_brand'])): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-tags fa-2x mb-2"></i>
                        <p>No hay datos de marcas</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Convert array of arrays to associative array
                    $sortedBrands = [];
                    foreach ($reportData['by_brand'] as $brandData) {
                        $sortedBrands[$brandData['brand']] = $brandData['count'];
                    }
                    arsort($sortedBrands);
                    $topBrands = array_slice($sortedBrands, 0, 8, true);
                    $maxCount = !empty($sortedBrands) ? max($sortedBrands) : 1;
                    ?>
                    <?php foreach ($topBrands as $brand => $count): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?= htmlspecialchars($brand ?: 'Sin marca') ?></strong>
                            </div>
                            <span class="badge bg-info"><?= $count ?></span>
                        </div>
                        <div class="progress mb-3" style="height: 4px;">
                            <div class="progress-bar bg-info"
                                 style="width: <?= ($count / $maxCount) * 100 ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($sortedBrands) > 8): ?>
                        <small class="text-muted">... y <?= count($sortedBrands) - 8 ?> marcas más</small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock Alert -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    Productos con Stock Bajo
                    <span class="badge bg-light text-dark"><?= count($reportData['low_stock_products']) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['low_stock_products'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>¡Excelente!</strong> No hay productos con stock bajo.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-info-circle"></i>
                        Se encontraron <strong><?= count($reportData['low_stock_products']) ?></strong> productos que requieren reabastecimiento.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Marca</th>
                                    <th>Código</th>
                                    <th class="text-end">Stock Total</th>
                                    <th class="text-center">Estado</th>
                                    <th>Stock por Almacén</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['low_stock_products'] as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($product['description']) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($product['brand']): ?>
                                                <span class="badge bg-light text-dark"><?= htmlspecialchars($product['brand']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($product['code']): ?>
                                                <code><?= htmlspecialchars($product['code']) ?></code>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php
                                            $stockLevel = 'danger';
                                            if ($product['total_stock'] > 5) $stockLevel = 'warning';
                                            if ($product['total_stock'] == 0) $stockLevel = 'dark';
                                            ?>
                                            <span class="badge bg-<?= $stockLevel ?> fs-6">
                                                <?= number_format($product['total_stock'], 2) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($product['total_stock'] == 0): ?>
                                                <span class="badge bg-dark">Sin Stock</span>
                                            <?php elseif ($product['total_stock'] <= 5): ?>
                                                <span class="badge bg-danger">Crítico</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Bajo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                $warehouseStocks = [];
                                                if (isset($product['warehouse_stocks'])) {
                                                    foreach ($product['warehouse_stocks'] as $warehouse => $stock) {
                                                        if ($stock > 0) {
                                                            $warehouseStocks[] = $warehouse . ': ' . number_format($stock, 2);
                                                        }
                                                    }
                                                }
                                                echo implode(', ', array_slice($warehouseStocks, 0, 3));
                                                if (count($warehouseStocks) > 3) echo '...';
                                                ?>
                                            </small>
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
</div>

<!-- Stock Analysis -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Distribución de Stock</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="stockDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Estadísticas de Inventario</h5>
            </div>
            <div class="card-body">
                <?php
                $stockPercentage = $reportData['summary']['total'] > 0 ?
                    ($reportData['summary']['with_stock'] / $reportData['summary']['total']) * 100 : 0;
                $lowStockPercentage = $reportData['summary']['total'] > 0 ?
                    ($reportData['summary']['low_stock'] / $reportData['summary']['total']) * 100 : 0;
                ?>

                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Productos con Stock</small>
                        <small><?= number_format($stockPercentage, 1) ?>%</small>
                    </div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: <?= $stockPercentage ?>%">
                            <?= $reportData['summary']['with_stock'] ?>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <small>Productos con Stock Bajo</small>
                        <small><?= number_format($lowStockPercentage, 1) ?>%</small>
                    </div>
                    <div class="progress mb-2" style="height: 20px;">
                        <div class="progress-bar bg-warning" style="width: <?= $lowStockPercentage ?>%">
                            <?= $reportData['summary']['low_stock'] ?>
                        </div>
                    </div>
                </div>

                <div class="row text-center">
                    <div class="col-4">
                        <div class="h4 text-success"><?= $reportData['summary']['with_stock'] ?></div>
                        <small>Con Stock</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-warning"><?= $reportData['summary']['low_stock'] ?></div>
                        <small>Stock Bajo</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-danger"><?= $reportData['summary']['total'] - $reportData['summary']['with_stock'] ?></div>
                        <small>Sin Stock</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Stock Distribution Chart
const stockCtx = document.getElementById('stockDistributionChart').getContext('2d');
new Chart(stockCtx, {
    type: 'doughnut',
    data: {
        labels: ['Con Stock', 'Stock Bajo', 'Sin Stock'],
        datasets: [{
            data: [
                <?= $reportData['summary']['with_stock'] - $reportData['summary']['low_stock'] ?>,
                <?= $reportData['summary']['low_stock'] ?>,
                <?= $reportData['summary']['total'] - $reportData['summary']['with_stock'] ?>
            ],
            backgroundColor: [
                '#28a745', // Success - with stock
                '#ffc107', // Warning - low stock
                '#dc3545'  // Danger - no stock
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>