<?php
/**
 * Exportar mis datos de inventario
 */

require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!Permissions::canAccessInventoryPanel($auth)) {
    $_SESSION['error_message'] = 'No tienes permisos para acceder al módulo de inventario';
    header('Location: ' . BASE_URL . '/dashboard_simple.php');
    exit;
}

$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

// Verificar sesión activa
$session = new InventorySession();
$activeSession = $session->getActiveSession($companyId);

if (!$activeSession) {
    $_SESSION['error_message'] = 'No hay sesión de inventario activa';
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
    exit;
}

// Exportar
try {
    $reports = new InventoryReports();
    $filePath = $reports->exportToExcel($activeSession['id'], 'user', $userId);

    $sessionName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $activeSession['name']);
    $filename = "Mi_Inventario_{$sessionName}_" . date('Y-m-d_His') . ".xlsx";

    $reports->downloadExcel($filePath, $filename);

} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al generar el archivo de exportación';
    header('Location: ' . BASE_URL . '/inventario/dashboard.php');
    exit;
}
