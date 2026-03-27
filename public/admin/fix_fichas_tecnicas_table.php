<?php
require_once __DIR__ . '/../../includes/init.php';
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    http_response_code(403);
    die('Acceso denegado.');
}

$db = getDBConnection();
$results = [];

try {
    $db->exec("CREATE TABLE IF NOT EXISTS fichas_tecnicas (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        codigo_producto VARCHAR(100) NOT NULL,
        ficha_url TEXT NOT NULL,
        nombre_archivo VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_codigo (codigo_producto)
    )");
    $results[] = ['status' => 'ok', 'msg' => 'Tabla fichas_tecnicas creada/verificada correctamente'];
} catch (PDOException $e) {
    $results[] = ['status' => 'error', 'msg' => 'Error: ' . $e->getMessage()];
}

// Create upload directory
$uploadDir = PUBLIC_PATH . '/uploads/fichas_tecnicas';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        $results[] = ['status' => 'ok', 'msg' => 'Directorio uploads/fichas_tecnicas creado'];
    } else {
        $results[] = ['status' => 'error', 'msg' => 'No se pudo crear directorio uploads/fichas_tecnicas'];
    }
} else {
    $results[] = ['status' => 'ok', 'msg' => 'Directorio uploads/fichas_tecnicas ya existe'];
}
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Fix: Fichas Técnicas</title>
<style>body{font-family:monospace;padding:2rem;background:#1a1a2e;color:#eee} .ok{color:#4ecca3} .error{color:#e94560}</style>
</head>
<body>
<h2>Fix: Tabla Fichas Técnicas</h2>
<?php foreach ($results as $r): ?>
<p class="<?= $r['status'] ?>">[<?= strtoupper($r['status']) ?>] <?= htmlspecialchars($r['msg']) ?></p>
<?php endforeach; ?>
<p style="color:#aaa;margin-top:2rem">⚠️ Elimina este archivo después: <code>public/admin/fix_fichas_tecnicas_table.php</code></p>
</body></html>
