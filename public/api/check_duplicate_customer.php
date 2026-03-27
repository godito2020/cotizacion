<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$companyId = $auth->getCompanyId();
$taxId = $_GET['tax_id'] ?? '';

if (empty($taxId)) {
    echo json_encode(['exists' => false, 'message' => 'Tax ID is required']);
    exit;
}

// Clean tax ID
$taxId = preg_replace('/[^0-9]/', '', $taxId);

if (strlen($taxId) != 8 && strlen($taxId) != 11) {
    echo json_encode(['exists' => false, 'message' => 'Invalid tax ID length']);
    exit;
}

try {
    $customerRepo = new Customer();
    $existingCustomer = $customerRepo->findByTaxId($taxId, $companyId);

    if ($existingCustomer) {
        echo json_encode([
            'exists' => true,
            'customer_name' => $existingCustomer['name'],
            'customer_id' => $existingCustomer['id'],
            'tax_id' => $taxId
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'tax_id' => $taxId
        ]);
    }

} catch (Exception $e) {
    error_log("Duplicate customer check error: " . $e->getMessage());
    echo json_encode([
        'exists' => false,
        'message' => 'Error checking duplicate customer'
    ]);
}
?>