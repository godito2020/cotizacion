<?php
// cotizacion/public/admin/product_delete.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$productRepo = new Product(); // Autoloaded

if (!$auth->isLoggedIn()) {
    // Should not happen if links are only shown to logged-in users, but good practice
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "User or company information is missing. Please re-login.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole('Company Admin')) {
    $_SESSION['error_message'] = "You are not authorized to delete products.";
    $auth->redirect(BASE_URL . '/admin/products.php'); // Or admin index
}

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    $_SESSION['error_message'] = "Product ID is missing for deletion.";
    $auth->redirect(BASE_URL . '/admin/products.php');
}

// Optional: Add CSRF token check here for POST requests if you change this to a form submission.
// For GET request deletion (less secure), a confirm dialog on client-side is important.

$product_to_delete = $productRepo->getById((int)$product_id, $company_id);

if (!$product_to_delete) {
    $_SESSION['error_message'] = "Product not found or you do not have permission to delete it.";
    $auth->redirect(BASE_URL . '/admin/products.php');
}

// Proceed with deletion
if ($productRepo->delete((int)$product_id, $company_id)) {
    $_SESSION['message'] = "Product '" . htmlspecialchars($product_to_delete['name']) . "' (ID: " . htmlspecialchars($product_id) . ") deleted successfully.";
} else {
    $_SESSION['error_message'] = "Failed to delete product '" . htmlspecialchars($product_to_delete['name']) . "'. It might be in use or a database error occurred.";
}

$auth->redirect(BASE_URL . '/admin/products.php');

?>
