<?php
/**
 * Public quotation approval page
 * Allows customers to approve or reject quotations via secure token link
 */

require_once __DIR__ . '/../../includes/init.php';

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? ''; // 'accept' or 'reject'

if (empty($token)) {
    die("Token inválido");
}

try {
    $db = getDBConnection();

    // Get quotation from token
    $stmt = $db->prepare("
        SELECT qat.*, q.id as quotation_id, q.quotation_number, q.status, q.company_id, q.user_id,
               c.name as customer_name, c.email as customer_email
        FROM quotation_approval_tokens qat
        INNER JOIN quotations q ON qat.quotation_id = q.id
        INNER JOIN customers c ON q.customer_id = c.id
        WHERE qat.token = ? AND qat.used_at IS NULL AND qat.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("El enlace ha expirado o ya fue utilizado");
    }

    $quotationId = $data['quotation_id'];
    $quotationNumber = $data['quotation_number'];
    $currentStatus = $data['status'];
    $companyId = $data['company_id'];
    $customerName = $data['customer_name'];
    $customerEmail = $data['customer_email'];

    // Process action if provided (only accept is allowed)
    if ($action === 'accept') {
        $newStatus = 'Accepted';

        // Update quotation status
        $stmt = $db->prepare("UPDATE quotations SET status = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$newStatus, $quotationId, $companyId]);

        // Mark token as used
        $stmt = $db->prepare("UPDATE quotation_approval_tokens SET used_at = NOW() WHERE token = ?");
        $stmt->execute([$token]);

        // Log activity
        $activityLog = new ActivityLog();
        $activityLog->log(
            0, // System action
            $companyId,
            'quotation_accepted',
            'quotation',
            $quotationId,
            "Cliente {$customerName} aceptó la cotización {$quotationNumber}"
        );

        // Create notification for quotation creator
        $notification = new Notification();
        $notificationType = 'quotation_accepted';
        $notificationTitle = '✅ Cotización Aceptada';
        $notificationMessage = "El cliente {$customerName} ha aceptado la cotización {$quotationNumber}";
        $notificationUrl = BASE_URL . '/quotations/view.php?id=' . $quotationId;

        $notification->create(
            $data['user_id'],
            $companyId,
            $notificationType,
            $notificationTitle,
            $notificationMessage,
            $quotationId,
            $notificationUrl
        );

        // Send confirmation email to company
        $companySettings = new CompanySettings();
        $companyEmail = $companySettings->getSetting($companyId, 'smtp_from_email') ?: 'admin@empresa.com';
        $companyName = $companySettings->getSetting($companyId, 'company_name') ?: 'Empresa';

        $subject = "Cotización {$quotationNumber} Aceptada";
        $message = "Estimado/a,\n\n";
        $message .= "El cliente {$customerName} ({$customerEmail}) ha ACEPTADO la cotización {$quotationNumber}.\n\n";
        $message .= "Fecha: " . date('d/m/Y H:i:s') . "\n\n";
        $message .= "Puede revisar los detalles en el sistema.\n\n";
        $message .= "Atentamente,\nSistema de Cotizaciones";

        // Send email to company
        @mail($companyEmail, $subject, $message, "From: noreply@cotizador.com\r\nReply-To: {$customerEmail}");

        $actionTaken = true;
    }

} catch (Exception $e) {
    error_log("Error in approve.php: " . $e->getMessage());
    die("Error al procesar la solicitud");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobación de Cotización</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .icon.success {
            background: #d4edda;
            color: #28a745;
        }

        .icon.danger {
            background: #f8d7da;
            color: #dc3545;
        }

        .icon.pending {
            background: #fff3cd;
            color: #ffc107;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .quotation-number {
            color: #667eea;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-accept {
            background: #28a745;
            color: white;
        }

        .btn-accept:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .customer-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }

        .customer-info h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .customer-info p {
            color: #666;
            margin: 5px 0;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($actionTaken) && $actionTaken): ?>
            <!-- Action completed -->
            <div class="icon success">
                ✓
            </div>
            <h1>Cotización Aceptada</h1>
            <div class="quotation-number"><?= htmlspecialchars($quotationNumber) ?></div>
            <div class="message">
                <p>Gracias por tu respuesta.</p>
                <p>Hemos registrado que has <strong>aceptado</strong> esta cotización.</p>
                <p>Recibirás una confirmación por correo electrónico próximamente.</p>
            </div>
        <?php else: ?>
            <!-- Pending action -->
            <div class="icon pending">
                📄
            </div>
            <h1>Cotización Pendiente</h1>
            <div class="quotation-number"><?= htmlspecialchars($quotationNumber) ?></div>

            <div class="customer-info">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($customerName) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($customerEmail) ?></p>
            </div>

            <div class="message">
                <p>Por favor, revisa la cotización adjunta en el correo que recibiste.</p>
                <p>¿Deseas aceptar esta cotización?</p>
            </div>

            <div class="buttons">
                <a href="?token=<?= htmlspecialchars($token) ?>&action=accept" class="btn btn-accept"
                   onclick="return confirm('¿Estás seguro de que deseas ACEPTAR esta cotización?')">
                    ✓ Aceptar Cotización
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
