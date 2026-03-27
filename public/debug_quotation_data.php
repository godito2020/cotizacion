<?php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    die("No autorizado");
}

$user = $auth->getUser();
$companyId = $auth->getCompanyId();

$db = getDBConnection();

echo "<h1>Debug: Datos de Cotizaciones</h1>";

// Crear una cotización de prueba
if (isset($_GET['create_test'])) {
    echo "<h2>Creando cotización de prueba...</h2>";

    $testItems = [
        [
            'product_id' => null,
            'description' => 'Producto de prueba 1',
            'quantity' => 2,
            'unit_price' => 100.00,
            'discount_percentage' => 0,
            'image_url' => null
        ]
    ];

    $quotationRepo = new Quotation();
    $customerRepo = new Customer();

    $customers = $customerRepo->getAllByCompany($companyId);
    if (empty($customers)) {
        echo "<p style='color: red;'>No hay clientes disponibles</p>";
    } else {
        $customerId = $customers[0]['id'];

        $result = $quotationRepo->create(
            $companyId,
            $customerId,
            $user['id'],
            date('Y-m-d'),
            null,
            $testItems,
            0,
            'Cotización de prueba',
            'Términos de prueba',
            'Draft',
            'PEN'
        );

        if ($result) {
            echo "<p style='color: green;'>Cotización creada con ID: {$result}</p>";
            echo "<a href='view.php?id={$result}'>Ver cotización</a>";
        } else {
            echo "<p style='color: red;'>Error al crear cotización</p>";
        }
    }

    echo "<hr>";
}

echo "<p><a href='?create_test=1'>Crear cotización de prueba</a></p>";

// Verificar última cotización
echo "<h2>Última Cotización</h2>";
$stmt = $db->query('SELECT id, quotation_number, customer_id, created_at FROM quotations ORDER BY id DESC LIMIT 1');
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($quotation) {
    echo "<p><strong>ID:</strong> {$quotation['id']}</p>";
    echo "<p><strong>Número:</strong> {$quotation['quotation_number']}</p>";
    echo "<p><strong>Cliente ID:</strong> {$quotation['customer_id']}</p>";
    echo "<p><strong>Creada:</strong> {$quotation['created_at']}</p>";

    // Verificar items de esta cotización
    echo "<h3>Items de la Cotización</h3>";
    $stmt = $db->prepare('SELECT * FROM quotation_items WHERE quotation_id = ?');
    $stmt->execute([$quotation['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Número de items:</strong> " . count($items) . "</p>";

    if (count($items) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Quotation ID</th><th>Product ID</th><th>Description</th><th>Quantity</th><th>Unit Price</th><th>Line Total</th></tr>";
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['quotation_id']}</td>";
            echo "<td>" . ($item['product_id'] ?? 'NULL') . "</td>";
            echo "<td>{$item['description']}</td>";
            echo "<td>{$item['quantity']}</td>";
            echo "<td>{$item['unit_price']}</td>";
            echo "<td>{$item['line_total']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>NO HAY ITEMS PARA ESTA COTIZACIÓN</strong></p>";
    }

    // Probar el método getById
    echo "<h3>Probando método getById</h3>";
    $quotationRepo = new Quotation();
    $fullQuotation = $quotationRepo->getById($quotation['id'], $companyId);

    if ($fullQuotation) {
        echo "<p><strong>Cotización encontrada</strong></p>";
        echo "<p><strong>Items en array:</strong> " . count($fullQuotation['items']) . "</p>";

        if (count($fullQuotation['items']) > 0) {
            echo "<ul>";
            foreach ($fullQuotation['items'] as $item) {
                echo "<li>{$item['description']} - Cant: {$item['quantity']} - Precio: {$item['unit_price']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'><strong>ARRAY DE ITEMS VACÍO</strong></p>";
        }
    } else {
        echo "<p style='color: red;'><strong>MÉTODO getById RETORNÓ FALSE</strong></p>";
    }

} else {
    echo "<p style='color: red;'>No hay cotizaciones en la base de datos</p>";
}

// Verificar todas las cotizaciones
echo "<h2>Todas las Cotizaciones</h2>";
$stmt = $db->query('SELECT COUNT(*) as total FROM quotations');
$total = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Total de cotizaciones:</strong> {$total['total']}</p>";

if ($total['total'] > 0) {
    $stmt = $db->query('SELECT id, quotation_number FROM quotations ORDER BY id DESC LIMIT 5');
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<ul>";
    foreach ($quotations as $q) {
        echo "<li><a href='view.php?id={$q['id']}' target='_blank'>{$q['quotation_number']} (ID: {$q['id']})</a></li>";
    }
    echo "</ul>";
}
?>