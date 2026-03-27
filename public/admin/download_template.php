<?php
// Increase memory limit for Excel generation
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder a esta sección';
    $auth->redirect(BASE_URL . '/dashboard_simple.php');
}

$templateType = $_GET['type'] ?? '';
$companyId = $auth->getCompanyId();

if (!in_array($templateType, ['products', 'stock'])) {
    $_SESSION['error_message'] = 'Tipo de plantilla inválido';
    $auth->redirect(BASE_URL . '/admin/import_products.php');
}

try {
    require_once __DIR__ . '/../../vendor/autoload.php';

    if ($templateType === 'products') {
        generateProductsTemplate();
    } else {
        generateStockTemplate($companyId);
    }

} catch (Exception $e) {
    error_log("Error generating template: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al generar la plantilla: ' . $e->getMessage();
    $auth->redirect(BASE_URL . '/admin/import_products.php');
}

function generateProductsTemplate() {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Productos');

    // Headers
    $headers = [
        'A1' => 'CODIGO',
        'B1' => 'DESCRIPCION',
        'C1' => 'MARCA',
        'D1' => 'SALDO',
        'E1' => 'PREMIUM',
        'F1' => 'PRECIO',
        'G1' => 'ULTCOSTO',
        'H1' => 'FECULTCOS',
        'I1' => 'IMAGEN'
    ];

    foreach ($headers as $cell => $header) {
        $sheet->setCellValue($cell, $header);
        $sheet->getStyle($cell)->getFont()->setBold(true);
        $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle($cell)->getFill()->getStartColor()->setRGB('4472C4');
        $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
    }

    // Sample data
    $sampleData = [
        ['PROD001', 'Producto de Ejemplo 1', 'Marca A', 100, 150.00, 120.00, 80.00, '2024-01-15', 'https://ejemplo.com/imagen1.jpg'],
        ['PROD002', 'Producto de Ejemplo 2', 'Marca B', 50, 75.50, 65.00, 45.00, '2024-01-20', ''],
        ['PROD003', 'Producto de Ejemplo 3', '', 25, 200.00, 180.00, 120.00, '2024-01-25', 'https://ejemplo.com/imagen3.jpg']
    ];

    $row = 2;
    foreach ($sampleData as $data) {
        $col = 'A';
        foreach ($data as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }

    // Add instructions sheet
    $instructionsSheet = $spreadsheet->createSheet();
    $instructionsSheet->setTitle('Instrucciones');

    $instructions = [
        'INSTRUCCIONES PARA IMPORTAR PRODUCTOS',
        '',
        'Columnas REQUERIDAS:',
        '• CODIGO: Código único del producto (texto)',
        '• DESCRIPCION: Nombre/descripción del producto (texto)',
        '',
        'Columnas OPCIONALES:',
        '• MARCA: Marca del producto (texto)',
        '• SALDO: Stock inicial del producto (número)',
        '• PREMIUM: Precio premium del producto (número decimal)',
        '• PRECIO: Precio regular del producto (número decimal)',
        '• ULTCOSTO: Último costo del producto (número decimal)',
        '• FECULTCOS: Fecha del último costo (formato: YYYY-MM-DD)',
        '• IMAGEN: URL de la imagen del producto (texto)',
        '',
        'IMPORTANTES:',
        '• Los códigos deben ser únicos',
        '• Las fechas deben estar en formato YYYY-MM-DD (ej: 2024-01-15)',
        '• Los precios deben ser números decimales (ej: 150.50)',
        '• Las URLs de imágenes deben ser completas (ej: https://ejemplo.com/imagen.jpg)',
        '• Si un producto ya existe (mismo código), se actualizará',
        '• Si un producto no existe, se creará nuevo',
        '',
        'FORMATO DEL ARCHIVO:',
        '• Usar formato Excel (.xlsx o .xls)',
        '• Los headers deben estar en la primera fila',
        '• Los datos deben empezar desde la segunda fila',
        '• Máximo 1000 productos por importación',
        '',
        'CONSEJOS:',
        '• Revisar los datos antes de importar',
        '• Hacer una copia de seguridad antes de importaciones masivas',
        '• Probar primero con pocos productos',
        '• Usar códigos descriptivos y únicos'
    ];

    $row = 1;
    foreach ($instructions as $instruction) {
        $instructionsSheet->setCellValue('A' . $row, $instruction);
        if ($row === 1) {
            $instructionsSheet->getStyle('A' . $row)->getFont()->setBold(true);
            $instructionsSheet->getStyle('A' . $row)->getFont()->setSize(14);
        } elseif (strpos($instruction, 'REQUERIDAS:') !== false ||
                  strpos($instruction, 'OPCIONALES:') !== false ||
                  strpos($instruction, 'IMPORTANTES:') !== false ||
                  strpos($instruction, 'FORMATO DEL ARCHIVO:') !== false ||
                  strpos($instruction, 'CONSEJOS:') !== false) {
            $instructionsSheet->getStyle('A' . $row)->getFont()->setBold(true);
        }
        $row++;
    }

    // Auto-size columns for both sheets
    foreach ([$sheet, $instructionsSheet] as $currentSheet) {
        foreach (range('A', 'I') as $column) {
            $currentSheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    // Set the products sheet as active
    $spreadsheet->setActiveSheetIndex(0);

    // Output
    $filename = 'Plantilla_Productos_' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function generateStockTemplate($companyId) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Stock por Almacén');

    // Get warehouse list from database or use default ones
    $warehouses = [
        'YP', 'PRODUCTORES', 'AR1902', 'PRO I', 'UNIVERSITARIA', 'PALAO 2',
        'AR1940', 'PTE. PIEDRA', 'PIURA', 'METRO', 'ZAPALLAL', 'GAMARRA',
        'AR1992', 'LA MARINA', 'CARABAYLLO', 'ALBOR', 'DISTRIBUCION',
        'ARR 1908', 'AREQ', 'LICITAC.LIMA', 'ALMACEN PRO II', 'TRAPICHE',
        'LA MOLINA', 'LIMA CAUCHO', 'SMP(PRO2)', 'TARMA', 'OTROS'
    ];

    // Headers
    $headers = ['Articulo', 'Descripcion', 'Med'];
    $headers = array_merge($headers, array_slice($warehouses, 0, 20)); // Limit to 20 warehouses for Excel

    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $sheet->getStyle($col . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle($col . '1')->getFill()->getStartColor()->setRGB('4472C4');
        $sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
        $col++;
    }

    // Sample data
    $sampleData = [
        [
            'PROD001', 'Producto de Ejemplo 1', 'UND',
            10, 5, 8, 12, 3, 7, 15, 2, 9, 4, 6, 11, 1, 8, 5, 9, 3, 7, 2, 4
        ],
        [
            'PROD002', 'Producto de Ejemplo 2', 'KG',
            25, 15, 30, 20, 10, 5, 8, 12, 18, 22, 7, 9, 13, 16, 11, 14, 6, 19, 8, 21, 17
        ],
        [
            'PROD003', 'Producto de Ejemplo 3', 'CAJA',
            0, 2, 1, 3, 0, 1, 2, 0, 1, 3, 2, 1, 0, 2, 1, 0, 3, 1, 2, 0, 1
        ]
    ];

    $row = 2;
    foreach ($sampleData as $data) {
        $col = 'A';
        foreach ($data as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }

    // Add instructions sheet
    $instructionsSheet = $spreadsheet->createSheet();
    $instructionsSheet->setTitle('Instrucciones');

    $instructions = [
        'INSTRUCCIONES PARA IMPORTAR STOCK POR ALMACÉN',
        '',
        'Columnas REQUERIDAS:',
        '• Articulo: Código del producto que debe existir en el sistema',
        '',
        'Columnas OPCIONALES:',
        '• Descripcion: Descripción del producto (solo referencial)',
        '• Med: Unidad de medida (UND, KG, CAJA, etc.)',
        '• [Nombres de Almacén]: Stock para cada almacén específico',
        '',
        'ALMACENES DISPONIBLES:',
        '• YP, PRODUCTORES, AR1902, PRO I',
        '• UNIVERSITARIA, PALAO 2, AR1940, PTE. PIEDRA',
        '• PIURA, METRO, ZAPALLAL, GAMARRA',
        '• AR1992, LA MARINA, CARABAYLLO, ALBOR',
        '• DISTRIBUCION, ARR 1908, AREQ, LICITAC.LIMA',
        '• ALMACEN PRO II, TRAPICHE, LA MOLINA',
        '• LIMA CAUCHO, SMP(PRO2), TARMA, OTROS',
        '',
        'IMPORTANTES:',
        '• Los códigos de artículo deben existir previamente en el sistema',
        '• Los stocks deben ser números enteros o decimales',
        '• Si no se especifica stock para un almacén, se asume 0',
        '• El stock existente será REEMPLAZADO por los nuevos valores',
        '• Solo se actualizarán los almacenes especificados en el archivo',
        '',
        'FORMATO DEL ARCHIVO:',
        '• Usar formato Excel (.xlsx o .xls)',
        '• Los headers deben estar en la primera fila',
        '• Los datos deben empezar desde la segunda fila',
        '• Los nombres de almacén deben coincidir exactamente',
        '• Máximo 1000 productos por importación',
        '',
        'CONSEJOS:',
        '• Verificar que los códigos de artículo existan',
        '• Hacer respaldo del stock actual antes de importar',
        '• Probar primero con pocos productos',
        '• Revisar los totales después de la importación',
        '• Usar números positivos para el stock'
    ];

    $row = 1;
    foreach ($instructions as $instruction) {
        $instructionsSheet->setCellValue('A' . $row, $instruction);
        if ($row === 1) {
            $instructionsSheet->getStyle('A' . $row)->getFont()->setBold(true);
            $instructionsSheet->getStyle('A' . $row)->getFont()->setSize(14);
        } elseif (strpos($instruction, 'REQUERIDAS:') !== false ||
                  strpos($instruction, 'OPCIONALES:') !== false ||
                  strpos($instruction, 'ALMACENES DISPONIBLES:') !== false ||
                  strpos($instruction, 'IMPORTANTES:') !== false ||
                  strpos($instruction, 'FORMATO DEL ARCHIVO:') !== false ||
                  strpos($instruction, 'CONSEJOS:') !== false) {
            $instructionsSheet->getStyle('A' . $row)->getFont()->setBold(true);
        }
        $row++;
    }

    // Auto-size columns for both sheets
    foreach ([$sheet, $instructionsSheet] as $currentSheet) {
        foreach (range('A', 'Z') as $column) {
            $currentSheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    // Set the stock sheet as active
    $spreadsheet->setActiveSheetIndex(0);

    // Output
    $filename = 'Plantilla_Stock_Almacenes_' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>