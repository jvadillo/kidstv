const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');

// The service worker runs with fake caches and network so the strategy for each request
// and the offline fallbacks can be checked without a browser.
const source = readFileSync(join(__dirname, '../../public/sw.js'), 'utf8');
const ORIGIN = 'https://nokoplay.example';

function worker({ online = true } = {}) {
    const stores = new Map();
    const listeners = {};
    const fetched = [];
    const response = (body, status = 200) => ({ body, status, ok: status >= 200 && status < 300, clone() { return response(body, status); } });
    const key = (request) => new URL(typeof request === 'string' ? request : request.url, ORIGIN).href;
    function store(name) {
        if (!stores.has(name)) stores.set(name, new Map());
        const entries = stores.get(name);
        return {
            entries,
            async match(request) { return entries.get(key(request)); },
            async put(request, value) { entries.set(key(request), value); },
            async addAll(urls) { urls.forEach((url) => entries.set(key(url), response('precached ' + url))); },
            async keys() { return [...entries.keys()].map((url) => ({ url })); },
            async delete(request) { return entries.delete(key(request)); },
        };
    }
    const context = {
        URL, Promise, Response: { error: () => response('error', 0) },
        self: {
            location: new URL(ORIGIN),
            registration: { navigationPreload: { enable: async () => {} } },
            clients: { claim: async () => {} },
            skipWaiting: async () => {},
            addEventListener(name, fn) { listeners[name] = fn; },
        },
        caches: {
            open: async (name) => store(name),
            keys: async () => [...stores.keys()],
            delete: async (name) => stores.delete(name),
        },
        fetch: async (request) => {
            fetched.push(key(request));
            if (!online) throw new TypeError('offline');
            return response('network ' + key(request));
        },
    };
    vm.runInNewContext(source, context);

    function request(path, { method = 'GET', mode = 'cors', headers = {} } = {}) {
        return { url: new URL(path, ORIGIN).href, method, mode, headers: { has: (name) => name in headers } };
    }
    async function dispatch(path, options) {
        let responded = null;
        const event = { request: request(path, options), preloadResponse: Promise.resolve(undefined), respondWith(p) { responded = p; } };
        listeners.fetch(event);
        return responded ? await responded : null;
    }
    async function lifecycle(name) {
        let wait;
        listeners[name]({ waitUntil(p) { wait = p; } });
        await wait;
    }
    return { context, stores, fetched, request, dispatch, lifecycle, set online(value) { online = value; } };
}

test('family data never goes through the cache; static assets do', () => {
    const sw = worker();
    const strategy = (path, options) => sw.context.strategy(new URL(path, ORIGIN), sw.request(path, options));

    assert.equal(strategy('/panel', { mode: 'navigate' }), 'page');
    assert.equal(strategy('/api/tv/session'), null);
    assert.equal(strategy('/api/tv/plays', { method: 'POST' }), null);
    assert.equal(strategy('/panel/contenidos/7/archivo'), null);
    assert.equal(strategy('/panel/contenidos/7/portada'), null);
    assert.equal(strategy('/media/abc', { headers: { range: 'bytes=0-' } }), null);
    assert.equal(strategy('https://i.ytimg.com/vi/x/hqdefault.jpg'), null);
    assert.equal(strategy('/css/app.css?v=123'), 'cache-first');
    assert.equal(strategy('/js/panel.js?v=123'), 'cache-first');
    assert.equal(strategy('/css/app.css'), 'network-first');
    assert.equal(strategy('/tv/js/app.js'), 'network-first');
    assert.equal(strategy('/tv/config.js'), 'network-first');
    assert.equal(strategy('/avatars/sol.svg'), 'cache-first');
    assert.equal(strategy('/img/pwa/icon-192.png'), 'cache-first');
});

test('offline pages fall back to the offline page, and the TV app to its last shell', async () => {
    const sw = worker();
    await sw.lifecycle('install');
    await sw.lifecycle('activate');

    // Online: pages come from the network; only the TV shell is kept.
    assert.equal((await sw.dispatch('/panel', { mode: 'navigate' })).body, 'network ' + ORIGIN + '/panel');
    assert.equal((await sw.dispatch('/tv/', { mode: 'navigate' })).body, 'network ' + ORIGIN + '/tv/');
    await new Promise((resolve) => setImmediate(resolve));
    const cache = sw.stores.get('nokoplay-v1');
    assert.ok(cache.has(ORIGIN + '/tv/'));
    assert.ok(!cache.has(ORIGIN + '/panel'));

    sw.online = false;
    assert.equal((await sw.dispatch('/panel/actividad', { mode: 'navigate' })).body, 'precached /offline.html');
    assert.equal((await sw.dispatch('/tv/', { mode: 'navigate' })).body, 'network ' + ORIGIN + '/tv/');
});

test('versioned assets keep only their latest version', async () => {
    const sw = worker();
    await sw.dispatch('/css/app.css?v=1');
    await sw.dispatch('/css/app.css?v=1');
    assert.equal(sw.fetched.filter((url) => url.endsWith('?v=1')).length, 1);
    await sw.dispatch('/css/app.css?v=2');
    const cached = [...sw.stores.get('nokoplay-v1').keys()];
    assert.deepEqual(cached, [ORIGIN + '/css/app.css?v=2']);
});

test('activation removes caches from older versions', async () => {
    const sw = worker();
    await sw.context.caches.open('nokoplay-v0');
    await sw.context.caches.open('otra-app');
    await sw.lifecycle('activate');
    assert.deepEqual([...sw.stores.keys()].sort(), ['otra-app']);
});
