<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$companyId = $auth->getCompanyId();
$user = $auth->getUser();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$customerEmail = $data['customer_email'] ?? '';
$customerName = $data['customer_name'] ?? '';
$htmlContent = $data['html_content'] ?? '';
$subject = $data['subject'] ?? 'Cotización';
$pdfContent = $data['pdf_content'] ?? ''; // Base64 encoded PDF
$pdfFilename = $data['pdf_filename'] ?? 'cotizacion.pdf';

if (empty($customerEmail) || empty($htmlContent)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email y contenido son requeridos']);
    exit;
}

try {
    // Get email settings from database
    $companySettings = new CompanySettings();
    $emailSettings = $companySettings->getEmailSettings($companyId);
    $company = $companySettings->getCompanyInfo($companyId);
    $bankAccounts = $companySettings->getBankAccounts($companyId, true); // only active accounts

    // Validate email settings
    if ($emailSettings['use_smtp']) {
        $missingFields = [];
        if (empty($emailSettings['smtp_host'])) $missingFields[] = 'Servidor SMTP';
        if (empty($emailSettings['smtp_username'])) $missingFields[] = 'Usuario SMTP';
        if (empty($emailSettings['smtp_password'])) $missingFields[] = 'Contraseña SMTP';
        if (empty($emailSettings['smtp_port'])) $missingFields[] = 'Puerto SMTP';

        if (!empty($missingFields)) {
            throw new Exception('Configuración SMTP incompleta: ' . implode(', ', $missingFields));
        }
    }

    if (empty($emailSettings['from_email'])) {
        throw new Exception('Email remitente no configurado. Por favor, configura el correo en Administración > Configuración de Correo.');
    }

    // Build simple text email (PDF will be attached)
    $hasPDF = !empty($pdfContent);

    $emailHtml = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
            .email-container { max-width: 800px; margin: 20px auto; background: white; }
            .email-header { background-color: #0d6efd; color: white; padding: 20px; text-align: center; }
            .email-body { padding: 20px; }
            .email-footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #dee2e6; }
            .bank-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <h1 style="margin: 0;">Cotización - ' . htmlspecialchars($company['name'] ?? 'Sistema de Cotizaciones') . '</h1>
            </div>
            <div class="email-body">
                <p>Estimado/a <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
                <p>Le enviamos la cotización solicitada adjunta en formato PDF.</p>';

    if ($hasPDF) {
        $emailHtml .= '
                <p><strong>📎 Adjunto:</strong> El documento PDF contiene todos los detalles de la cotización.</p>';
    }

    // Add bank accounts if available
    if (!empty($bankAccounts)) {
        $emailHtml .= '
                <div class="bank-info">
                    <h3 style="color: #0d6efd; margin-top: 0;">Datos Bancarios para Transferencias:</h3>';

        foreach ($bankAccounts as $account) {
            $emailHtml .= '
                    <p style="margin: 8px 0;">
                        <strong>' . htmlspecialchars($account['bank_name']) . '</strong> (' . htmlspecialchars($account['currency']) . ')<br>
                        <span style="margin-left: 10px;">' . ucfirst(htmlspecialchars($account['account_type'])) . ': ' . htmlspecialchars($account['account_number']) . '</span>';

            if (!empty($account['cci'])) {
                $emailHtml .= '<br><span style="margin-left: 10px;">CCI: ' . htmlspecialchars($account['cci']) . '</span>';
            }

            if (!empty($account['account_holder'])) {
                $emailHtml .= '<br><span style="margin-left: 10px;">Titular: ' . htmlspecialchars($account['account_holder']) . '</span>';
            }

            $emailHtml .= '
                    </p>';
        }

        $emailHtml .= '
                </div>';
    }

    $emailHtml .= '
                <p>Si tiene alguna pregunta o requiere alguna aclaración, no dude en contactarnos.</p>
                <p>Atentamente,<br>
                <strong>' . htmlspecialchars($user['username'] ?? 'Sistema de Cotizaciones') . '</strong><br>
                ' . htmlspecialchars($company['name'] ?? 'Sistema de Cotizaciones') . '</p>
            </div>
            <div class="email-footer">
                <p>Este es un correo automático generado por el Sistema de Cotizaciones.</p>
                <p>' . htmlspecialchars($company['name'] ?? '') . ' | ' . htmlspecialchars($emailSettings['from_email'] ?? '') . '</p>
            </div>
        </div>
    </body>
    </html>
    ';

    // Prepare attachments
    $attachments = [];
    if (!empty($pdfContent)) {
        $attachments[] = [
            'content' => $pdfContent,
            'filename' => $pdfFilename,
            'type' => 'application/pdf'
        ];
        error_log("PDF attachment prepared: " . $pdfFilename . ", content length: " . strlen($pdfContent));
    } else {
        error_log("No PDF content received for attachment");
    }

    // Use sendEmail function (inline implementation to avoid require loops)
    $result = sendQuotationEmail($customerEmail, $subject, $emailHtml, $companyId, $attachments);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Correo enviado exitosamente a ' . $customerEmail
        ]);
    } else {
        throw new Exception('No se pudo enviar el correo. Verifique la configuración SMTP.');
    }

} catch (Exception $e) {
    error_log("Error sending quotation email: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Simplified email sending function for quotations
function sendQuotationEmail($to, $subject, $message, $companyId, $attachments = []) {
    try {
        $companySettings = new CompanySettings();
        $emailSettings = $companySettings->getEmailSettings($companyId);

        if (empty($emailSettings['from_email'])) {
            throw new Exception('Email remitente no configurado');
        }

        if ($emailSettings['use_smtp']) {
            return sendQuotationViaSmtp($to, $subject, $message, $emailSettings, $attachments);
        } else {
            return sendQuotationViaPhpMail($to, $subject, $message, $emailSettings, $attachments);
        }

    } catch (Exception $e) {
        error_log("Quotation email sending error: " . $e->getMessage());
        throw $e;
    }
}

function sendQuotationViaSmtp($to, $subject, $message, $emailSettings, $attachments = []) {
    $host = $emailSettings['smtp_host'];
    $port = $emailSettings['smtp_port'];
    $username = $emailSettings['smtp_username'];
    $password = $emailSettings['smtp_password'];
    $encryption = $emailSettings['smtp_encryption'];

    // Log para debugging
    $logFile = __DIR__ . '/../../logs/email_debug.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Sending email with " . count($attachments) . " attachment(s)\n", FILE_APPEND);
    if (!empty($attachments)) {
        foreach ($attachments as $i => $att) {
            file_put_contents($logFile, "  Attachment $i: {$att['filename']}, type: {$att['type']}, size: " . strlen($att['content']) . " bytes\n", FILE_APPEND);
        }
    }

    // Function to wrap long lines to comply with RFC 5321 (max 998 chars per line)
    $wrapMessage = function($message) {
        $lines = explode("\n", $message);
        $wrapped = [];

        foreach ($lines as $line) {
            // Remove any existing \r
            $line = str_replace("\r", "", $line);

            // If line is longer than 998 characters, split it
            if (strlen($line) > 998) {
                $chunks = str_split($line, 998);
                foreach ($chunks as $chunk) {
                    $wrapped[] = $chunk;
                }
            } else {
                $wrapped[] = $line;
            }
        }

        return implode("\r\n", $wrapped);
    };

    try {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Iniciando envío SMTP\n", FILE_APPEND);
        file_put_contents($logFile, "Host: $host, Port: $port, Username: $username, Encryption: $encryption\n", FILE_APPEND);
        file_put_contents($logFile, "Password length: " . strlen($password) . "\n", FILE_APPEND);

        $contextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $context = stream_context_create($contextOptions);

        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        file_put_contents($logFile, "Conectando a: $host:$port\n", FILE_APPEND);

        if ($encryption === 'ssl') {
            $smtp = @stream_socket_client($host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        } else {
            $smtp = @fsockopen($host, $port, $errno, $errstr, 10);
        }

        if (!$smtp) {
            file_put_contents($logFile, "Error de conexión: $errstr ($errno)\n", FILE_APPEND);
            throw new Exception("No se pudo conectar al servidor SMTP: $errstr ($errno)");
        }

        file_put_contents($logFile, "Conexión establecida\n", FILE_APPEND);

        stream_set_timeout($smtp, 10);
        stream_set_blocking($smtp, true);

        // Helper function to read multiline SMTP responses
        $readResponse = function($smtp, $logFile) {
            $response = '';
            do {
                $line = fgets($smtp, 512);
                $response .= $line;
                file_put_contents($logFile, "  << " . trim($line) . "\n", FILE_APPEND);
                // Check if this is the last line (no dash after code)
                if (preg_match('/^\d{3} /', $line)) {
                    break;
                }
            } while ($line !== false);
            return $response;
        };

        // Read welcome
        file_put_contents($logFile, "Reading welcome message:\n", FILE_APPEND);
        $response = $readResponse($smtp, $logFile);

        // EHLO
        $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
        file_put_contents($logFile, "Sending EHLO:\n", FILE_APPEND);
        fputs($smtp, "EHLO $hostname\r\n");
        $response = $readResponse($smtp, $logFile);

        // Start TLS if needed
        if ($encryption === 'tls') {
            file_put_contents($logFile, "Starting TLS:\n", FILE_APPEND);
            fputs($smtp, "STARTTLS\r\n");
            $response = $readResponse($smtp, $logFile);
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            file_put_contents($logFile, "Sending EHLO after TLS:\n", FILE_APPEND);
            fputs($smtp, "EHLO $hostname\r\n");
            $response = $readResponse($smtp, $logFile);
        }

        // Authenticate
        file_put_contents($logFile, "Iniciando autenticación:\n", FILE_APPEND);
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = $readResponse($smtp, $logFile);

        file_put_contents($logFile, "Sending username:\n", FILE_APPEND);
        fputs($smtp, base64_encode($username) . "\r\n");
        $response = $readResponse($smtp, $logFile);

        file_put_contents($logFile, "Sending password:\n", FILE_APPEND);
        fputs($smtp, base64_encode($password) . "\r\n");
        $response = $readResponse($smtp, $logFile);

        // Get the last line's response code
        $lines = explode("\n", trim($response));
        $lastLine = end($lines);
        $responseCode = substr(trim($lastLine), 0, 3);

        file_put_contents($logFile, "Auth response code: $responseCode\n", FILE_APPEND);

        if ($responseCode !== '235') {
            file_put_contents($logFile, "Error de autenticación. Código: $responseCode, Respuesta completa: " . trim($response) . "\n", FILE_APPEND);
            throw new Exception("Error en autenticación: credenciales inválidas. Código: $responseCode, Respuesta: " . trim($lastLine));
        }

        file_put_contents($logFile, "Autenticación exitosa\n", FILE_APPEND);

        // MAIL FROM
        file_put_contents($logFile, "Sending MAIL FROM:\n", FILE_APPEND);
        fputs($smtp, "MAIL FROM: <" . $emailSettings['from_email'] . ">\r\n");
        $response = $readResponse($smtp, $logFile);

        // RCPT TO
        file_put_contents($logFile, "Sending RCPT TO:\n", FILE_APPEND);
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = $readResponse($smtp, $logFile);

        // DATA
        file_put_contents($logFile, "Sending DATA:\n", FILE_APPEND);
        fputs($smtp, "DATA\r\n");
        $response = $readResponse($smtp, $logFile);

        // Send email
        file_put_contents($logFile, "Sending email content:\n", FILE_APPEND);

        // Wrap message to comply with RFC 5321 line length limits
        $wrappedMessage = $wrapMessage($message);
        file_put_contents($logFile, "Message lines before wrap: " . substr_count($message, "\n") . "\n", FILE_APPEND);
        file_put_contents($logFile, "Message lines after wrap: " . substr_count($wrappedMessage, "\n") . "\n", FILE_APPEND);

        $headers = "From: " . $emailSettings['from_name'] . " <" . $emailSettings['from_email'] . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        // Build email body with attachments if present
        $emailBody = '';

        if (!empty($attachments)) {
            // Generate boundary
            $boundary = md5(time());
            $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            $headers .= "\r\n";

            // HTML part
            $emailBody .= "--$boundary\r\n";
            $emailBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $emailBody .= "Content-Transfer-Encoding: 8bit\r\n";
            $emailBody .= "\r\n";
            $emailBody .= $wrappedMessage . "\r\n";

            // Attachments
            foreach ($attachments as $attachment) {
                $emailBody .= "--$boundary\r\n";
                $emailBody .= "Content-Type: " . $attachment['type'] . "; name=\"" . $attachment['filename'] . "\"\r\n";
                $emailBody .= "Content-Transfer-Encoding: base64\r\n";
                $emailBody .= "Content-Disposition: attachment; filename=\"" . $attachment['filename'] . "\"\r\n";
                $emailBody .= "\r\n";

                // Split base64 content into 76-character lines (RFC 2045)
                $emailBody .= chunk_split($attachment['content'], 76, "\r\n");
            }

            $emailBody .= "--$boundary--\r\n";
        } else {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "\r\n";
            $emailBody = $wrappedMessage;
        }

        fputs($smtp, $headers . $emailBody . "\r\n.\r\n");
        $response = $readResponse($smtp, $logFile);

        // QUIT
        file_put_contents($logFile, "Sending QUIT:\n", FILE_APPEND);
        fputs($smtp, "QUIT\r\n");
        $response = $readResponse($smtp, $logFile);
        fclose($smtp);

        file_put_contents($logFile, "Correo enviado exitosamente\n", FILE_APPEND);
        file_put_contents($logFile, str_repeat("=", 50) . "\n\n", FILE_APPEND);

        return true;

    } catch (Exception $e) {
        file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($logFile, str_repeat("=", 50) . "\n\n", FILE_APPEND);

        if (isset($smtp) && is_resource($smtp)) {
            fclose($smtp);
        }
        throw $e;
    }
}

function sendQuotationViaPhpMail($to, $subject, $message, $emailSettings, $attachments = []) {
    if (!empty($attachments)) {
        // With attachments, use multipart
        $boundary = md5(time());

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'From: ' . $emailSettings['from_name'] . ' <' . $emailSettings['from_email'] . '>',
            'X-Mailer: PHP/' . phpversion()
        ];

        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $message . "\r\n";

        foreach ($attachments as $attachment) {
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: " . $attachment['type'] . "; name=\"" . $attachment['filename'] . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"" . $attachment['filename'] . "\"\r\n\r\n";
            $body .= chunk_split($attachment['content']) . "\r\n";
        }

        $body .= "--$boundary--";

        $result = mail($to, $subject, $body, implode("\r\n", $headers));
    } else {
        // Without attachments, simple HTML email
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $emailSettings['from_name'] . ' <' . $emailSettings['from_email'] . '>',
            'X-Mailer: PHP/' . phpversion()
        ];

        $result = mail($to, $subject, $message, implode("\r\n", $headers));
    }

    if (!$result) {
        throw new Exception('PHP mail() falló. Verifica la configuración del servidor.');
    }

    return true;
}
?>
