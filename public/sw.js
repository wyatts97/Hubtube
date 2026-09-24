// v2 drops the v1 caches, which held user-specific JSON and pages.
const CACHE_NAME = 'hubtube-v2';
const OFFLINE_URL = '/offline';
// Hashed build files pile up across deploys; keep only the most recent ones.
const MAX_ASSET_ENTRIES = 150;

// Install: precache the offline page only. Never precache '/', which would
// store the signed-in user's page (and their props) on the device.
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)).catch(() => {})
    );
    self.skipWaiting();
});

// Activate: delete every other cache, including the old API cache
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))
        ))
    );
    self.clients.claim();
});

async function trimCache(cache) {
    const keys = await cache.keys();
    const excess = keys.length - MAX_ASSET_ENTRIES;
    for (let i = 0; i < excess; i++) {
        if (!keys[i].url.endsWith(OFFLINE_URL)) await cache.delete(keys[i]);
    }
}

// Fetch: only the offline fallback and immutable /build/ assets are handled.
// Everything else (pages, JSON, thumbnails, media) goes straight to the
// network so nothing user-specific or unbounded is stored.
self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => (await caches.match(OFFLINE_URL)) || Response.error())
        );
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) return cached;

                const response = await fetch(request);
                if (response.ok) {
                    cache.put(request, response.clone()).then(() => trimCache(cache));
                }
                return response;
            })
        );
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
