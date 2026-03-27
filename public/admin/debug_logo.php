<?php
require_once __DIR__ . '/../../includes/init.php';
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    die('Acceso denegado.');
}
$db = getDBConnection();

// Columnas reales de la tabla companies
$cols = $db->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_ASSOC);

// Logo de cada empresa
$companies = $db->query("SELECT id, name, logo_url FROM companies ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/html; charset=utf-8');
?>
<style>body{font-family:monospace;padding:1rem;} table{border-collapse:collapse;width:100%;margin:1rem 0;} th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;} .ok{color:green} .bad{color:red} .warn{color:orange}</style>

<h3>Columnas de la tabla companies</h3>
<table>
<tr><th>Field</th><th>Type</th><th>Key</th><th>Extra</th></tr>
<?php foreach($cols as $c): ?>
<tr>
  <td><?= $c['Field'] ?></td>
  <td><?= $c['Type'] ?></td>
  <td><?= $c['Key'] ?></td>
  <td><?= $c['Extra'] ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3>Logo URL por empresa</h3>
<table>
<tr><th>ID</th><th>Empresa</th><th>logo_url (BD)</th><th>URL generada</th><th>Archivo en disco</th><th>Preview</th></tr>
<?php foreach($companies as $c):
    $dbVal = $c['logo_url'];
    $url = '';
    $fileExists = false;
    $filePath = '';
    if ($dbVal) {
        $url = BASE_URL . '/' . ltrim($dbVal, '/');
        $filePath = PUBLIC_PATH . '/' . ltrim($dbVal, '/');
        $fileExists = file_exists($filePath);
    }
?>
<tr>
  <td><?= $c['id'] ?></td>
  <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
  <td><?= htmlspecialchars($dbVal ?? '(null)') ?></td>
  <td><?= htmlspecialchars($url ?: '-') ?></td>
  <td class="<?= $dbVal ? ($fileExists ? 'ok' : 'bad') : 'warn' ?>">
    <?php if (!$dbVal): ?>sin logo<?php elseif ($fileExists): ?>✓ existe<br><small><?= htmlspecialchars($filePath) ?></small><?php else: ?>✗ NO EXISTE<br><small><?= htmlspecialchars($filePath) ?></small><?php endif; ?>
  </td>
  <td><?php if ($url): ?><img src="<?= htmlspecialchars($url) ?>" style="max-height:40px" onerror="this.outerHTML='<span class=bad>✗ no carga</span>'"><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</table>

<h3>Archivos en uploads/company/</h3>
<?php
$uploadDir = PUBLIC_PATH . '/uploads/company/';
$files = glob($uploadDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
echo '<p>Directorio: ' . htmlspecialchars($uploadDir) . '</p>';
echo '<ul>';
foreach ($files as $f) {
    echo '<li>' . basename($f) . ' (' . round(filesize($f)/1024) . 'KB) - ' . date('Y-m-d H:i', filemtime($f)) . '</li>';
}
echo '</ul>';
?>
<p style="color:#888;margin-top:2rem;font-size:0.85em">⚠️ Elimina este archivo: public/admin/debug_logo.php</p>
