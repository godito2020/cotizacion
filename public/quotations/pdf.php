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

require_once __DIR__ . '/../../includes/init.php';

// Check if this is a public access via token (from public_pdf.php)
$isPublicAccess = defined('PUBLIC_PDF_ACCESS') && PUBLIC_PDF_ACCESS === true;

if ($isPublicAccess) {
    $companyId = defined('PUBLIC_PDF_COMPANY_ID') ? PUBLIC_PDF_COMPANY_ID : 0;
    $user = null;
} else {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        exit('No autorizado');
    }
    $user = $auth->getUser();
    $companyId = $auth->getCompanyId();
}

$quotationId = $_GET['id'] ?? 0;
if (empty($quotationId)) {
    http_response_code(400);
    exit('ID de cotización requerido');
}

$quotationRepo = new Quotation();
$quotation = $quotationRepo->getById($quotationId, $companyId);
if (!$quotation) {
    http_response_code(404);
    exit('Cotización no encontrada');
}

// Get company settings
$db = getDBConnection();
$settingsStmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE company_id = ?");
$settingsStmt->execute([$companyId]);
$company = [];
foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $company[$row['setting_key']] = $row['setting_value'];
}
$companyRepo = new Company();
$companyInfo = $companyRepo->getById($companyId);
if ($companyInfo) $company = array_merge($company, $companyInfo);

$customerRepo  = new Customer();
$customer      = $customerRepo->getById($quotation['customer_id'], $companyId);
$companySettings = new CompanySettings();
$bankAccounts  = $companySettings->getBankAccounts($companyId, true);
$brandLogos    = $companySettings->getBrandLogos($companyId, true);

$currency       = $quotation['currency'] ?? 'USD';
$currencySymbol = $currency === 'PEN' ? 'S/' : '$';
$currencyLabel  = $currency === 'PEN' ? 'Soles' : 'Dólares';

$vendorStmt = $db->prepare("SELECT id, username, email, phone, first_name, last_name, signature_url FROM users WHERE id = ?");
$vendorStmt->execute([$quotation['user_id']]);
$vendor = $vendorStmt->fetch(PDO::FETCH_ASSOC);

// Primary color for PDF headers (RGB)
$headerColorHex = $company['pdf_header_color'] ?? '#1B3A6B';
$hexClean = ltrim($headerColorHex, '#');
$hR = hexdec(substr($hexClean, 0, 2));
$hG = hexdec(substr($hexClean, 2, 2));
$hB = hexdec(substr($hexClean, 4, 2));

// Clear accumulated output but KEEP buffering active so any GD/libpng warnings
// generated inside TCPDF are captured in the buffer instead of leaking to output.
ob_clean();

try {
    require_once ROOT_PATH . '/vendor/autoload.php';

    class QuotationPDF extends TCPDF {
        private $company;
        private $quotation;
        private $vendor;
        private $brandLogos;
        private $hR, $hG, $hB;
        public  $headerHeight = 50;

        public function setData($company, $quotation, $vendor, $brandLogos, $hR, $hG, $hB) {
            $this->company    = $company;
            $this->quotation  = $quotation;
            $this->vendor     = $vendor;
            $this->brandLogos = $brandLogos;
            $this->hR = $hR; $this->hG = $hG; $this->hB = $hB;
        }

        public function Header() {
            $pageW = $this->getPageWidth();
            $mL    = 15;
            $mR    = 15;
            $usable = $pageW - $mL - $mR;  // 180mm

            $y0 = 8;

            // ── Layout dimensions ─────────────────────────────────────────
            $logoW  = 45;
            $logoX  = $mL;
            $infoX  = $mL + $logoW + 4;
            $infoW  = $usable - $logoW - 4 - 55; // leave 55mm for COTIZACION box

            // ── Center column: Company info (rendered first to measure height) ──
            $cy = $y0;
            $this->SetFont('helvetica', 'B', 10);
            $this->SetTextColor(0, 0, 0);
            $this->SetXY($infoX, $cy);
            $this->Cell($infoW, 5, strtoupper($this->company['company_name'] ?? ''), 0, 1, 'L');
            $cy += 5;

            $this->SetFont('helvetica', '', 8);
            $fields = [
                'RUC'   => $this->company['company_tax_id']  ?? '',
                'Dir'   => $this->company['company_address'] ?? '',
                'Tel'   => $this->company['company_phone']   ?? '',
                'Email' => $this->company['company_email']   ?? '',
                'Web'   => $this->company['company_website'] ?? '',
            ];
            foreach ($fields as $label => $val) {
                if (empty($val)) continue;
                $this->SetXY($infoX, $cy);
                $this->SetFont('helvetica', 'B', 7.5);
                $this->Cell(8, 4, $label . ':', 0, 0, 'L');
                $this->SetFont('helvetica', '', 7.5);
                $this->MultiCell($infoW - 8, 4, $val, 0, 'L', false, 1, $infoX + 8, $cy);
                $cy = $this->GetY();
            }

            // ── Left column: Logo (centered vertically against info block) ──
            if (!empty($this->company['company_logo_url'])) {
                $logoPath = PUBLIC_PATH . '/' . $this->company['company_logo_url'];
                if (file_exists($logoPath)) {
                    // Calculate rendered logo height from aspect ratio
                    list($iw, $ih) = @getimagesize($logoPath) ?: [1, 1];
                    $logoH = $ih > 0 ? round($logoW * $ih / $iw, 1) : $logoW;
                    $infoBlockH = $cy - $y0;
                    $logoY = $infoBlockH > $logoH
                        ? $y0 + ($infoBlockH - $logoH) / 2
                        : $y0;
                    $this->Image($logoPath, $logoX, $logoY, $logoW, 0, '', '', '', true, 300,
                                 '', false, false, 0, 'CM', false, false);
                }
            }

            // ── Right column: COTIZACION box ──────────────────────────────
            $boxX = $pageW - $mR - 52;
            $boxW = 52;
            $this->SetFillColor($this->hR, $this->hG, $this->hB);
            $this->Rect($boxX, $y0, $boxW, 10, 'F');
            $this->SetFont('helvetica', 'B', 14);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($boxX, $y0 + 1.5);
            $this->Cell($boxW, 7, 'COTIZACIÓN', 0, 1, 'C');

            $this->SetFillColor(240, 243, 250);
            $this->Rect($boxX, $y0 + 10, $boxW, 8, 'F');
            $this->SetFont('helvetica', 'B', 11);
            $this->SetTextColor(0, 0, 0);
            $this->SetXY($boxX, $y0 + 11.5);
            $this->Cell($boxW, 5, $this->quotation['quotation_number'], 0, 1, 'C');
            $this->SetTextColor(0, 0, 0);

            // ── Brand logos row ───────────────────────────────────────────
            $hasBrands = !empty($this->brandLogos);
            $brandRowY = max($cy + 2, $y0 + 24);

            if ($hasBrands) {
                $this->SetDrawColor(220, 220, 220);
                $this->Line($mL, $brandRowY, $pageW - $mR, $brandRowY);
                $brandRowY += 1.5;

                $logoH   = 8;
                $rowW    = $pageW - $mL - $mR; // usable width

                // First pass: calculate each logo's width to find total content width
                $logoWidths = [];
                foreach ($this->brandLogos as $bl) {
                    $bPath = PUBLIC_PATH . '/' . $bl['logo_url'];
                    if (!file_exists($bPath)) { $logoWidths[] = null; continue; }
                    list($iw, $ih) = @getimagesize($bPath) ?: [1, 1];
                    $ratio = $ih > 0 ? $iw / $ih : 1;
                    $bw = round($logoH * $ratio, 1);
                    if ($bw < 5)  $bw = 5;
                    if ($bw > 32) $bw = 32;
                    $logoWidths[] = $bw;
                }

                // Remove nulls (missing files) and compute spacing
                $validWidths = array_filter($logoWidths, fn($w) => $w !== null);
                $count = count($validWidths);
                if ($count > 0) {
                    $totalLogoW = array_sum($validWidths);
                    // Gap between logos so they are evenly distributed across the row
                    $gap = $count > 1 ? ($rowW - $totalLogoW) / ($count - 1) : 0;
                    if ($gap < 3)  $gap = 3;   // minimum gap
                    if ($gap > 25) $gap = 25;  // maximum gap (avoid huge spaces)
                    // Re-centre if there's only one logo or gap is capped
                    $actualTotal = $totalLogoW + $gap * ($count - 1);
                    $lx = $mL + ($rowW - $actualTotal) / 2;

                    $i = 0;
                    foreach ($this->brandLogos as $bl) {
                        $bPath = PUBLIC_PATH . '/' . $bl['logo_url'];
                        if (!file_exists($bPath)) continue;
                        $bw = $validWidths[$i] ?? null;
                        if ($bw === null) { $i++; continue; }
                        try {
                            $this->Image($bPath, $lx, $brandRowY, $bw, $logoH, '', '', '', true, 150,
                                         '', false, false, 0, 'CM', false, false);
                        } catch (Exception $e) { /* skip broken image */ }
                        $lx += $bw + $gap;
                        $i++;
                    }
                }
                $brandRowY += $logoH + 2;
            }

            // ── Bottom separator ──────────────────────────────────────────
            $this->SetDrawColor($this->hR, $this->hG, $this->hB);
            $this->SetLineWidth(0.6);
            $this->Line($mL, $brandRowY + 1, $pageW - $mR, $brandRowY + 1);
            $this->SetLineWidth(0.2);
            $this->SetDrawColor(0, 0, 0);

            $this->headerHeight = $brandRowY + 5;
        }

        public function Footer() {
            $this->SetY(-12);
            $this->SetFont('helvetica', 'I', 7.5);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    // ── Create PDF ────────────────────────────────────────────────────────
    $pdf = new QuotationPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->setData($company, $quotation, $vendor, $brandLogos, $hR, $hG, $hB);
    $pdf->SetCreator('Sistema de Cotizaciones');
    $pdf->SetAuthor($company['company_name'] ?? 'Empresa');
    $pdf->SetTitle('Cotización ' . $quotation['quotation_number']);
    $pdf->SetMargins(15, 55, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $contentY = $pdf->headerHeight;
    $pdf->SetY($contentY);

    // ── Payment / meta data ───────────────────────────────────────────────
    $paymentCondition = $quotation['payment_condition'] ?? 'cash';
    $paymentText = $paymentCondition === 'credit' ? 'Crédito' : 'Contado';
    if ($paymentCondition === 'credit' && !empty($quotation['credit_days'])) {
        $paymentText .= ' (' . $quotation['credit_days'] . ' días)';
    }

    // ── Customer info box ─────────────────────────────────────────────────
    $boxY = $pdf->GetY();
    $pageW = $pdf->getPageWidth();
    $mL = 15; $mR = 15;
    $usable = $pageW - $mL - $mR;
    $leftW  = $usable * 0.62;
    $rightW = $usable - $leftW;
    $leftX  = $mL;
    $rightX = $mL + $leftW + 2;
    $cellH  = 5.5;
    $boxPad = 2;

    // Draw box background
    $pdf->SetFillColor(248, 249, 252);
    $pdf->Rect($leftX, $boxY, $usable, 28, 'FD');

    // Left side — customer data
    $cy2 = $boxY + $boxPad;
    $lblW = 20;
    $valW = $leftW - $lblW - $boxPad * 2;

    $customerFields = [
        'Señores'   => $customer['name'] ?? '',
        'Dirección' => $customer['address'] ?? '',
        'R.U.C.'    => $customer['tax_id'] ?? '',
        'Atención'  => $customer['contact_person'] ?? '',
    ];
    foreach ($customerFields as $label => $val) {
        if (empty($val)) continue;
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($leftX + $boxPad, $cy2);
        $pdf->Cell($lblW, $cellH, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($leftX + $boxPad + $lblW, $cy2);
        $pdf->MultiCell($valW, $cellH, $val, 0, 'L', false, 1, $leftX + $boxPad + $lblW, $cy2);
        $cy2 = $pdf->GetY();
    }

    // Right side — quotation meta
    $metaFields = [
        'Fecha'          => date('d/m/Y', strtotime($quotation['quotation_date'])),
        'Cond. de Pago'  => $paymentText,
        'Moneda'         => $currencyLabel,
    ];
    if ($quotation['valid_until']) {
        $metaFields['Válida hasta'] = date('d/m/Y', strtotime($quotation['valid_until']));
    }
    $my2 = $boxY + $boxPad;
    $mlblW = 24;
    $mvalW = $rightW - $mlblW - 4;
    foreach ($metaFields as $label => $val) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($rightX, $my2);
        $pdf->Cell($mlblW, $cellH, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($mvalW, $cellH, $val, 0, 1, 'L');
        $my2 += $cellH;
    }

    // Intro line
    $pdf->SetY(max($cy2, $my2) + 3);
    $pdf->SetFont('helvetica', 'I', 8);
    $validity = $quotation['validity_days'] ?? 7;
    $pdf->Cell(0, 5,
        'De acuerdo a su amable solicitud, le hacemos llegar la siguiente propuesta:    ' .
        'Validez de la cotización: ' . $validity . ' días',
        0, 2, 'L');
    $pdf->Ln(1);

    // ── Items table ───────────────────────────────────────────────────────
    // Column widths (total = 180mm)
    $cItem  = 10;
    $cCode  = 22;
    $cDesc  = 60;
    $cUnd   = 12;
    $cQty   = 13;
    $cPUnit = 25;
    $cDisc  = 13;
    $cTotal = 25;

    // Header row
    $pdf->SetFillColor($hR, $hG, $hB);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetDrawColor(255, 255, 255);
    $pdf->Cell($cItem,  7, 'Item',        1, 0, 'C', true);
    $pdf->Cell($cCode,  7, 'Código',      1, 0, 'C', true);
    $pdf->Cell($cDesc,  7, 'Descripción', 1, 0, 'C', true);
    $pdf->Cell($cUnd,   7, 'UND',         1, 0, 'C', true);
    $pdf->Cell($cQty,   7, 'Cantidad',    1, 0, 'C', true);
    $pdf->Cell($cPUnit, 7, 'Precio Unit.',1, 0, 'C', true);
    $pdf->Cell($cDisc,  7, 'Desc.%',      1, 0, 'C', true);
    $pdf->Cell($cTotal, 7, 'Total',       1, 1, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetFont('helvetica', '', 8);

    $totalDiscount = 0;
    $subtotalNeto  = 0;

    foreach ($quotation['items'] as $idx => $item) {
        $isEven = ($idx % 2 === 0);
        $pdf->SetFillColor($isEven ? 255 : 248, $isEven ? 255 : 249, $isEven ? 255 : 252);

        $descText  = $item['description'] ?? '';
        $codeText  = $item['product_code'] ?? '';

        // Strip embedded [CODE] prefix from description if present
        if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $descText, $m)) {
            if (empty($codeText)) $codeText = $m[1];
            $descText = trim($m[2]);
        }

        $rowH = 6;

        // Estimate row height for description
        $descLines = $pdf->getNumLines($descText, $cDesc - 2);
        if ($descLines > 1) $rowH = max($rowH, $descLines * 4 + 2);

        $rowY = $pdf->GetY();

        // Check page break
        if ($rowY + $rowH > $pdf->getPageHeight() - 25) {
            $pdf->AddPage();
            // Reprint table header on new page
            $pdf->SetFillColor($hR, $hG, $hB);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->Cell($cItem,  7, 'Item',        1, 0, 'C', true);
            $pdf->Cell($cCode,  7, 'Código',      1, 0, 'C', true);
            $pdf->Cell($cDesc,  7, 'Descripción', 1, 0, 'C', true);
            $pdf->Cell($cUnd,   7, 'UND',         1, 0, 'C', true);
            $pdf->Cell($cQty,   7, 'Cantidad',    1, 0, 'C', true);
            $pdf->Cell($cPUnit, 7, 'Precio Unit.',1, 0, 'C', true);
            $pdf->Cell($cDisc,  7, 'Desc.%',      1, 0, 'C', true);
            $pdf->Cell($cTotal, 7, 'Total',       1, 1, 'C', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor($isEven ? 255 : 248, $isEven ? 255 : 249, $isEven ? 255 : 252);
            $rowY = $pdf->GetY();
        }

        $pdf->Cell($cItem, $rowH, $idx + 1, 1, 0, 'C', true);
        $pdf->Cell($cCode, $rowH, $codeText, 1, 0, 'C', true);

        // Description MultiCell
        $pdf->SetXY($mL + $cItem + $cCode, $rowY);
        $pdf->MultiCell($cDesc, $rowH, $descText, 1, 'L', true, 0);

        $pdf->SetXY($mL + $cItem + $cCode + $cDesc, $rowY);
        $pdf->Cell($cUnd,   $rowH, 'UND',                                                                   1, 0, 'C', true);
        $pdf->Cell($cQty,   $rowH, number_format((float)($item['quantity'] ?? 0), 2),                       1, 0, 'C', true);
        $pdf->Cell($cPUnit, $rowH, $currencySymbol . ' ' . number_format((float)($item['unit_price'] ?? 0), 2), 1, 0, 'R', true);
        $pdf->Cell($cDisc,  $rowH, number_format((float)($item['discount_percentage'] ?? 0), 1) . '%',      1, 0, 'C', true);
        $pdf->Cell($cTotal, $rowH, $currencySymbol . ' ' . number_format((float)($item['line_total'] ?? 0), 2), 1, 1, 'R', true);

        $totalDiscount += (float)($item['discount_amount'] ?? 0);
        $subtotalNeto  += (float)($item['line_total'] ?? 0);
    }

    // ── Totals box ────────────────────────────────────────────────────────
    $pdf->Ln(3);
    $igvMode = $quotation['igv_mode'] ?? 'included';
    $total   = (float)($quotation['total'] ?? 0);

    if ($igvMode === 'plus_igv') {
        $subtotalSinIGV = $total / 1.18;
        $igvAmount      = $total - $subtotalSinIGV;
        $totalVenta     = $subtotalSinIGV;
    } else {
        $subtotalSinIGV = $total / 1.18;
        $igvAmount      = $total - $subtotalSinIGV;
        $totalVenta     = $subtotalSinIGV;
    }

    if ($quotation['global_discount_percentage'] > 0) {
        $totalDiscount += (float)($quotation['global_discount_amount'] ?? 0);
    }

    $totalsX  = $mL + $cItem + $cCode + $cDesc + $cUnd + $cQty;  // starts at Price Unit col
    $lblColW  = $cPUnit + $cDisc;  // 38mm label
    $valColW  = $cTotal;           // 25mm value

    $totalsRows = [
        ['label' => 'DSCTO. TOTAL',   'value' => $totalDiscount > 0 ? '-' . $currencySymbol . ' ' . number_format($totalDiscount, 2) : $currencySymbol . ' -', 'bold' => false, 'bg' => false],
        ['label' => 'TOTAL VENTA',    'value' => $currencySymbol . ' ' . number_format($totalVenta, 2),   'bold' => false, 'bg' => false],
        ['label' => 'I.G.V.',         'value' => $currencySymbol . ' ' . number_format($igvAmount, 2),    'bold' => false, 'bg' => false],
        ['label' => 'TOTAL IMPORTE',  'value' => $currencySymbol . ' ' . number_format($total, 2),        'bold' => true,  'bg' => true],
    ];

    foreach ($totalsRows as $tRow) {
        $pdf->SetX($totalsX);
        if ($tRow['bg']) {
            $pdf->SetFillColor($hR, $hG, $hB);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($lblColW, 7, $tRow['label'], 1, 0, 'L', true);
            $pdf->Cell($valColW, 7, $tRow['value'], 1, 1, 'R', true);
            $pdf->SetTextColor(0, 0, 0);
        } else {
            $pdf->SetFillColor(248, 249, 252);
            $pdf->SetFont('helvetica', $tRow['bold'] ? 'B' : '', 8.5);
            $pdf->Cell($lblColW, 6, $tRow['label'], 1, 0, 'L', true);
            $pdf->Cell($valColW, 6, $tRow['value'], 1, 1, 'R', true);
        }
    }

    // ── Notes / Observations ──────────────────────────────────────────────
    if (!empty($quotation['notes']) || !empty($quotation['terms_and_conditions'])) {
        $pdf->Ln(4);

        if (!empty($quotation['notes'])) {
            // Dark header bar like reference PDF
            $obsY = $pdf->GetY();
            $pdf->SetFillColor($hR, $hG, $hB);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(25, 6, 'Observaciones:', 'LTB', 0, 'L', true);
            $pdf->SetFillColor(248, 249, 252);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell($usable - 25, 6, $quotation['notes'], 'RTB', 'L', true, 1);
        }

        if (!empty($quotation['terms_and_conditions'])) {
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'B', 8.5);
            $pdf->Cell(0, 5, 'Términos y Condiciones:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->MultiCell(0, 4.5, $quotation['terms_and_conditions'], 0, 'L', false, 1);
        }
    }

    // ── Bank accounts ─────────────────────────────────────────────────────
    if (!empty($bankAccounts)) {
        $pdf->Ln(4);
        $pdf->SetFillColor($hR, $hG, $hB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 6, '  Datos Bancarios para Transferencias', 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(235, 238, 245);
        $pdf->Cell(50, 5, 'Banco / Moneda', 1, 0, 'C', true);
        $pdf->Cell(65, 5, 'Número de Cuenta', 1, 0, 'C', true);
        $pdf->Cell(65, 5, 'CCI', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetFillColor(255, 255, 255);
        foreach ($bankAccounts as $acct) {
            $pdf->Cell(50, 5, $acct['bank_name'] . ' (' . $acct['currency'] . ')', 1, 0, 'L', true);
            $pdf->Cell(65, 5, ucfirst($acct['account_type']) . ': ' . $acct['account_number'], 1, 0, 'L', true);
            $pdf->Cell(65, 5, $acct['cci'] ?? '-', 1, 1, 'L', true);
        }
    }

    // ── Vendor signature area ─────────────────────────────────────────────
    $pdf->Ln(8);
    if ($pdf->GetY() > 240) {
        $pdf->AddPage();
        $pdf->SetY($pdf->headerHeight + 5);
    }

    $sigY   = $pdf->GetY();
    $sigLX  = $mL;
    $sigRX  = $mL + $usable / 2 + 5;
    $sigHW  = $usable / 2 - 5;

    // Vendor (left)
    $pdf->SetXY($sigLX, $sigY);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Cell($sigHW, 5, 'Atentamente:', 0, 1, 'L');

    if (!empty($vendor['signature_url']) && file_exists(__DIR__ . '/../' . $vendor['signature_url'])) {
        $sigPath = __DIR__ . '/../' . $vendor['signature_url'];
        $pdf->Image($sigPath, $sigLX, $pdf->GetY(), 40, 15, 'PNG', '', '', true, 300,
                    '', false, false, 0, false, false, false);
        $pdf->SetXY($sigLX, $pdf->GetY() + 16);
    } else {
        $pdf->SetX($sigLX);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell($sigHW, 12, '', 'B', 1, 'L');
    }

    $vendorName = trim(($vendor['first_name'] ?? '') . ' ' . ($vendor['last_name'] ?? ''));
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetX($sigLX);
    $pdf->Cell($sigHW, 4.5, $vendorName, 0, 1, 'L');
    if (!empty($vendor['phone'])) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX($sigLX);
        $pdf->Cell($sigHW, 4, 'WhatsApp: ' . $vendor['phone'], 0, 1, 'L');
    }
    if (!empty($vendor['email'])) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX($sigLX);
        $pdf->Cell($sigHW, 4, $vendor['email'], 0, 1, 'L');
    }
    if (!empty($company['company_address'])) {
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetX($sigLX);
        $pdf->MultiCell($sigHW, 4, $company['company_address'], 0, 'L', false, 1, $sigLX, $pdf->GetY());
    }

    // Customer (right)
    $pdf->SetXY($sigRX, $sigY);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Cell($sigHW, 5, 'Firma del Cliente:', 0, 1, 'L');
    $pdf->SetX($sigRX);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell($sigHW, 12, '', 'B', 1, 'L');
    $pdf->SetX($sigRX);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Cell($sigHW, 4.5, $customer['name'] ?? '', 0, 1, 'L');
    if (!empty($customer['tax_id'])) {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetX($sigRX);
        $taxLabel = strlen($customer['tax_id']) == 8 ? 'DNI: ' : 'RUC: ';
        $pdf->Cell($sigHW, 4, $taxLabel . $customer['tax_id'], 0, 1, 'L');
    }

    // ── Product images (4-column grid) ────────────────────────────────────
    // Pre-collect valid image items (resolve URL→local path once)
    $imageItems = [];
    foreach ($quotation['items'] as $item) {
        $imageUrl = $item['product_image_url'] ?? $item['image_url'] ?? null;
        if (empty($imageUrl)) continue;

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $baseUrl = rtrim(BASE_URL, '/');
            if (str_starts_with($imageUrl, $baseUrl)) {
                $relativePath = ltrim(substr($imageUrl, strlen($baseUrl)), '/');
                $localPath    = PUBLIC_PATH . '/' . $relativePath;
            } else {
                $relativePath = parse_url($imageUrl, PHP_URL_PATH);
                $relativePath = preg_replace('#^/public/#', '', $relativePath);
                $localPath    = PUBLIC_PATH . '/' . ltrim($relativePath, '/');
            }
            $imagePath = file_exists($localPath) ? $localPath : $imageUrl;
        } else {
            $imagePath = PUBLIC_PATH . '/' . ltrim($imageUrl, '/');
        }

        $capText = $item['description'] ?? '';
        if (preg_match('/^\[[^\]]+\]\s*(.*)$/s', $capText, $cm)) $capText = trim($cm[1]);
        $caption = mb_substr($capText, 0, 35) . (mb_strlen($capText) > 35 ? '…' : '');

        $imageItems[] = ['path' => $imagePath, 'caption' => $caption];
    }

    if (!empty($imageItems)) {
        $imgH       = 38;   // image area height
        $imgW       = 42;   // cell width
        $imgGap     = 4;    // horizontal gap between cells
        $perRow     = 4;
        $cellH      = $imgH + 8;   // border-box height (46mm)
        $rowSpacing = $imgH + 10;  // Y increment per row   (48mm)
        $bottomMgn  = 20;          // reserved bottom margin

        $numRows  = (int)ceil(count($imageItems) / $perRow);
        // Total height: (rows-1)*rowSpacing + cellH
        $totalH   = ($numRows - 1) * $rowSpacing + $cellH;
        // Space needed: Ln(8) pre-gap + title(6) + images
        $needed   = 8 + 6 + $totalH;

        $available = $pdf->getPageHeight() - $bottomMgn - $pdf->GetY();

        if ($available < $needed) {
            $pdf->AddPage();
            $pdf->SetY($pdf->headerHeight + 4);
        } else {
            $pdf->Ln(8);
        }

        // Section title
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor($hR, $hG, $hB);
        $pdf->Cell(0, 5, 'IMÁGENES DE PRODUCTOS', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(1);

        $imgRowY  = $pdf->GetY();
        $imgX     = $mL;
        $imgCount = 0;

        foreach ($imageItems as $imgItem) {
            if ($imgCount > 0 && $imgCount % $perRow === 0) {
                $imgRowY += $rowSpacing;
                $imgX     = $mL;
                if ($imgRowY + $cellH > $pdf->getPageHeight() - $bottomMgn) {
                    $pdf->AddPage();
                    $imgRowY = $pdf->headerHeight + 4;
                }
            }

            // Border box
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->Rect($imgX, $imgRowY, $imgW, $cellH, 'D');

            try {
                $pdf->Image($imgItem['path'], $imgX + 1, $imgRowY + 1, $imgW - 2, $imgH - 2,
                            '', '', '', true, 150, '', false, false, 0, false, false, false);
            } catch (Exception $e) {
                $pdf->SetXY($imgX, $imgRowY + $imgH / 2 - 3);
                $pdf->SetFont('helvetica', 'I', 7);
                $pdf->MultiCell($imgW, 3, 'Imagen no disponible', 0, 'C', false, 1);
            }

            // Caption inside the border box
            $pdf->SetXY($imgX, $imgRowY + $imgH - 1);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->MultiCell($imgW, 3, $imgItem['caption'], 0, 'C', false, 1);

            $imgX += $imgW + $imgGap;
            $imgCount++;
        }
    }

    // ── Output ────────────────────────────────────────────────────────────
    $filename = 'Cotizacion_' . $quotation['quotation_number'] . '.pdf';

    if (!empty($GLOBALS['_PDF_RETURN_AS_STRING'])) {
        // Called from email sender — return PDF binary via echo into caller's buffer
        echo $pdf->Output('', 'S');
    } else {
        // Called directly — discard GD/libpng warnings then send download
        while (ob_get_level()) ob_end_clean();
        $pdf->Output($filename, 'D');
    }

} catch (Exception $e) {
    if (empty($GLOBALS['_PDF_RETURN_AS_STRING'])) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
    }
    error_log("Error generating PDF: " . $e->getMessage());
    exit('Error al generar el PDF: ' . $e->getMessage());
}
