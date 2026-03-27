<?php
// Guardar errores de JavaScript en el log
$logFile = __DIR__ . '/../../logs/javascript_errors.log';

// Crear directorio de logs si no existe
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Obtener datos del error
$rawData = file_get_contents('php://input');
$errorData = json_decode($rawData, true);

if ($errorData) {
    $logEntry = sprintf(
        "[%s] %s: %s\n  File: %s:%d:%d\n  Stack: %s\n\n",
        $errorData['timestamp'] ?? date('Y-m-d H:i:s'),
        $errorData['type'] ?? 'Error',
        $errorData['message'] ?? 'Unknown error',
        $errorData['filename'] ?? 'unknown',
        $errorData['lineno'] ?? 0,
        $errorData['colno'] ?? 0,
        $errorData['stack'] ?? 'No stack trace'
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
}
?>
