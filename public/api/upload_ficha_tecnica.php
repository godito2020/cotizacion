<?php
require_once __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');
$auth = new Auth();
if (!$auth->isLoggedIn()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }
if (!$auth->hasRole(['Administrador de Empresa', 'Administrador del Sistema'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Sin permisos']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Método no permitido']); exit; }

$codigo = trim($_POST['codigo'] ?? '');
if (empty($codigo)) { echo json_encode(['success'=>false,'message'=>'Código de producto requerido']); exit; }
if (!isset($_FILES['ficha']) || $_FILES['ficha']['error'] !== UPLOAD_ERR_OK) {
    $errMsgs = [UPLOAD_ERR_INI_SIZE=>'Archivo muy grande (límite PHP)',UPLOAD_ERR_FORM_SIZE=>'Archivo muy grande',UPLOAD_ERR_PARTIAL=>'Subida incompleta',UPLOAD_ERR_NO_FILE=>'No se seleccionó archivo',UPLOAD_ERR_NO_TMP_DIR=>'Sin directorio temporal',UPLOAD_ERR_CANT_WRITE=>'Sin permisos de escritura'];
    $errCode = $_FILES['ficha']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success'=>false,'message'=>$errMsgs[$errCode] ?? 'Error al subir archivo (código '.$errCode.')']); exit;
}

$file = $_FILES['ficha'];
$allowedMimes = ['image/jpeg','image/png','application/pdf'];
$allowedExts  = ['jpg','jpeg','png','pdf'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($mimeType, $allowedMimes) || !in_array($ext, $allowedExts)) {
    echo json_encode(['success'=>false,'message'=>'Tipo no permitido. Use JPG, PNG o PDF']); exit;
}
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success'=>false,'message'=>'Archivo muy grande (máx 10MB)']); exit;
}

$uploadDir = PUBLIC_PATH . '/uploads/fichas_tecnicas/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

$filename = $codigo . '_' . uniqid() . '.' . $ext;
$filepath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success'=>false,'message'=>'Error al guardar archivo']); exit;
}

$fichaUrl = BASE_URL . '/uploads/fichas_tecnicas/' . $filename;
$nombreOriginal = pathinfo($file['name'], PATHINFO_FILENAME);

try {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO fichas_tecnicas (codigo_producto, ficha_url, nombre_archivo) VALUES (?, ?, ?)");
    $stmt->execute([$codigo, $fichaUrl, $nombreOriginal]);
    echo json_encode(['success'=>true,'message'=>'Ficha técnica subida correctamente','ficha_url'=>$fichaUrl,'id'=>$db->lastInsertId()]);
} catch (Exception $e) {
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode(['success'=>false,'message'=>'Error BD: '.$e->getMessage()]);
}
