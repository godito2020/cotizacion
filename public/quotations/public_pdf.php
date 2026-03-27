<?php
/**
 * Public PDF viewer - Allows viewing/downloading quotation PDF via secure token
 * No authentication required - uses same token as approval system
 */

require_once __DIR__ . '/../../includes/init.php';

$token = $_GET['token'] ?? '';
$download = isset($_GET['download']) ? true : false;

if (empty($token)) {
    die("Token inválido");
}

try {
    $db = getDBConnection();

    // Get quotation from token
    $stmt = $db->prepare("
        SELECT qat.*, q.id as quotation_id, q.company_id
        FROM quotation_approval_tokens qat
        INNER JOIN quotations q ON qat.quotation_id = q.id
        WHERE qat.token = ? AND qat.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("El enlace ha expirado o es inválido");
    }

    $quotationId = $data['quotation_id'];
    $companyId = $data['company_id'];

    // Generate PDF - set flag to bypass auth check in pdf.php
    define('PUBLIC_PDF_ACCESS', true);
    define('PUBLIC_PDF_COMPANY_ID', $companyId);

    ob_start();
    $_GET['id'] = $quotationId;
    include __DIR__ . '/pdf.php';
    $pdfContent = ob_get_clean();

    // Verify it's a valid PDF
    if (empty($pdfContent) || substr($pdfContent, 0, 4) !== '%PDF') {
        die("Error al generar el PDF");
    }

    // Get quotation number for filename
    $quotationRepo = new Quotation();
    $quotation = $quotationRepo->getById($quotationId, $companyId);
    $filename = "Cotizacion_{$quotation['quotation_number']}.pdf";

    // Send PDF
    header('Content-Type: application/pdf');

    if ($download) {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }

    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $pdfContent;

} catch (Exception $e) {
    error_log("Error in public_pdf.php: " . $e->getMessage());
    die("Error al cargar el PDF");
}
?>
