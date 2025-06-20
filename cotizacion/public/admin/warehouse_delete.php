<?php
// cotizacion/public/admin/warehouse_delete.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$warehouseRepo = new Warehouse(); // Autoloaded

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

if (!$auth->hasRole('Company Admin')) {
    $_SESSION['error_message'] = "You are not authorized to delete warehouses.";
    $auth->redirect(BASE_URL . '/admin/warehouses.php'); // Or admin index
}

$warehouse_id = $_GET['id'] ?? null;

if (!$warehouse_id) {
    $_SESSION['error_message'] = "Warehouse ID is missing for deletion.";
    $auth->redirect(BASE_URL . '/admin/warehouses.php');
}

$warehouse_to_delete = $warehouseRepo->getById((int)$warehouse_id, $company_id);

if (!$warehouse_to_delete) {
    $_SESSION['error_message'] = "Warehouse not found or you do not have permission to delete it.";
    $auth->redirect(BASE_URL . '/admin/warehouses.php');
}

// Proceed with deletion
if ($warehouseRepo->delete((int)$warehouse_id, $company_id)) {
    $_SESSION['message'] = "Warehouse '" . htmlspecialchars($warehouse_to_delete['name']) . "' (ID: " . htmlspecialchars($warehouse_id) . ") deleted successfully.";
} else {
    $_SESSION['error_message'] = "Failed to delete warehouse '" . htmlspecialchars($warehouse_to_delete['name']) . "'. It might be in use (e.g., has stock records) or a database error occurred. Check logs.";
}

$auth->redirect(BASE_URL . '/admin/warehouses.php');

?>
