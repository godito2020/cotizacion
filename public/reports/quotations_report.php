<!-- Quotations Report -->
<div class="row">
    <!-- Summary Cards -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-file-invoice fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['total']) ?></div>
                    <div class="small">Total Cotizaciones</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-dollar-sign fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0">S/ <?= number_format($reportData['summary']['total_amount'], 2) ?></div>
                    <div class="small">Monto Total</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-calculator fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0">S/ <?= number_format($reportData['summary']['average_amount'], 2) ?></div>
                    <div class="small">Promedio</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-percentage fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['conversion_rate'], 1) ?>%</div>
                    <div class="small">Conversión</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quotations by Status Detail -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Distribución por Estado</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    $statusNames = [
                        'Draft' => 'Borrador',
                        'Sent' => 'Enviada',
                        'Accepted' => 'Aceptada',
                        'Rejected' => 'Rechazada',
                        'Invoiced' => 'Facturada'
                    ];
                    $statusColors = [
                        'Draft' => 'secondary',
                        'Sent' => 'info',
                        'Accepted' => 'success',
                        'Rejected' => 'danger',
                        'Invoiced' => 'primary'
                    ];
                    ?>
                    <?php foreach ($reportData['summary']['by_status'] as $statusData): ?>
                        <?php
                        $status = $statusData['status'];
                        $count = $statusData['count'];
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <span class="badge bg-<?= $statusColors[$status] ?? 'secondary' ?> rounded-pill" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                        <?= $count ?>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0"><?= $statusNames[$status] ?? $status ?></h6>
                                    <small class="text-muted">
                                        <?= $reportData['summary']['total'] > 0 ? number_format(($count / $reportData['summary']['total']) * 100, 1) : 0 ?>%
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotations by User -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Por Usuario</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['by_user'])): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-user fa-2x mb-2"></i>
                        <p>No hay datos disponibles</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reportData['by_user'] as $userStat): ?>
                        <?php
                        $userName = trim(($userStat['first_name'] ?? '') . ' ' . ($userStat['last_name'] ?? ''));
                        if (empty($userName)) {
                            $userName = $userStat['username'] ?? 'Usuario desconocido';
                        }
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?= htmlspecialchars($userName) ?></strong>
                                <br><small class="text-muted">S/ <?= number_format($userStat['total_amount'] ?? 0, 2) ?></small>
                            </div>
                            <span class="badge bg-primary"><?= $userStat['count'] ?? 0 ?></span>
                        </div>
                        <hr class="my-2">
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tendencia Mensual</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Quotations -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Cotizaciones Recientes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['recent_quotations'])): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-file-invoice fa-3x mb-3"></i>
                        <p>No hay cotizaciones en el período seleccionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th class="text-end">Monto</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['recent_quotations'] as $quotation): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= BASE_URL ?>/quotations/view.php?id=<?= $quotation['id'] ?>" class="text-decoration-none">
                                                <strong><?= htmlspecialchars($quotation['quotation_number']) ?></strong>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($quotation['customer_name']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($quotation['quotation_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusColors[$quotation['status']] ?? 'secondary' ?>">
                                                <?= $statusNames[$quotation['status']] ?? $quotation['status'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong>S/ <?= number_format($quotation['total'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $userName = trim(($quotation['first_name'] ?? '') . ' ' . ($quotation['last_name'] ?? ''));
                                            if (empty($userName)) {
                                                $userName = 'N/A';
                                            }
                                            ?>
                                            <small><?= htmlspecialchars($userName) ?></small>
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

<script>
// Trend Chart
const trendData = <?= json_encode($reportData['by_month']) ?>;
const trendLabels = Object.keys(trendData);
const trendValues = Object.values(trendData);

const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Cotizaciones',
            data: trendValues,
            backgroundColor: 'rgba(0, 123, 255, 0.8)',
            borderColor: '#007bff',
            borderWidth: 1
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