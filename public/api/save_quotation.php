<?php
// Suprimir warnings que rompen el JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

// Log para debug
error_log("save_quotation.php called - POST data: " . print_r($_POST, true));

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$companyId = $auth->getCompanyId();
$user = $auth->getUser();

// Get POST data
$data = $_POST;

$errors = [];

// Validation
if (empty($data['customer_id']) || !is_numeric($data['customer_id']) || (int)$data['customer_id'] <= 0) {
    $errors['customer_id'] = 'Debe seleccionar un cliente válido';
}

if (empty($data['quotation_date'])) {
    $errors['quotation_date'] = 'La fecha es requerida';
}

if (empty($data['items']) || !is_array($data['items'])) {
    $errors['items'] = 'Debe agregar al menos un producto';
} else {
    $validItems = [];
    foreach ($data['items'] as $index => $item) {
        // Solo procesar items que tengan al menos descripción
        if (!empty(trim($item['description']))) {
            // Si no tiene cantidad o precio, usar valores por defecto
            $quantity = isset($item['quantity']) && $item['quantity'] !== '' ? (float)$item['quantity'] : 1;
            $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float)$item['unit_price'] : 0;

            // product_id puede ser código COBOL (string) o ID local (int)
            // Si es string alfanumérico (código COBOL), guardarlo como NULL en product_id
            // y agregar el código al inicio de la descripción
            $productId = $item['product_id'] ?? null;
            $description = trim($item['description']);

            // Si product_id es alfanumérico (código COBOL), no es un ID válido de la tabla products
            if ($productId && !is_numeric($productId)) {
                // Agregar código al inicio de descripción si no está ya
                if (strpos($description, $productId) === false) {
                    $description = '[' . $productId . '] ' . $description;
                }
                $productId = null; // No hay producto local, usar NULL
            } else {
                $productId = $productId ? (int)$productId : null;
            }

            $validItems[] = [
                'product_id' => $productId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percentage' => (float)($item['discount_percentage'] ?? 0),
                'image_url' => $item['image_url'] ?? null
            ];
        }
    }

    if (empty($validItems)) {
        $errors['items'] = 'Debe agregar al menos un producto válido con descripción, cantidad y precio';
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Errores de validación', 'errors' => $errors]);
    exit;
}

try {
    error_log("Creating quotation - validItems: " . json_encode($validItems));

    $quotationRepo = new Quotation();

    // Get currency from form
    $currency = $data['currency'] ?? 'USD';

    error_log("Calling quotationRepo->create with companyId=$companyId, customerId={$data['customer_id']}, currency=$currency");

    // Payment condition
    $paymentCondition = $data['payment_condition'] ?? 'cash';
    $creditDays = ($paymentCondition === 'credit' && !empty($data['credit_days'])) ? (int)$data['credit_days'] : null;

    // IGV mode
    $igvMode = $data['price_includes_igv'] ?? 'included';

    $quotationId = $quotationRepo->create(
        $companyId,
        (int)$data['customer_id'],
        $user['id'],
        $data['quotation_date'],
        $data['valid_until'] ?: null,
        $validItems,
        (float)($data['global_discount_percentage'] ?? 0),
        $data['notes'] ?: null,
        $data['terms_and_conditions'] ?: null,
        'Draft',
        $currency,
        $paymentCondition,
        $creditDays,
        $igvMode
    );

    if ($quotationId) {
        // Obtener número de cotización para la notificación
        $qData = $quotationRepo->getById($quotationId, $companyId);
        $qNumber = $qData['quotation_number'] ?? "COT-$quotationId";
        Notification::notifyQuotationCreated($user['id'], $companyId, $quotationId, $qNumber);

        echo json_encode([
            'success' => true,
            'message' => 'Cotización guardada exitosamente',
            'quotation_id' => $quotationId
        ]);
    } else {
        throw new Exception('Error al crear la cotización');
    }

} catch (Exception $e) {
    error_log("Error saving quotation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar: ' . $e->getMessage()
    ]);
}
