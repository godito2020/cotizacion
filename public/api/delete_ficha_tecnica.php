<?php
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');
$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }
if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Sin permisos']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }

try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT ficha_url FROM fichas_tecnicas WHERE id = ?");
    $stmt->execute([$id]);
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ficha) { echo json_encode(['success'=>false,'message'=>'No encontrado']); exit; }

    // Delete file if it's a local upload
    if (strpos($ficha['ficha_url'], BASE_URL . '/uploads/fichas_tecnicas/') === 0) {
        $filename = basename($ficha['ficha_url']);
        $filepath = PUBLIC_PATH . '/uploads/fichas_tecnicas/' . $filename;
        if (file_exists($filepath)) @unlink($filepath);
    }

    $db->prepare("DELETE FROM fichas_tecnicas WHERE id = ?")->execute([$id]);
    echo json_encode(['success'=>true,'message'=>'Ficha eliminada']);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
