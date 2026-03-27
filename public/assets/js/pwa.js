/**
 * PWA Installation and Service Worker Registration
 */
(function() {
    'use strict';

    // Variables para la instalación
    let deferredPrompt = null;
    let installButton = null;

    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/public/sw.js')
                .then(function(registration) {
                    console.log('[PWA] Service Worker registered:', registration.scope);

                    // Verificar actualizaciones
                    registration.addEventListener('updatefound', function() {
                        const newWorker = registration.installing;
                        console.log('[PWA] New Service Worker installing...');

                        newWorker.addEventListener('statechange', function() {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Hay una nueva versión disponible
                                showUpdateNotification();
                            }
                        });
                    });
                })
                .catch(function(error) {
                    console.error('[PWA] Service Worker registration failed:', error);
                });
        });
    }

    // Capturar evento de instalación
    window.addEventListener('beforeinstallprompt', function(e) {
        console.log('[PWA] beforeinstallprompt fired');
        e.preventDefault();
        deferredPrompt = e;

        // Mostrar botón de instalación si existe
        showInstallButton();
    });

    // Detectar instalación completada
    window.addEventListener('appinstalled', function(e) {
        console.log('[PWA] App installed successfully');
        deferredPrompt = null;
        hideInstallButton();

        // Mostrar mensaje de éxito
        showInstallSuccess();
    });

    // Mostrar botón de instalación
    function showInstallButton() {
        installButton = document.getElementById('pwa-install-btn');
        if (installButton) {
            installButton.style.display = 'inline-flex';
            installButton.addEventListener('click', installPWA);
        }

        // También agregar al menú si existe
        const menuInstallBtn = document.getElementById('menu-install-pwa');
        if (menuInstallBtn) {
            menuInstallBtn.style.display = 'block';
            menuInstallBtn.addEventListener('click', installPWA);
        }
    }

    // Ocultar botón de instalación
    function hideInstallButton() {
        if (installButton) {
            installButton.style.display = 'none';
        }
        const menuInstallBtn = document.getElementById('menu-install-pwa');
        if (menuInstallBtn) {
            menuInstallBtn.style.display = 'none';
        }
    }

    // Función para instalar PWA
    window.installPWA = function() {
        if (!deferredPrompt) {
            console.log('[PWA] No installation prompt available');
            showManualInstallInstructions();
            return;
        }

        // Mostrar el prompt de instalación
        deferredPrompt.prompt();

        deferredPrompt.userChoice.then(function(choiceResult) {
            if (choiceResult.outcome === 'accepted') {
                console.log('[PWA] User accepted installation');
            } else {
                console.log('[PWA] User dismissed installation');
            }
            deferredPrompt = null;
        });
    };

    // Mostrar notificación de actualización disponible
    function showUpdateNotification() {
        const updateHtml = `
            <div id="pwa-update-banner" class="alert alert-info alert-dismissible fade show position-fixed"
                 style="bottom: 20px; left: 20px; right: 20px; max-width: 400px; z-index: 9999; margin: 0 auto;">
                <strong>Nueva versión disponible</strong>
                <p class="mb-2 small">Hay una actualización disponible para la aplicación.</p>
                <button class="btn btn-primary btn-sm me-2" onclick="window.location.reload()">
                    Actualizar ahora
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', updateHtml);
    }

    // Mostrar mensaje de instalación exitosa
    function showInstallSuccess() {
        if (typeof showNotification === 'function') {
            showNotification('Aplicación instalada correctamente', 'success');
        } else {
            alert('¡Aplicación instalada correctamente!');
        }
    }

    // Mostrar instrucciones manuales de instalación
    function showManualInstallInstructions() {
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        const isAndroid = /Android/.test(navigator.userAgent);

        let instructions = '';

        if (isIOS) {
            instructions = `
                <h5>Instalar en iOS</h5>
                <ol>
                    <li>Toca el botón <strong>Compartir</strong> <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 5l-1.42 1.42-1.59-1.59V16h-2V4.83L9.42 6.42 8 5l4-4 4 4zm4 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10a2 2 0 0 1 2-2h3v2H6v11h12V10h-3V8h3a2 2 0 0 1 2 2z"/></svg></li>
                    <li>Desplázate y toca <strong>"Añadir a pantalla de inicio"</strong></li>
                    <li>Toca <strong>"Añadir"</strong></li>
                </ol>
            `;
        } else if (isAndroid) {
            instructions = `
                <h5>Instalar en Android</h5>
                <ol>
                    <li>Toca el menú <strong>⋮</strong> de tu navegador</li>
                    <li>Selecciona <strong>"Instalar aplicación"</strong> o <strong>"Añadir a pantalla de inicio"</strong></li>
                    <li>Confirma la instalación</li>
                </ol>
            `;
        } else {
            instructions = `
                <h5>Instalar en PC</h5>
                <ol>
                    <li>En Chrome/Edge, busca el icono <strong>⊕</strong> en la barra de direcciones</li>
                    <li>Haz clic en <strong>"Instalar"</strong></li>
                    <li>La aplicación se abrirá en su propia ventana</li>
                </ol>
            `;
        }

        // Mostrar modal con instrucciones
        const modalHtml = `
            <div class="modal fade" id="installInstructionsModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Instalar Aplicación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${instructions}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remover modal existente si hay
        const existingModal = document.getElementById('installInstructionsModal');
        if (existingModal) {
            existingModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modal = new bootstrap.Modal(document.getElementById('installInstructionsModal'));
        modal.show();
    }

    // Verificar si ya está instalada como PWA
    function isInstalledPWA() {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone === true;
    }

    // Si ya está instalada, ocultar opciones de instalación
    if (isInstalledPWA()) {
        console.log('[PWA] Running as installed PWA');
        document.addEventListener('DOMContentLoaded', function() {
            hideInstallButton();
        });
    }

    // Exponer función para verificar estado
    window.isPWAInstalled = isInstalledPWA;

})();
