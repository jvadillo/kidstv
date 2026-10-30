/*
 * NokoPlay · service worker de la aplicación instalable (panel y «Modo TV» en el navegador).
 *
 * Las páginas y la API van siempre a la red: tienen datos de la familia y no se guardan.
 * Solo se guardan recursos estáticos (estilos, scripts, imágenes, avatares, fuentes), la
 * página «sin conexión» y la portada de la app de TV, que se abre sin red y espera a que vuelva.
 */
'use strict';

const CACHE = 'nokoplay-v1';
const OFFLINE = '/offline.html';
const TV_SHELL = '/tv/';
const PRECACHE = [OFFLINE, '/img/pwa/icon-192.png', '/tv/img/logo-horizontal.png'];

/** Qué hacer con cada petición GET del propio sitio: null = la red, sin intervenir. */
function strategy(url, request) {
    if (request.method !== 'GET' || request.headers.has('range')) return null;
    if (url.origin !== self.location.origin) return null;
    if (request.mode === 'navigate') return 'page';
    const path = url.pathname;
    // App de TV: sin versión en la URL, así que primero la red (y la copia solo sin conexión).
    if (/^\/tv\/(?:css|js|img)\//.test(path) || path === '/tv/config.js') return 'network-first';
    // Estilos y scripts del panel llevan ?v=<fecha>: cada versión es inmutable.
    if (/^\/(?:css|js)\//.test(path)) return url.search ? 'cache-first' : 'network-first';
    if (/^\/(?:img|avatars|fonts)\//.test(path) || path === '/favicon.ico') return 'cache-first';
    return null;
}

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(Promise.all([
        caches.keys().then((keys) => Promise.all(keys
            .filter((key) => key.indexOf('nokoplay-') === 0 && key !== CACHE)
            .map((key) => caches.delete(key)))),
        self.registration.navigationPreload ? self.registration.navigationPreload.enable() : null,
    ]).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    const how = strategy(url, event.request);
    if (how === 'page') event.respondWith(page(event, url));
    else if (how === 'network-first') event.respondWith(networkFirst(event.request));
    else if (how === 'cache-first') event.respondWith(cacheFirst(event.request, url));
});

async function page(event, url) {
    const isTv = url.pathname === TV_SHELL || url.pathname === TV_SHELL + 'index.html';
    try {
        const response = (await event.preloadResponse) || await fetch(event.request);
        if (isTv && response.ok) {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put(TV_SHELL, copy));
        }
        return response;
    } catch (error) {
        const cache = await caches.open(CACHE);
        return (isTv && await cache.match(TV_SHELL)) || await cache.match(OFFLINE) || Response.error();
    }
}

async function networkFirst(request) {
    const cache = await caches.open(CACHE);
    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch (error) {
        return (await cache.match(request)) || Response.error();
    }
}

async function cacheFirst(request, url) {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) {
        await cache.put(request, response.clone());
        // De un recurso versionado solo se conserva la última versión.
        if (url.search) {
            const keys = await cache.keys();
            await Promise.all(keys.filter((key) => {
                const other = new URL(key.url);
                return other.pathname === url.pathname && other.search !== url.search;
            }).map((key) => cache.delete(key)));
        }
    }
    return response;
}
