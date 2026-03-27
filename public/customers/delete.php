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

$customerId = $_POST['id'] ?? 0;
$companyId = $auth->getCompanyId();
$isAdmin = $auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa']);

try {
    $customerRepo = new Customer();
    if ($isAdmin) {
        $customer = $customerRepo->getByIdGlobal($customerId);
    } else {
        $customer = $customerRepo->getById($customerId, $companyId);
    }

    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        exit;
    }

    $customerCompanyId = $customer['company_id'];

    // Check if customer has quotations
    $quotationRepo = new Quotation();
    $quotations = $quotationRepo->getQuotationsWithFilters($customerCompanyId, ['customer_id' => $customerId], 1, 1);

    if (!empty($quotations['quotations'])) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar un cliente con cotizaciones asociadas']);
        exit;
    }

    $result = $customerRepo->delete($customerId, $customerCompanyId);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Cliente eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el cliente']);
    }

} catch (Exception $e) {
    error_log("Error deleting customer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>