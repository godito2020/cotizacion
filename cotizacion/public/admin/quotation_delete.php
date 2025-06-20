<?php
// cotizacion/public/admin/quotation_delete.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();
$quotationRepo = new Quotation();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

// Role check: For now, only Company Admin can delete. Salesperson might only delete their own Drafts (more complex logic).
if (!$auth->hasRole('Company Admin')) {
    $_SESSION['error_message'] = "No está autorizado para eliminar cotizaciones.";
    $auth->redirect(BASE_URL . '/admin/quotations.php');
}

$quotation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$quotation_id) {
    $_SESSION['error_message'] = "ID de cotización ausente para eliminación.";
    $auth->redirect(BASE_URL . '/admin/quotations.php');
}

$quotation_to_delete = $quotationRepo->getById($quotation_id, $company_id);

if (!$quotation_to_delete) {
    $_SESSION['error_message'] = "Cotización no encontrada o no tiene permiso para eliminarla.";
    $auth->redirect(BASE_URL . '/admin/quotations.php');
}

// Additional check: Maybe only allow deletion of 'Draft' or 'Rejected' quotations by default
// if (!in_array($quotation_to_delete['status'], ['Draft', 'Rejected', 'Cancelled']) && !$auth->hasRole('System Admin')) { // System Admin can override
//     $_SESSION['error_message'] = "Solo cotizaciones en estado Borrador, Rechazado o Cancelado pueden ser eliminadas. Estado actual: " . $quotation_to_delete['status'];
//     $auth->redirect(BASE_URL . '/admin/quotations.php');
// }


if ($quotationRepo->delete($quotation_id, $company_id)) {
    $_SESSION['message'] = "Cotización #" . htmlspecialchars($quotation_to_delete['quotation_number']) . " eliminada exitosamente.";
} else {
    $_SESSION['error_message'] = "Error al eliminar la cotización #" . htmlspecialchars($quotation_to_delete['quotation_number']) . ". Verifique los logs.";
}

$auth->redirect(BASE_URL . '/admin/quotations.php');

?>
