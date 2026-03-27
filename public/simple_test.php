<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die("No autorizado");
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

echo "<h1>Prueba Simple de Creación de Cotización</h1>";

// Simular datos POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Datos POST recibidos:</h2>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";

    // Procesar como en create.php
    if (!empty($_POST['items']) && is_array($_POST['items'])) {
        $validItems = [];
        foreach ($_POST['items'] as $index => $item) {
            if (!empty(trim($item['description']))) {
                $quantity = isset($item['quantity']) && $item['quantity'] !== '' ? (float)$item['quantity'] : 1;
                $unitPrice = isset($item['unit_price']) && $item['unit_price'] !== '' ? (float)$item['unit_price'] : 0;

                $validItems[] = [
                    'product_id' => !empty($item['product_id']) ? (int)$item['product_id'] : null,
                    'description' => trim($item['description']),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => (float)($item['discount_percentage'] ?? 0),
                    'image_url' => $item['image_url'] ?? null
                ];
            }
        }

        echo "<h2>Items válidos procesados:</h2>";
        echo "<pre>" . print_r($validItems, true) . "</pre>";

        if (!empty($validItems)) {
            // Intentar crear cotización
            $quotationRepo = new Quotation();

            // Obtener primer cliente
            $customerRepo = new Customer();
            $customers = $customerRepo->getAllByCompany($companyId);
            $customerId = $customers[0]['id'] ?? null;

            if ($customerId) {
                $result = $quotationRepo->create(
                    $companyId,
                    $customerId,
                    $user['id'],
                    date('Y-m-d'),
                    null,
                    $validItems,
                    0,
                    'Prueba simple',
                    'Términos de prueba',
                    'Draft',
                    'PEN'
                );

                if ($result) {
                    echo "<p style='color: green; font-weight: bold;'>¡Cotización creada exitosamente! ID: {$result}</p>";
                    echo "<a href='quotations/view.php?id={$result}' target='_blank'>Ver cotización</a>";
                } else {
                    echo "<p style='color: red; font-weight: bold;'>Error al crear cotización</p>";
                }
            } else {
                echo "<p style='color: red;'>No hay clientes disponibles</p>";
            }
        } else {
            echo "<p style='color: red;'>No hay items válidos</p>";
        }
    } else {
        echo "<p style='color: red;'>No se encontraron items en POST</p>";
    }

    echo "<hr><a href='simple_test.php'>Volver</a>";
    exit;
}
?>

<form method="POST">
    <h2>Item 1</h2>
    <div>
        <label>Descripción:</label>
        <input type="text" name="items[0][description]" value="Producto de prueba 1">
    </div>
    <div>
        <label>Cantidad:</label>
        <input type="number" name="items[0][quantity]" value="2" step="0.01">
    </div>
    <div>
        <label>Precio Unitario:</label>
        <input type="number" name="items[0][unit_price]" value="100.50" step="0.01">
    </div>
    <div>
        <label>Descuento %:</label>
        <input type="number" name="items[0][discount_percentage]" value="0" step="0.01">
    </div>

    <h2>Item 2</h2>
    <div>
        <label>Descripción:</label>
        <input type="text" name="items[1][description]" value="Producto de prueba 2">
    </div>
    <div>
        <label>Cantidad:</label>
        <input type="number" name="items[1][quantity]" value="1" step="0.01">
    </div>
    <div>
        <label>Precio Unitario:</label>
        <input type="number" name="items[1][unit_price]" value="50.25" step="0.01">
    </div>

    <br>
    <button type="submit">Crear Cotización de Prueba</button>
</form>