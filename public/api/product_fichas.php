<?php
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');
$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }

$codigo = trim($_GET['codigo'] ?? '');
if (empty($codigo)) { echo json_encode(['success'=>false,'message'=>'Código requerido']); exit; }

try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, codigo_producto, ficha_url, nombre_archivo, created_at FROM fichas_tecnicas WHERE codigo_producto = ? ORDER BY created_at DESC");
    $stmt->execute([$codigo]);
    $fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true, 'fichas'=>$fichas]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
}
