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
$companyId = $auth->getCompanyId();

try {
    $quotationRepo = new Quotation();
    $quotation = $quotationRepo->getById($quotationId, $companyId);

    if (!$quotation) {
        echo json_encode(['success' => false, 'message' => 'Cotización no encontrada']);
        exit;
    }

    // Check if user can delete this quotation
    $user = $auth->getUser();
    if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']) && $quotation['user_id'] != $user['id']) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar esta cotización']);
        exit;
    }

    // Delete quotation items first
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
    $stmt->execute([$quotationId]);

    // Delete quotation
    $stmt = $db->prepare("DELETE FROM quotations WHERE id = ? AND company_id = ?");
    $result = $stmt->execute([$quotationId, $companyId]);

    if ($result) {
        // Log the deletion
        $activityLog = new ActivityLog();
        $activityLog->log($companyId, $auth->getUserId(), 'quotation_deleted', "Cotización {$quotation['quotation_number']} eliminada", $quotationId);

        echo json_encode(['success' => true, 'message' => 'Cotización eliminada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la cotización']);
    }

} catch (Exception $e) {
    error_log("Error deleting quotation: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>