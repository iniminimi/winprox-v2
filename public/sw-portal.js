const CACHE = 'wp-portal-shell-v1';
const OFFLINE_URL = '/portal-offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await cache.addAll([OFFLINE_URL]);
        self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)));
        self.clients.claim();
    })());
});

function shouldCache(url) {
    if (url.origin !== self.location.origin) {
        return false;
    }

    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/images/')
        || url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/fonts/')
        || url.pathname === '/favicon.ico';
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.pathname.startsWith('/livewire') || url.pathname.startsWith('/portal/field-sync')) {
        return;
    }

    if (request.mode === 'navigate') {
        const isPortal = url.pathname.startsWith('/melden') || url.pathname.startsWith('/time');
        if (!isPortal) {
            return;
        }

        event.respondWith((async () => {
            try {
                return await fetch(request);
            } catch {
                const cached = await caches.match(OFFLINE_URL);
                return cached || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
            }
        })());
        return;
    }

    if (!shouldCache(url)) {
        return;
    }

    event.respondWith((async () => {
        const cache = await caches.open(CACHE);
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }

        try {
            const response = await fetch(request);
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        } catch {
            return cached || Response.error();
        }
    })());
});
