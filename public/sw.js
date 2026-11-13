/*
 * Service worker de SIGUE (app web instalable).
 * - Páginas y datos: siempre de la red (la información escolar debe estar al día).
 *   Sin conexión se muestra una página de aviso.
 * - Archivos estáticos compilados (/build/), íconos y fuentes: caché primero.
 */
const VERSION = 'sigue-v1';
const STATIC = `${VERSION}-static`;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll([OFFLINE_URL, '/images/pwa/icon-192.png', '/images/logo-sigue-sm.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // Navegación: red primero; sin red, página de aviso.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Estáticos versionados, íconos y fuentes: caché primero.
    const isStatic = url.origin === location.origin && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/images/'));
    const isFont = url.hostname.endsWith('gstatic.com') || url.hostname.endsWith('googleapis.com');
    if (isStatic || isFont) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok || res.type === 'opaque') {
                    const copy = res.clone();
                    caches.open(STATIC).then((c) => c.put(req, copy));
                }
                return res;
            })),
        );
    }
    // Todo lo demás (Inertia, API, archivos privados): directo a la red, sin caché.
});
