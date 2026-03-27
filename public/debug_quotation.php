<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php');
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

// Test data
$testItems = [
    [
        'product_id' => null,
        'description' => 'Producto de prueba 1',
        'quantity' => 2,
        'unit_price' => 100.00,
        'discount_percentage' => 0,
        'image_url' => null
    ],
    [
        'product_id' => null,
        'description' => 'Producto de prueba 2',
        'quantity' => 1,
        'unit_price' => 50.00,
        'discount_percentage' => 10,
        'image_url' => null
    ]
];

$quotationRepo = new Quotation();

echo "<h1>Creando cotización de prueba...</h1>";

// Get first customer
$customerRepo = new Customer();
$customers = $customerRepo->getAllByCompany($companyId);
if (empty($customers)) {
    die("No hay clientes disponibles");
}

$customerId = $customers[0]['id'];

echo "<p>Usando cliente ID: {$customerId}</p>";
echo "<p>Items a crear: " . count($testItems) . "</p>";

$result = $quotationRepo->create(
    $companyId,
    $customerId,
    $user['id'],
    date('Y-m-d'),
    null,
    $testItems,
    0,
    'Notas de prueba',
    'Términos de prueba',
    'Draft',
    'PEN'
);

if ($result) {
    echo "<p style='color: green;'>Cotización creada exitosamente con ID: {$result}</p>";
    echo "<a href='" . BASE_URL . "/quotations/view.php?id={$result}'>Ver cotización</a>";
} else {
    echo "<p style='color: red;'>Error al crear la cotización</p>";
}
?>