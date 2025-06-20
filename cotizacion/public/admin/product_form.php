<?php
// cotizacion/public/admin/product_form.php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
}

$loggedInUser = $auth->getUser();
if (!$loggedInUser || !isset($loggedInUser['company_id'])) {
    $_SESSION['error_message'] = "Usuario o información de la compañía ausente. Por favor, re-ingrese.";
    $auth->logout();
    $auth->redirect(BASE_URL . '/login.php');
}
$company_id = $loggedInUser['company_id'];

if (!$auth->hasRole(['Company Admin', 'System Admin'])) {
    $_SESSION['error_message'] = "No está autorizado para gestionar productos.";
    $auth->redirect(BASE_URL . '/admin/index.php');
}

$productRepo = new Product();

$product_id_get = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$is_edit_mode = false;
$form_values = [ // For sticky form and pre-population
    'id' => null, 'name' => '', 'sku' => '', 'price' => '', 'description' => ''
];

if ($product_id_get) {
    $product_data = $productRepo->getById($product_id_get, $company_id);
    if ($product_data) {
        $is_edit_mode = true;
        $page_title_action = "Editar Producto: " . htmlspecialchars($product_data['name']);
        $form_values = $product_data; // Populate with existing data
    } else {
        $_SESSION['error_message'] = "Producto no encontrado o no tiene permiso para editarlo.";
        $auth->redirect(BASE_URL . '/admin/products.php');
    }
} else {
    $page_title_action = "Añadir Nuevo Producto";
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_values['name'] = trim($_POST['name'] ?? '');
    $form_values['sku'] = trim($_POST['sku'] ?? '');
    $form_values['price'] = trim($_POST['price'] ?? '');
    $form_values['description'] = trim($_POST['description'] ?? null);
    $current_product_id = $_POST['product_id'] ?? null;

    if (empty($form_values['name'])) { $errors[] = "El nombre del producto es requerido."; }
    if (empty($form_values['sku'])) { $errors[] = "El SKU es requerido."; }
    if (!is_numeric($form_values['price']) || floatval($form_values['price']) < 0) {
        $errors[] = "El precio debe ser un número no negativo.";
    } else {
        $form_values['price'] = floatval($form_values['price']);
    }

    $excludeProductIdCheck = $is_edit_mode ? (int)$current_product_id : null;
    if (!empty($form_values['sku']) && !$productRepo->isSkuUniqueForCompany($form_values['sku'], $company_id, $excludeProductIdCheck)) {
        $errors[] = "Este SKU ya está en uso por otro producto en su compañía.";
    }

    if (empty($errors)) {
        if ($is_edit_mode) {
            if (!$current_product_id) {
                $_SESSION['error_message'] = "ID de producto ausente para la actualización.";
                $auth->redirect(BASE_URL . '/admin/products.php');
            }
            $success = $productRepo->update(
                (int)$current_product_id, $company_id, $form_values['name'],
                $form_values['sku'], $form_values['price'], $form_values['description']
            );
            if ($success) {
                $_SESSION['message'] = "Producto actualizado exitosamente.";
            } else {
                $_SESSION['error_message'] = "Error al actualizar el producto. Revise los logs.";
            }
        } else {
            $new_id = $productRepo->create(
                $company_id, $form_values['name'], $form_values['sku'],
                $form_values['price'], $form_values['description']
            );
            if ($new_id) {
                $_SESSION['message'] = "Producto creado exitosamente.";
            } else {
                $_SESSION['error_message'] = "Error al crear el producto. Revise los logs (ej. SKU duplicado).";
            }
        }
        if(isset($_SESSION['message'])) $auth->redirect(BASE_URL . '/admin/products.php');
        // If error, it will be displayed on the form
    }
}

$page_title = $page_title_action . " - " . APP_NAME;
require_once TEMPLATES_PATH . '/header.php';
?>

<nav aria-label="breadcrumb">
  <ul>
    <li><a href="<?php echo BASE_URL; ?>/admin/index.php">Panel Admin</a></li>
    <li><a href="<?php echo BASE_URL; ?>/admin/products.php">Gestionar Productos</a></li>
    <li><?php echo $page_title_action; ?></li>
  </ul>
</nav>

<article>
    <header>
        <h2><?php echo $page_title_action; ?></h2>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="error-messages" role="alert">
            <strong>Por favor corrija los siguientes errores:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php /* Display session error if redirected here with one, and not a POST request with its own errors */ ?>
    <?php if (isset($_SESSION['error_message']) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <p role="alert" class="pico-background-red-200 pico-color-red-800" style="padding:0.5rem;"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
    <?php endif; ?>


    <form action="product_form.php<?php echo $is_edit_mode ? '?id=' . (int)$product_id_get : ''; ?>" method="POST">
        <?php if ($is_edit_mode): ?>
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($form_values['id']); ?>">
        <?php endif; ?>

        <div class="grid">
            <label for="name">
                Nombre del Producto <span style="color:red;">*</span>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($form_values['name']); ?>" required>
            </label>
            <label for="sku">
                SKU <span style="color:red;">*</span>
                <input type="text" id="sku" name="sku" value="<?php echo htmlspecialchars($form_values['sku']); ?>" required>
            </label>
        </div>

        <label for="price">
            Precio <span style="color:red;">*</span>
            <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($form_values['price']); ?>" step="0.01" min="0" required>
        </label>

        <label for="description">
            Descripción
            <textarea id="description" name="description"><?php echo htmlspecialchars($form_values['description'] ?? ''); ?></textarea>
        </label>

        <footer class="grid">
            <a href="products.php" role="button" class="secondary">Cancelar</a>
            <button type="submit"><?php echo $is_edit_mode ? 'Actualizar Producto' : 'Crear Producto'; ?></button>
        </footer>
    </form>
</article>

<?php
require_once TEMPLATES_PATH . '/footer.php';
?>
