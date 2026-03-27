<?php
/**
 * Optimized WhatsApp sending for quotations
 * Opens WhatsApp Web/App with pre-filled message including approval link
 */
require_once __DIR__ . '/../../includes/init.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/whatsapp_debug.log');

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$quotationId = $_POST['id'] ?? 0;
$companyId = $auth->getCompanyId();

error_log("=== ENVÍO POR WHATSAPP INICIADO ===");
error_log("Quotation ID: $quotationId");
error_log("Company ID: $companyId");

try {
    $quotationRepo = new Quotation();
    $quotation = $quotationRepo->getById($quotationId, $companyId);

    if (!$quotation) {
        error_log("ERROR: Cotización no encontrada");
        echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
        exit;
    }

    error_log("Cotización encontrada: " . $quotation['quotation_number']);

    // Get customer phone
    $customerRepo = new Customer();
    $customer = $customerRepo->getById($quotation['customer_id'], $companyId);

    if (!$customer) {
        error_log("ERROR: Cliente no encontrado");
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit;
    }

    if (!$customer['phone']) {
        error_log("ERROR: Cliente no tiene teléfono");
        echo json_encode(['success' => false, 'message' => 'Cliente no tiene teléfono configurado']);
        exit;
    }

    error_log("Teléfono del cliente: " . $customer['phone']);

    // Get company settings
    $companySettings = new CompanySettings();
    $companyName = $companySettings->getSetting($companyId, 'company_name') ?: 'LLANTA SAN MARTIN S.R.LTDA.';
    $companyRuc = $companySettings->getSetting($companyId, 'company_tax_id') ?: '20381499627';
    $companyPhone = $companySettings->getSetting($companyId, 'company_whatsapp') ?: '910253575';

    // Generate approval token (valid for 5 days)
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 days'));

    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO quotation_approval_tokens (quotation_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$quotationId, $token, $expiresAt]);

    error_log("Token de aprobación generado: " . $token);

    // Build URLs
    $approvalUrl = BASE_URL . "/quotations/approve.php?token=" . $token;
    $pdfUrl = BASE_URL . "/quotations/public_pdf.php?token=" . $token;
    $pdfDownloadUrl = BASE_URL . "/quotations/public_pdf.php?token=" . $token . "&download=1";

    // Check if customer has email - just to show in message
    $emailSent = false;
    if (!empty($customer['email'])) {
        error_log("Cliente tiene email: " . $customer['email']);

        // Check if already sent by email (just for informational purposes)
        $stmt = $db->prepare("SELECT COUNT(*) FROM activity_logs WHERE entity_type = 'quotation' AND entity_id = ? AND action = 'email_sent'");
        $stmt->execute([$quotationId]);
        $emailSent = $stmt->fetchColumn() > 0;

        if ($emailSent) {
            error_log("La cotización ya fue enviada por correo anteriormente");
        }
    } else {
        error_log("Cliente no tiene email configurado");
    }

    // NOTE: Email is NOT sent automatically here to improve response time
    // User should use the "Send Email" button separately if needed

    // Get bank accounts
    $stmt = $db->prepare("SELECT bank_name, account_number, account_type, currency FROM bank_accounts WHERE company_id = ? AND is_active = 1 ORDER BY is_default DESC LIMIT 2");
    $stmt->execute([$companyId]);
    $bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build WhatsApp message with beautiful formatting
    $message = generateWhatsAppMessage(
        $customer['name'],
        $quotation['quotation_number'],
        $approvalUrl,
        $pdfUrl,
        $emailSent,
        $customer['email'],
        $companyName,
        $companyRuc,
        $companyPhone,
        $bankAccounts
    );

    // Format phone number (remove spaces, dashes, etc.)
    $phone = preg_replace('/[^0-9]/', '', $customer['phone']);

    // If doesn't start with country code, assume Peru (+51)
    if (strlen($phone) == 9) {
        $phone = '51' . $phone;
    }

    error_log("Teléfono formateado: " . $phone);

    // Update quotation status to "Sent"
    try {
        $stmt = $db->prepare("UPDATE quotations SET status = 'Sent' WHERE id = ? AND company_id = ?");
        $stmt->execute([$quotationId, $companyId]);
        error_log("Estado de cotización actualizado a 'Sent'");
    } catch (Exception $e) {
        error_log("Error actualizando estado: " . $e->getMessage());
    }

    // Log the WhatsApp action
    $activityLog = new ActivityLog();
    $activityLog->log(
        $auth->getUserId(),
        $companyId,
        'whatsapp_opened',
        'quotation',
        $quotationId,
        "WhatsApp abierto para enviar cotización {$quotation['quotation_number']} a {$customer['name']}"
    );

    error_log("WhatsApp preparado exitosamente");

    // Return raw text + phone so JavaScript can encode with encodeURIComponent().
    // Do NOT use JSON_UNESCAPED_UNICODE: keeping emoji as \uXXXX (ASCII) prevents
    // IIS charset-conversion from corrupting the multi-byte UTF-8 sequences.
    echo json_encode([
        'success'      => true,
        'message'      => 'WhatsApp preparado exitosamente',
        'message_text' => $message,
        'phone'        => $phone
    ]);

} catch (Exception $e) {
    error_log("EXCEPCIÓN: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

/**
 * Generate elegant WhatsApp message
 */
function generateWhatsAppMessage($customerName, $quotationNumber, $approvalUrl, $pdfUrl, $emailSent, $customerEmail, $companyName, $companyRuc, $companyPhone, $bankAccounts) {
    // Use ASCII placeholders for emoji — JavaScript will substitute the real emoji
    // after JSON decoding, avoiding all PHP/IIS charset encoding issues.
    $sep = "=================";

    $message  = "[E:CIRCLE] *{$companyName}*\n";
    $message .= "{$sep}\n\n";

    $message .= "Hola *{$customerName}*,\n\n";
    $message .= "Le enviamos la cotizacion *{$quotationNumber}* [E:DOC]\n\n";

    if ($emailSent && $customerEmail) {
        $message .= "[E:MAIL] _Tambien enviada a su correo: {$customerEmail}_\n\n";
    }

    $message .= "[E:DOC] *VER/DESCARGAR COTIZACION*\n";
    $message .= "Ver PDF: {$pdfUrl}\n\n";

    $message .= "{$sep}\n";
    $message .= "[E:CHECK] *APROBAR/RECHAZAR*\n\n";
    $message .= "Para aprobar o rechazar esta cotizacion:\n";
    $message .= "[E:POINT] {$approvalUrl}\n\n";

    $message .= "{$sep}\n";
    $message .= "[E:CARD] *CUENTAS BANCARIAS*\n\n";

    foreach ($bankAccounts as $account) {
        $currency = $account['currency'] === 'USD' ? "[E:DOLLAR] USD" : "[E:MONEY] PEN";
        $message .= "*{$account['bank_name']}*\n";
        $message .= "{$currency} - Cta. {$account['account_type']}\n";
        $message .= "`{$account['account_number']}`\n\n";
    }

    $message .= "{$sep}\n";
    $message .= "[E:WARN] *INFORMACION IMPORTANTE*\n\n";
    $message .= "[E:PIN] Orden de compra a nombre de:\n";
    $message .= "*{$companyName}*\n";
    $message .= "RUC: *{$companyRuc}*\n\n";
    $message .= "- Stock sujeto a disponibilidad\n";
    $message .= "- Consultar tipo de cambio del dia\n";
    $message .= "- Precios NO incluyen percepcion\n";
    $message .= "- Somos agentes de percepcion (D.S. N 091-2013-EF)\n\n";

    $message .= "{$sep}\n";
    $message .= "[E:PHONE] *CONTACTO*\n";
    $message .= "WhatsApp: +51 {$companyPhone}\n\n";
    $message .= "[E:CHECK] _Enlaces validos por 5 dias_";

    return $message;
}
?>