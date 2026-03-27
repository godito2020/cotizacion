<?php
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$companyId = $auth->getCompanyId();

// Allow sysadmin to specify company_id
if ($auth->hasRole('Administrador del Sistema') && isset($_GET['company_id'])) {
    $companyId = (int)$_GET['company_id'];
}

try {
    $db = getDBConnection();

    // Get default template for this company
    $stmt = $db->prepare("
        SELECT id, name, template_header, template_item, template_footer
        FROM cotirapi_templates
        WHERE company_id = ? AND is_active = 1 AND is_default = 1
        LIMIT 1
    ");
    $stmt->execute([$companyId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no default template, get the first active template
    if (!$template) {
        $stmt = $db->prepare("
            SELECT id, name, template_header, template_item, template_footer
            FROM cotirapi_templates
            WHERE company_id = ? AND is_active = 1
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($template) {
        echo json_encode([
            'success' => true,
            'template' => $template
        ]);
    } else {
        // Return a fallback basic template if none exists
        echo json_encode([
            'success' => true,
            'template' => [
                'id' => null,
                'name' => 'Plantilla Básica',
                'template_header' => "COTIZACIÓN\n\nCliente: {CUSTOMER_NAME}\nFecha: {DATE}\n\n",
                'template_item' => "{ITEM_NUMBER}. {DESCRIPTION}\n{CODE_LINE}Cantidad: {QUANTITY} | Precio: {CURRENCY} {UNIT_PRICE}\n{DISCOUNT_LINE}Total: {CURRENCY} {TOTAL}\n\n",
                'template_footer' => "-------------------\nSubtotal: {CURRENCY} {SUBTOTAL}\nIGV: {CURRENCY} {IGV}\nTOTAL: {CURRENCY} {GRAND_TOTAL}\n\nPrecios incluyen IGV"
            ]
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener plantilla: ' . $e->getMessage()
    ]);
}
