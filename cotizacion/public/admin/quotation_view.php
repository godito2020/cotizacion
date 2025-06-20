<?php
// cotizacion/public/admin/quotation_view.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$quotationRepo = new Quotation();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "User or company information is missing. Please re-login.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole(['Company Admin', 'Salesperson'])) {
    $_SESSION['error_message'] = "You are not authorized to view quotations.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$userRepo = new User(); // For header display of roles

$quotation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$quotation_id) {
    $_SESSION['error_message'] = "Invalid Quotation ID specified.";
    $auth->redirect(BASE_URL . '/admin/quotations.php');
}

$quotation = $quotationRepo->getById($quotation_id, $company_id);

if (!$quotation) {
    $_SESSION['error_message'] = "Quotation not found or you do not have permission to view it.";
    $auth->redirect(BASE_URL . '/admin/quotations.php');
}

// For currency formatting
$fmt = new NumberFormatter('es_PE', NumberFormatter::CURRENCY); // Example for Peruvian Soles
// Or a generic one:
// $fmt = new NumberFormatter( 'en_US', NumberFormatter::CURRENCY );
// $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);
// $fmt->setSymbol(NumberFormatter::CURRENCY_SYMBOL, ''); // If you want to add symbol manually

function formatCurrency($value, $formatter) {
    // return $formatter->formatCurrency($value, "PEN"); // For PEN
    return number_format($value, 2); // Generic fallback
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotation #<?php echo htmlspecialchars($quotation['quotation_number']); ?> - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background-color: #f4f7f6; color: #333; }
        .admin-header { background-color: #333; color: white; padding: 15px 20px; text-align: center; }
        .admin-header h1 { margin: 0; }
        .admin-nav { background-color: #444; padding: 10px; text-align: center; }
        .admin-nav a { color: white; margin: 0 15px; text-decoration: none; font-size: 16px; }
        .admin-nav a:hover { text-decoration: underline; }
        .admin-container { padding: 20px; max-width: 900px; margin: 20px auto; }
        .admin-content { background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .user-info { text-align: right; padding: 10px 20px; background-color: #555; color: white; }
        .user-info a { color: #ffc107; }

        .quotation-header h2 { margin-top: 0; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .quotation-meta, .customer-details, .quotation-summary { margin-bottom: 20px; padding-bottom:15px; border-bottom: 1px solid #eee; }
        .quotation-meta p, .customer-details p, .quotation-summary p { margin: 5px 0; line-height: 1.6; }
        .quotation-meta strong, .customer-details strong, .quotation-summary strong { display: inline-block; width: 180px; }

        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .items-table th { background-color: #f0f0f0; }
        .items-table td.number, .items-table th.number { text-align: right; }

        .totals-section { float: right; width: 300px; margin-top: 20px; }
        .totals-section p { display: flex; justify-content: space-between; margin: 8px 0; }
        .totals-section strong { font-weight: bold; }
        .grand-total { font-size: 1.2em; border-top: 2px solid #333; padding-top: 10px; }

        .notes-terms { margin-top: 30px; clear: both; }
        .notes-terms h4 { margin-bottom: 5px; }
        .notes-terms pre { background-color: #f9f9f9; padding: 10px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; font-family: inherit;}

        .actions-bar { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: right; }
        .actions-bar a, .actions-bar button {
            display: inline-block; padding: 10px 18px; margin-left: 10px;
            text-decoration: none; border-radius: 5px; font-size: 15px;
            cursor: pointer; border: none;
        }
        .actions-bar .btn-edit { background-color: #007bff; color: white; }
        .actions-bar .btn-edit:hover { background-color: #0056b3; }
        .actions-bar .btn-print { background-color: #6c757d; color: white; } /* Placeholder */
        .actions-bar .btn-pdf { background-color: #dc3545; color: white; } /* Placeholder */
        .actions-bar .btn-send { background-color: #28a745; color: white; } /* Placeholder */

        .status-badge { padding: 5px 10px; border-radius: 15px; color: white; font-weight: bold; display:inline-block; }
        .status-Draft { background-color: #6c757d; }
        .status-Sent { background-color: #007bff; }
        .status-Accepted { background-color: #28a745; }
        .status-Rejected { background-color: #dc3545; }
        .status-Invoiced { background-color: #ffc107; color: #333;}

        @media print {
            body { background-color: white; margin:0; padding:0; }
            .admin-header, .admin-nav, .user-info, .actions-bar { display: none !important; }
            .admin-container { padding:0; margin:0; max-width:100%;}
            .admin-content { box-shadow:none; border-radius:0; padding:15px;}
            .quotation-header h2 { font-size:1.5em; }
        }

    </style>
</head>
<body>

    <header class="admin-header"><h1>Admin Panel</h1></header>
    <div class="user-info">
        Logged in as: <?php echo htmlspecialchars($loggedInUser['username'] ?? 'User'); ?> (<?php echo htmlspecialchars(implode(', ', array_column($userRepo->getRoles($loggedInUser['id']), 'role_name'))); ?>) |
        Company ID: <?php echo htmlspecialchars($company_id); ?> |
        <a href="<?php echo BASE_URL; ?>/logout.php">Logout</a>
    </div>
    <nav class="admin-nav">
        <a href="<?php echo BASE_URL; ?>/admin/index.php">Admin Home</a>
        <?php if ($auth->hasRole('System Admin')): ?><a href="<?php echo BASE_URL; ?>/admin/companies.php">Manage Companies</a><?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'Salesperson', 'System Admin'])): ?>
             <a href="<?php echo BASE_URL; ?>/admin/customers.php">Manage Customers</a>
             <a href="<?php echo BASE_URL; ?>/admin/quotations.php">Manage Quotations</a>
        <?php endif; ?>
        <?php if ($auth->hasRole(['Company Admin', 'System Admin'])): ?>
            <a href="<?php echo BASE_URL; ?>/admin/products.php">Manage Products</a>
            <a href="<?php echo BASE_URL; ?>/admin/warehouses.php">Manage Warehouses</a>
            <a href="<?php echo BASE_URL; ?>/admin/stock_management.php">Manage Stock</a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>/dashboard.php">Main Dashboard</a>
    </nav>

    <div class="admin-container">
        <div class="admin-content">
            <div class="quotation-header">
                <h2>Quotation #<?php echo htmlspecialchars($quotation['quotation_number']); ?></h2>
            </div>

            <div class="quotation-meta">
                <p><strong>Status:</strong> <span class="status-badge status-<?php echo htmlspecialchars(str_replace(' ', '', $quotation['status'])); ?>"><?php echo htmlspecialchars($quotation['status']); ?></span></p>
                <p><strong>Quotation Date:</strong> <?php echo htmlspecialchars(date("F j, Y", strtotime($quotation['quotation_date']))); ?></p>
                <p><strong>Valid Until:</strong> <?php echo $quotation['valid_until'] ? htmlspecialchars(date("F j, Y", strtotime($quotation['valid_until']))) : 'N/A'; ?></p>
                <p><strong>Created By:</strong> <?php echo htmlspecialchars($quotation['user_first_name'] . ' ' . $quotation['user_last_name'] . ' (' . $quotation['user_username'] . ')'); ?></p>
            </div>

            <div class="customer-details">
                <h3>Customer Details</h3>
                <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($quotation['customer_name']); ?></p>
                <?php
                $customer = (new Customer())->getById($quotation['customer_id'], $company_id); // Fetch full customer details if needed
                if ($customer): ?>
                <p><strong>Tax ID:</strong> <?php echo htmlspecialchars($customer['tax_id'] ?? 'N/A'); ?></p>
                <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($customer['contact_person'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($customer['address'] ?? 'N/A')); ?></p>
                <?php endif; ?>
            </div>

            <h3>Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product/Service</th>
                        <th>SKU</th>
                        <th class="number">Quantity</th>
                        <th class="number">Unit Price</th>
                        <th class="number">Discount (%)</th>
                        <th class="number">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $item_num = 1; foreach ($quotation['items'] as $item): ?>
                    <tr>
                        <td><?php echo $item_num++; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td><?php echo htmlspecialchars($item['product_sku'] ?? 'N/A'); ?></td>
                        <td class="number"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="number"><?php echo htmlspecialchars(formatCurrency($item['unit_price'], $fmt)); ?></td>
                        <td class="number"><?php echo htmlspecialchars(number_format($item['discount_percentage'], 2)); ?>%</td>
                        <td class="number"><?php echo htmlspecialchars(formatCurrency($item['line_total'], $fmt)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-section">
                <p>Subtotal: <span><?php echo htmlspecialchars(formatCurrency($quotation['subtotal'] + $quotation['global_discount_amount'] , $fmt)); // This is subtotal before global discount ?></span></p>
                <p>Global Discount (<?php echo htmlspecialchars(number_format($quotation['global_discount_percentage'], 2)); ?>%): <span>- <?php echo htmlspecialchars(formatCurrency($quotation['global_discount_amount'], $fmt)); ?></span></p>
                <p class="grand-total"><strong>Total:</strong> <strong><?php echo htmlspecialchars(formatCurrency($quotation['total'], $fmt)); ?></strong></p>
            </div>

            <div class="notes-terms">
                <?php if (!empty($quotation['notes'])): ?>
                    <h4>Notes:</h4>
                    <pre><?php echo htmlspecialchars($quotation['notes']); ?></pre>
                <?php endif; ?>
                <?php if (!empty($quotation['terms_and_conditions'])): ?>
                    <h4>Terms & Conditions:</h4>
                    <pre><?php echo htmlspecialchars($quotation['terms_and_conditions']); ?></pre>
                <?php endif; ?>
            </div>

            <div class="actions-bar">
                <?php if ($quotation['status'] == 'Draft' || $auth->hasRole('Company Admin')): ?>
                <a href="quotation_form.php?id=<?php echo $quotation['id']; ?>" class="btn-edit">Edit Quotation</a>
                <?php endif; ?>
                <button type="button" class="btn-print" onclick="window.print();">Print</button>
                <button type="button" class="btn-pdf">Download PDF</button> <!-- Placeholder -->
                <button type="button" class="btn-send">Send Email</button> <!-- Placeholder -->
            </div>

        </div>
    </div>

</body>
</html>
