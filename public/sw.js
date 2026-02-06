const CACHE_NAME = 'hubtube-v1';
const OFFLINE_URL = '/offline';

const PRECACHE_URLS = [
    '/',
    '/offline',
];

// Install: precache essential assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_URLS).catch(() => {
                // Silently fail if precaching fails (e.g., offline install)
            });
        })
    );
    self.skipWaiting();
});

// Activate: clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        })
    );
    self.clients.claim();
});

// Fetch: network-first with offline fallback
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests and chrome-extension requests
    if (event.request.method !== 'GET') return;
    if (event.request.url.startsWith('chrome-extension://')) return;

    // For navigation requests, try network first then offline page
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match(OFFLINE_URL) || caches.match('/');
            })
        );
        return;
    }

    // For static assets, try cache first then network
    if (event.request.url.match(/\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|webp|ico)$/)) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached;
                return fetch(event.request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                }).catch(() => new Response('', { status: 408 }));
            })
        );
        return;
    }
});

// Push notification handler
self.addEventListener('push', (event) => {
    let data = { title: 'HubTube', body: 'You have a new notification', icon: '/icons/icon-192x192.png' };

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() };
        }
    } catch (e) {
        if (event.data) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon || '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
        image: data.image || undefined,
        data: {
            url: data.url || '/',
        },
        actions: data.actions || [],
        vibrate: [100, 50, 100],
        tag: data.tag || 'hubtube-notification',
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Focus existing window if available
            for (const client of clientList) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            // Open new window
            return clients.openWindow(url);
        })
    );
});
