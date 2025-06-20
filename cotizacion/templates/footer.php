<?php
// cotizacion/templates/footer.php
?>
    <!-- Page content ends here -->
</main>

<footer class="container">
    <small>
        &copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? APP_NAME : "Sistema de Cotizaciones"; ?>. Todos los derechos reservados.
        <!-- Optional: Add more footer links or info -->
        <!-- <br>
        <a href="#">Política de Privacidad</a> | <a href="#">Términos de Servicio</a> -->
    </small>
</footer>

<!-- Common JavaScript files can be linked here -->
<!-- Example: <script src="<?php echo rtrim(BASE_URL, '/'); ?>/js/main.js"></script> -->
<?php
// Output any page-specific JavaScript blocks if $page_scripts array is set
if (isset($page_scripts) && is_array($page_scripts)) {
    foreach ($page_scripts as $script) {
        echo "<script src=\"" . htmlspecialchars($script) . "\"></script>\n";
    }
}
// Output any page-specific inline JavaScript if $inline_script is set
if (isset($inline_script) && !empty($inline_script)) {
    echo "<script>\n" . $inline_script . "\n</script>\n";
}
?>
</body>
</html>
