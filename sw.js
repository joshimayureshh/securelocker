// Secure Locker Service Worker (PWA)
const CACHE_NAME = 'securelocker-v1.0';
const STATIC_ASSETS = [
    './offline.html',
    './manifest.json',
    './css/dashboard-ui.css',
    './assets/images/logo.png',
    './assets/images/icon-192.png',
    './assets/images/icon-512.png'
];

// Install Event - Pre-cache core static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

// Activate Event - Clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event - Smart caching strategy
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Do NOT cache API actions, downloads, logout, or account deletions
    if (
        url.pathname.endsWith('api.php') ||
        url.pathname.endsWith('download.php') ||
        url.pathname.endsWith('logout.php') ||
        url.pathname.endsWith('delete_account.php')
    ) {
        return;
    }

    // Navigation requests (HTML pages) - Network-first with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    return networkResponse;
                })
                .catch(() => {
                    return caches.match('./offline.html');
                })
        );
        return;
    }

    // Static assets (CSS, Images) - Stale-while-revalidate
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            const fetchPromise = fetch(request).then((networkResponse) => {
                if (networkResponse && networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(() => {
                return cachedResponse;
            });

            return cachedResponse || fetchPromise;
        })
    );
});
