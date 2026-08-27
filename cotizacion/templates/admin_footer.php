</div> <!-- .content-area -->
            <footer class="main-footer">
                <p>&copy; <?php echo date("Y"); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.</p>
            </footer>
        </main> <!-- .main-content -->
    </div> <!-- .admin-wrapper -->

    <!-- Botón para toggle del sidebar en móviles/tablets -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Scripts JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const sidebarToggle = document.getElementById('sidebarToggle');

            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    mainContent.classList.toggle('sidebar-open');
                    // Cambiar icono del toggle (opcional)
                    const icon = sidebarToggle.querySelector('i');
                    if (sidebar.classList.contains('open')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            }

            // Opcional: Cerrar sidebar si se hace clic fuera en modo overlay (más complejo)
            // document.addEventListener('click', function(event) {
            //     if (sidebar.classList.contains('open') && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
            //         sidebar.classList.remove('open');
            //         mainContent.classList.remove('sidebar-open');
            //         const icon = sidebarToggle.querySelector('i');
            //         icon.classList.remove('fa-times');
            //         icon.classList.add('fa-bars');
            //     }
            // });

        });
    </script>
    <!-- Otros scripts como Select2 ya están en cotizacion_form.php, podrían moverse a un admin_scripts.js global -->
    <!-- <script src="<?php echo BASE_URL; ?>public/js/admin_scripts.js"></script> -->
</body>
</html>
