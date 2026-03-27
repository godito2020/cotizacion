<?php
// cotizacion/public/admin/customer_delete.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$customerRepo = new Customer(); // Autoloaded

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

// Role check: 'Administrador de Empresa' or 'Vendedor'
if (!$auth->hasRole(['Administrador de Empresa', 'Vendedor'])) {
    $_SESSION['error_message'] = "You are not authorized to delete customers.";
    $auth->redirect(BASE_URL . '/admin/customers.php');
}

$customer_id = $_GET['id'] ?? null;

if (!$customer_id) {
    $_SESSION['error_message'] = "Customer ID is missing for deletion.";
    $auth->redirect(BASE_URL . '/admin/customers.php');
}

$customer_to_delete = $customerRepo->getById((int)$customer_id, $company_id);

if (!$customer_to_delete) {
    $_SESSION['error_message'] = "Customer not found or you do not have permission to delete it.";
    $auth->redirect(BASE_URL . '/admin/customers.php');
}

// Proceed with deletion
if ($customerRepo->delete((int)$customer_id, $company_id)) {
    $_SESSION['message'] = "Customer '" . htmlspecialchars($customer_to_delete['name']) . "' (ID: " . htmlspecialchars($customer_id) . ") deleted successfully.";
} else {
    $_SESSION['error_message'] = "Failed to delete customer '" . htmlspecialchars($customer_to_delete['name']) . "'. They might have existing quotations or a database error occurred. Check logs.";
}

$auth->redirect(BASE_URL . '/admin/customers.php');

?>
