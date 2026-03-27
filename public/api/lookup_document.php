<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$companyId = $auth->getCompanyId();
$action = $_GET['action'] ?? $_POST['action'] ?? $_GET['type'] ?? $_POST['type'] ?? '';
$document = $_GET['document'] ?? $_POST['document'] ?? '';

if (empty($document)) {
    echo json_encode(['success' => false, 'message' => 'Document number is required']);
    exit;
}

// Auto-detect document type if not provided
if (empty($action)) {
    $documentLength = strlen($document);
    if ($documentLength == 8) {
        $action = 'dni';
    } elseif ($documentLength == 10 || $documentLength == 11) {
        $action = 'ruc';
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid document length. DNI must be 8 digits, RUC must be 10 or 11 digits.']);
        exit;
    }
}

try {
    // Log the request for debugging
    error_log("API Lookup Request - Action: $action, Document: $document, Company: $companyId");

    $apiClient = new PeruApiClient($companyId);

    switch ($action) {
        case 'dni':
            $result = $apiClient->consultarDni($document);
            break;
        case 'ruc':
            $result = $apiClient->consultarRuc($document);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action. Use "dni" or "ruc"']);
            exit;
    }

    // Log the result for debugging
    error_log("API Lookup Result - Success: " . ($result['success'] ? 'YES' : 'NO') .
              ", Message: " . ($result['message'] ?? 'N/A'));

    // Normalize response format for frontend
    if ($result['success'] && isset($result['data'])) {
        // Return the data in the format expected by the frontend (data.data)
        $response = [
            'success' => true,
            'data' => $result['data']  // Keep original data structure for frontend
        ];

        echo json_encode($response);
    } else {
        echo json_encode($result);
    }

} catch (Exception $e) {
    error_log("Document lookup error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error performing lookup: ' . $e->getMessage()
    ]);
}
?>