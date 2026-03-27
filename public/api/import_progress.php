<?php
// Start output buffering to prevent any unwanted output
ob_start();

// Suppress warnings and notices that could interfere with JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Increase memory limit for Excel processing
ini_set('memory_limit', '1024M'); // 1GB
ini_set('max_execution_time', 600); // 10 minutes

require_once __DIR__ . '/../../includes/init.php';

// Clean any output that might have been generated
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

/**
 * Send clean JSON response
 */
function sendJsonResponse($data, $httpCode = 200) {
    // Clean any output buffer
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    sendJsonResponse(['error' => 'Unauthorized'], 401);
}

if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    sendJsonResponse(['error' => 'Insufficient permissions'], 403);
}

$companyId = $auth->getCompanyId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'validate':
            if (empty($_FILES['excel_file']['tmp_name'])) {
                sendJsonResponse(['error' => 'No file uploaded']);
            }

            $importer = new ExcelImporter();
            $validation = $importer->validateExcelFile($_FILES['excel_file']['tmp_name']);

            if (!$validation['valid']) {
                sendJsonResponse([
                    'valid' => false,
                    'error' => $validation['error'] ?? 'Invalid Excel file',
                    'missing_columns' => $validation['missing_columns'] ?? []
                ]);
            } else {
                sendJsonResponse([
                    'valid' => true,
                    'total_rows' => $validation['total_rows'] ?? 0,
                    'found_columns' => $validation['found_columns'] ?? [],
                    'warehouse_columns' => $validation['warehouse_columns'] ?? []
                ]);
            }
            break;

        case 'import':
            if (empty($_FILES['excel_file']['tmp_name'])) {
                sendJsonResponse(['error' => 'No file uploaded']);
            }

            $importType = $_POST['import_type'] ?? 'products';
            $importCurrency = $_POST['import_currency'] ?? 'USD';

            // Create session ID for progress tracking
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $sessionId = session_id();

            // Initialize progress
            $_SESSION['import_progress'] = [
                'started' => true,
                'progress' => 0,
                'total' => 0,
                'current_row' => 0,
                'status' => 'Iniciando importación...',
                'completed' => false,
                'error' => null,
                'start_time' => time()
            ];

            $importer = new ExcelImporter();

            if ($importType === 'products') {
                $result = $importer->importProductsWithProgress($_FILES['excel_file']['tmp_name'], $companyId, $importCurrency, $sessionId);
            } else if ($importType === 'stock') {
                $result = $importer->importWarehouseStockWithProgress($_FILES['excel_file']['tmp_name'], $companyId, $sessionId);
            } else {
                sendJsonResponse(['error' => 'Invalid import type']);
            }

            // Mark as completed
            $_SESSION['import_progress']['completed'] = true;
            $_SESSION['import_progress']['progress'] = 100;
            $_SESSION['import_progress']['status'] = 'Importación completada';

            sendJsonResponse($result);
            break;

        case 'progress':
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $progress = $_SESSION['import_progress'] ?? [
                'started' => false,
                'progress' => 0,
                'total' => 0,
                'current_row' => 0,
                'status' => 'No hay importación en progreso',
                'completed' => false,
                'error' => null
            ];

            // Calculate estimated time
            if ($progress['started'] && isset($progress['start_time']) && $progress['current_row'] > 0) {
                $elapsed = time() - $progress['start_time'];
                $rate = $progress['current_row'] / $elapsed; // rows per second
                $remaining = $progress['total'] - $progress['current_row'];
                $estimated_seconds = $remaining > 0 && $rate > 0 ? ceil($remaining / $rate) : 0;
                $progress['estimated_time'] = $estimated_seconds;
            } else {
                $progress['estimated_time'] = 0;
            }

            sendJsonResponse($progress);
            break;

        case 'reset':
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['import_progress']);
            sendJsonResponse(['success' => true]);
            break;

        default:
            sendJsonResponse(['error' => 'Invalid action'], 400);
            break;
    }

} catch (Exception $e) {
    error_log("Import API Error: " . $e->getMessage());

    // Update progress with error
    if (isset($_SESSION)) {
        $_SESSION['import_progress']['error'] = $e->getMessage();
        $_SESSION['import_progress']['completed'] = true;
        $_SESSION['import_progress']['status'] = 'Error: ' . $e->getMessage();
    }

    sendJsonResponse(['error' => 'Import failed: ' . $e->getMessage()], 500);
}
?>