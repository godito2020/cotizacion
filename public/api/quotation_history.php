<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$companyId = $auth->getCompanyId();
$action = $_GET['action'] ?? '';

$quotationRepo = new Quotation();

try {
    switch ($action) {
        case 'last_price':
            $productId = $_GET['product_id'] ?? 0;
            $customerId = $_GET['customer_id'] ?? 0;

            if (!$productId || !$customerId) {
                throw new Exception('Product ID and Customer ID are required');
            }

            $lastPrice = $quotationRepo->getLastQuotedPrice($productId, $customerId, $companyId);

            echo json_encode([
                'success' => true,
                'data' => $lastPrice
            ]);
            break;

        case 'customer_history':
            $customerId = $_GET['customer_id'] ?? 0;

            if (!$customerId) {
                throw new Exception('Customer ID is required');
            }

            $history = $quotationRepo->getCustomerQuotationHistory($customerId, $companyId);

            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;

        case 'product_history':
            $customerId = $_GET['customer_id'] ?? 0;
            $productId = $_GET['product_id'] ?? 0;

            if (!$customerId || !$productId) {
                throw new Exception('Customer ID and Product ID are required');
            }

            $history = $quotationRepo->getCustomerProductHistory($customerId, $productId, $companyId);

            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;

        case 'products_by_date':
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $customerId = $_GET['customer_id'] ?? null;

            $history = $quotationRepo->getProductHistoryByDate($companyId, $dateFrom, $dateTo, $customerId);

            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>