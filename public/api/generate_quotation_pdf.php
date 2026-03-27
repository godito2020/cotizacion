<?php
// Increase memory and timeout for PDF generation
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');
set_time_limit(300);

// Suppress ALL output including errors
error_reporting(0);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Start output buffering to catch any unexpected output
ob_start();

// Define ROOT_PATH if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

try {
    require_once __DIR__ . '/../../includes/init.php';
    require_once __DIR__ . '/../../vendor/autoload.php';
} catch (Exception $e) {
    error_log("Error loading dependencies: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading dependencies: ' . $e->getMessage()]);
    exit;
}

// Clear any output that might have been generated
ob_end_clean();

// Re-enable error reporting for our code
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }
} catch (Exception $e) {
    error_log("Auth error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Auth error: ' . $e->getMessage()]);
    exit;
}

$companyId = $auth->getCompanyId();
$user = $auth->getUser();

// Get POST data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

$quotationId = intval($data['quotation_id'] ?? 0);

error_log("PDF API called with data: " . json_encode($data));
error_log("Quotation ID: " . $quotationId);

if (empty($quotationId)) {
    error_log("No quotation_id provided");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'quotation_id requerido']);
    exit;
}

error_log("Generating PDF for quotation ID: " . $quotationId);

try {
    // Get quotation data
    $quotationRepo = new Quotation();
    error_log("Getting quotation with ID: $quotationId, Company ID: $companyId");

    if (!is_numeric($quotationId) || $quotationId <= 0) {
        error_log("Invalid quotation ID: $quotationId");
        throw new Exception('ID de cotización inválido');
    }

    $quotation = $quotationRepo->getById($quotationId, $companyId);

    if (!$quotation) {
        error_log("Quotation not found for ID: $quotationId, Company ID: $companyId");
        throw new Exception('Cotización no encontrada o no pertenece a esta empresa');
    }

    error_log("Quotation found: " . json_encode(array_keys($quotation)));

    // Validate quotation has required fields
    $requiredFields = ['quotation_number', 'quotation_date', 'customer_id', 'subtotal', 'total', 'items'];
    foreach ($requiredFields as $field) {
        if (!isset($quotation[$field])) {
            error_log("Missing required field: $field in quotation: " . json_encode($quotation));
            throw new Exception("Campo requerido faltante en la cotización: $field");
        }
    }

    // Validate items is an array
    if (!is_array($quotation['items'])) {
        error_log("Items is not an array: " . gettype($quotation['items']));
        throw new Exception("Los items de la cotización no son válidos");
    }

    // Set currency symbol
    $currency = $quotation['currency'] ?? 'USD';
    $currencySymbol = $currency === 'PEN' ? 'S/' : '$';

    // Get company information from settings (similar to view.php)
    $db = getDBConnection();
    $settingsQuery = "SELECT setting_key, setting_value FROM settings WHERE company_id = ?";
    $settingsStmt = $db->prepare($settingsQuery);
    $settingsStmt->execute([$companyId]);
    $settingsRows = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

    $company = [];
    foreach ($settingsRows as $row) {
        $company[$row['setting_key']] = $row['setting_value'];
    }
    
    // Also get company basic info
    $companyRepo = new Company();
    $companyInfo = $companyRepo->getById($companyId);
    if ($companyInfo) {
        $company = array_merge($company, $companyInfo);
    }

    // Get customer information
    $customerRepo = new Customer();
    $customer = $customerRepo->getById($quotation['customer_id'], $companyId);

    // Get vendor (user) information - using the quotation's user_id
    $db = getDBConnection(); // Ensure we have the database connection
    $vendorQuery = "SELECT id, username, email, phone, first_name, last_name, signature_url FROM users WHERE id = ?";
    $vendorStmt = $db->prepare($vendorQuery);
    $vendorStmt->execute([$quotation['user_id']]);
    $vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure $vendor is not null to prevent errors
    if (!$vendor) {
        $vendor = [
            'id' => $quotation['user_id'],
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => ''
        ];
    }

    // Get company settings for additional data
    $companySettings = new CompanySettings();
    $bankAccounts = $companySettings->getBankAccounts($companyId, true);

    error_log("Data loaded successfully");

    // Check if TCPDF class exists
    if (!class_exists('TCPDF')) {
        error_log("TCPDF class not found");
        throw new Exception('TCPDF class not found. Please check composer installation.');
    }

    error_log("TCPDF class found, creating PDF instance");

    // Define the PDF class for API use
    class QuotationPDFAPI extends TCPDF {
        private $company;
        private $quotation;
        private $vendor;

        public function setData($company, $quotation, $vendor) {
            $this->company = $company;
            $this->quotation = $quotation;
            $this->vendor = $vendor;
        }

        public function Header() {
            // Single column layout - Logo and Company Info
            $startY = 10;

            // Company logo
            $logoUrl = $this->company['company_logo_url'] ?? $this->company['logo_url'] ?? '';
            if (!empty($logoUrl) && file_exists(PUBLIC_PATH . '/' . $logoUrl)) {
                $this->Image(PUBLIC_PATH . '/' . $logoUrl, 15, $startY, 35);
                $startY += 10; // Much reduced space after logo (was 18, originally 25)
            }

            // Company name
            $this->SetFont('helvetica', 'B', 14);
            $this->SetXY(15, $startY);
            $this->Cell(0, 6, strtoupper($this->company['company_name'] ?? $this->company['name'] ?? 'EMPRESA'), 0, 1, 'L');
            $startY += 6;

            // Company tax ID
            $this->SetFont('helvetica', '', 9);
            if (!empty($this->company['company_tax_id']) || !empty($this->company['tax_id'])) {
                $this->SetXY(15, $startY);
                $this->Cell(0, 4, 'RUC: ' . ($this->company['company_tax_id'] ?? $this->company['tax_id'] ?? ''), 0, 1, 'L');
                $startY += 4;
            }

            // Company address
            if (!empty($this->company['company_address']) || !empty($this->company['address'])) {
                $this->SetXY(15, $startY);
                $this->Cell(0, 4, $this->company['company_address'] ?? $this->company['address'] ?? '', 0, 1, 'L');
                $startY += 4;
            }

            // Contact info in single column
            $phone = $this->company['company_phone'] ?? $this->company['phone'] ?? '';
            if (!empty($phone)) {
                $this->SetXY(15, $startY);
                $this->Cell(0, 4, 'Tel: ' . $phone, 0, 1, 'L');
                $startY += 4;
            }

            $email = $this->company['company_email'] ?? $this->company['email'] ?? '';
            if (!empty($email)) {
                $this->SetXY(15, $startY);
                $this->Cell(0, 4, 'Email: ' . $email, 0, 1, 'L');
                $startY += 4;
            }

            $website = $this->company['company_website'] ?? $this->company['website'] ?? '';
            if (!empty($website)) {
                $this->SetXY(15, $startY);
                $this->Cell(0, 4, 'Web: ' . $website, 0, 1, 'L');
                $startY += 4;
            }

            // Quotation title and number (top right) - moved down 2 lines
            $this->SetFont('helvetica', 'B', 18);
            $this->SetTextColor(0, 123, 255);
            $this->SetXY(140, 20);
            $this->Cell(0, 8, 'COTIZACIÓN', 0, 1, 'R');

            $this->SetFont('helvetica', 'B', 12);
            $this->SetXY(140, 28);
            $this->Cell(0, 6, $this->quotation['quotation_number'], 0, 1, 'R');

            $this->SetTextColor(0, 0, 0);

            // Line separator - adjusted position
            $this->Line(15, 48, 195, 48);
        }

        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(128);
            $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    // Start output buffering for PDF generation
    ob_start();

    // Create PDF instance
    $pdf = new QuotationPDFAPI('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setData($company, $quotation, $vendor);

    // Set document information
    $pdf->SetCreator('Sistema de Cotizaciones');
    $pdf->SetAuthor($company['name'] ?? 'Empresa');
    $pdf->SetTitle('Cotización ' . $quotation['quotation_number']);
    $pdf->SetSubject('Cotización');

    // Set margins
    $pdf->SetMargins(15, 55, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 20);

    // Add page
    $pdf->AddPage();

    // Customer and quotation info - moved up 11 lines (from 80 to 49)
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetY(49);

    // Customer section
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'CLIENTE', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    $customerInfo = [
        $customer['name'] ?? ''
    ];

    if ($customer['contact_person']) {
        $customerInfo[] = 'Contacto: ' . $customer['contact_person'];
    }

    if ($customer['tax_id']) {
        $customerInfo[] = (strlen($customer['tax_id']) == 8 ? 'DNI: ' : 'RUC: ') . $customer['tax_id'];
    }

    if ($customer['email']) {
        $customerInfo[] = 'Email: ' . $customer['email'];
    }

    if ($customer['phone']) {
        $customerInfo[] = 'Teléfono: ' . $customer['phone'];
    }

    if ($customer['address']) {
        $customerInfo[] = 'Dirección: ' . $customer['address'];
    }

    foreach ($customerInfo as $info) {
        $pdf->Cell(0, 5, $info, 0, 1, 'L');
    }

    // Quotation details (right side) - moved up 5 lines (25 units total)
    $pdf->SetXY(120, 63);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 5, 'Fecha:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, date('d/m/Y', strtotime($quotation['quotation_date'])), 0, 1, 'L');

    if ($quotation['valid_until']) {
        $pdf->SetXY(120, 68);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 5, 'Válida hasta:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, date('d/m/Y', strtotime($quotation['valid_until'])), 0, 1, 'L');
    }

    // Status - moved up 5 lines (25 units total)
    $statusNames = [
        'Draft' => 'Borrador',
        'Sent' => 'Enviada',
        'Accepted' => 'Aceptada',
        'Rejected' => 'Rechazada',
        'Invoiced' => 'Facturada'
    ];

    $pdf->SetXY(120, 73);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 5, 'Estado:', 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $statusNames[$quotation['status']] ?? $quotation['status'], 0, 1, 'L');

    // Items table - moved up 3 lines from the default position (from +10 to +7)
    $pdf->SetY($pdf->GetY() + 7);

    // Table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('helvetica', 'B', 9);

    $pdf->Cell(10, 8, '#', 1, 0, 'C', true);
    $pdf->Cell(70, 8, 'Descripción', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Cantidad', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'P. Unitario', 1, 0, 'C', true);
    $pdf->Cell(18, 8, 'Desc. %', 1, 0, 'C', true);
    $pdf->Cell(22, 8, 'Desc. ' . $currencySymbol, 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Total', 1, 1, 'C', true);

    // Table content
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);

    foreach ($quotation['items'] as $index => $item) {
        $rowHeight = 6;

        // Check if description needs multiple lines
        $descWidth = 70;
        $description = $item['description'];
        if ($item['product_code']) {
            $description .= "\nCódigo: " . $item['product_code'];
        }

        // Calculate needed height for description
        $lines = $pdf->getStringHeight($descWidth, $description);
        if ($lines > $rowHeight) {
            $rowHeight = $lines + 2;
        }

        $y = $pdf->GetY();

        $pdf->Cell(10, $rowHeight, $index + 1, 1, 0, 'C', true);

        // Description cell with MultiCell
        $pdf->SetXY(25, $y);
        $pdf->MultiCell($descWidth, $rowHeight, $description, 1, 'L', true, 0);

        $pdf->SetXY(95, $y);
        $pdf->Cell(20, $rowHeight, number_format($item['quantity'], 2), 1, 0, 'C', true);
        $pdf->Cell(25, $rowHeight, $currencySymbol . ' ' . number_format($item['unit_price'], 2), 1, 0, 'R', true);
        $pdf->Cell(18, $rowHeight, number_format($item['discount_percentage'], 1) . '%', 1, 0, 'C', true);
        $pdf->Cell(22, $rowHeight, $currencySymbol . ' ' . number_format($item['discount_amount'], 2), 1, 0, 'R', true);
        $pdf->Cell(25, $rowHeight, $currencySymbol . ' ' . number_format($item['line_total'], 2), 1, 1, 'R', true);
    }

    // Totals section - moved up 3 lines (from +5 to +2)
    $pdf->SetY($pdf->GetY() + 2);
    $pdf->SetX(140);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(30, 6, 'Subtotal:', 0, 0, 'L');
    $pdf->Cell(25, 6, $currencySymbol . ' ' . number_format($quotation['subtotal'], 2), 0, 1, 'R');

    if ($quotation['global_discount_percentage'] > 0) {
        $pdf->SetX(140);
        $pdf->Cell(30, 6, 'Descuento Global (' . number_format($quotation['global_discount_percentage'], 1) . '%)', 0, 0, 'L');
        $pdf->Cell(25, 6, $currencySymbol . ' ' . number_format($quotation['global_discount_amount'], 2), 0, 1, 'R');
    }

    // Total
    $pdf->SetX(140);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(30, 8, 'TOTAL:', 1, 0, 'L', true);
    $pdf->Cell(25, 8, $currencySymbol . ' ' . number_format($quotation['total'], 2), 1, 1, 'R', true);

    // Notes section
    if ($quotation['notes'] || $quotation['terms_and_conditions']) {
        $pdf->SetY($pdf->GetY() + 7);  // Reduced from +10 to +7

        if ($quotation['notes']) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'Notas:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $quotation['notes'], 0, 'L', false, 1, '', '', true, 0, false, true, 0);
            $pdf->Ln(3);
        }

        if ($quotation['terms_and_conditions']) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'Términos y Condiciones:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $quotation['terms_and_conditions'], 0, 'L', false, 1, '', '', true, 0, false, true, 0);
            $pdf->Ln(3);
        }
    }

    // Bank accounts section - Table with 2 columns
    if (!empty($bankAccounts)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Datos Bancarios para Transferencias:', 0, 1, 'L');
        $pdf->Ln(2);

        // Table header
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 6, 'Banco / Moneda', 1, 0, 'C', true);
        $pdf->Cell(60, 6, 'Número de Cuenta', 1, 0, 'C', true);
        $pdf->Cell(60, 6, 'CCI', 1, 1, 'C', true);

        // Table content
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetFillColor(255, 255, 255);

        foreach ($bankAccounts as $account) {
            $bankInfo = $account['bank_name'] . ' (' . $account['currency'] . ')';
            $accountInfo = ucfirst($account['account_type']) . ': ' . $account['account_number'];
            $cci = $account['cci'] ?? '-';

            $pdf->Cell(50, 5, $bankInfo, 1, 0, 'L', true);
            $pdf->Cell(60, 5, $accountInfo, 1, 0, 'L', true);
            $pdf->Cell(60, 5, $cci, 1, 1, 'L', true);
        }
    }

    // Signature section - positioned dynamically after content (BEFORE images)
    // Add space before signatures (minimum 15mm from current position)
    $pdf->Ln(15);

    // Check if we need a new page for signatures
    if ($pdf->GetY() > 240) {
        $pdf->AddPage();
        $pdf->SetY(60);
    }

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    // Save current Y position for both signatures
    $signatureY = $pdf->GetY();
 
    // VENDOR SIGNATURE (Left side)
    $pdf->SetXY(15, $signatureY);
    $pdf->Cell(80, 6, 'Firma del Vendedor:', 0, 1, 'L');

    // If vendor has a signature image, add it
    if (!empty($vendor['signature_url']) && file_exists(PUBLIC_PATH . '/' . $vendor['signature_url'])) {
        $signaturePath = PUBLIC_PATH . '/' . $vendor['signature_url'];
        $pdf->Image($signaturePath, 15, $pdf->GetY(), 40, 15, '', '', '', true, 300, '', false, false, 0, false, false, false);
        $pdf->SetXY(15, $pdf->GetY() + 16); // Ajustar la posición después de la imagen
    } else {
        $pdf->SetX(15);
        $pdf->Cell(80, 10, '_________________________________', 0, 1, 'L'); // Línea para firma
    }

    // Vendor name
    $pdf->SetX(15);
    $pdf->Cell(80, 5, trim(($vendor['first_name'] ?? '') . ' ' . ($vendor['last_name'] ?? '')), 0, 1, 'L');

    // Vendor phone (WhatsApp)
    if (!empty($vendor['phone'])) {
        $pdf->SetX(15);
        $pdf->Cell(80, 5, 'WhatsApp: ' . $vendor['phone'], 0, 1, 'L');
    }

    // Vendor email
    if (!empty($vendor['email'])) {
        $pdf->SetX(15);
        $pdf->Cell(80, 5, $vendor['email'], 0, 1, 'L');
    }

    // CUSTOMER SIGNATURE (Right side)
    $pdf->SetXY(110, $signatureY);
    $pdf->Cell(85, 6, 'Firma del Cliente:', 0, 1, 'L');

    // Line for customer signature
    $pdf->SetX(110);
    $pdf->Cell(85, 10, '_________________________________', 0, 1, 'L');

    // Customer name
    $pdf->SetX(110);
    $customerName = $customer['name'] ?? '';
    $pdf->Cell(85, 5, $customerName, 0, 1, 'L');

    // Customer tax ID or email
    $pdf->SetX(110);
    if (!empty($customer['tax_id'])) {
        $taxLabel = strlen($customer['tax_id']) == 8 ? 'DNI: ' : 'RUC: ';
        $pdf->Cell(85, 5, $taxLabel . $customer['tax_id'], 0, 1, 'L');
    } elseif (!empty($customer['email'])) {
        $pdf->Cell(85, 5, $customer['email'], 0, 1, 'L');
    }

    // Add second page with product images if available (AFTER signatures)
    $hasImages = false;
    foreach ($quotation['items'] as $item) {
        // Check if image is a URL or local file
        $imageUrl = $item['product_image_url'] ?? $item['image_url'] ?? null;
        if (!empty($imageUrl)) {
            $hasImages = true;
            break;
        }
    }

    if ($hasImages) {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetY(60);
        $pdf->Cell(0, 10, 'IMÁGENES DE PRODUCTOS', 0, 1, 'C');
        $pdf->Ln(5);

        $startX = 15;
        $startY = $pdf->GetY();
        $imageWidth = 85;
        $imageHeight = 85;
        $spacing = 10;
        $col = 0;
        $row = 0;
        $maxCols = 2; // 2 columns per page

        foreach ($quotation['items'] as $index => $item) {
            $imageUrl = $item['product_image_url'] ?? $item['image_url'] ?? null;

            if (!empty($imageUrl)) {
                // Check if it's a URL or local path
                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $imagePath = $imageUrl; // External URL
                } else {
                    $imagePath = PUBLIC_PATH . '/' . $imageUrl; // Local file
                }

                // Calculate position
                $x = $startX + $col * ($imageWidth + $spacing);
                $y = $startY + $row * ($imageHeight + $spacing + 15);

                // Check if we need a new page (4 images per page = 2 rows)
                if ($row >= 2) {
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->SetY(60);
                    $pdf->Cell(0, 10, 'IMÁGENES DE PRODUCTOS (continuación)', 0, 1, 'C');
                    $pdf->Ln(5);
                    $startY = $pdf->GetY();
                    $row = 0;
                    $y = $startY;
                    $x = $startX + $col * ($imageWidth + $spacing);
                }

                // Draw border box
                $pdf->Rect($x, $y, $imageWidth, $imageHeight + 10);

                // Add image
                $pdf->Image($imagePath, $x + 2, $y + 2, $imageWidth - 4, $imageHeight - 4, '', '', '', true, 300, '', false, false, 0, false, false, false);

                // Add product description below image
                $pdf->SetXY($x, $y + $imageHeight - 2);
                $pdf->SetFont('helvetica', '', 8);
                $description = substr($item['description'], 0, 60);
                if (strlen($item['description']) > 60) $description .= '...';
                $pdf->MultiCell($imageWidth, 4, $description, 0, 'C', false, 1);

                // Move to next position
                $col++;
                if ($col >= $maxCols) {
                    $col = 0;
                    $row++;
                }
            }
        }
    }

    // Get PDF as string
    $pdfContent = $pdf->Output('', 'S');

    // Discard output buffer
    ob_end_clean();

    error_log("PDF generated successfully. Size: " . strlen($pdfContent) . " bytes");

    // Encode to base64
    $base64Content = base64_encode($pdfContent);

    // Send response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'pdf_content' => $base64Content,
        'message' => 'PDF generado exitosamente'
    ]);

} catch (Exception $e) {
    // Clear output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    error_log("Error generating PDF: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar PDF: ' . $e->getMessage()
    ]);
}
?>
