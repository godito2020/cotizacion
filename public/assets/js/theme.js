/**
 * Global Theme Toggle Functionality
 * Handles light/dark theme switching across the entire application
 */

// AGGRESSIVE THEME ENFORCEMENT - Apply theme immediately to prevent flash
(function() {
    // FORCE light theme by default - override everything
    document.documentElement.removeAttribute('data-theme');

    // Clear any existing dark theme from localStorage unless explicitly set
    const savedTheme = localStorage.getItem('theme');

    // Only keep dark theme if user explicitly wants it
    if (savedTheme !== 'dark') {
        localStorage.setItem('theme', 'light');
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // Force body styles immediately
    const style = document.createElement('style');
    style.textContent = `
        body:not([data-theme="dark"]) {
            background-color: #ffffff !important;
            color: #212529 !important;
        }
        html[data-theme="dark"] body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
    `;
    document.head.appendChild(style);
})();

class ThemeManager {
    constructor() {
        this.init();
    }

    init() {
        // Apply saved theme on page load
        this.applySavedTheme();

        // Setup theme toggle button if it exists
        this.setupThemeToggle();

        // Listen for theme changes from other tabs/windows
        window.addEventListener('storage', (e) => {
            if (e.key === 'theme') {
                this.applyTheme(e.newValue);
                this.updateThemeIcon();
            }
        });
    }

    applySavedTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        // Force light theme by default
        if (savedTheme !== 'dark') {
            localStorage.setItem('theme', 'light');
        }
        this.applyTheme(savedTheme);
        // Ensure body has correct styles
        this.forceBodyStyles();
    }

    forceBodyStyles() {
        const currentTheme = this.getCurrentTheme();
        const body = document.body;

        if (currentTheme === 'light') {
            body.style.backgroundColor = '#ffffff';
            body.style.color = '#212529';
        }
    }

    applyTheme(theme) {
        const html = document.documentElement;

        if (theme === 'dark') {
            html.setAttribute('data-theme', 'dark');
        } else {
            html.removeAttribute('data-theme');
        }

        // Store theme preference
        localStorage.setItem('theme', theme);

        // Update icon
        this.updateThemeIcon();
    }

    updateThemeIcon() {
        const themeIcon = document.getElementById('themeIcon');
        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = this.getCurrentTheme();

        if (themeIcon) {
            if (currentTheme === 'dark') {
                themeIcon.className = 'fas fa-sun';
                if (themeToggle) themeToggle.title = 'Cambiar a tema claro';
            } else {
                themeIcon.className = 'fas fa-moon';
                if (themeToggle) themeToggle.title = 'Cambiar a tema oscuro';
            }
        }
    }

    toggleTheme() {
        const currentTheme = this.getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.applyTheme(newTheme);
    }

    setupThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                this.toggleTheme();
            });
        }
    }

    getCurrentTheme() {
        return document.documentElement.hasAttribute('data-theme') ? 'dark' : 'light';
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.themeManager = new ThemeManager();
});

// Global function for easy access
window.toggleTheme = function() {
    if (window.themeManager) {
        window.themeManager.toggleTheme();
    }
};