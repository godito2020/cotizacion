<?php
require_once __DIR__ . '/../../includes/init.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/email_debug.log');

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$quotationId = $_POST['id'] ?? 0;
$companyId = $auth->getCompanyId();

error_log("=== ENVÍO DE CORREO INICIADO ===");
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

    // Check if quotation is already accepted
    $forceResend = $_POST['force_resend'] ?? false;

    if ($quotation['status'] === 'Accepted' && !$forceResend) {
        error_log("ADVERTENCIA: Cotización ya está aceptada");
        echo json_encode([
            'success' => false,
            'message' => 'Esta cotización ya fue ACEPTADA por el cliente. ¿Está seguro de reenviarla?',
            'needs_confirmation' => true,
            'status' => 'Accepted'
        ]);
        exit;
    }

    if ($quotation['status'] === 'Accepted' && $forceResend) {
        error_log("Reenviando cotización aceptada con confirmación del usuario");
    }

    // Get email settings from database
    $companySettings = new CompanySettings();
    $smtpEnabled = $companySettings->getSetting($companyId, 'smtp_enabled');
    $smtpHost = $companySettings->getSetting($companyId, 'smtp_host');
    $smtpPort = $companySettings->getSetting($companyId, 'smtp_port');
    $smtpUser = $companySettings->getSetting($companyId, 'smtp_username');
    $smtpPassEncrypted = $companySettings->getSetting($companyId, 'smtp_password');

    // Decrypt SMTP password
    $smtpPass = decryptSmtpPassword($smtpPassEncrypted);

    $smtpEncryption = $companySettings->getSetting($companyId, 'smtp_encryption');
    $fromEmail = $companySettings->getSetting($companyId, 'email_from');
    $fromName = $companySettings->getSetting($companyId, 'email_from_name');

    error_log("SMTP Enabled: " . ($smtpEnabled ? 'Yes' : 'No'));
    error_log("SMTP Host: $smtpHost");
    error_log("SMTP User: $smtpUser");
    error_log("SMTP Password length: " . strlen($smtpPass) . " chars (decrypted)");
    error_log("From Email: $fromEmail");

    if (!$fromEmail) {
        echo json_encode(['success' => false, 'message' => 'Configuración de correo no completa - falta email remitente']);
        exit;
    }

    if ($smtpEnabled && (!$smtpHost || !$smtpUser || !$smtpPass)) {
        echo json_encode(['success' => false, 'message' => 'Configuración SMTP incompleta']);
        exit;
    }

    // Get customer email
    $customerRepo = new Customer();
    $customer = $customerRepo->getById($quotation['customer_id'], $companyId);

    if (!$customer || !$customer['email']) {
        error_log("ERROR: Cliente sin email");
        echo json_encode(['success' => false, 'message' => 'Cliente no tiene correo electrónico configurado']);
        exit;
    }

    error_log("Email destino: " . $customer['email']);

    // Generate PDF file
    error_log("Generando PDF...");
    $pdfContent = generateQuotationPDF($quotationId, $companyId);

    if (!$pdfContent) {
        error_log("ERROR: No se pudo generar el PDF");
        echo json_encode(['success' => false, 'message' => 'Error al generar el PDF']);
        exit;
    }

    error_log("PDF generado exitosamente, tamaño: " . strlen($pdfContent) . " bytes");

    // Generate approval token - expires based on quotation's valid_until date
    $token = bin2hex(random_bytes(32));

    // Use quotation's valid_until date if available, otherwise default to 30 days
    if (!empty($quotation['valid_until'])) {
        // Set expiration to end of the valid_until day (23:59:59)
        $expiresAt = date('Y-m-d 23:59:59', strtotime($quotation['valid_until']));
    } else {
        // Default to 30 days if no valid_until is set
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    }

    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO quotation_approval_tokens (quotation_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$quotationId, $token, $expiresAt]);

    error_log("Token de aprobación generado: " . $token);

    // Build approval URL
    $approvalUrl = BASE_URL . "/quotations/approve.php?token=" . $token;

    // Get bank accounts
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT bank_name, account_number, account_type, currency FROM bank_accounts WHERE company_id = ? AND is_active = 1 ORDER BY is_default DESC");
    $stmt->execute([$companyId]);
    $bankAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare email
    $companyName = $companySettings->getSetting($companyId, 'company_name') ?: 'Nuestra Empresa';
    $companyRuc = $companySettings->getSetting($companyId, 'company_tax_id') ?: '20381499627';
    $companyPhone = $companySettings->getSetting($companyId, 'company_phone') ?: '';
    $companyEmail = $companySettings->getSetting($companyId, 'company_email') ?: $fromEmail;

    // Load vendor (salesperson) to set Reply-To and personalize From display name
    $vendorReplyTo = '';
    $db2 = getDBConnection();
    $vStmt = $db2->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $vStmt->execute([$quotation['user_id']]);
    $vendor = $vStmt->fetch(PDO::FETCH_ASSOC);
    if ($vendor && !empty($vendor['email'])) {
        $vendorReplyTo = trim($vendor['email']);
        $vendorFullName = trim(($vendor['first_name'] ?? '') . ' ' . ($vendor['last_name'] ?? ''));
        if ($vendorFullName) {
            // Show vendor name as sender display; actual From stays as company SMTP email
            $fromName = $vendorFullName . ' | ' . $companyName;
        }
    }

    $subject = "Cotización {$quotation['quotation_number']} - {$companyName}";

    // Calculate validity text for email
    $validityText = !empty($quotation['valid_until'])
        ? 'Válido hasta ' . date('d/m/Y', strtotime($quotation['valid_until']))
        : 'Válido por 30 días';

    // Create HTML email
    $htmlMessage = generateHtmlEmailBody(
        $customer['name'],
        $quotation['quotation_number'],
        $approvalUrl,
        $companyName,
        $companyRuc,
        $companyPhone,
        $companyEmail,
        $bankAccounts,
        $quotation['currency'],
        $validityText
    );

    // Create plain text version as fallback
    $plainMessage = "Estimado/a {$customer['name']},\n\n";
    $plainMessage .= "Le enviamos adjunta la cotización {$quotation['quotation_number']}.\n\n";
    $plainMessage .= "Por favor revise el documento adjunto.\n\n";
    $plainMessage .= "Para aprobar o rechazar esta cotización, haga clic en el siguiente enlace:\n";
    $plainMessage .= $approvalUrl . "\n\n";
    $plainMessage .= "Este enlace es válido por 5 días.\n\n";
    $plainMessage .= "Atentamente,\n{$fromName}\n{$companyName}";

    // Send email with PDF attachment
    $emailSent = false;

    if ($smtpEnabled && $smtpHost && $smtpUser && $smtpPass) {
        error_log("Intentando enviar vía SMTP...");
        $emailSent = sendEmailWithAttachment(
            $customer['email'],
            $subject,
            $htmlMessage,
            $pdfContent,
            "Cotizacion_{$quotation['quotation_number']}.pdf",
            $fromEmail,
            $fromName,
            $smtpHost,
            $smtpPort,
            $smtpUser,
            $smtpPass,
            $smtpEncryption,
            $vendorReplyTo
        );
    } else {
        error_log("Intentando enviar vía mail() PHP...");
        $emailSent = sendEmailWithAttachmentBasic(
            $customer['email'],
            $subject,
            $htmlMessage,
            $pdfContent,
            "Cotizacion_{$quotation['quotation_number']}.pdf",
            $fromEmail,
            $fromName,
            $vendorReplyTo
        );
    }

    if ($emailSent) {
        // Update quotation status to "Sent"
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE quotations SET status = 'Sent' WHERE id = ? AND company_id = ?");
            $stmt->execute([$quotationId, $companyId]);
            error_log("Estado de cotización actualizado a 'Sent'");
        } catch (Exception $e) {
            error_log("Error actualizando estado: " . $e->getMessage());
            // Continue even if status update fails
        }

        // Log the email send
        $activityLog = new ActivityLog();
        $activityLog->log(
            $auth->getUserId(),
            $companyId,
            'email_sent',
            'quotation',
            $quotationId,
            "Cotización {$quotation['quotation_number']} enviada por correo a {$customer['email']}"
        );

        error_log("Correo enviado exitosamente");
        echo json_encode(['success' => true, 'message' => 'Cotización enviada por correo exitosamente']);
    } else {
        error_log("ERROR: No se pudo enviar el correo");
        echo json_encode(['success' => false, 'message' => 'Error al enviar el correo. Revise logs/email_debug.log']);
    }

} catch (Exception $e) {
    error_log("EXCEPCIÓN: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

/**
 * Generate PDF content for quotation using pdf.php
 */
function generateQuotationPDF($quotationId, $companyId) {
    try {
        error_log("Generando PDF usando pdf.php...");

        // Tell pdf.php to echo the PDF string into the buffer instead of sending headers
        $GLOBALS['_PDF_RETURN_AS_STRING'] = true;

        // Capture the output of pdf.php
        ob_start();

        // Set GET parameter for pdf.php
        $_GET['id'] = $quotationId;

        // Include pdf.php to generate the PDF
        include __DIR__ . '/pdf.php';

        // Get the PDF content
        $pdfContent = ob_get_clean();

        // Reset flag
        $GLOBALS['_PDF_RETURN_AS_STRING'] = false;

        // Verify it's a valid PDF
        if (empty($pdfContent) || substr($pdfContent, 0, 4) !== '%PDF') {
            error_log("ERROR: El contenido generado no es un PDF válido");
            error_log("Primeros 100 caracteres: " . substr($pdfContent, 0, 100));
            return false;
        }

        error_log("PDF generado exitosamente, tamaño: " . strlen($pdfContent) . " bytes");

        return $pdfContent;

    } catch (Exception $e) {
        $GLOBALS['_PDF_RETURN_AS_STRING'] = false;
        error_log("Error generando PDF: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Send email with PDF attachment using basic mail()
 */
function sendEmailWithAttachmentBasic($to, $subject, $message, $pdfContent, $pdfFilename, $fromEmail, $fromName, $replyTo = '') {
    try {
        // Generate boundary
        $boundary = md5(uniqid(time()));

        // Headers
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $replyToAddr = $replyTo ?: $fromEmail;
        $headers .= "Reply-To: {$replyToAddr}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

        // Message body - use multipart/alternative for HTML
        $altBoundary = md5(uniqid(time() . 'alt'));

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n";
        $body .= "\r\n";

        // Plain text version (extract from HTML if needed)
        $plainText = strip_tags($message);
        $plainText = wordwrap($plainText, 75, "\r\n", true);
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $plainText . "\r\n\r\n";

        // HTML version (if message contains HTML tags)
        if (strpos($message, '<html') !== false || strpos($message, '<!DOCTYPE') !== false) {
            $body .= "--{$altBoundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($message), 76, "\r\n");
            $body .= "\r\n";
        }

        $body .= "--{$altBoundary}--\r\n\r\n";

        // PDF attachment
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n\r\n";
        // Ensure PDF is split into 76 char lines with CRLF
        $body .= chunk_split(base64_encode($pdfContent), 76, "\r\n");
        $body .= "\r\n";
        $body .= "--{$boundary}--";

        error_log("Enviando con mail() a: $to");
        return mail($to, $subject, $body, $headers);

    } catch (Exception $e) {
        error_log("Error en sendEmailWithAttachmentBasic: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email with PDF attachment using SMTP
 */
function sendEmailWithAttachment($to, $subject, $message, $pdfContent, $pdfFilename, $fromEmail, $fromName, $host, $port, $username, $password, $encryption = 'tls', $replyTo = '') {
    try {
        error_log("Conectando a SMTP: $host:$port");

        // Connect to SMTP server
        $socket = @fsockopen(($encryption === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 30);

        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }

        // Read server greeting (may be multiline)
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            error_log("Greeting line: " . trim($line));
            // Stop when we get a line that doesn't start with "220-"
            if (substr($line, 0, 4) === '220 ' || substr($line, 3, 1) === ' ') {
                break;
            }
        }
        error_log("Server greeting complete: " . trim(substr($response, 0, 100)));

        if (!checkSmtpResponse($response, '220')) {
            error_log("Invalid server greeting");
            fclose($socket);
            return false;
        }

        // Send EHLO
        fputs($socket, "EHLO localhost\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            error_log("EHLO line: " . trim($line));
            // Stop when we get a line with space in position 3 (means last line)
            if (substr($line, 3, 1) === ' ') break;
        }
        error_log("EHLO response complete: " . trim(substr($response, 0, 100)));

        // Start TLS if required (only for port 587, not for SSL port 465)
        if ($encryption === 'tls' && $port != 465) {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            error_log("STARTTLS response: " . trim($response));

            if (!checkSmtpResponse($response, '220')) {
                fclose($socket);
                return false;
            }

            // Upgrade to TLS
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("TLS upgrade failed");
                fclose($socket);
                return false;
            }

            // Send EHLO again after TLS
            fputs($socket, "EHLO localhost\r\n");
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            error_log("EHLO after TLS: " . trim($response));
        }

        // Send AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        error_log("AUTH response: " . trim($response));

        if (!checkSmtpResponse($response, '334')) {
            fclose($socket);
            return false;
        }

        // Send username
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        if (!checkSmtpResponse($response, '334')) {
            error_log("Username rejected");
            fclose($socket);
            return false;
        }

        // Send password
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        if (!checkSmtpResponse($response, '235')) {
            error_log("Authentication failed");
            fclose($socket);
            return false;
        }

        error_log("Authentication successful");

        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
        $response = fgets($socket, 515);
        if (!checkSmtpResponse($response, '250')) {
            error_log("MAIL FROM rejected");
            fclose($socket);
            return false;
        }

        // Send RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        if (!checkSmtpResponse($response, '250')) {
            error_log("RCPT TO rejected");
            fclose($socket);
            return false;
        }

        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (!checkSmtpResponse($response, '354')) {
            error_log("DATA command rejected");
            fclose($socket);
            return false;
        }

        // Generate boundary
        $boundary = md5(uniqid(time()));

        // Build email content with attachment
        $replyToAddr = $replyTo ?: $fromEmail;
        $emailContent = "Subject: $subject\r\n";
        $emailContent .= "From: {$fromName} <{$fromEmail}>\r\n";
        $emailContent .= "Reply-To: {$replyToAddr}\r\n";
        $emailContent .= "To: <$to>\r\n";
        $emailContent .= "MIME-Version: 1.0\r\n";
        $emailContent .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $emailContent .= "\r\n";

        // Message body - use multipart/alternative for HTML and plain text
        $altBoundary = md5(uniqid(time() . 'alt'));

        $emailContent .= "--{$boundary}\r\n";
        $emailContent .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n";
        $emailContent .= "\r\n";

        // Plain text version - extract text from HTML and wrap lines
        $plainText = strip_tags($message);
        $plainText = wordwrap($plainText, 75, "\r\n", true);

        $emailContent .= "--{$altBoundary}\r\n";
        $emailContent .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $emailContent .= "Content-Transfer-Encoding: 7bit\r\n";
        $emailContent .= "\r\n";
        $emailContent .= $plainText . "\r\n";
        $emailContent .= "\r\n";

        // HTML version (if message contains HTML tags)
        if (strpos($message, '<html') !== false || strpos($message, '<!DOCTYPE') !== false) {
            $emailContent .= "--{$altBoundary}\r\n";
            $emailContent .= "Content-Type: text/html; charset=UTF-8\r\n";
            $emailContent .= "Content-Transfer-Encoding: base64\r\n";
            $emailContent .= "\r\n";
            // Use base64 encoding - guarantees max 76 chars per line
            $emailContent .= chunk_split(base64_encode($message), 76, "\r\n");
            $emailContent .= "\r\n";
        }

        $emailContent .= "--{$altBoundary}--\r\n";
        $emailContent .= "\r\n";

        // PDF attachment
        $emailContent .= "--{$boundary}\r\n";
        $emailContent .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\r\n";
        $emailContent .= "Content-Transfer-Encoding: base64\r\n";
        $emailContent .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\r\n";
        $emailContent .= "\r\n";
        // Ensure PDF is split into 76 char lines with CRLF
        $emailContent .= chunk_split(base64_encode($pdfContent), 76, "\r\n");
        $emailContent .= "\r\n";
        $emailContent .= "--{$boundary}--\r\n";
        $emailContent .= ".\r\n";

        // Send email content
        fputs($socket, $emailContent);

        $response = fgets($socket, 515);
        error_log("Send response: " . trim($response));

        if (!checkSmtpResponse($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        error_log("Email sent successfully via SMTP");
        return true;

    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check SMTP response code
 */
function checkSmtpResponse($response, $expectedCode) {
    return substr($response, 0, 3) === $expectedCode;
}

/**
 * Decrypt SMTP password
 */
function decryptSmtpPassword($encryptedPassword) {
    if (empty($encryptedPassword)) {
        return '';
    }

    try {
        $key = hash('sha256', 'cotizacion_secret_key');
        $iv = substr(hash('sha256', 'cotizacion_iv'), 0, 16);
        $decrypted = openssl_decrypt($encryptedPassword, 'AES-256-CBC', $key, 0, $iv);

        if ($decrypted === false) {
            error_log("Warning: Failed to decrypt SMTP password, using as-is");
            return $encryptedPassword; // Return as-is if decryption fails
        }

        return $decrypted;
    } catch (Exception $e) {
        error_log("Error decrypting SMTP password: " . $e->getMessage());
        return $encryptedPassword; // Return as-is on error
    }
}

/**
 * Generate elegant HTML email body
 */
function generateHtmlEmailBody($customerName, $quotationNumber, $approvalUrl, $companyName, $companyRuc, $companyPhone, $companyEmail, $bankAccounts, $currency, $validityText = 'Válido por 30 días') {
    $currencySymbol = $currency === 'USD' ? '$' : 'S/';

    // Build bank accounts HTML (short lines)
    $bankAccountsHtml = '';
    foreach ($bankAccounts as $account) {
        $accountCurrency = $account['currency'] === 'USD' ? 'USD' : 'PEN (Soles)';
        $bankAccountsHtml .= "<tr>\n";
        $bankAccountsHtml .= "<td style='padding:12px;border-bottom:1px solid #e9ecef;'>\n";
        $bankAccountsHtml .= "<strong style='color:#495057;'>{$account['bank_name']}</strong><br>\n";
        $bankAccountsHtml .= "<span style='color:#6c757d;font-size:14px;'>";
        $bankAccountsHtml .= "Cta. {$account['account_type']}: {$account['account_number']}</span><br>\n";
        $bankAccountsHtml .= "<span style='color:#007bff;font-weight:600;'>{$accountCurrency}</span>\n";
        $bankAccountsHtml .= "</td>\n";
        $bankAccountsHtml .= "</tr>\n";
    }

    // Build HTML in parts with short lines
    $html = "<!DOCTYPE html>\n";
    $html .= "<html lang='es'>\n<head>\n";
    $html .= "<meta charset='UTF-8'>\n";
    $html .= "<title>Cotización {$quotationNumber}</title>\n";
    $html .= "</head>\n";
    $html .= "<body style='margin:0;padding:0;font-family:Arial,sans-serif;";
    $html .= "background:#f4f4f4;'>\n";
    $html .= "<table width='100%' cellpadding='0' cellspacing='0' ";
    $html .= "style='background:#f4f4f4;padding:20px 0;'>\n<tr>\n<td align='center'>\n";
    $html .= "<table width='600' cellpadding='0' cellspacing='0' ";
    $html .= "style='background:#fff;border-radius:8px;'>\n";

    // Header
    $html .= "<tr>\n<td style='background:#667eea;padding:40px 30px;text-align:center;'>\n";
    $html .= "<h1 style='color:#fff;margin:0;font-size:28px;'>{$companyName}</h1>\n";
    $html .= "<p style='color:#f0f0f0;margin:10px 0 0;'>Nueva Cotización</p>\n";
    $html .= "</td>\n</tr>\n";

    // Main content
    $html .= "<tr>\n<td style='padding:40px 30px;'>\n";
    $html .= "<h2 style='color:#333;margin:0 0 20px;'>Estimado/a {$customerName},</h2>\n";
    $html .= "<p style='color:#666;line-height:1.6;margin:0 0 20px;'>\n";
    $html .= "Nos complace enviarle la cotización ";
    $html .= "<strong style='color:#667eea;'>{$quotationNumber}</strong> ";
    $html .= "adjunta.\n</p>\n";
    $html .= "<p style='color:#666;line-height:1.6;margin:0 0 30px;'>\n";
    $html .= "Revise el documento PDF adjunto.\n</p>\n";

    // Action button
    $html .= "<table width='100%' cellpadding='0' cellspacing='0' ";
    $html .= "style='margin:30px 0;'>\n<tr>\n";
    $html .= "<td align='center' style='padding:20px 0;background:#f8f9fa;'>\n";
    $html .= "<p style='color:#495057;margin:0 0 20px;font-weight:600;'>\n";
    $html .= "¿Desea aprobar esta cotización?\n</p>\n";
    $html .= "<a href='{$approvalUrl}' ";
    $html .= "style='display:inline-block;padding:14px 40px;background:#28a745;";
    $html .= "color:#fff;text-decoration:none;border-radius:6px;font-weight:600;'>";
    $html .= "APROBAR</a>\n";
    $html .= "<p style='color:#6c757d;margin:15px 0 0;font-size:13px;'>\n";
    $html .= "{$validityText}\n</p>\n";
    $html .= "</td>\n</tr>\n</table>\n";

    // Bank accounts
    $html .= "<div style='margin:30px 0;padding:25px;background:#f8f9fa;";
    $html .= "border-left:4px solid #667eea;'>\n";
    $html .= "<h3 style='color:#333;margin:0 0 20px;'>💳 Cuentas Bancarias</h3>\n";
    $html .= "<table width='100%' cellpadding='0' cellspacing='0' ";
    $html .= "style='background:#fff;'>\n";
    $html .= $bankAccountsHtml;
    $html .= "</table>\n</div>\n";

    // Important info
    $html .= "<div style='margin:30px 0;padding:25px;background:#fff3cd;";
    $html .= "border-left:4px solid #ffc107;'>\n";
    $html .= "<h3 style='color:#856404;margin:0 0 15px;'>";
    $html .= "⚠️ Información Importante</h3>\n";
    $html .= "<ul style='color:#856404;line-height:1.8;margin:0;";
    $html .= "padding-left:20px;font-size:14px;'>\n";
    $html .= "<li style='margin-bottom:8px;'>\n";
    $html .= "<strong>Orden de compra a nombre de:</strong><br>\n";
    $html .= "LLANTA SAN MARTIN S.R.LTDA. - RUC: {$companyRuc}\n</li>\n";
    $html .= "<li style='margin-bottom:8px;'>Mercadería en stock ";
    $html .= "<strong>salvo venta previa</strong>.</li>\n";
    $html .= "<li style='margin-bottom:8px;'>Consultar ";
    $html .= "<strong>tipo de cambio</strong> antes de pagar.</li>\n";
    $html .= "<li style='margin-bottom:8px;'>Precios ";
    $html .= "<strong>NO incluyen Percepción</strong>.</li>\n";
    $html .= "<li><strong>Agentes de Percepción:</strong> ";
    $html .= "Según D.S. N° 091-2013-EF (14/05/2013), ";
    $html .= "somos agentes desde 01/07/2013.</li>\n";
    $html .= "</ul>\n</div>\n";

    $html .= "</td>\n</tr>\n";

    // Footer
    $html .= "<tr>\n<td style='background:#343a40;padding:30px;text-align:center;'>\n";
    $html .= "<p style='color:#fff;margin:0 0 10px;font-weight:600;'>";
    $html .= "{$companyName}</p>\n";
    $html .= "<p style='color:#adb5bd;margin:0;font-size:14px;'>\n";
    $html .= "RUC: {$companyRuc}<br>\n";
    if ($companyPhone) $html .= "Tel: {$companyPhone}<br>\n";
    $html .= "Email: {$companyEmail}\n</p>\n";
    $html .= "<div style='margin-top:20px;padding-top:20px;";
    $html .= "border-top:1px solid #495057;'>\n";
    $html .= "<p style='color:#6c757d;margin:0;font-size:12px;'>\n";
    $html .= "Correo automático, no responder.<br>\n";
    $html .= "Contacto: {$companyEmail}\n</p>\n</div>\n";
    $html .= "</td>\n</tr>\n";

    $html .= "</table>\n</td>\n</tr>\n</table>\n";
    $html .= "</body>\n</html>";

    return $html;
}
?>
