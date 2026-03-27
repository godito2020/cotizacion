<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Get parameters
$exportFormat = $_GET['export'] ?? 'excel';
$reportType = $_GET['type'] ?? 'dashboard';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Validate format
if (!in_array($exportFormat, ['excel', 'pdf'])) {
    $_SESSION['error_message'] = 'Formato de exportación inválido';
    $auth->redirect(BASE_URL . '/reports/index.php');
}

// Get company information
$companyRepo = new Company();
$company = $companyRepo->getById($companyId);

try {
    require_once __DIR__ . '/../../vendor/autoload.php';

    if ($exportFormat === 'excel') {
        exportToExcel($reportType, $startDate, $endDate, $companyId, $company);
    } else {
        exportToPDF($reportType, $startDate, $endDate, $companyId, $company, $user);
    }

} catch (Exception $e) {
    error_log("Error exporting report: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al exportar el reporte: ' . $e->getMessage();
    $auth->redirect(BASE_URL . '/reports/index.php?type=' . $reportType);
}

function exportToExcel($reportType, $startDate, $endDate, $companyId, $company) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator($company['name'] ?? 'Sistema de Cotizaciones')
        ->setTitle("Reporte " . ucfirst($reportType))
        ->setDescription("Reporte generado del $startDate al $endDate");

    // Header
    $sheet->setCellValue('A1', strtoupper($company['name'] ?? 'EMPRESA'));
    $sheet->setCellValue('A2', "REPORTE " . strtoupper($reportType));
    $sheet->setCellValue('A3', "Período: $startDate al $endDate");
    $sheet->setCellValue('A4', "Generado: " . date('d/m/Y H:i:s'));

    // Style header
    $sheet->getStyle('A1:A4')->getFont()->setBold(true);
    $sheet->getStyle('A1')->getFont()->setSize(16);
    $sheet->getStyle('A2')->getFont()->setSize(14);

    $currentRow = 6;

    // Get data based on report type
    switch ($reportType) {
        case 'quotations':
            $currentRow = exportQuotationsData($sheet, $currentRow, $companyId, $startDate, $endDate);
            break;
        case 'customers':
            $currentRow = exportCustomersData($sheet, $currentRow, $companyId, $startDate, $endDate);
            break;
        case 'products':
            $currentRow = exportProductsData($sheet, $currentRow, $companyId, $startDate, $endDate);
            break;
        default:
            $currentRow = exportDashboardData($sheet, $currentRow, $companyId, $startDate, $endDate);
    }

    // Auto-size columns
    foreach (range('A', 'J') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Output file
    $filename = "Reporte_" . ucfirst($reportType) . "_" . date('Y-m-d') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportToPDF($reportType, $startDate, $endDate, $companyId, $company, $user) {
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Sistema de Cotizaciones');
    $pdf->SetAuthor($company['name'] ?? 'Empresa');
    $pdf->SetTitle("Reporte " . ucfirst($reportType));
    $pdf->SetSubject("Reporte del $startDate al $endDate");

    // Set margins
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 20);

    // Add page
    $pdf->AddPage();

    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($company['name'] ?? 'EMPRESA'), 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, "REPORTE " . strtoupper($reportType), 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, "Período: $startDate al $endDate", 0, 1, 'C');
    $pdf->Cell(0, 6, "Generado: " . date('d/m/Y H:i:s') . " por " . $user['first_name'] . ' ' . $user['last_name'], 0, 1, 'C');

    $pdf->Ln(10);

    // Get and add report content
    switch ($reportType) {
        case 'quotations':
            addQuotationsPDFContent($pdf, $companyId, $startDate, $endDate);
            break;
        case 'customers':
            addCustomersPDFContent($pdf, $companyId, $startDate, $endDate);
            break;
        case 'products':
            addProductsPDFContent($pdf, $companyId, $startDate, $endDate);
            break;
        default:
            addDashboardPDFContent($pdf, $companyId, $startDate, $endDate);
    }

    // Output file
    $filename = "Reporte_" . ucfirst($reportType) . "_" . date('Y-m-d') . ".pdf";
    $pdf->Output($filename, 'D');
    exit;
}

function exportQuotationsData($sheet, $startRow, $companyId, $startDate, $endDate) {
    $quotationRepo = new Quotation();

    // Summary
    $sheet->setCellValue('A' . $startRow, 'RESUMEN DE COTIZACIONES');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow += 2;

    $summary = [
        'Total Cotizaciones' => $quotationRepo->getCount($companyId, $startDate, $endDate),
        'Monto Total' => 'S/ ' . number_format($quotationRepo->getTotalAmount($companyId, $startDate, $endDate), 2),
        'Promedio' => 'S/ ' . number_format($quotationRepo->getAverageAmount($companyId, $startDate, $endDate), 2),
        'Tasa de Conversión' => number_format($quotationRepo->getConversionRate($companyId, $startDate, $endDate), 1) . '%'
    ];

    foreach ($summary as $label => $value) {
        $sheet->setCellValue('A' . $startRow, $label);
        $sheet->setCellValue('B' . $startRow, $value);
        $startRow++;
    }

    $startRow += 2;

    // Recent quotations
    $sheet->setCellValue('A' . $startRow, 'COTIZACIONES RECIENTES');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow++;

    $headers = ['Número', 'Cliente', 'Fecha', 'Estado', 'Monto', 'Usuario'];
    $columns = ['A', 'B', 'C', 'D', 'E', 'F'];
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($columns[$col] . $startRow, $header);
        $sheet->getStyle($columns[$col] . $startRow)->getFont()->setBold(true);
    }
    $startRow++;

    $quotations = $quotationRepo->getRecent($companyId, 50, $startDate, $endDate);
    foreach ($quotations as $quotation) {
        $sheet->setCellValue('A' . $startRow, $quotation['quotation_number']);
        $sheet->setCellValue('B' . $startRow, $quotation['customer_name']);
        $sheet->setCellValue('C' . $startRow, date('d/m/Y', strtotime($quotation['quotation_date'])));
        $sheet->setCellValue('D' . $startRow, $quotation['status']);
        $sheet->setCellValue('E' . $startRow, 'S/ ' . number_format($quotation['total'], 2));
        $sheet->setCellValue('F' . $startRow, $quotation['user_name']);
        $startRow++;
    }

    return $startRow + 2;
}

function exportCustomersData($sheet, $startRow, $companyId, $startDate, $endDate) {
    $customerRepo = new Customer();

    // Summary
    $sheet->setCellValue('A' . $startRow, 'RESUMEN DE CLIENTES');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow += 2;

    $summary = [
        'Total Clientes' => $customerRepo->getCount($companyId),
        'Nuevos Clientes' => $customerRepo->getNewCustomersCount($companyId, $startDate, $endDate),
        'Clientes Activos' => $customerRepo->getActiveCustomersCount($companyId, $startDate, $endDate)
    ];

    foreach ($summary as $label => $value) {
        $sheet->setCellValue('A' . $startRow, $label);
        $sheet->setCellValue('B' . $startRow, $value);
        $startRow++;
    }

    $startRow += 2;

    // Top customers
    $sheet->setCellValue('A' . $startRow, 'TOP CLIENTES');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow++;

    $headers = ['Cliente', 'Documento', 'Email', 'Cotizaciones', 'Monto Total'];
    $columns = ['A', 'B', 'C', 'D', 'E'];
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($columns[$col] . $startRow, $header);
        $sheet->getStyle($columns[$col] . $startRow)->getFont()->setBold(true);
    }
    $startRow++;

    $topCustomers = $customerRepo->getTopCustomers($companyId, $startDate, $endDate, 50);
    foreach ($topCustomers as $customer) {
        $sheet->setCellValue('A' . $startRow, $customer['name']);
        $sheet->setCellValue('B' . $startRow, $customer['tax_id']);
        $sheet->setCellValue('C' . $startRow, $customer['email']);
        $sheet->setCellValue('D' . $startRow, $customer['quotation_count']);
        $sheet->setCellValue('E' . $startRow, 'S/ ' . number_format($customer['total_amount'], 2));
        $startRow++;
    }

    return $startRow + 2;
}

function exportProductsData($sheet, $startRow, $companyId, $startDate, $endDate) {
    $productRepo = new Product();

    // Summary
    $sheet->setCellValue('A' . $startRow, 'RESUMEN DE PRODUCTOS');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow += 2;

    $summary = [
        'Total Productos' => $productRepo->getCount($companyId),
        'Con Stock' => $productRepo->getCountWithStock($companyId),
        'Stock Bajo' => $productRepo->getLowStockCount($companyId)
    ];

    foreach ($summary as $label => $value) {
        $sheet->setCellValue('A' . $startRow, $label);
        $sheet->setCellValue('B' . $startRow, $value);
        $startRow++;
    }

    $startRow += 2;

    // Most quoted products
    $sheet->setCellValue('A' . $startRow, 'PRODUCTOS MÁS COTIZADOS');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow++;

    $headers = ['Producto', 'Código', 'Marca', 'Veces Cotizado', 'Cantidad Total', 'Stock'];
    $columns = ['A', 'B', 'C', 'D', 'E', 'F'];
    foreach ($headers as $col => $header) {
        $sheet->setCellValue($columns[$col] . $startRow, $header);
        $sheet->getStyle($columns[$col] . $startRow)->getFont()->setBold(true);
    }
    $startRow++;

    $mostQuoted = $productRepo->getMostQuotedProducts($companyId, $startDate, $endDate, 50);
    foreach ($mostQuoted as $product) {
        $sheet->setCellValue('A' . $startRow, $product['description']);
        $sheet->setCellValue('B' . $startRow, $product['code']);
        $sheet->setCellValue('C' . $startRow, $product['brand']);
        $sheet->setCellValue('D' . $startRow, $product['quote_count']);
        $sheet->setCellValue('E' . $startRow, number_format($product['total_quantity'], 2));
        $sheet->setCellValue('F' . $startRow, number_format($product['total_stock'], 2));
        $startRow++;
    }

    return $startRow + 2;
}

function exportDashboardData($sheet, $startRow, $companyId, $startDate, $endDate) {
    $quotationRepo = new Quotation();
    $customerRepo = new Customer();
    $productRepo = new Product();

    // General summary
    $sheet->setCellValue('A' . $startRow, 'RESUMEN GENERAL');
    $sheet->getStyle('A' . $startRow)->getFont()->setBold(true);
    $startRow += 2;

    $summary = [
        'Total Cotizaciones' => $quotationRepo->getCount($companyId, $startDate, $endDate),
        'Monto Total' => 'S/ ' . number_format($quotationRepo->getTotalAmount($companyId, $startDate, $endDate), 2),
        'Total Clientes' => $customerRepo->getCount($companyId),
        'Nuevos Clientes' => $customerRepo->getNewCustomersCount($companyId, $startDate, $endDate),
        'Total Productos' => $productRepo->getCount($companyId),
        'Productos con Stock Bajo' => $productRepo->getLowStockCount($companyId)
    ];

    foreach ($summary as $label => $value) {
        $sheet->setCellValue('A' . $startRow, $label);
        $sheet->setCellValue('B' . $startRow, $value);
        $startRow++;
    }

    return $startRow + 2;
}

function addQuotationsPDFContent($pdf, $companyId, $startDate, $endDate) {
    $quotationRepo = new Quotation();

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'RESUMEN DE COTIZACIONES', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $summary = [
        'Total Cotizaciones: ' . number_format($quotationRepo->getCount($companyId, $startDate, $endDate)),
        'Monto Total: S/ ' . number_format($quotationRepo->getTotalAmount($companyId, $startDate, $endDate), 2),
        'Promedio: S/ ' . number_format($quotationRepo->getAverageAmount($companyId, $startDate, $endDate), 2),
        'Tasa de Conversión: ' . number_format($quotationRepo->getConversionRate($companyId, $startDate, $endDate), 1) . '%'
    ];

    foreach ($summary as $line) {
        $pdf->Cell(0, 6, $line, 0, 1, 'L');
    }
}

function addCustomersPDFContent($pdf, $companyId, $startDate, $endDate) {
    $customerRepo = new Customer();

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'RESUMEN DE CLIENTES', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $summary = [
        'Total Clientes: ' . number_format($customerRepo->getCount($companyId)),
        'Nuevos Clientes: ' . number_format($customerRepo->getNewCustomersCount($companyId, $startDate, $endDate)),
        'Clientes Activos: ' . number_format($customerRepo->getActiveCustomersCount($companyId, $startDate, $endDate))
    ];

    foreach ($summary as $line) {
        $pdf->Cell(0, 6, $line, 0, 1, 'L');
    }
}

function addProductsPDFContent($pdf, $companyId, $startDate, $endDate) {
    $productRepo = new Product();

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'RESUMEN DE PRODUCTOS', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $summary = [
        'Total Productos: ' . number_format($productRepo->getCount($companyId)),
        'Con Stock: ' . number_format($productRepo->getCountWithStock($companyId)),
        'Stock Bajo: ' . number_format($productRepo->getLowStockCount($companyId))
    ];

    foreach ($summary as $line) {
        $pdf->Cell(0, 6, $line, 0, 1, 'L');
    }
}

function addDashboardPDFContent($pdf, $companyId, $startDate, $endDate) {
    $quotationRepo = new Quotation();
    $customerRepo = new Customer();
    $productRepo = new Product();

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'RESUMEN GENERAL', 0, 1, 'L');
    $pdf->Ln(2);

    $pdf->SetFont('helvetica', '', 10);
    $summary = [
        'Total Cotizaciones: ' . number_format($quotationRepo->getCount($companyId, $startDate, $endDate)),
        'Monto Total: S/ ' . number_format($quotationRepo->getTotalAmount($companyId, $startDate, $endDate), 2),
        'Total Clientes: ' . number_format($customerRepo->getCount($companyId)),
        'Nuevos Clientes: ' . number_format($customerRepo->getNewCustomersCount($companyId, $startDate, $endDate)),
        'Total Productos: ' . number_format($productRepo->getCount($companyId)),
        'Stock Bajo: ' . number_format($productRepo->getLowStockCount($companyId))
    ];

    foreach ($summary as $line) {
        $pdf->Cell(0, 6, $line, 0, 1, 'L');
    }
}
?>