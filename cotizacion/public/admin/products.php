<?php
// cotizacion/public/admin/products.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout(); // Force logout
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole(['Company Admin', 'System Admin'])) { // System admin might also manage products
    $_SESSION['error_message'] = "No está autorizado para gestionar productos.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$productRepo = new Product();
$products = $productRepo->getAllByCompany($company_id);

$page_title = "Gestionar Productos - " . APP_NAME;
require_once TEMPLATES_PATH . '/header.php';
?>

<nav aria-label="breadcrumb">
  <ul>
    <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Panel Admin</a></li>
    <li>Gestionar Productos</li>
  </ul>
</nav>

<hgroup>
    <h1>Gestionar Productos</h1>
    <p>Administre los productos de su empresa (Compañía ID: <?php echo htmlspecialchars($company_id); ?>)</p>
</hgroup>

<div class="grid">
    <div>
        <a href="product_form.php" role="button">Añadir Nuevo Producto</a>
    </div>
    <div>
        <a href="product_import.php" role="button" class="secondary outline">Importar Productos (CSV)</a>
    </div>
</div>

<figure style="overflow-x: auto;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>SKU</th>
                <th style="text-align:right;">Precio</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;">No se encontraron productos para su compañía.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['id']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                        <td style="text-align:right;"><?php echo htmlspecialchars(number_format($product['price'], 2)); ?></td>
                        <td><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)) . (strlen($product['description'] ?? '') > 50 ? '...' : ''); ?></td>
                        <td>
                            <a href="product_form.php?id=<?php echo $product['id']; ?>" role="button" class="outline">Editar</a>
                            <a href="product_delete.php?id=<?php echo $product['id']; ?>" role="button" class="secondary outline" onclick="return confirm('¿Está seguro de que desea eliminar este producto? Esta acción no se puede deshacer.');">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</figure>

<?php
require_once TEMPLATES_PATH . '/footer.php';
?>
