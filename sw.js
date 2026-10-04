/*
 * Service worker for the installable app (manifest.json).
 *
 * Deliberately minimal: pages are NEVER cached, so prices, stock, the cart
 * and orders are always live. The only thing it adds is a friendly offline
 * page when a page is opened with no connection. Form posts, downloads and
 * every non-page request go straight to the network untouched.
 */
const CACHE = 'tph-offline-v1';
const OFFLINE_URL = 'offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.mode !== 'navigate' || req.method !== 'GET') return;
    event.respondWith(
        fetch(req).catch(() => caches.match(OFFLINE_URL))
    );
});
