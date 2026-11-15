<?php
// includes/app_footer.php
// Master App Shell Footer - gstack Design System
?>
            </main> <!-- .app-content-body -->
        </div> <!-- .app-main-wrapper -->
    </div> <!-- .app-shell -->

    <!-- Universal Mobile Bottom Navigation (Notion/Linear Clean Mobile Bar) -->
    <?php include __DIR__ . '/mobile_bottom_nav.php'; ?>

    <!-- Offcanvas Mobile Drawer Backdrop -->
    <div class="app-drawer-backdrop" id="appDrawerBackdrop"></div>

    <!-- Bootstrap 5.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Controller Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/theme-pro.js?v=<?= @filemtime(APP_ROOT . "/public/assets/css/theme-pro.css") ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/pwa.js"></script>

    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
