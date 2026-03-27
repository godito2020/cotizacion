<?php
/**
 * API para solicitar facturación de una cotización aceptada
 * Cambia el billing_status a 'Pending' para que aparezca en la cola de facturación
 */

require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$quotationId = $_POST['quotation_id'] ?? 0;
$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

if (empty($quotationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de cotización requerido']);
    exit;
}

try {
    $db = getDBConnection();

    // Verificar que la cotización existe y está aceptada
    $stmt = $db->prepare("
        SELECT id, quotation_number, status, billing_status
        FROM quotations
        WHERE id = ? AND company_id = ?
    ");
    $stmt->execute([$quotationId, $companyId]);
    $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
        exit;
    }

    // Verificar que está aceptada
    if (strtolower($quotation['status']) !== 'accepted') {
        echo json_encode(['success' => false, 'message' => 'Solo se pueden facturar cotizaciones aceptadas']);
        exit;
    }

    // Verificar que no esté ya facturada
    if (!empty($quotation['billing_status']) && $quotation['billing_status'] === 'Invoiced') {
        echo json_encode(['success' => false, 'message' => 'Esta cotización ya fue facturada']);
        exit;
    }

    // Verificar que no esté ya en proceso de facturación
    if (!empty($quotation['billing_status']) && $quotation['billing_status'] === 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Esta cotización ya está pendiente de facturación']);
        exit;
    }

    // Actualizar el billing_status a 'Pending'
    $stmt = $db->prepare("
        UPDATE quotations
        SET billing_status = 'Pending',
            updated_at = NOW()
        WHERE id = ? AND company_id = ?
    ");
    $stmt->execute([$quotationId, $companyId]);

    // Registrar en el log de actividad
    $activityLog = new ActivityLog();
    $activityLog->log(
        $userId,
        $companyId,
        'invoice_requested',
        'quotation',
        $quotationId,
        "Solicitud de facturación para cotización {$quotation['quotation_number']}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'Cotización enviada a facturación. Aparecerá en la cola de pendientes.'
    ]);

} catch (Exception $e) {
    error_log("Error in request_invoice.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}
