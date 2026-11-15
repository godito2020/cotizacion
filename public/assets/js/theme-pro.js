/**
 * COTI · gstack Industrial Design System Controller (theme-pro.js)
 * Clean, lightweight, and robust:
 * - 3-State Theme Management: System (default), Light, Dark
 * - OS Live Listener (prefers-color-scheme)
 * - Native Desktop Sidebar & Mobile Offcanvas Drawer Handler
 * - Segmented Pill Control & Profile Card Synchronization
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'coti-theme';
    var media = window.matchMedia('(prefers-color-scheme: dark)');

    /* --- Theme Core Functions --- */

    function getMode() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved === 'light' || saved === 'dark' || saved === 'system') {
                return saved;
            }
        } catch (e) {}
        return 'system';
    }

    function resolveMode(mode) {
        if (mode === 'system') {
            return media.matches ? 'dark' : 'light';
        }
        return mode === 'dark' ? 'dark' : 'light';
    }

    function getCurrentTheme() {
        return resolveMode(getMode());
    }

    function getNextMode(currentMode) {
        if (currentMode === 'system') return 'light';
        if (currentMode === 'light') return 'dark';
        return 'system';
    }

    function syncUI(mode, resolvedTheme) {
        var next = getNextMode(mode);

        var modeLabels = {
            system: 'Sistema',
            light: 'Claro',
            dark: 'Oscuro'
        };

        var tooltips = {
            system: 'Tema: Sistema (Automático). Clic para cambiar a Claro',
            light: 'Tema: Claro. Clic para cambiar a Oscuro',
            dark: 'Tema: Oscuro. Clic para cambiar a Sistema'
        };

        // Update all toggle buttons
        var buttons = document.querySelectorAll('[data-theme-toggle], #themeToggle, .theme-toggle, .mobile-theme-toggle, .mobile-nav-theme, .app-theme-toggle');
        buttons.forEach(function (button) {
            button.setAttribute('data-theme-mode', mode);
            button.setAttribute('data-theme-next', next);
            button.setAttribute('title', tooltips[mode]);
            button.setAttribute('aria-label', tooltips[mode]);

            var icon = button.querySelector('i');
            if (icon) {
                icon.className = 'fas ' + (mode === 'system' ? 'fa-circle-half-stroke' : (mode === 'light' ? 'fa-sun' : 'fa-moon'));
            }

            var textSpan = button.querySelector('.theme-mode-text, span:not(.sr-only)');
            if (textSpan && !button.classList.contains('mobile-nav-theme')) {
                textSpan.textContent = modeLabels[mode];
            }
        });

        // Update 3-state Segmented Pills
        var pillButtons = document.querySelectorAll('.theme-toggle-pill .theme-btn, [data-theme-val]');
        pillButtons.forEach(function (pill) {
            var val = pill.getAttribute('data-theme-val');
            if (val === mode) {
                pill.classList.add('is-active');
                pill.setAttribute('aria-checked', 'true');
            } else {
                pill.classList.remove('is-active');
                pill.setAttribute('aria-checked', 'false');
            }
        });

        // Update Profile Theme Choice Cards
        var choiceCards = document.querySelectorAll('.theme-choice-card');
        choiceCards.forEach(function (card) {
            var cardMode = card.getAttribute('data-choice-mode');
            if (cardMode === mode) {
                card.classList.add('is-active');
                card.setAttribute('aria-checked', 'true');
            } else {
                card.classList.remove('is-active');
                card.setAttribute('aria-checked', 'false');
            }
        });
    }

    function apply(mode) {
        if (mode !== 'light' && mode !== 'dark' && mode !== 'system') {
            mode = 'system';
        }

        try {
            if (mode === 'system') {
                localStorage.removeItem(STORAGE_KEY);
            } else {
                localStorage.setItem(STORAGE_KEY, mode);
            }
        } catch (e) {}

        var resolvedTheme = resolveMode(mode);
        document.documentElement.setAttribute('data-bs-theme', resolvedTheme);
        document.documentElement.setAttribute('data-coti-theme-mode', mode);

        var metaTheme = document.querySelector('meta[name="theme-color"]');
        if (metaTheme) {
            metaTheme.setAttribute('content', resolvedTheme === 'dark' ? '#0C0C0C' : '#FAFAF9');
        }

        syncUI(mode, resolvedTheme);
    }

    function toggle() {
        var currentMode = getMode();
        var next = getNextMode(currentMode);
        apply(next);
    }

    /* --- Native Sidebar & Drawer Controls --- */

    function initSidebar() {
        var sidebar = document.querySelector('.app-sidebar');
        if (!sidebar) return;

        // Ensure backdrop exists
        var backdrop = document.querySelector('.app-drawer-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'app-drawer-backdrop';
            document.body.appendChild(backdrop);
        }

        function toggleSidebar(e) {
            if (e) e.preventDefault();
            if (window.innerWidth >= 992) {
                document.body.classList.toggle('sidebar-collapsed');
                try {
                    localStorage.setItem('coti-sidebar-collapsed', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
                } catch (err) {}
            } else {
                sidebar.classList.toggle('is-open');
                backdrop.classList.toggle('is-visible');
            }
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('is-open');
            backdrop.classList.remove('is-visible');
        }

        // Toggle buttons
        var toggleBtns = document.querySelectorAll('#sidebarCollapseToggle, #mobileSidebarToggle, [data-toggle-sidebar], .sidebar-toggle-btn');
        toggleBtns.forEach(function (btn) {
            btn.addEventListener('click', toggleSidebar);
        });

        // Close on backdrop click
        backdrop.addEventListener('click', closeMobileSidebar);

        // Restore desktop collapsed state
        try {
            if (window.innerWidth >= 992 && localStorage.getItem('coti-sidebar-collapsed') === '1') {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (err) {}
    }

    /* --- Global Initialization --- */

    function initQuickSearch() {
        var searchInput = document.getElementById('globalQuickSearch');
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                if (searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });

        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var q = searchInput.value.trim();
                    if (q) {
                        var baseUrl = window.location.origin + (window.location.pathname.startsWith('/cotizacion') ? '/cotizacion' : '');
                        window.location.href = baseUrl + '/quotations/index.php?search=' + encodeURIComponent(q);
                    }
                }
            });
        }
    }

    function init() {
        initSidebar();
        initQuickSearch();

        // Delegate click for theme toggles
        document.addEventListener('click', function (e) {
            // Direct explicit value button (pill)
            var valTarget = e.target.closest('[data-theme-val]');
            if (valTarget) {
                e.preventDefault();
                var chosenVal = valTarget.getAttribute('data-theme-val');
                if (chosenVal) {
                    apply(chosenVal);
                    return;
                }
            }

            var toggleTarget = e.target.closest('[data-theme-toggle], #themeToggle, .theme-toggle, .mobile-theme-toggle, .mobile-nav-theme');
            if (toggleTarget) {
                e.preventDefault();
                toggle();
                return;
            }

            // Profile choice cards
            var choiceTarget = e.target.closest('.theme-choice-card');
            if (choiceTarget) {
                e.preventDefault();
                var selected = choiceTarget.getAttribute('data-choice-mode');
                if (selected) {
                    apply(selected);
                }
            }
        });

        // Apply theme mode on load
        apply(getMode());
    }

    // Media query listener for OS changes
    if (media.addEventListener) {
        media.addEventListener('change', function () {
            if (getMode() === 'system') apply('system');
        });
    } else if (media.addListener) {
        media.addListener(function () {
            if (getMode() === 'system') apply('system');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.COTITheme = {
        apply: apply,
        toggle: toggle,
        mode: getMode,
        current: getCurrentTheme,
        resolve: resolveMode
    };
})();
