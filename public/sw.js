// Service Worker para Sistema de Cotizaciones PWA
const CACHE_NAME = 'cotizaciones-v7';
const OFFLINE_URL = '/public/offline.html';

// Archivos a cachear para funcionamiento offline básico
const STATIC_CACHE = [
    '/public/assets/css/style.css',
    '/public/assets/js/main.js',
    '/public/assets/js/theme.js',
    '/public/assets/icons/icon-192x192.png',
    '/public/assets/icons/icon-512x512.png',
    '/public/offline.html',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Instalación del Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_CACHE);
            })
            .then(() => {
                console.log('[SW] Service Worker installed');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Failed to cache:', error);
            })
    );
});

// Activación del Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...');
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Estrategia de fetch: Network First, fallback to Cache
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Solo manejar requests GET
    if (request.method !== 'GET') {
        return;
    }

    // Ignorar requests de extensiones de Chrome y similares
    if (request.url.startsWith('chrome-extension://') ||
        request.url.startsWith('moz-extension://')) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                // Si la respuesta es válida, cachearla
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            // Solo cachear recursos estáticos (excluir img.php — usa HTTP cache propio)
                            const url = request.url;
                            const isUploadProxy = url.includes('img.php');
                            if (!isUploadProxy && (
                                url.includes('/assets/') ||
                                url.includes('.css') ||
                                url.includes('.js') ||
                                url.includes('.png') ||
                                url.includes('.jpg') ||
                                url.includes('.ico')
                            )) {
                                cache.put(request, responseClone);
                            }
                        });
                }
                return response;
            })
            .catch(() => {
                // Si falla la red, buscar en cache
                return caches.match(request)
                    .then((cachedResponse) => {
                        if (cachedResponse) {
                            return cachedResponse;
                        }
                        // Si es una página HTML, mostrar página offline
                        if (request.headers.get('accept').includes('text/html')) {
                            return caches.match(OFFLINE_URL);
                        }
                    });
            })
    );
});

// Manejar notificaciones push (para futuras implementaciones)
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body || 'Nueva notificación',
            icon: '/public/assets/icons/icon-192x192.png',
            badge: '/public/assets/icons/icon-72x72.png',
            vibrate: [100, 50, 100],
            data: {
                url: data.url || '/public/quotations/index.php'
            }
        };

        event.waitUntil(
            self.registration.showNotification(data.title || 'Cotizaciones', options)
        );
    }
});

// Manejar clicks en notificaciones
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                const url = event.notification.data.url;

                // Si ya hay una ventana abierta, enfocarla
                for (const client of clientList) {
                    if (client.url.includes('/public/') && 'focus' in client) {
                        return client.focus();
                    }
                }

                // Si no hay ventana abierta, abrir una nueva
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// Sincronización en segundo plano (para futuras implementaciones)
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-quotations') {
        event.waitUntil(syncQuotations());
    }
});

async function syncQuotations() {
    console.log('[SW] Syncing quotations...');
    // Implementar sincronización de cotizaciones pendientes
}
