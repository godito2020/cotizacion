<?php
// cotizacion/templates/header.php
if (session_status() == PHP_SESSION_NONE && !headers_sent()) { // Ensure session is started if not already (e.g. by init.php)
    session_start();
}

// APP_NAME and BASE_URL should be defined in config.php, which should be loaded via init.php
// If init.php is always included before this header, this explicit require might be redundant
// but good for standalone template usage or clarity.
if (!defined('BASE_URL') || !defined('APP_NAME')) {
    // Attempt to load config if not already loaded by init.php
    // This path assumes header.php is in 'templates' and config.php is in 'config' at the same root level as 'templates'
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        // Fallback definitions if config is not found
        define('BASE_URL', '/'); // Adjust this fallback as needed
        define('APP_NAME', 'Cotizaciones');
        // It's better to ensure init.php is always loaded first.
    }
}

$auth = null; // Initialize $auth
if (class_exists('Auth')) { // Check if Auth class is available (loaded by autoloader via init.php)
    $auth = new Auth();
}

$page_title = $page_title ?? APP_NAME; // Allow pages to set their own $page_title

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/css/pico.min.css">
    <!-- Optional: Link to a custom.css for overrides -->
    <!-- <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/'); ?>/css/custom.css"> -->
    <style>
        /* Basic custom styles for layout */
        body > header {
            padding: 1rem 0; /* Add some padding to the main header nav */
        }
        main.container {
            padding-top: 1rem; /* Add space below the fixed/sticky header if used */
            padding-bottom: 2rem;
        }
        body > footer {
            padding: 1rem 0;
            text-align: center;
            border-top: 1px solid var(--pico-muted-border-color);
            margin-top: 2rem;
        }
        /* Consistent nav styling */
        nav ul li a strong { /* App Name */
            color: var(--pico-h1-color); /* Or a specific brand color */
        }
        /* Adjust Pico's default container padding for the main content area if header/footer are outside */
        /* If header/footer are inside main.container, this is not needed. */
        /* For this setup, header/footer are outside main.container */
    </style>
</head>
<body>

<header class="container">
    <nav>
      <ul>
        <li><a href="<?php echo rtrim(BASE_URL, '/'); ?>/index.php"><strong><?php echo APP_NAME; ?></strong></a></li>
      </ul>
      <ul>
        <?php if ($auth && $auth->isLoggedIn()): ?>
          <li><a href="<?php echo rtrim(BASE_URL, '/'); ?>/dashboard.php">Dashboard</a></li>
          <?php if ($auth->hasRole(['System Admin', 'Company Admin', 'Salesperson'])): // Check if user has any of these roles to show Admin Panel link ?>
            <li><a href="<?php echo rtrim(BASE_URL, '/'); ?>/admin/index.php" role="button" class="secondary outline">Panel Admin</a></li>
          <?php endif; ?>
          <li><a href="<?php echo rtrim(BASE_URL, '/'); ?>/logout.php" role="button">Cerrar Sesión</a></li>
        <?php else: ?>
          <li><a href="<?php echo rtrim(BASE_URL, '/'); ?>/login.php">Iniciar Sesión</a></li>
          <li><a href="<?php echo rtrim(BASE_URL, '/'); ?>/register.php">Registrarse</a></li>
        <?php endif; ?>
      </ul>
    </nav>
</header>

<main class="container">
    <?php
    // Display session messages if they exist
    if (isset($_SESSION['message'])): ?>
        <article class="message success" style="background-color: var(--pico-form-element-valid-active-border-color); color: var(--pico-primary-inverse); padding: 0.75rem; margin-bottom:1rem;">
            <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
        </article>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <article class="message error" style="background-color: var(--pico-form-element-invalid-active-border-color); color: var(--pico-primary-inverse); padding: 0.75rem; margin-bottom:1rem;">
             <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </article>
    <?php endif; ?>
    <!-- Page content starts here -->
