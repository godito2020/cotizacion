// Service Worker para COTI Inventario PWA
const CACHE_NAME = 'coti-inventario-v7';
const OFFLINE_URL = './offline.html';

// Archivos a cachear
const ASSETS_TO_CACHE = [
    './',
    './dashboard.php',
    './select_warehouse.php',
    './my_entries.php',
    './offline.html',
    './manifest.php',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Instalación del Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando Service Worker v2...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Cache abierto');
                // Cachear archivos uno por uno para manejar errores
                return Promise.allSettled(
                    ASSETS_TO_CACHE.map(url =>
                        cache.add(url).catch(err => {
                            console.log('[SW] No se pudo cachear:', url, err);
                        })
                    )
                );
            })
            .catch((error) => {
                console.log('[SW] Error al abrir cache:', error);
            })
    );
    self.skipWaiting();
});

// Activación del Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Activando Service Worker...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Eliminando cache antiguo:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Interceptar peticiones
self.addEventListener('fetch', (event) => {
    // Solo cachear peticiones GET
    if (event.request.method !== 'GET') {
        return;
    }

    // No cachear requests a API
    if (event.request.url.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Estrategia: Network first, fallback to cache
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Si la respuesta es válida, clonarla y guardarla en cache
                if (response && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME)
                        .then((cache) => {
                            cache.put(event.request, responseClone);
                        });
                }
                return response;
            })
            .catch(() => {
                // Si falla la red, buscar en cache
                return caches.match(event.request)
                    .then((response) => {
                        if (response) {
                            return response;
                        }
                        // Si es una navegación, mostrar página offline
                        if (event.request.mode === 'navigate') {
                            return caches.match(OFFLINE_URL);
                        }
                        return new Response('Offline', { status: 503 });
                    });
            })
    );
});

// Manejar mensajes
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// Notificaciones push (opcional para futuro)
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        const options = {
            body: data.body || 'Nueva notificación',
            vibrate: [100, 50, 100],
            data: {
                url: data.url || './'
            }
        };
        event.waitUntil(
            self.registration.showNotification(data.title || 'Inventario', options)
        );
    }
});

// Click en notificación
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url || './')
    );
});
