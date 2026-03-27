<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$companyId = $auth->getCompanyId();
$document = $_GET['document'] ?? $_POST['document'] ?? '';
$type = $_GET['type'] ?? $_POST['type'] ?? 'ruc'; // ruc or dni

if (empty($document)) {
    echo json_encode(['success' => false, 'message' => 'Documento requerido']);
    exit;
}

try {
    $peruApiClient = new PeruApiClient($companyId);

    if ($type === 'ruc') {
        // Validate RUC format
        if (strlen($document) !== 11 || !is_numeric($document)) {
            echo json_encode(['success' => false, 'message' => 'RUC debe tener 11 dígitos']);
            exit;
        }

        $result = $peruApiClient->consultarRuc($document, $companyId);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'document' => $result['data']['ruc'],
                    'business_name' => $result['data']['razon_social'],
                    'commercial_name' => $result['data']['nombre_comercial'],
                    'address' => $result['data']['direccion'],
                    'district' => $result['data']['distrito'],
                    'province' => $result['data']['provincia'],
                    'department' => $result['data']['departamento'],
                    'state' => $result['data']['estado'],
                    'condition' => $result['data']['condicion'],
                    'document_type' => 'ruc'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }

    } elseif ($type === 'dni') {
        // Validate DNI format
        if (strlen($document) !== 8 || !is_numeric($document)) {
            echo json_encode(['success' => false, 'message' => 'DNI debe tener 8 dígitos']);
            exit;
        }

        $result = $peruApiClient->consultarDni($document, $companyId);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'document' => $result['data']['dni'],
                    'first_name' => $result['data']['nombres'],
                    'last_name' => $result['data']['apellido_paterno'] . ' ' . $result['data']['apellido_materno'],
                    'full_name' => $result['data']['nombre_completo'],
                    'document_type' => 'dni'
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo de documento inválido']);
    }

} catch (Exception $e) {
    error_log("Error searching customer: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la consulta: ' . $e->getMessage()
    ]);
}
?>