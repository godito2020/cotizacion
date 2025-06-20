<?php
// cotizacion/public/api/index.php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../includes/init.php'; // For DB, models, etc.

// --- Helper function for JSON responses ---
function jsonResponse(int $statusCode, array $data, array $headers = []): void {
    http_response_code($statusCode);
    foreach ($headers as $headerName => $headerValue) {
        header("$headerName: $headerValue");
    }
    echo json_encode($data);
    exit;
}

// --- API Authentication and Routing ---
$apiAuth = new ApiAuth(); // Autoloader should handle this

$apiAuth->authenticate(function($user, $permissions) {
    // --- Basic URI Parsing for Routing ---
    // Example: /api/products/123 or /api/products
    // Assumes BASE_URL/api is the entry point.
    // Strip BASE_URL part if it's included in REQUEST_URI
    $baseApiUri = rtrim(BASE_URL, '/') . '/api';
    $requestUri = $_SERVER['REQUEST_URI'];

    // Remove query string if present
    if (false !== $pos = strpos($requestUri, '?')) {
        $requestUri = substr($requestUri, 0, $pos);
    }

    // Remove base API URI part
    if (strpos($requestUri, $baseApiUri) === 0) {
        $path = substr($requestUri, strlen($baseApiUri));
    } else {
         // This case might happen if .htaccess is not routing correctly or direct access
        $path = $requestUri;
    }

    $path = trim($path, '/');
    $segments = explode('/', $path);

    $resource = $segments[0] ?? null;
    $resourceId = $segments[1] ?? null;
    if ($resourceId !== null && !ctype_digit((string)$resourceId)) { // Ensure ID is numeric if present
        jsonResponse(400, ['error' => 'ID de recurso inválido. Debe ser numérico.']);
        return;
    }
    $resourceId = $resourceId ? (int)$resourceId : null;

    $method = $_SERVER['REQUEST_METHOD'];
    $company_id = $user['company_id']; // From authenticated user

    // --- Routing Logic ---
    if ($method === 'GET') {
        switch ($resource) {
            case 'products':
                if (!$apiAuth->checkPermissions($permissions, 'products:read')) {
                    jsonResponse(403, ['error' => 'Permiso denegado para leer productos.']);
                }
                $productRepo = new Product();
                if ($resourceId) {
                    $product = $productRepo->getById($resourceId, $company_id);
                    if ($product) {
                        jsonResponse(200, $product);
                    } else {
                        jsonResponse(404, ['error' => 'Producto no encontrado o no pertenece a su compañía.']);
                    }
                } else {
                    $products = $productRepo->getAllByCompany($company_id);
                    jsonResponse(200, $products);
                }
                break;

            case 'customers':
                if (!$apiAuth->checkPermissions($permissions, 'customers:read')) {
                    jsonResponse(403, ['error' => 'Permiso denegado para leer clientes.']);
                }
                $customerRepo = new Customer();
                if ($resourceId) {
                    $customer = $customerRepo->getById($resourceId, $company_id);
                    if ($customer) {
                        jsonResponse(200, $customer);
                    } else {
                        jsonResponse(404, ['error' => 'Cliente no encontrado o no pertenece a su compañía.']);
                    }
                } else {
                    $customers = $customerRepo->getAllByCompany($company_id);
                    jsonResponse(200, $customers);
                }
                break;

            // TODO: Add more resources (quotations, etc.) here later

            default:
                jsonResponse(404, ['error' => 'Recurso API no encontrado.']);
                break;
        }
    } else {
        jsonResponse(405, ['error' => 'Método no permitido. Solo GET está implementado por ahora.']);
    }
});

?>
