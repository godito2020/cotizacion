<?php
// cotizacion/public/login.php
require_once __DIR__ . '/../includes/init.php';

$auth = new Auth();

// If user is already logged in, redirect them away from login page
if ($auth->isLoggedIn()) {
    $auth->redirect(BASE_URL . '/dashboard.php');
}

$error_message_login = ''; // Use a specific variable for login errors

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = $_POST['username_or_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($usernameOrEmail) || empty($password)) {
        $error_message_login = 'Por favor, ingrese usuario/email y contraseña.';
    } else {
        if ($auth->login($usernameOrEmail, $password)) {
            $auth->redirect(BASE_URL . '/dashboard.php');
        } else {
            $error_message_login = 'Usuario/email o contraseña incorrectos. Intente de nuevo.';
            error_log("Failed login attempt for: " . htmlspecialchars($usernameOrEmail));
        }
    }
}

$page_title = "Iniciar Sesión - " . APP_NAME;
require_once TEMPLATES_PATH . '/header.php'; // Include the new header
?>

<article class="grid">
  <div>
    <hgroup>
      <h1>Iniciar Sesión</h1>
      <h2>Acceda a su cuenta</h2>
    </hgroup>

    <?php if (!empty($error_message_login)): ?>
        <p><mark class="pico-color-red-200"><?php echo htmlspecialchars($error_message_login); ?></mark></p>
    <?php endif; ?>

    <?php if (isset($_GET['registered']) && $_GET['registered'] === 'true'): ?>
        <p class="pico-background-green-200 pico-color-green-800" style="padding: 0.5rem;">¡Registro exitoso! Por favor, inicie sesión.</p>
    <?php endif; ?>
    <?php if (isset($_GET['logged_out']) && $_GET['logged_out'] === 'true'): ?>
         <p class="pico-background-green-200 pico-color-green-800" style="padding: 0.5rem;">Ha cerrado sesión exitosamente.</p>
    <?php endif; ?>
     <?php if (isset($_GET['unauthorized']) && $_GET['unauthorized'] === 'true'): ?>
         <p class="pico-background-red-200 pico-color-red-800" style="padding: 0.5rem;">No tiene autorización para acceder a esa página. Por favor, inicie sesión con una cuenta autorizada.</p>
    <?php endif; ?>
     <?php if (isset($_GET['redirect_to'])): ?>
         <p class="pico-background-yellow-200 pico-color-yellow-800" style="padding: 0.5rem;">Debe iniciar sesión para ver esa página.</p>
    <?php endif; ?>


    <form action="<?php echo BASE_URL; ?>/login.php<?php echo isset($_GET['redirect_to']) ? '?redirect_to=' . urlencode($_GET['redirect_to']) : ''; ?>" method="POST">
      <input type="text" name="username_or_email" placeholder="Usuario o Email" aria-label="Usuario o Email" autocomplete="username" required>
      <input type="password" name="password" placeholder="Contraseña" aria-label="Contraseña" autocomplete="current-password" required>
      <fieldset>
        <label for="remember">
          <input type="checkbox" role="switch" id="remember" name="remember">
          Recordarme (funcionalidad no implementada)
        </label>
      </fieldset>
      <button type="submit" class="contrast">Iniciar Sesión</button>
    </form>
     <p><a href="<?php echo BASE_URL; ?>/register.php">¿No tiene una cuenta? Regístrese aquí.</a></p>
  </div>
  <div>
      <!-- Optional: Add an image or promotional content here -->
      <!-- <img src="path/to/your/login-image.jpg" alt="Login Visual"> -->
  </div>
</article>

<?php
require_once TEMPLATES_PATH . '/footer.php'; // Include the new footer
?>
