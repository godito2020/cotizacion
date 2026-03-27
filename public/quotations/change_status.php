<?php
require_once __DIR__ . '/../../includes/init.php';

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
$newStatus = $_POST['status'] ?? '';
$companyId = $auth->getCompanyId();

$validStatuses = ['Draft', 'Sent', 'Accepted', 'Rejected', 'Invoiced'];

if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

try {
    $quotationRepo = new Quotation();
    $quotation = $quotationRepo->getById($quotationId, $companyId);

    if (!$quotation) {
        echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
        exit;
    }

    $result = $quotationRepo->updateStatus($quotationId, $companyId, $newStatus);

    if ($result) {
        Notification::notifyQuotationStatusChange(
            $quotation['user_id'], $companyId, $quotationId,
            $quotation['quotation_number'], $newStatus
        );
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
    }

} catch (Exception $e) {
    error_log("Error changing quotation status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>