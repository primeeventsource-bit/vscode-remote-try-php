const CACHE_NAME = 'crmprime-v1';
const SHELL_ASSETS = [
    '/build/app.css',
    '/favicon.svg',
    '/icons/icon-192.png',
    '/manifest.json',
];

// Install — cache app shell
self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate — clean old caches
self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Fetch — network-first for HTML, cache-first for assets
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);

    // Skip non-GET, external, and Livewire requests
    if (e.request.method !== 'GET') return;
    if (url.origin !== location.origin) return;
    if (url.pathname.startsWith('/livewire/')) return;

    // HTML pages — network first, fallback to cache
    if (e.request.headers.get('Accept')?.includes('text/html')) {
        e.respondWith(
            fetch(e.request)
                .then(res => {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(e.request, clone));
                    return res;
                })
                .catch(() => caches.match(e.request))
        );
        return;
    }

    // Static assets — cache first, fallback to network
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/images/')) {
        e.respondWith(
            caches.match(e.request).then(cached =>
                cached || fetch(e.request).then(res => {
                    const clone = res.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(e.request, clone));
                    return res;
                })
            )
        );
        return;
    }
});
