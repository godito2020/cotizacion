/* =====================================================================
   COTI · theme-pro.js — Cambio de tema claro/oscuro
   ---------------------------------------------------------------------
   Usa el modo oscuro nativo de Bootstrap 5.3 (data-bs-theme en <html>).
   Persiste la preferencia en localStorage('coti-theme').

   IMPORTANTE: para evitar el parpadeo (FOUC), incluir ADEMÁS este
   snippet inline en el <head>, ANTES de cargar las hojas de estilo:

     <script>
       (function(){
         var t = localStorage.getItem('coti-theme')
                 || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
         document.documentElement.setAttribute('data-bs-theme', t);
       })();
     </script>

   Llamar a COTITheme.toggle() desde un botón, o usar [data-theme-toggle].
   ===================================================================== */
(function () {
    'use strict';
    var KEY = 'coti-theme';

    function current() {
        return document.documentElement.getAttribute('data-bs-theme') || 'light';
    }

    function apply(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        try { localStorage.setItem(KEY, theme); } catch (e) {}
        syncButtons(theme);
    }

    function toggle() {
        apply(current() === 'dark' ? 'light' : 'dark');
    }

    // Actualiza icono y accesibilidad de todos los botones de tema
    function syncButtons(theme) {
        var dark = theme === 'dark';
        document.querySelectorAll('[data-theme-toggle], #themeToggle, .theme-toggle').forEach(function (btn) {
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-moon', !dark);
                icon.classList.toggle('fa-sun', dark);
            }
            var label = dark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        });
    }

    function init() {
        // El tema ya fue fijado por el snippet inline; solo enganchamos botones.
        document.querySelectorAll('[data-theme-toggle], #themeToggle, .theme-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) { e.preventDefault(); toggle(); });
        });
        syncButtons(current());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // API pública
    window.COTITheme = { toggle: toggle, apply: apply, current: current };
})();
