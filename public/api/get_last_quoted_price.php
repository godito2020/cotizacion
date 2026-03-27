<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$productId = $_GET['product_id'] ?? null;
$customerId = $_GET['customer_id'] ?? null;

if (!$productId || !$customerId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetros requeridos: product_id y customer_id']);
    exit;
}

try {
    $quotationRepo = new Quotation();
    $lastPrice = $quotationRepo->getLastQuotedPrice((int)$productId, (int)$customerId, $companyId);

    if ($lastPrice) {
        $currency = $lastPrice['currency'] ?? 'USD';
        $currencySymbol = $currency === 'PEN' ? 'S/.' : '$';

        echo json_encode([
            'success' => true,
            'last_price' => $lastPrice['unit_price'],
            'formatted_price' => number_format($lastPrice['unit_price'], 2),
            'discount_percentage' => $lastPrice['discount_percentage'],
            'quotation_number' => $lastPrice['quotation_number'],
            'created_at' => $lastPrice['created_at'],
            'currency' => $currency,
            'currency_symbol' => $currencySymbol
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró precio anterior para este producto']);
    }

} catch (Exception $e) {
    error_log("Error getting last quoted price: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>