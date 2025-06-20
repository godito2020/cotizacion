<?php
// cotizacion/public/admin/quotation_form.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$quotationRepo = new Quotation();
$customerRepo = new Customer();
$productRepo = new Product();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id']) || !isset($loggedInUser['id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];
$user_id = $loggedInUser['id'];

if (!$auth->hasRole(['Company Admin', 'Salesperson'])) {
    $_SESSION['error_message'] = "No está autorizado para gestionar cotizaciones.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User();

$quotation_id_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit_mode = false;
$quotation_data_for_form = null; // Used to populate form in edit mode
$page_title = "Crear Nueva Cotización";
$form_action = "quotation_form.php";

$all_customers = $customerRepo->getAllByCompany($company_id);
$all_products = $productRepo->getAllByCompany($company_id);

// Default values for a new quotation
$form_values = [
    'quotation_number' => 'Auto-generado',
    'status' => 'Draft',
    'quotation_date' => date('Y-m-d'),
    'valid_until' => date('Y-m-d', strtotime('+30 days')),
    'customer_id' => null,
    'notes' => '',
    'terms_and_conditions' => '', // Could load default terms from settings later
    'global_discount_percentage' => 0.0,
    'items' => []
];

if ($quotation_id_get) {
    $quotation_data_for_form = $quotationRepo->getById($quotation_id_get, $company_id);
    if ($quotation_data_for_form) {
        $is_edit_mode = true;
        $page_title = "Editar Cotización #" . htmlspecialchars($quotation_data_for_form['quotation_number']);
        $form_action = "quotation_form.php?id=" . $quotation_id_get;

        $form_values['quotation_number'] = htmlspecialchars($quotation_data_for_form['quotation_number']);
        $form_values['status'] = htmlspecialchars($quotation_data_for_form['status']);
        $form_values['quotation_date'] = htmlspecialchars($quotation_data_for_form['quotation_date']);
        $form_values['valid_until'] = htmlspecialchars($quotation_data_for_form['valid_until'] ?? '');
        $form_values['customer_id'] = $quotation_data_for_form['customer_id'];
        $form_values['notes'] = htmlspecialchars($quotation_data_for_form['notes'] ?? '');
        $form_values['terms_and_conditions'] = htmlspecialchars($quotation_data_for_form['terms_and_conditions'] ?? '');
        $form_values['global_discount_percentage'] = floatval($quotation_data_for_form['global_discount_percentage'] ?? 0.0);
        // Transform items for form
        foreach($quotation_data_for_form['items'] as $item) {
            $form_values['items'][] = [
                'product_id' => $item['product_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_percentage' => $item['discount_percentage']
            ];
        }

        if ($form_values['status'] !== 'Draft' && !$auth->hasRole('Company Admin')) {
             $_SESSION['error_message'] = "Solo cotizaciones en estado 'Borrador' pueden ser editadas por vendedores. Contacte a un administrador.";
             $auth->redirect(BASE_URL . '/admin/quotation_view.php?id=' . $quotation_id_get);
        }
    } else {
        $_SESSION['error_message'] = "Cotización no encontrada o no tiene permiso para editarla.";
        $auth->redirect(BASE_URL . '/admin/quotations.php');
    }
}

$errors = [];
// --- SERVER-SIDE FORM PROCESSING (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize header data
    $posted_customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $posted_quotation_date = trim($_POST['quotation_date'] ?? '');
    $posted_valid_until = trim($_POST['valid_until'] ?? null);
    if (empty($posted_valid_until)) $posted_valid_until = null; // Ensure NULL if empty

    $posted_global_discount_percentage = filter_input(INPUT_POST, 'global_discount_percentage', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    if ($posted_global_discount_percentage === false) $posted_global_discount_percentage = 0.0;

    $posted_notes = trim($_POST['notes'] ?? null);
    $posted_terms = trim($_POST['terms_and_conditions'] ?? null);
    $posted_status = trim($_POST['status'] ?? 'Draft'); // Status might come from form if editable

    // Repopulate form_values with POSTed data for sticky form
    $form_values['customer_id'] = $posted_customer_id;
    $form_values['quotation_date'] = $posted_quotation_date;
    $form_values['valid_until'] = $posted_valid_until;
    $form_values['global_discount_percentage'] = $posted_global_discount_percentage;
    $form_values['notes'] = $posted_notes;
    $form_values['terms_and_conditions'] = $posted_terms;
    $form_values['status'] = $posted_status; // if status becomes editable

    // Validation
    if (empty($posted_customer_id)) $errors[] = "Debe seleccionar un cliente.";
    if (empty($posted_quotation_date)) $errors[] = "La fecha de cotización es requerida.";
    // Add more date validations if needed (e.g., valid_until after quotation_date)

    // Collect and validate line items
    $posted_items_data = [];
    if (isset($_POST['items']) && is_array($_POST['items'])) {
        foreach ($_POST['items'] as $idx => $item_input) {
            $product_id = filter_var($item_input['product_id'], FILTER_VALIDATE_INT);
            if ($item_input['product_id'] === 'custom') $product_id = null; // Handle custom item

            $description = trim($item_input['description'] ?? '');
            $quantity = filter_var($item_input['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $unit_price = filter_var($item_input['unit_price'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
            $discount_percentage = filter_var($item_input['discount_percentage'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
            if ($discount_percentage === false) $discount_percentage = 0.0;


            if (!$product_id && empty($description)) {
                $errors[] = "Ítem #" . ($idx + 1) . ": Descripción es requerida para ítems personalizados.";
            }
            if ($quantity === false) $errors[] = "Ítem #" . ($idx + 1) . ": Cantidad debe ser un número entero positivo.";
            if ($unit_price === false) $errors[] = "Ítem #" . ($idx + 1) . ": Precio Unitario debe ser un número positivo.";

            // Verify product_id belongs to company if not custom
            if ($product_id) {
                $product_check = $productRepo->getById($product_id, $company_id);
                if (!$product_check) {
                    $errors[] = "Ítem #" . ($idx + 1) . ": Producto seleccionado no es válido o no pertenece a su compañía.";
                }
            }

            $posted_items_data[] = [
                'product_id' => $product_id,
                'description' => $description, // If product_id is set, this might be overridden by product name from DB in Quotation class
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'discount_percentage' => $discount_percentage
            ];
        }
    }
    if (empty($posted_items_data) && empty($errors)) { // only error if no other errors yet
        $errors[] = "Debe añadir al menos un ítem a la cotización.";
    }
    $form_values['items'] = $posted_items_data; // For sticky form items

    if (empty($errors)) {
        $quotation_id_hidden = filter_input(INPUT_POST, 'quotation_id', FILTER_VALIDATE_INT);

        if ($is_edit_mode && $quotation_id_hidden == $quotation_id_get) {
            // Update existing quotation
            $success = $quotationRepo->update(
                $quotation_id_get, $company_id, $posted_customer_id, $user_id,
                $posted_quotation_date, $posted_valid_until, $posted_items_data,
                $posted_global_discount_percentage, $posted_notes, $posted_terms, $posted_status
            );
            if ($success) {
                $_SESSION['message'] = "Cotización #" . htmlspecialchars($form_values['quotation_number']) . " actualizada exitosamente.";
                $auth->redirect(BASE_URL . '/admin/quotation_view.php?id=' . $quotation_id_get);
            } else {
                $errors[] = "Error al actualizar la cotización. Por favor, intente de nuevo.";
            }
        } else {
            // Create new quotation
            $new_quotation_id = $quotationRepo->create(
                $company_id, $posted_customer_id, $user_id,
                $posted_quotation_date, $posted_valid_until, $posted_items_data,
                $posted_global_discount_percentage, $posted_notes, $posted_terms, $posted_status
            );
            if ($new_quotation_id) {
                $_SESSION['message'] = "Cotización creada exitosamente.";
                $auth->redirect(BASE_URL . '/admin/quotation_view.php?id=' . $new_quotation_id);
            } else {
                $errors[] = "Error al crear la cotización. Por favor, intente de nuevo.";
            }
        }
    }
    // If errors, they will be displayed below.
}


// Data for JavaScript
$js_all_products = json_encode(array_map(function($p) {
    return ['id' => $p['id'], 'name' => $p['name'], 'sku' => $p['sku'], 'price' => $p['price'], 'description' => $p['description'] ?? $p['name']];
}, $all_products));

$js_initial_items = json_encode($form_values['items']);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; max-width: 1000px; margin: 20px auto; }
        .admin-content { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }

        .form-section { margin-bottom: 25px; padding-bottom:15px; border-bottom:1px solid #eee; }
        .form-section h3 { margin-top:0; color:#555; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%; /* Full width within its grid cell */
            padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        .form-group textarea { min-height: 80px; }
        .form-group .readonly { background-color: #f0f0f0; cursor:not-allowed; }

        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align:top;}
        .items-table th { background-color: #f0f0f0; }
        .items-table input[type="text"], .items-table input[type="number"], .items-table select { width: 100%; padding:6px; box-sizing:border-box;}
        .items-table .action-delete-item { color: red; cursor: pointer; text-align:center; font-weight:bold; padding:5px; }

        .summary-section { margin-top: 20px; padding-top:15px; border-top:1px solid #eee; }
        .summary-grid { display: grid; grid-template-columns: 1fr auto; gap: 10px 20px; max-width:400px; margin-left:auto; }
        .summary-grid label { font-weight: bold; text-align: right; }
        .summary-grid span, .summary-grid input[type="number"] { text-align: right; font-size:1.1em; padding:5px; }
        .summary-grid input[type="number"] {width: 100px; } /* for global discount % */
        .summary-grid #summaryTotal {font-weight:bold;}


        .form-actions { text-align: right; margin-top: 30px; padding-top:20px; border-top:1px solid #ccc;}
        .form-actions button { padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size:16px; }
        .form-actions button[type="submit"] { background-color: #007bff; color: white; }
        .form-actions button[type="submit"]:hover { background-color: #0056b3; }
        .form-actions a { display: inline-block; padding: 12px 25px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
        .form-actions a:hover { background-color: #5a6268; }
        .error-messages { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .error-messages ul { padding-left: 20px; margin: 0; }
    </style>
</head>
<body>
    <header class="admin-header"><h1>Admin Panel</h1></header>
    <div class="user-info">
        Usuario: <?php echo htmlspecialchars($loggedInUser['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($loggedInUser['id']), 'role_name'))); ?>) |
        Compañía ID: <?php echo htmlspecialchars($company_id); ?> |
        <a href="<?php echo BASE_URL; ?>/logout.php">Cerrar Sesión</a>
    </div>
     <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Inicio Admin</a>
        <?php if ($auth->hasRole('System Admin')): ?><a href="<?php echo BASE_URL; ?>/admin/companies.php">Empresas</a><?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'Salesperson', 'System Admin'])): ?>
             <a href="<?php echo BASE_URL; ?>/admin/customers.php">Clientes</a>
             <a href="<?php echo BASE_URL; ?>/admin/quotations.php">Cotizaciones</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'System Admin'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Productos</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Almacenes</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Stock</a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Dashboard Principal</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <h2><?php echo $page_title; ?></h2>

            <?php if (!empty($errors)): ?>
                <div class="error-messages"><strong>Por favor corrija los siguientes errores:</strong><ul>
                    <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['message']) && !isset($_POST['save_quotation']) ): /* Show only if not a POST submission */ ?>
                <div class="message success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message']) && !isset($_POST['save_quotation']) ):  /* Show only if not a POST submission */ ?>
                <div class="message error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>


            <form id="quotationForm" action="<?php echo $form_action; ?>" method="POST">
                <input type="hidden" name="company_id_form" value="<?php echo $company_id; ?>"> <!-- Renamed to avoid conflict with $company_id -->
                <input type="hidden" name="user_id_form" value="<?php echo $user_id; ?>">
                <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="quotation_id" value="<?php echo $quotation_id_get; ?>">
                <?php endif; ?>

                <div class="form-section">
                    <h3>Información General</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="customer_id">Cliente:</label>
                            <select name="customer_id" id="customer_id" required>
                                <option value="">-- Seleccione Cliente --</option>
                                <?php foreach ($all_customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo ($form_values['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['tax_id'] ?? 'N/A'); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="quotation_number_display">Número de Cotización:</label>
                            <input type="text" id="quotation_number_display" name="quotation_number_display" value="<?php echo $form_values['quotation_number']; ?>" readonly class="readonly">
                        </div>
                        <div class="form-group">
                            <label for="quotation_date">Fecha de Cotización:</label>
                            <input type="date" id="quotation_date" name="quotation_date" value="<?php echo $form_values['quotation_date']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="valid_until">Válido Hasta:</label>
                            <input type="date" id="valid_until" name="valid_until" value="<?php echo $form_values['valid_until']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="status_display">Estado:</label>
                             <input type="text" id="status_display" name="status_display" value="<?php echo $form_values['status']; ?>" readonly class="readonly">
                             <input type="hidden" name="status" value="<?php echo $form_values['status']; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Ítems de la Cotización</h3>
                    <table class="items-table" id="quotationItemsTable">
                        <thead>
                            <tr>
                                <th>Producto/Servicio</th>
                                <th>Descripción Personalizada</th>
                                <th style="width:100px;">Cantidad</th>
                                <th style="width:120px;">Precio Unit.</th>
                                <th style="width:100px;">Dscto. (%)</th>
                                <th style="width:120px;">Total Línea</th>
                                <th style="width:60px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="quotationItemsTbody">
                            <!-- Item rows will be added here by JavaScript -->
                        </tbody>
                    </table>
                    <button type="button" id="addItemButton" style="margin-top:10px;">Añadir Ítem</button>
                </div>

                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="notes">Notas:</label>
                            <textarea id="notes" name="notes"><?php echo htmlspecialchars($form_values['notes']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="terms_and_conditions">Términos y Condiciones:</label>
                            <textarea id="terms_and_conditions" name="terms_and_conditions"><?php echo htmlspecialchars($form_values['terms_and_conditions']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="summary-section">
                    <div class="summary-grid">
                        <label>Subtotal (Suma de Ítems):</label> <span id="summarySubtotal">0.00</span>
                        <label for="global_discount_percentage">Descuento Global (%):</label>
                        <input type="number" name="global_discount_percentage" id="global_discount_percentage" value="<?php echo $form_values['global_discount_percentage']; ?>" min="0" max="100" step="0.01">
                        <label>Monto Descuento Global:</label> <span id="summaryGlobalDiscountAmount">0.00</span>
                        <label style="font-size:1.2em;">Total:</label> <strong style="font-size:1.2em;" id="summaryTotal">0.00</strong>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="quotations.php">Cancelar</a>
                    <button type="submit" name="save_quotation"><?php echo $is_edit_mode ? 'Actualizar Cotización' : 'Guardar Cotización'; ?></button>
                </div>
            </form>
        </div>
    </div>

<script>
    const allProducts = <?php echo $js_all_products; ?>;
    const initialItems = <?php echo $js_initial_items; ?>;
    let itemIndex = 0;

    document.addEventListener('DOMContentLoaded', function() {
        const addItemButton = document.getElementById('addItemButton');
        const itemsTbody = document.getElementById('quotationItemsTbody');
        const globalDiscountInput = document.getElementById('global_discount_percentage');

        addItemButton.addEventListener('click', () => agregarFilaProducto());
        itemsTbody.addEventListener('change', handleItemChange);
        itemsTbody.addEventListener('input', handleItemChange); // For number inputs primarily
        if(globalDiscountInput) globalDiscountInput.addEventListener('input', calcularTotalesGlobales);

        // Load initial items if in edit mode or if form was re-populated after error
        initialItems.forEach(itemData => agregarFilaProducto(itemData));
        if (initialItems.length === 0 && itemsTbody.rows.length === 0) { // Add one empty row if new and no items from POST
             agregarFilaProducto();
        }
        calcularTotalesGlobales(); // Initial calculation
    });

    function handleItemChange(event) {
        const target = event.target;
        if (target.classList.contains('item-product') ||
            target.classList.contains('item-quantity') ||
            target.classList.contains('item-unit-price') ||
            target.classList.contains('item-discount-percentage')) {

            const row = target.closest('tr');
            if (target.classList.contains('item-product')) {
                populateProductData(row, target.value);
            }
            calcularTotalLinea(row);
            calcularTotalesGlobales();
        }
    }

    function agregarFilaProducto(itemData = null) {
        const tbody = document.getElementById('quotationItemsTbody');
        const newRow = document.createElement('tr');
        newRow.setAttribute('data-item-index', itemIndex);

        let productOptions = '<option value="">-- Seleccione Producto --</option>';
        allProducts.forEach(p => {
            productOptions += `<option value="${p.id}" data-price="${p.price}" data-description="${escapeHtml(p.description)}">${escapeHtml(p.name)} (SKU: ${escapeHtml(p.sku)})</option>`;
        });
        productOptions += '<option value="custom">-- Ítem Personalizado --</option>';

        newRow.innerHTML = `
            <td>
                <select name="items[${itemIndex}][product_id]" class="item-product">
                    ${productOptions}
                </select>
            </td>
            <td><input type="text" name="items[${itemIndex}][description]" class="item-description" placeholder="Descripción personalizada"></td>
            <td><input type="number" name="items[${itemIndex}][quantity]" class="item-quantity" value="1" min="1" step="1"></td>
            <td><input type="number" name="items[${itemIndex}][unit_price]" class="item-unit-price" value="0.00" step="0.01" min="0"></td>
            <td><input type="number" name="items[${itemIndex}][discount_percentage]" class="item-discount-percentage" value="0.00" step="0.01" min="0" max="100"></td>
            <td><input type="text" class="item-line-total" value="0.00" readonly style="background-color:#f0f0f0;"></td>
            <td><button type="button" class="action-delete-item" onclick="eliminarFilaProducto(this)">X</button></td>
        `;
        tbody.appendChild(newRow);

        if (itemData) {
            const productSelect = newRow.querySelector('.item-product');
            if (itemData.product_id) {
                productSelect.value = itemData.product_id;
            } else {
                 productSelect.value = 'custom'; // Select custom if no product_id
            }
            newRow.querySelector('.item-description').value = itemData.description || '';
            newRow.querySelector('.item-quantity').value = itemData.quantity || 1;
            newRow.querySelector('.item-unit-price').value = parseFloat(itemData.unit_price || 0).toFixed(2);
            newRow.querySelector('.item-discount-percentage').value = parseFloat(itemData.discount_percentage || 0).toFixed(2);
            if (itemData.product_id) populateProductData(newRow, itemData.product_id, itemData); // ensure description and price are from product if selected
            else if (productSelect.value === 'custom') newRow.querySelector('.item-description').readOnly = false;


            calcularTotalLinea(newRow); // Calculate line total for existing items
        } else {
            // For new rows, if a product is selected, its data will be populated by populateProductData
            // If 'custom' is selected, description becomes editable.
            newRow.querySelector('.item-product').dispatchEvent(new Event('change', { bubbles: true }));
        }

        itemIndex++;
    }

    function populateProductData(row, productId, existingItemData = null) {
        const descriptionInput = row.querySelector('.item-description');
        const unitPriceInput = row.querySelector('.item-unit-price');

        if (productId === 'custom' || !productId) {
            descriptionInput.readOnly = false;
            if (!existingItemData || !existingItemData.product_id) { // only clear if not populating from existing custom item
                 if (!existingItemData) descriptionInput.value = ''; // Clear if new custom row
                 // unitPriceInput.value = '0.00'; // Don't clear price if just switching to custom
            }
        } else {
            const selectedProduct = allProducts.find(p => p.id == productId);
            if (selectedProduct) {
                descriptionInput.value = existingItemData ? (existingItemData.description || selectedProduct.description) : selectedProduct.description;
                unitPriceInput.value = parseFloat(existingItemData ? (existingItemData.unit_price || selectedProduct.price) : selectedProduct.price).toFixed(2);
                descriptionInput.readOnly = false; // Allow editing of description even if product selected
            } else { // Should not happen if allProducts is correct
                descriptionInput.readOnly = false;
            }
        }
    }

    function calcularTotalLinea(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.item-unit-price').value) || 0;
        const discountPerc = parseFloat(row.querySelector('.item-discount-percentage').value) || 0;

        const lineSubtotal = quantity * unitPrice;
        const discountAmount = lineSubtotal * (discountPerc / 100);
        const lineTotal = lineSubtotal - discountAmount;

        row.querySelector('.item-line-total').value = lineTotal.toFixed(2);
        return lineTotal;
    }

    function calcularTotalesGlobales() {
        let subtotalSumaItems = 0;
        document.querySelectorAll('#quotationItemsTbody tr').forEach(row => {
            subtotalSumaItems += parseFloat(row.querySelector('.item-line-total').value) || 0;
        });
        document.getElementById('summarySubtotal').textContent = subtotalSumaItems.toFixed(2);

        const globalDiscountPerc = parseFloat(document.getElementById('global_discount_percentage').value) || 0;
        const globalDiscountAmount = subtotalSumaItems * (globalDiscountPerc / 100);
        document.getElementById('summaryGlobalDiscountAmount').textContent = globalDiscountAmount.toFixed(2);

        const grandTotal = subtotalSumaItems - globalDiscountAmount;
        document.getElementById('summaryTotal').textContent = grandTotal.toFixed(2);
    }

    function eliminarFilaProducto(button) {
        button.closest('tr').remove();
        calcularTotalesGlobales();
        // Note: Re-indexing item names (items[index][field]) is not handled here.
        // Server-side should expect potentially non-sequential indexes or simply iterate what's submitted.
        // Or, re-index JS-side before submit, which is more complex.
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return String(unsafe)
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

</script>
</body>
</html>
