<!-- Dashboard Report -->
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-file-invoice fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['quotations']['total']) ?></div>
                    <div class="small">Cotizaciones</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['customers']['total']) ?></div>
                    <div class="small">Total Clientes</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-box fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['products']['total']) ?></div>
                    <div class="small">Productos</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-dollar-sign fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0">S/ <?= number_format($reportData['quotations']['total_amount'], 2) ?></div>
                    <div class="small">Monto Total</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quotations by Status -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Cotizaciones por Estado</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotations by Month -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Cotizaciones por Mes</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <!-- Top Customers -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top Clientes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['customers']['top_customers'])): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <p>No hay datos de clientes en el período seleccionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th class="text-center">Cotizaciones</th>
                                    <th class="text-end">Monto Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['customers']['top_customers'] as $customer): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                            <?php if ($customer['tax_id']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($customer['tax_id']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $customer['quotation_count'] ?></span>
                                        </td>
                                        <td class="text-end">
                                            <strong>S/ <?= number_format($customer['total_amount'], 2) ?></strong>
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

    <!-- Most Quoted Products -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Productos Más Cotizados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['products']['most_quoted'])): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-box fa-3x mb-3"></i>
                        <p>No hay datos de productos en el período seleccionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Veces Cotizado</th>
                                    <th class="text-end">Cantidad Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['products']['most_quoted'] as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($product['description'] ?? $product['descripcion'] ?? '') ?></strong>
                                            <?php $code = $product['code'] ?? $product['codigo'] ?? ''; ?>
                                            <?php if ($code): ?>
                                                <br><small class="text-muted">Código: <?= htmlspecialchars($code) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?= $product['times_quoted'] ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format($product['total_quantity'], 2) ?>
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

<!-- New Customers and Low Stock Alerts -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus"></i> Nuevos Clientes
                    <span class="badge bg-light text-dark"><?= $reportData['customers']['new_customers'] ?></span>
                </h5>
            </div>
            <div class="card-body">
                <p class="mb-0">
                    En el período del <?= date('d/m/Y', strtotime($startDate)) ?>
                    al <?= date('d/m/Y', strtotime($endDate)) ?>
                    se registraron <strong><?= $reportData['customers']['new_customers'] ?></strong> nuevos clientes.
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle"></i> Stock Bajo
                    <span class="badge bg-light text-dark"><?= count($reportData['products']['low_stock']) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['products']['low_stock'])): ?>
                    <p class="text-success mb-0">
                        <i class="fas fa-check"></i> No hay productos con stock bajo.
                    </p>
                <?php else: ?>
                    <p class="mb-2">Productos que requieren reabastecimiento:</p>
                    <ul class="list-unstyled small">
                        <?php foreach (array_slice($reportData['products']['low_stock'], 0, 5) as $product): ?>
                            <?php
                            $descripcion = $product['descripcion'] ?? $product['description'] ?? '';
                            $stockActual = $product['saldo'] ?? $product['stock_actual'] ?? $product['total_stock'] ?? 0;
                            $codigo = $product['codigo'] ?? $product['code'] ?? '';
                            ?>
                            <li>
                                <i class="fas fa-box text-warning"></i>
                                <strong><?= htmlspecialchars($codigo) ?></strong> -
                                <?= htmlspecialchars($descripcion) ?>
                                <span class="badge bg-warning"><?= $stockActual ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (count($reportData['products']['low_stock']) > 5): ?>
                            <li class="text-muted">... y <?= count($reportData['products']['low_stock']) - 5 ?> más</li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Status Chart
const statusData = <?= json_encode($reportData['quotations']['by_status']) ?>;
const statusLabels = Object.keys(statusData);
const statusValues = Object.values(statusData);

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels.map(status => {
            const names = {
                'Draft': 'Borrador',
                'Sent': 'Enviada',
                'Accepted': 'Aceptada',
                'Rejected': 'Rechazada',
                'Invoiced': 'Facturada'
            };
            return names[status] || status;
        }),
        datasets: [{
            data: statusValues,
            backgroundColor: [
                '#6c757d', // Draft - gray
                '#17a2b8', // Sent - info
                '#28a745', // Accepted - success
                '#dc3545', // Rejected - danger
                '#007bff'  // Invoiced - primary
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

// Month Chart
const monthData = <?= json_encode($reportData['quotations']['by_month']) ?>;
const monthLabels = Object.keys(monthData);
const monthValues = Object.values(monthData);

const monthCtx = document.getElementById('monthChart').getContext('2d');
new Chart(monthCtx, {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Cotizaciones',
            data: monthValues,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>