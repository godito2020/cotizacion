<?php
// cotizacion/public/admin/reports.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$reportRepo = new Report();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole('Company Admin')) { // Only Company Admins can view reports for now
    $_SESSION['error_message'] = "No está autorizado para acceder a esta página.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$page_title = "Reportes - " . APP_NAME;
require_once TEMPLATES_PATH . '/header.php';

$report_type = $_POST['report_type'] ?? $_GET['report_type'] ?? null;
$date_from = $_POST['date_from'] ?? $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_POST['date_to'] ?? $_GET['date_to'] ?? date('Y-m-t');
$status_filter = $_POST['status_filter'] ?? $_GET['status_filter'] ?? null;
if ($status_filter === 'ALL') $status_filter = null; // Treat 'ALL' as no filter

$report_data = null;
$report_title = "";
$report_headers = [];

// Handle CSV Export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    if (isset($_SESSION['report_data']) && isset($_SESSION['report_headers']) && isset($_SESSION['report_filename_base'])) {
        $dataToExport = $_SESSION['report_data'];
        $headersToExport = $_SESSION['report_headers'];
        $filenameBase = $_SESSION['report_filename_base'];

        unset($_SESSION['report_data'], $_SESSION['report_headers'], $_SESSION['report_filename_base']); // Clear from session

        if (!empty($dataToExport)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '_' . date('Ymd') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, array_values($headersToExport)); // Write headers

            foreach ($dataToExport as $row) {
                $ordered_row = [];
                foreach(array_keys($headersToExport) as $key) {
                    $ordered_row[] = $row[$key] ?? '';
                }
                fputcsv($output, $ordered_row);
            }
            fclose($output);
            exit;
        } else {
            $_SESSION['error_message'] = "No hay datos para exportar.";
            $auth->redirect(BASE_URL . '/admin/reports.php'); // Redirect back if no data
        }
    } else {
        $_SESSION['error_message'] = "No se encontró información del reporte para exportar. Genere el reporte primero.";
        $auth->redirect(BASE_URL . '/admin/reports.php');
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['report_type'])) { // Allow GET for re-generating after export
    // Validate dates
    if (empty($date_from) || empty($date_to)) {
        $errors[] = "Las fechas 'Desde' y 'Hasta' son requeridas.";
    } elseif (strtotime($date_from) > strtotime($date_to)) {
        $errors[] = "La fecha 'Desde' no puede ser posterior a la fecha 'Hasta'.";
    }

    if (empty($errors)) {
        $statuses_sales = ['Aceptada', 'Facturada']; // Spanish status values from DB
        switch ($report_type) {
            case 'quotations_summary':
                $report_title = "Resumen de Cotizaciones";
                $report_headers = ['date' => 'Fecha', 'count' => 'Cantidad de Cotizaciones', 'total_value' => 'Valor Total'];
                $report_data = $reportRepo->getQuotationsSummary($company_id, $date_from, $date_to, $status_filter);
                break;
            case 'sales_by_customer':
                $report_title = "Ventas por Cliente";
                 $report_headers = ['customer_name' => 'Cliente', 'quotation_count' => 'Nº Cotizaciones', 'total_sales' => 'Ventas Totales'];
                $report_data = $reportRepo->getSalesByCustomer($company_id, $date_from, $date_to, $statuses_sales);
                break;
            case 'sales_by_product':
                $report_title = "Ventas por Producto";
                $report_headers = ['product_sku' => 'SKU', 'product_name' => 'Producto', 'total_quantity_sold' => 'Cantidad Vendida', 'total_value_sold' => 'Valor Total Vendido'];
                $report_data = $reportRepo->getSalesByProduct($company_id, $date_from, $date_to, $statuses_sales);
                break;
            case 'sales_by_salesperson':
                $report_title = "Ventas por Vendedor";
                $report_headers = ['salesperson_name' => 'Vendedor', 'salesperson_username' => 'Usuario', 'quotation_count' => 'Nº Cotizaciones', 'total_sales' => 'Ventas Totales'];
                $report_data = $reportRepo->getSalesBySalesperson($company_id, $date_from, $date_to, $statuses_sales);
                break;
            default:
                $errors[] = "Tipo de reporte no válido seleccionado.";
        }

        if (!empty($report_data)) {
            $_SESSION['report_data'] = $report_data;
            $_SESSION['report_headers'] = $report_headers;
            $_SESSION['report_filename_base'] = strtolower(str_replace(' ', '_', $report_type));
        } elseif(empty($errors)) { // No data but also no errors means empty report
            $_SESSION['message'] = "No se encontraron datos para los criterios seleccionados.";
            // Clear previous report data from session if any
            unset($_SESSION['report_data'], $_SESSION['report_headers'], $_SESSION['report_filename_base']);
        }
    }
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode("<br>", $errors);
        // Clear report data from session if errors occurred
        unset($_SESSION['report_data'], $_SESSION['report_headers'], $_SESSION['report_filename_base']);
    }
}

$quotation_statuses = ['Borrador', 'Enviada', 'Aceptada', 'Rechazada', 'Facturada', 'Cancelada']; // Common statuses

?>
<nav aria-label="breadcrumb">
  <ul>
    <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Panel Admin</a></li>
    <li>Reportes</li>
  </ul>
</nav>

<article>
    <hgroup>
        <h1>Generador de Reportes</h1>
        <p>Seleccione los criterios para generar un reporte.</p>
    </hgroup>

    <form action="reports.php" method="POST">
        <div class="grid">
            <label for="report_type">
                Tipo de Reporte:
                <select id="report_type" name="report_type" required>
                    <option value="">-- Seleccione --</option>
                    <option value="quotations_summary" <?php echo ($report_type === 'quotations_summary' ? 'selected' : ''); ?>>Resumen de Cotizaciones</option>
                    <option value="sales_by_customer" <?php echo ($report_type === 'sales_by_customer' ? 'selected' : ''); ?>>Ventas por Cliente</option>
                    <option value="sales_by_product" <?php echo ($report_type === 'sales_by_product' ? 'selected' : ''); ?>>Ventas por Producto</option>
                    <option value="sales_by_salesperson" <?php echo ($report_type === 'sales_by_salesperson' ? 'selected' : ''); ?>>Ventas por Vendedor</option>
                </select>
            </label>
            <label for="status_filter">
                Estado de Cotización (para Resumen):
                <select id="status_filter" name="status_filter">
                    <option value="ALL">-- Todos --</option>
                    <?php foreach ($quotation_statuses as $status_opt): ?>
                    <option value="<?php echo $status_opt; ?>" <?php echo ($status_filter === $status_opt ? 'selected' : ''); ?>><?php echo $status_opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid">
            <label for="date_from">
                Fecha Desde:
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
            </label>
            <label for="date_to">
                Fecha Hasta:
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
            </label>
        </div>
        <button type="submit">Generar Reporte</button>
    </form>

    <?php if (!empty($report_data) && empty($errors)): ?>
    <hr>
    <hgroup>
        <h2><?php echo htmlspecialchars($report_title); ?></h2>
        <p>Periodo: <?php echo htmlspecialchars(date("d/m/Y", strtotime($date_from))); ?> - <?php echo htmlspecialchars(date("d/m/Y", strtotime($date_to))); ?></p>
    </hgroup>

    <a href="reports.php?action=export_csv&report_type=<?php echo urlencode($report_type); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&status_filter=<?php echo urlencode($status_filter); ?>"
       role="button" class="outline">Exportar a CSV</a>

    <figure style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <?php foreach ($report_headers as $header_display): ?>
                    <th><?php echo htmlspecialchars($header_display); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $row): ?>
                <tr>
                    <?php foreach (array_keys($report_headers) as $key): ?>
                    <td>
                        <?php
                        // Basic formatting for numbers/dates
                        if (is_numeric($row[$key] ?? null) && (strpos($key, 'total') !== false || strpos($key, 'value') !== false || strpos($key, 'price') !== false)) {
                            echo htmlspecialchars(number_format(floatval($row[$key]), 2, ',', '.'));
                        } elseif (strpos($key, 'date') !== false && !empty($row[$key])) {
                            echo htmlspecialchars(date("d/m/Y", strtotime($row[$key])));
                        } else {
                            echo htmlspecialchars($row[$key] ?? '');
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </figure>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && isset($report_data) && empty($report_data)): ?>
        <p>No se encontraron datos para los criterios seleccionados.</p>
    <?php endif; ?>

</article>

<?php
require_once TEMPLATES_PATH . '/footer.php';
?>
