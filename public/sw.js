const CACHE_NAME = 'crmprime-v2';
const SHELL_ASSETS = [
    '/build/app.css',
    '/favicon.svg',
    '/icons/icon-192.png',
    '/manifest.json',
];

// ── Install — cache app shell ─────────────────────────────
self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// ── Activate — clean old caches ───────────────────────────
self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// ── Fetch — network-first HTML, cache-first assets ────────
self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET') return;
    if (url.origin !== location.origin) return;
    if (url.pathname.startsWith('/livewire/')) return;

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

// ── Push — unified handler for calls + messages ───────────
self.addEventListener('push', (e) => {
    if (!e.data) return;

    let payload;
    try {
        payload = e.data.json();
    } catch (err) {
        payload = { title: 'CRM Prime', body: e.data.text() };
    }

    const title = payload.title || 'CRM Prime';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/icon-192.png',
        tag: payload.tag || 'crmprime',
        renotify: true,
        data: payload.data || {},
    };

    // Vibrate for incoming calls
    if (payload.data?.type === 'incoming_call') {
        options.vibrate = [300, 100, 300, 100, 300];
        options.requireInteraction = true;
        options.actions = [
            { action: 'accept', title: 'Accept' },
            { action: 'decline', title: 'Decline' },
        ];
    }

    e.waitUntil(self.registration.showNotification(title, options));
});

// ── Notification click — deep-link routing ────────────────
self.addEventListener('notificationclick', (e) => {
    e.notification.close();

    const data = e.notification.data || {};
    let targetUrl = data.url || '/dashboard';

    // Handle call accept/decline actions
    if (e.action === 'accept' && data.meeting_uuid) {
        targetUrl = '/meeting/' + data.meeting_uuid;
    } else if (e.action === 'decline' && data.meeting_uuid) {
        // POST decline and go to calls
        e.waitUntil(
            fetch('/meetings/' + data.meeting_uuid + '/decline', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
            }).catch(() => {})
        );
        targetUrl = '/calls';
    }

    // Focus existing window or open new one
    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            // Try to focus an existing CRM Prime window
            for (const client of windowClients) {
                if (client.url.includes(self.location.origin)) {
                    client.navigate(targetUrl);
                    return client.focus();
                }
            }
            // No window open — open new
            return clients.openWindow(targetUrl);
        })
    );
});

// ── Notification close (optional tracking) ────────────────
self.addEventListener('notificationclose', (e) => {
    // Could log dismissed notifications here if needed
});
