/*
 * NokoPlay TV · utilidades comunes.
 * Escrito en ES5 sin dependencias para funcionar en navegadores de Smart TV antiguos
 * (LG webOS 3+ / Chromium 38, Samsung Tizen, Android TV WebView).
 */
(function (window, document) {
    'use strict';

    var config = window.NOKOPLAY_CONFIG || {};
    config.platform = config.platform || 'web';

    var K = window.NokoPlay = { config: config };

    /* ---------- Texto e i18n ---------- */

    var lang = (config.lang || 'es');
    var dict = (window.NOKOPLAY_I18N && window.NOKOPLAY_I18N[lang]) || {};

    K.t = function (key, params) {
        var text = dict[key] || key;
        if (params) {
            for (var p in params) {
                if (params.hasOwnProperty(p)) text = text.split('{' + p + '}').join(String(params[p]));
            }
        }
        return text;
    };

    K.esc = function (value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };

    K.formatTime = function (seconds) {
        seconds = Math.max(0, Math.floor(seconds || 0));
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        var mm = h > 0 && m < 10 ? '0' + m : String(m);
        return (h > 0 ? h + ':' : '') + mm + ':' + (s < 10 ? '0' : '') + s;
    };

    K.closest = function (el, className) {
        while (el && el !== document) {
            if (el.classList && el.classList.contains(className)) return el;
            el = el.parentNode;
        }
        return null;
    };

    K.avatar = function (name) {
        var safe = /^[a-z]+$/.test(name || '') ? name : 'estrella';
        return (config.avatarBase || '/avatars/') + safe + '.svg';
    };

    /* ---------- Almacenamiento local ---------- */

    K.store = {
        get: function (key) {
            try { return window.localStorage.getItem('nokoplay.' + key); } catch (e) { return null; }
        },
        set: function (key, value) {
            try {
                if (value === null || value === undefined) window.localStorage.removeItem('nokoplay.' + key);
                else window.localStorage.setItem('nokoplay.' + key, value);
            } catch (e) { /* almacenamiento no disponible */ }
        }
    };

    /* ---------- HTTP (XMLHttpRequest: fetch no existe en webOS antiguos) ---------- */

    K.http = function (method, path, options, callback) {
        options = options || {};
        var xhr = new XMLHttpRequest();
        var url = /^https?:/.test(path) ? path : (config.apiBase || '') + path;
        var done = false;

        xhr.open(method, url, true);
        xhr.timeout = options.timeout || 15000;
        xhr.setRequestHeader('Accept', 'application/json');
        if (options.headers) {
            for (var h in options.headers) {
                if (options.headers.hasOwnProperty(h) && options.headers[h]) xhr.setRequestHeader(h, options.headers[h]);
            }
        }

        function finish(status, data) {
            if (done) return;
            done = true;
            callback(status, data || {});
        }

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
            finish(xhr.status, data);
        };
        xhr.ontimeout = function () { finish(0, { error: 'timeout' }); };
        xhr.onerror = function () { finish(0, { error: 'network' }); };

        if (options.body !== undefined) {
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(options.body));
        } else {
            xhr.send();
        }
        return xhr;
    };

    /* ---------- Teclas del mando ---------- */

    var KEYS = {
        37: 'left', 38: 'up', 39: 'right', 40: 'down',
        13: 'ok', 32: 'ok',
        461: 'back', 10009: 'back', 27: 'back', 8: 'back', 166: 'back',
        415: 'play', 19: 'pause', 179: 'playpause', 10252: 'playpause', 463: 'playpause',
        413: 'stop', 417: 'ff', 412: 'rw', 228: 'ff', 227: 'rw'
    };

    K.keyName = function (e) {
        var code = e.keyCode || e.which;
        if (code >= 48 && code <= 57) return 'digit';
        if (code >= 96 && code <= 105) return 'digit';
        if (KEYS[code]) return KEYS[code];
        if (e.key === 'MediaPlayPause') return 'playpause';
        if (e.key === 'GoBack' || e.key === 'BrowserBack') return 'back';
        return null;
    };

    K.keyDigit = function (e) {
        var code = e.keyCode || e.which;
        if (code >= 48 && code <= 57) return String(code - 48);
        if (code >= 96 && code <= 105) return String(code - 96);
        return null;
    };

    /* ---------- Navegación espacial ---------- */

    var Nav = K.nav = {
        root: null,
        current: null
    };

    Nav.setRoot = function (root) {
        Nav.root = root;
        Nav.current = null;
    };

    Nav.focusables = function () {
        if (!Nav.root) return [];
        var list = Nav.root.querySelectorAll('.focusable');
        var out = [];
        for (var i = 0; i < list.length; i++) {
            if (list[i].offsetWidth > 0 && !list[i].classList.contains('disabled')) out.push(list[i]);
        }
        return out;
    };

    Nav.focus = function (el) {
        if (!el) return;
        if (Nav.current && Nav.current !== el) Nav.current.classList.remove('focused');
        Nav.current = el;
        el.classList.add('focused');
        Nav.ensureVisible(el);
        if (typeof Nav.onFocus === 'function') Nav.onFocus(el);
    };

    /** Enfoca el elemento con data-key igual a key; si no existe, el primero. */
    Nav.restore = function (key) {
        var list = Nav.focusables();
        if (key) {
            for (var i = 0; i < list.length; i++) {
                if (list[i].getAttribute('data-key') === key) { Nav.focus(list[i]); return true; }
            }
        }
        var preferred = Nav.root && Nav.root.querySelector('.focusable.autofocus');
        Nav.focus(preferred && preferred.offsetWidth > 0 ? preferred : list[0]);
        return false;
    };

    Nav.currentKey = function () {
        return Nav.current ? Nav.current.getAttribute('data-key') : null;
    };

    Nav.move = function (dir) {
        var list = Nav.focusables();
        var cur = Nav.current;
        if (!cur || !cur.parentNode || list.indexOf(cur) === -1) {
            Nav.focus(list[0]);
            return true;
        }

        var cr = cur.getBoundingClientRect();
        var cx = cr.left + cr.width / 2;
        var cy = cr.top + cr.height / 2;
        var best = null;
        var bestScore = Infinity;

        for (var i = 0; i < list.length; i++) {
            var el = list[i];
            if (el === cur) continue;
            var r = el.getBoundingClientRect();
            var ex = r.left + r.width / 2;
            var ey = r.top + r.height / 2;
            var primary, secondary;

            // Izquierda/derecha solo dentro de la misma fila: al final de una fila no se salta a otra zona.
            var sameRow = r.top < cr.bottom - 2 && r.bottom > cr.top + 2;
            if (dir === 'left') { if (!sameRow || (r.right > cr.left + 2 && ex >= cx - 2)) continue; primary = cx - ex; secondary = Math.abs(ey - cy); }
            else if (dir === 'right') { if (!sameRow || (r.left < cr.right - 2 && ex <= cx + 2)) continue; primary = ex - cx; secondary = Math.abs(ey - cy); }
            else if (dir === 'up') { if (r.bottom > cr.top + 2 && ey >= cy - 2) continue; primary = cy - ey; secondary = Math.abs(ex - cx); }
            else { if (r.top < cr.bottom - 2 && ey <= cy + 2) continue; primary = ey - cy; secondary = Math.abs(ex - cx); }

            if (primary <= 0) continue;
            var score = primary + secondary * 2.2;
            if (score < bestScore) { bestScore = score; best = el; }
        }

        if (best) { Nav.focus(best); return true; }
        return false;
    };

    /** Desplaza el contenedor con scroll para que el elemento enfocado quede visible. */
    Nav.ensureVisible = function (el) {
        var y = K.closest(el.parentNode, 'scroll-y');
        if (y) {
            var yr = y.getBoundingClientRect();
            var er = el.getBoundingClientRect();
            var margin = yr.height * 0.12;
            if (er.top < yr.top + margin) y.scrollTop -= (yr.top + margin - er.top);
            else if (er.bottom > yr.bottom - margin) y.scrollTop += (er.bottom - (yr.bottom - margin));
        }
        var x = K.closest(el.parentNode, 'scroll-x');
        if (x) {
            var xr = x.getBoundingClientRect();
            var er2 = el.getBoundingClientRect();
            var m = xr.width * 0.08;
            if (er2.left < xr.left + m) x.scrollLeft -= (xr.left + m - er2.left);
            else if (er2.right > xr.right - m) x.scrollLeft += (er2.right - (xr.right - m));
        }
    };

    /* ---------- Escalado: se diseña a 1920x1080 en rem ---------- */

    function rescale() {
        var w = window.innerWidth || 1920;
        var h = window.innerHeight || 1080;
        var scale = Math.min(w / 1920, h / 1080);
        document.documentElement.style.fontSize = Math.max(6, 16 * scale) + 'px';
    }
    rescale();
    window.addEventListener('resize', rescale);

    /* ---------- Salir de la app (según plataforma) ---------- */

    K.canExit = function () {
        return config.platform !== 'web';
    };

    K.exitApp = function () {
        try {
            if (window.webOSSystem && window.webOSSystem.platformBack) return window.webOSSystem.platformBack();
            if (window.PalmSystem && window.PalmSystem.platformBack) return window.PalmSystem.platformBack();
            if (window.tizen && window.tizen.application) return window.tizen.application.getCurrentApplication().exit();
        } catch (e) { /* continúa */ }
        window.close();
    };
})(window, document);
