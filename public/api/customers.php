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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new customer
    $tax_id = trim($_POST['document'] ?? '');
    $name = trim($_POST['business_name'] ?? $_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $company_status = trim($_POST['company_status'] ?? 'ACTIVO');

    // Limit field lengths to match database schema
    $phone = substr($phone, 0, 50); // phone column is VARCHAR(50)
    $email = substr($email, 0, 100); // email column is VARCHAR(100)
    $name = substr($name, 0, 255); // name column is VARCHAR(255)

    if (empty($tax_id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Documento y razón social son requeridos']);
        exit;
    }

    try {
        $customerRepo = new Customer();

        // Check if customer with this tax_id already exists
        $existingCustomer = $customerRepo->findByTaxId($tax_id, $companyId);
        if ($existingCustomer) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un cliente con este documento']);
            exit;
        }

        // Get current user ID
        $userId = $auth->getUserId();

        // Create new customer using Customer class
        $customerId = $customerRepo->create(
            $companyId,
            $userId,        // user_id is required
            $name,
            null,          // contact_person
            $email,
            null,          // email_cc
            $phone,
            $address,
            $tax_id,
            $company_status
        );

        if (!$customerId) {
            // Get the error from session if available
            $dbError = $_SESSION['db_error'] ?? 'Unknown error';
            unset($_SESSION['db_error']);
            error_log("Customer creation failed: " . $dbError);
            echo json_encode(['success' => false, 'message' => 'Error al crear el cliente: ' . $dbError]);
            exit;
        }

        Notification::notifyNewCustomer($userId, $companyId, $customerId, $name);

        // Get the created customer
        $customer = $customerRepo->getById($customerId, $companyId);

        echo json_encode([
            'success' => true,
            'customer' => [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'business_name' => $customer['name'], // Alias for compatibility
                'document' => $customer['tax_id'], // Alias for compatibility
                'tax_id' => $customer['tax_id'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'address' => $customer['address'],
                'company_status' => $customer['company_status']
            ],
            'message' => 'Cliente creado exitosamente'
        ]);

    } catch (Exception $e) {
        error_log("Error creating customer: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear el cliente: ' . $e->getMessage()
        ]);
    }

} else {
    // Get customers list
    try {
        $customerRepo = new Customer();
        $customers = $customerRepo->getAllByCompany($companyId);

        // Format customers for compatibility
        $formattedCustomers = array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'business_name' => $customer['name'], // Alias for compatibility
                'document' => $customer['tax_id'], // Alias for compatibility
                'tax_id' => $customer['tax_id'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'address' => $customer['address'],
                'company_status' => $customer['company_status']
            ];
        }, $customers);

        echo json_encode([
            'success' => true,
            'customers' => $formattedCustomers
        ]);

    } catch (Exception $e) {
        error_log("Error fetching customers: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener clientes: ' . $e->getMessage()
        ]);
    }
}
?>