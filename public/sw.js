const MOBILE_CACHE = 'ngf-codequest-mobile-v1';
const OFFLINE_PAGE = '/offline.html';
const PUBLIC_FILES = [OFFLINE_PAGE, '/images/branding/app-icon-192.png', '/images/branding/app-icon-512.png'];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(MOBILE_CACHE);
        for (const path of PUBLIC_FILES) {
            const response = await fetch(new Request(path, {cache: 'reload', credentials: 'omit'}));
            const expectedType = path.endsWith('.png') ? 'image/png' : 'text/html';
            if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes(expectedType)) {
                throw new Error('A public app file could not be prepared.');
            }
            await cache.put(path, response);
        }
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if (name.startsWith('ngf-codequest-mobile-') && name !== MOBILE_CACHE) await caches.delete(name);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => {
            const cache = await caches.open(MOBILE_CACHE);
            return await cache.match(OFFLINE_PAGE) || Response.error();
        }));
        return;
    }
    if (PUBLIC_FILES.includes(url.pathname)) {
        event.respondWith((async () => {
            const cache = await caches.open(MOBILE_CACHE);
            return await cache.match(url.pathname) || fetch(request);
        })());
    }
});
