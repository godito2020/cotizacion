<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    // Handle GET request (from link)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ' . BASE_URL . '/quotations/index.php');
        exit;
    }
    // Handle POST request (AJAX)
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$companyId = $auth->getCompanyId();
$userId = $auth->getUserId();

// Handle GET request - duplicate and redirect to edit
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $quotationId = $_GET['id'] ?? 0;

    if (!$quotationId) {
        $_SESSION['error'] = 'ID de cotización no válido';
        header('Location: ' . BASE_URL . '/quotations/index.php');
        exit;
    }

    try {
        $quotationRepo = new Quotation();
        $newId = $quotationRepo->duplicate($quotationId, $companyId, $userId);

        if ($newId) {
            $_SESSION['success'] = 'Cotización duplicada exitosamente. Puedes editarla ahora.';

            // Detect if mobile and redirect accordingly
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $isMobile = preg_match('/(android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $userAgent);

            if ($isMobile) {
                header('Location: ' . BASE_URL . '/quotations/view_mobile.php?id=' . $newId);
            } else {
                header('Location: ' . BASE_URL . '/quotations/edit.php?id=' . $newId);
            }
            exit;
        } else {
            $_SESSION['error'] = 'Error al duplicar la cotización';
            header('Location: ' . BASE_URL . '/quotations/index.php');
            exit;
        }

    } catch (Exception $e) {
        error_log("Error duplicating quotation: " . $e->getMessage());
        $_SESSION['error'] = 'Error interno del servidor';
        header('Location: ' . BASE_URL . '/quotations/index.php');
        exit;
    }
}

// Handle POST request - AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $quotationId = $_POST['id'] ?? 0;

    try {
        $quotationRepo = new Quotation();
        $result = $quotationRepo->duplicate($quotationId, $companyId, $userId);

        if ($result) {
            echo json_encode(['success' => true, 'new_id' => $result, 'message' => 'Cotización duplicada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al duplicar la cotización']);
        }

    } catch (Exception $e) {
        error_log("Error duplicating quotation: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
    exit;
}

// If neither GET nor POST
header('Location: ' . BASE_URL . '/quotations/index.php');
exit;
?>