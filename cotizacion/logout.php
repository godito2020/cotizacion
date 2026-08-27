<?php
require_once 'config/config.php'; // Para BASE_URL
require_once 'utils/auth_helper.php'; // Para logout_user()

logout_user();

// Redirigir a la página de login
header("Location: " . BASE_URL . "login.php?status=loggedout");
exit;
?>
