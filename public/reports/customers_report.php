<!-- Customers Report -->
<div class="row">
    <!-- Summary Cards -->
    <div class="col-md-4">
        <div class="stat-card">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['total']) ?></div>
                    <div class="small">Total Clientes</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card success">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user-plus fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['new_customers']) ?></div>
                    <div class="small">Nuevos Clientes</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card info">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user-check fa-2x"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <div class="h4 mb-0"><?= number_format($reportData['summary']['active_customers']) ?></div>
                    <div class="small">Clientes Activos</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Top Customers -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top 20 Clientes por Volumen</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['top_customers'])): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-3x mb-3"></i>
                        <p>No hay datos de clientes en el período seleccionado</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th class="text-center">Cotizaciones</th>
                                    <th class="text-end">Monto Total</th>
                                    <th class="text-end">Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['top_customers'] as $index => $customer): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?>">
                                                <?= $index + 1 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                                <?php if ($customer['contact_person']): ?>
                                                    <br><small class="text-muted">Contacto: <?= htmlspecialchars($customer['contact_person']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($customer['tax_id']): ?>
                                                <span class="badge bg-light text-dark">
                                                    <?= strlen($customer['tax_id']) == 8 ? 'DNI' : 'RUC' ?>: <?= htmlspecialchars($customer['tax_id']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $customer['quotation_count'] ?></span>
                                        </td>
                                        <td class="text-end">
                                            <strong>S/ <?= number_format($customer['total_amount'], 2) ?></strong>
                                        </td>
                                        <td class="text-end">
                                            S/ <?= number_format($customer['average_amount'], 2) ?>
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

    <!-- Customer Types Distribution -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tipos de Cliente</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['by_type'])): ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-chart-pie fa-2x mb-2"></i>
                        <p>No hay datos disponibles</p>
                    </div>
                <?php else: ?>
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="customerTypeChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Customer Activity Analysis -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Análisis de Actividad</h5>
            </div>
            <div class="card-body">
                <?php
                $totalCustomers = $reportData['summary']['total'];
                $activeCustomers = $reportData['summary']['active_customers'];
                $inactiveCustomers = $totalCustomers - $activeCustomers;
                $activityRate = $totalCustomers > 0 ? ($activeCustomers / $totalCustomers) * 100 : 0;
                ?>

                <div class="row text-center">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="h2 text-success"><?= $activeCustomers ?></div>
                            <small>Clientes Activos</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="h2 text-muted"><?= $inactiveCustomers ?></div>
                            <small>Clientes Inactivos</small>
                        </div>
                    </div>
                </div>

                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar bg-success" role="progressbar"
                         style="width: <?= $activityRate ?>%"
                         aria-valuenow="<?= $activityRate ?>" aria-valuemin="0" aria-valuemax="100">
                        <?= number_format($activityRate, 1) ?>%
                    </div>
                </div>

                <p class="mb-0 small text-muted">
                    <?= number_format($activityRate, 1) ?>% de tus clientes han realizado cotizaciones en el período seleccionado.
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Crecimiento de Clientes</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h1 text-primary">+<?= $reportData['summary']['new_customers'] ?></div>
                    <p class="mb-0">Nuevos clientes en el período</p>
                    <small class="text-muted">
                        Del <?= date('d/m/Y', strtotime($startDate)) ?> al <?= date('d/m/Y', strtotime($endDate)) ?>
                    </small>
                </div>

                <?php
                $growthRate = 0;
                $previousPeriodCustomers = $totalCustomers - $reportData['summary']['new_customers'];
                if ($previousPeriodCustomers > 0) {
                    $growthRate = ($reportData['summary']['new_customers'] / $previousPeriodCustomers) * 100;
                }
                ?>

                <?php if ($growthRate > 0): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-arrow-up"></i>
                        Crecimiento del <?= number_format($growthRate, 1) ?>% en el período
                    </div>
                <?php elseif ($reportData['summary']['new_customers'] == 0): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-minus"></i>
                        Sin nuevos clientes en el período
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Customers -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Clientes Registrados Recientemente</h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['recent_customers'])): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <p>No hay clientes registrados recientemente</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Tipo</th>
                                    <th>Documento</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Fecha Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['recent_customers'] as $customer): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>
                                                    <?php if ($customer['company_name']): ?>
                                                        <?= htmlspecialchars($customer['company_name']) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                                    <?php endif; ?>
                                                </strong>
                                                <?php if ($customer['contact_person']): ?>
                                                    <br><small class="text-muted">Contacto: <?= htmlspecialchars($customer['contact_person']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $customer['company_name'] ? 'primary' : 'info' ?>">
                                                <?= $customer['company_name'] ? 'Empresa' : 'Persona' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($customer['tax_id']): ?>
                                                <code><?= htmlspecialchars($customer['tax_id']) ?></code>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['email']): ?>
                                                <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                                                    <?= htmlspecialchars($customer['email']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['phone']): ?>
                                                <a href="tel:<?= htmlspecialchars($customer['phone']) ?>">
                                                    <?= htmlspecialchars($customer['phone']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y H:i', strtotime($customer['created_at'])) ?></small>
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

<?php if (!empty($reportData['by_type'])): ?>
<script>
// Customer Type Chart
const typeData = <?= json_encode($reportData['by_type']) ?>;
const typeLabels = Object.keys(typeData).map(type => type === 'company' ? 'Empresas' : 'Personas');
const typeValues = Object.values(typeData);

const typeCtx = document.getElementById('customerTypeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'pie',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeValues,
            backgroundColor: [
                '#007bff', // Companies - primary
                '#17a2b8'  // Individuals - info
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
<?php endif; ?>