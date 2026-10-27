/*
 * KidsTV TV · aplicación principal.
 * Pantallas: vinculación → perfiles → catálogo → reproductor, y zona de adultos con PIN.
 * Toda la navegación funciona con flechas, OK y Atrás; el ratón/puntero es opcional.
 */
(function (window, document, K) {
    'use strict';

    var t = K.t;
    var esc = K.esc;
    var Nav = K.nav;
    var app = document.getElementById('app');
    var overlay = document.getElementById('overlay');

    var ICON_LOCK = '<svg viewBox="0 0 24 24" class="ico"><rect x="5" y="11" width="14" height="9" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
    var ICON_NOTE = '<svg viewBox="0 0 24 24" class="ico"><path d="M9 18V5l11-2v13" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="6" cy="18" r="3" fill="currentColor"/><circle cx="17" cy="16" r="3" fill="currentColor"/></svg>';
    var ICON_PLAY = '<svg viewBox="0 0 24 24" class="ico"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>';
    var ICON_HOURGLASS = '<svg viewBox="0 0 24 24" class="ico"><path d="M6 3h12M6 21h12M8 3v3c0 2.5 4 4 4 6s-4 3.5-4 6v3M16 3v3c0 2.5-4 4-4 6s4 3.5 4 6v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.5 7h5L12 10zM9 20c0-1.8 3-2.6 3-3.6 0 1 3 1.8 3 3.6z" fill="currentColor"/></svg>';
    var ICON_STAR = '<svg viewBox="0 0 24 24"><path d="M12 2l2.9 6.6 7.1.6-5.4 4.7 1.6 7L12 17.3 5.8 20.9l1.6-7L2 9.2l7.1-.6z" fill="currentColor"/></svg>';
    // Pantalla de descanso: una tele dormida con estrellas y «zetas» (animado con CSS).
    var TIMEUP_ART =
        '<div class="timeup-art" aria-hidden="true">' +
            '<span class="timeup-star timeup-star-1">' + ICON_STAR + '</span>' +
            '<span class="timeup-star timeup-star-2">' + ICON_STAR + '</span>' +
            '<span class="timeup-star timeup-star-3">' + ICON_STAR + '</span>' +
            '<span class="timeup-star timeup-star-4">' + ICON_STAR + '</span>' +
            '<div class="timeup-tv"><svg viewBox="0 0 240 220">' +
                '<path d="M104 44L82 10M136 44l26-36" stroke="#ffc83d" stroke-width="7" stroke-linecap="round"/>' +
                '<circle cx="80" cy="8" r="8" fill="#ff8fb1"/><circle cx="164" cy="6" r="8" fill="#8fd3ff"/>' +
                '<rect x="62" y="186" width="18" height="24" rx="7" fill="#e0a92a"/><rect x="160" y="186" width="18" height="24" rx="7" fill="#e0a92a"/>' +
                '<rect x="20" y="40" width="200" height="152" rx="38" fill="#ffc83d"/>' +
                '<rect x="42" y="60" width="156" height="110" rx="26" fill="#2b2560"/>' +
                '<path d="M76 110q15 13 30 0M134 110q15 13 30 0" fill="none" stroke="#ffd978" stroke-width="7" stroke-linecap="round"/>' +
                '<circle cx="78" cy="132" r="9" fill="#ff8fb1" opacity="0.55"/><circle cx="162" cy="132" r="9" fill="#ff8fb1" opacity="0.55"/>' +
                '<path d="M110 138q10 8 20 0" fill="none" stroke="#ffd978" stroke-width="6" stroke-linecap="round"/>' +
            '</svg></div>' +
            '<span class="timeup-z timeup-z-1">Z</span><span class="timeup-z timeup-z-2">z</span><span class="timeup-z timeup-z-3">z</span>' +
        '</div>';

    var S = {
        token: null,
        session: null,
        profile: null,
        catalog: null,
        rows: [],
        rowMemory: {},
        rowOffsets: {},
        adult: null,
        adultCatalog: false,
        screen: null,
        memory: {},
        player: null,
        playing: null,
        watch: null,
        limit: null,
        limitWarned: false,
        timers: {},
        lastKeyAt: new Date().getTime(),
        lastAdultPing: 0
    };

    var screens = {};

    /* ---------- Utilidades ---------- */

    function clearTimer(name) {
        if (S.timers[name]) { clearTimeout(S.timers[name]); clearInterval(S.timers[name]); S.timers[name] = null; }
    }

    function headers() {
        return {
            'Authorization': S.token ? 'Bearer ' + S.token : null,
            'X-Adult-Token': S.adult ? S.adult.token : null
        };
    }

    /** Llamada a la API del televisor con gestión común de desvinculación y red. */
    function api(method, path, body, callback, options) {
        options = options || {};
        K.http(method, '/api/tv' + path, { body: body, headers: headers() }, function (status, data) {
            if (status === 401) { handleRevoked(); return; }
            if (status === 403 && data && data.error === 'account_blocked') { handleBlocked(); return; }
            if (status === 0 || status >= 500) {
                if (options.silent) { if (callback) callback(status, data); return; }
                showOffline(status === 0 ? t('error.offline') : t('error.server'), function () { api(method, path, body, callback, options); });
                return;
            }
            hideOffline();
            if (callback) callback(status, data);
        });
    }

    function familyName() {
        return (S.session && S.session.family && S.session.family.name) || t('family.default');
    }

    function serverHost() {
        var url = (S.session && S.session.manage_url) || K.config.apiBase || window.location.origin;
        return String(url).replace(/^https?:\/\//, '').replace(/\/.*$/, '');
    }

    function toast(message) {
        var el = document.createElement('div');
        el.className = 'toast';
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.classList.add('visible'); }, 20);
        setTimeout(function () {
            el.classList.remove('visible');
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 400);
        }, 2600);
    }

    function show(name, arg) {
        var prev = screens[S.screen];
        if (prev && prev.leave) prev.leave();
        if (S.screen) S.memory[S.screen] = Nav.currentKey();
        S.screen = name;
        Nav.onFocus = null;
        screens[name].enter(arg || {});
    }

    function mount(html) {
        app.innerHTML = html;
        Nav.setRoot(app);
    }

    function loading(text) {
        mount('<div class="screen screen-center"><div class="spinner"></div><p class="muted">' + esc(text || t('player.loading')) + '</p></div>');
    }

    /* ---------- Diálogos (con su propio foco) ---------- */

    var dialog = null;

    function openDialog(opts) {
        if (dialog) closeDialog(true);
        dialog = { opts: opts, prevRoot: Nav.root, prevEl: Nav.current };
        var buttons = opts.buttons || [{ label: t('common.ok') }];
        var html = '<div class="dialog' + (opts.kind ? ' dialog-' + opts.kind : '') + '">' +
            (opts.title ? '<h2>' + esc(opts.title) + '</h2>' : '') +
            (opts.text ? '<p>' + esc(opts.text) + '</p>' : '') +
            (opts.html || '') +
            '<div class="dialog-buttons">';
        for (var i = 0; i < buttons.length; i++) {
            html += '<div class="btn focusable' + (i === (opts.focus || 0) ? ' autofocus' : '') + '" data-action="dialog" data-arg="' + i + '">' + esc(buttons[i].label) + '</div>';
        }
        html += '</div></div>';
        overlay.innerHTML = html;
        overlay.className = 'overlay';
        Nav.setRoot(overlay);
        Nav.restore();
    }

    function closeDialog(silent) {
        if (!dialog) return;
        var d = dialog;
        dialog = null;
        if (d.opts.isOffline) offline.visible = false;
        overlay.className = 'overlay hidden';
        overlay.innerHTML = '';
        Nav.setRoot(d.prevRoot);
        if (d.prevEl && d.prevEl.parentNode && document.body.contains(d.prevEl)) Nav.focus(d.prevEl);
        else Nav.restore(S.memory[S.screen]);
        if (!silent && d.opts.onClose) d.opts.onClose();
    }

    function dialogAction(index) {
        var d = dialog;
        var button = (d.opts.buttons || [{}])[index] || {};
        closeDialog(true);
        if (button.action) button.action();
        else if (d.opts.onClose) d.opts.onClose();
    }

    /* ---------- Sin conexión / desvinculada ---------- */

    var offline = { visible: false, retry: null };

    function showOffline(title, retry) {
        offline.retry = retry;
        clearTimer('offline');
        S.timers.offline = setTimeout(function () { if (offline.retry) offline.retry(); }, 5000);
        if (offline.visible) return;
        offline.visible = true;
        openDialog({
            kind: 'error',
            isOffline: true,
            title: title,
            text: t('error.offlineHint'),
            buttons: [{ label: t('common.retry'), action: function () { offline.visible = false; if (offline.retry) offline.retry(); } }],
            onClose: function () { offline.visible = false; }
        });
    }

    function hideOffline() {
        clearTimer('offline');
        offline.retry = null;
        if (offline.visible) { offline.visible = false; closeDialog(true); }
    }

    function stopPlayer() {
        watchStop();
        if (S.player) { S.player.destroy(); S.player = null; }
        S.playing = null;
    }

    /* ---------- Tiempo de visionado (estadísticas de uso) ---------- */

    // Solo cuenta mientras el vídeo avanza de verdad; se envía cada 30 s y al terminar,
    // con el perfil que lo ve (límite diario de cada niño y «Actividad» en el panel).
    function watchStart(item) {
        watchStop();
        S.watch = {
            key: Math.random().toString(36).slice(2, 12) + new Date().getTime().toString(36),
            content: item.id,
            profile: S.adultCatalog || !S.profile ? null : S.profile.id,
            seconds: 0,
            since: 0,
            sent: 0
        };
        S.timers.watch = setInterval(watchSend, 30000);
    }

    function watchTick() {
        var w = S.watch;
        if (!w || !w.since) return;
        var now = new Date().getTime();
        var delta = (now - w.since) / 1000;
        w.seconds += delta;
        w.since = now;
        if (S.limit && !S.adultCatalog) S.limit.remaining -= delta;
    }

    function watchPlaying(playing) {
        var w = S.watch;
        if (!w) return;
        watchTick();
        w.since = playing ? (w.since || new Date().getTime()) : 0;
    }

    function watchSend() {
        var w = S.watch;
        if (!w) return;
        watchTick();
        var seconds = Math.floor(w.seconds);
        if (seconds < 5 || seconds - w.sent < 5) return;
        w.sent = seconds;
        api('POST', '/plays', { key: w.key, content_id: w.content, profile_id: w.profile, seconds: seconds }, function (status, data) {
            // Lo visto desde que salió el aviso aún no lo sabe el servidor.
            if (status === 200 && data && !S.adultCatalog) limitSync(data.screen_time, S.watch === w ? w.seconds - seconds : 0);
        }, { silent: true });
    }

    function watchStop() {
        if (!S.watch) return;
        watchPlaying(false);
        watchSend();
        clearTimer('watch');
        S.watch = null;
    }

    /* ---------- Límite diario de tele ---------- */

    // El servidor dice cuánto le queda hoy al niño (sumando todas las teles, sin la Zona
    // de adultos); entre aviso y aviso, la tele lo descuenta mientras el vídeo avanza.
    function limitSync(status, unsent) {
        if (status === undefined) return;
        S.limit = status ? { remaining: status.remaining_seconds - (unsent || 0), warn: status.warn_seconds } : null;
        if (!S.limit || S.limit.remaining > S.limit.warn) S.limitWarned = false;
    }

    /** Estado del límite de un perfil según la última sesión cargada. */
    function profileLimit(id) {
        var profiles = (S.session && S.session.profiles) || [];
        for (var i = 0; i < profiles.length; i++) {
            if (profiles[i].id === id) return profiles[i].screen_time;
        }
        return undefined;
    }

    function timeIsUp() {
        return !S.adultCatalog && !!S.limit && S.limit.remaining <= 0;
    }

    function timeUp() {
        if (dialog && !offline.visible) closeDialog(true);
        stopPlayer();
        show('timeup');
    }

    function limitWarning() {
        var minutes = Math.max(1, Math.round(S.limit.remaining / 60));
        var el = document.createElement('div');
        el.className = 'limit-warning';
        el.innerHTML = ICON_HOURGLASS + '<div><strong>' + esc(t(minutes === 1 ? 'limit.warnOne' : 'limit.warn', { n: minutes })) + '</strong>' +
            '<span>' + esc(t('limit.warnHint')) + '</span></div>';
        document.body.appendChild(el);
        setTimeout(function () { el.classList.add('visible'); }, 20);
        setTimeout(function () {
            el.classList.remove('visible');
            setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 500);
        }, 10000);
    }

    setInterval(function () {
        if (S.screen !== 'player' || !S.limit || S.adultCatalog) return;
        watchTick();
        if (S.limit.remaining <= 0) { timeUp(); return; }
        if (S.limit.remaining <= S.limit.warn && !S.limitWarned) {
            S.limitWarned = true;
            limitWarning();
        }
    }, 1000);

    screens.timeup = {
        enter: function () {
            mount(
                '<div class="screen screen-center screen-timeup">' + TIMEUP_ART +
                    '<h1>' + esc(t('limit.title')) + '</h1>' +
                    '<p class="muted">' + esc(t('limit.hint')) + '</p>' +
                    '<div class="btn focusable autofocus" data-action="profiles" data-key="profiles">' + esc(t('limit.change')) + '</div>' +
                    '<div class="adult-corner"><div class="btn btn-quiet focusable" data-action="adult" data-key="adult">' + ICON_LOCK + '<span>' + esc(t('adult.button')) + '</span></div></div>' +
                '</div>'
            );
            Nav.restore();
            clearTimer('poll');
            // Al día siguiente, o si un adulto amplía o quita el límite, la tele vuelve sola a los perfiles.
            S.timers.poll = setInterval(function () {
                loadSession(function () { if (S.screen === 'timeup' && !timeIsUp()) show('profiles'); }, true);
            }, 60000);
        },
        leave: function () { clearTimer('poll'); },
        action: function (action) {
            if (action === 'adult') openAdult();
            // Otro niño puede tener aún tiempo: se recarga lo que le queda a cada uno.
            else if (action === 'profiles') loadSession(function () { show('profiles'); });
        },
        key: function (name) {
            if (name === 'back') { confirmExit(); return true; }
            return false;
        }
    };

    function handleBlocked() {
        stopPlayer();
        if (S.screen !== 'blocked') show('blocked');
    }

    screens.blocked = {
        enter: function () {
            mount('<div class="screen screen-center"><h1>' + esc(t('error.blocked')) + '</h1><p class="muted">' + esc(t('error.blockedHint')) + '</p></div>');
            clearTimer('poll');
            // Si se reactiva la cuenta, la tele vuelve sola a los perfiles.
            S.timers.poll = setInterval(function () { loadSession(function () { show('profiles'); }, true); }, 60000);
        },
        leave: function () { clearTimer('poll'); },
        key: function (name) {
            if (name === 'back') { confirmExit(); return true; }
            return false;
        }
    };

    function handleRevoked() {
        stopPlayer();
        S.token = null;
        S.session = null;
        S.adult = null;
        K.store.set('token', null);
        hideOffline();
        show('pairing');
        openDialog({ kind: 'error', title: t('error.revoked'), text: t('error.revokedHint') });
    }

    window.addEventListener('offline', function () {
        showOffline(t('error.offline'), function () { if (navigator.onLine) hideOffline(); });
    });
    window.addEventListener('online', function () { if (offline.retry) offline.retry(); });

    /* ---------- Vinculación ---------- */

    screens.pairing = {
        enter: function () {
            loading(t('pair.loading'));
            this.request();
        },
        request: function () {
            var self = this;
            clearTimer('pairPoll');
            clearTimer('pairCountdown');
            K.http('POST', '/api/tv/pairings', { body: { platform: K.config.platform } }, function (status, data) {
                if (S.screen !== 'pairing') return;
                if (status !== 201) {
                    S.timers.pairPoll = setTimeout(function () { self.request(); }, 5000);
                    mount('<div class="screen screen-center"><h1>' + esc(t('error.offline')) + '</h1><p class="muted">' + esc(t('error.offlineHint')) + '</p></div>');
                    return;
                }
                self.render(data);
            });
        },
        render: function (p) {
            var self = this;
            var remaining = p.expires_in;
            mount(
                '<div class="screen screen-pairing">' +
                    '<div class="pair-text">' +
                        '<div class="brand-big"><span class="logo"></span>KidsTV</div>' +
                        '<h1>' + esc(t('pair.title')) + '</h1>' +
                        '<p class="pair-step">' + esc(t('pair.step1')) + '</p>' +
                        '<p class="pair-url">' + esc(p.link_display) + '</p>' +
                        '<p class="pair-step">' + esc(t('pair.step2')) + '</p>' +
                        '<p class="pair-code">' + esc(p.code_display) + '</p>' +
                        '<p class="muted pair-expiry"></p>' +
                        '<p class="muted small">' + esc(t('pair.noaccount')) + '</p>' +
                    '</div>' +
                    '<div class="pair-qr">' +
                        '<div class="qr-box"><img src="' + esc(p.qr_url) + '" alt=""></div>' +
                        '<p class="muted">' + esc(t('pair.qr')) + '</p>' +
                    '</div>' +
                '</div>'
            );
            var expiry = app.querySelector('.pair-expiry');
            function tick() {
                expiry.textContent = t('pair.expires', { time: K.formatTime(remaining) });
                if (remaining-- <= 0) self.request();
            }
            tick();
            S.timers.pairCountdown = setInterval(tick, 1000);

            function poll() {
                K.http('GET', '/api/tv/pairings/' + encodeURIComponent(p.id), { headers: { 'X-Pairing-Secret': p.secret } }, function (status, data) {
                    if (S.screen !== 'pairing') return;
                    if (status === 200 && data.status === 'paired') {
                        clearTimer('pairCountdown');
                        S.token = data.token;
                        K.store.set('token', data.token);
                        loading(t('pair.done'));
                        start();
                        return;
                    }
                    if (status === 410 || status === 404) { self.request(); return; }
                    S.timers.pairPoll = setTimeout(poll, 3000);
                });
            }
            S.timers.pairPoll = setTimeout(poll, 3000);
        },
        leave: function () {
            clearTimer('pairPoll');
            clearTimer('pairCountdown');
        },
        key: function (name) {
            if (name === 'back') { confirmExit(); return true; }
            return false;
        }
    };

    function confirmExit() {
        if (!K.canExit()) return;
        openDialog({
            title: t('common.exitConfirm'),
            buttons: [{ label: t('common.exit'), action: K.exitApp }, { label: t('common.cancel') }],
            focus: 1
        });
    }

    /* ---------- Perfiles ---------- */

    screens.profiles = {
        enter: function () {
            var self = this;
            this.render();
            this.schedule();
            // Al volver, se actualiza enseguida el tiempo que le queda a cada niño.
            loadSession(function (changed) { if (changed && S.screen === 'profiles' && !dialog) self.render(); }, true);
        },
        render: function () {
            var profiles = (S.session && S.session.profiles) || [];
            var html = '<div class="screen screen-profiles">' +
                '<div class="topline"><span class="family">' + esc(familyName()) + '</span></div>' +
                '<h1 class="screen-title">' + esc(t('profiles.title')) + '</h1>';

            if (!profiles.length) {
                html += '<p class="empty-big">' + esc(t('profiles.empty')) + '</p>';
            } else {
                html += '<div class="profiles-wrap scroll-y"><div class="profiles-grid">';
                for (var i = 0; i < profiles.length; i++) {
                    var p = profiles[i];
                    var resting = p.screen_time && p.screen_time.remaining_seconds <= 0;
                    html += '<div class="profile-card focusable' + (resting ? ' profile-resting' : '') + '" data-action="profile" data-arg="' + p.id + '" data-key="p-' + p.id + '">' +
                        '<img class="profile-avatar" src="' + esc(K.avatar(p.avatar)) + '" alt="">' +
                        '<span class="profile-name">' + esc(p.name) + '</span>' +
                        (resting ? '<span class="profile-rest">' + esc(t('limit.badge')) + '</span>' : '') + '</div>';
                }
                html += '</div></div>';
            }
            html += '<div class="adult-corner"><div class="btn btn-quiet focusable" data-action="adult" data-key="adult">' + ICON_LOCK + '<span>' + esc(t('adult.button')) + '</span></div></div></div>';

            var key = Nav.currentKey() || S.memory.profiles || (S.profile ? 'p-' + S.profile.id : null);
            mount(html);
            Nav.restore(key);
        },
        schedule: function () {
            var self = this;
            clearTimer('poll');
            S.timers.poll = setInterval(function () {
                loadSession(function (changed) { if (changed && S.screen === 'profiles' && !dialog) self.render(); }, true);
            }, 60000);
        },
        leave: function () { clearTimer('poll'); },
        action: function (action, arg) {
            if (action === 'profile') openProfile(parseInt(arg, 10));
            else if (action === 'adult') openAdult();
        },
        key: function (name) {
            if (name === 'back') { confirmExit(); return true; }
            return false;
        }
    };

    function loadSession(callback, silent) {
        api('GET', '/session', undefined, function (status, data) {
            if (status !== 200) return;
            var before = JSON.stringify(S.session);
            S.session = data;
            if (S.profile && !S.adultCatalog) limitSync(profileLimit(S.profile.id));
            callback(before !== JSON.stringify(data));
        }, { silent: !!silent });
    }

    function openProfile(id) {
        var profiles = (S.session && S.session.profiles) || [];
        for (var i = 0; i < profiles.length; i++) {
            if (profiles[i].id === id) {
                if (!S.profile || S.profile.id !== id || S.adultCatalog) { S.rowMemory = {}; S.rowOffsets = {}; S.memory.catalog = null; S.catalog = null; }
                // Elegir un perfil infantil cierra la zona de adultos.
                lockAdult(true);
                S.profile = profiles[i];
                S.limit = null;
                S.limitWarned = false;
                limitSync(profiles[i].screen_time);
                K.store.set('profile', String(id));
                show('catalog', { reload: true });
                return;
            }
        }
    }

    /* ---------- Catálogo: una fila por lista, con carrusel horizontal ---------- */

    function catalogPath() {
        return S.adultCatalog ? '/adult/catalog' : '/profiles/' + S.profile.id + '/catalog';
    }

    /** Agrupa los contenidos en filas siguiendo el orden de listas del adulto. */
    function buildRows(catalog) {
        var lists = catalog.lists || [];
        var items = catalog.items || [];
        var rows = [];
        for (var c = 0; c < lists.length; c++) {
            var row = { key: 'r-' + (lists[c].id === null ? 'otros' : lists[c].id), title: lists[c].name, items: [] };
            for (var i = 0; i < items.length; i++) {
                if (String(items[i].list_id) === String(lists[c].id)) row.items.push(items[i]);
            }
            if (row.items.length) rows.push(row);
        }
        // Si nada está en una lista, una única fila «Todo» en lugar de «Otros».
        if (rows.length === 1 && rows[0].key === 'r-otros') rows[0].title = t('catalog.all');
        return rows;
    }

    function rowsWithItem(id) {
        for (var r = 0; r < S.rows.length; r++) {
            for (var i = 0; i < S.rows[r].items.length; i++) {
                if (S.rows[r].items[i].id === id) return { row: S.rows[r], index: i };
            }
        }
        return null;
    }

    function setTranslate(el, x, y) {
        var value = 'translate(' + Math.round(x) + 'px,' + Math.round(y) + 'px)';
        el.style.webkitTransform = value;
        el.style.transform = value;
    }

    function remPx() {
        return parseFloat(window.getComputedStyle(document.documentElement).fontSize) || 16;
    }

    /** Desplaza la fila para que la tarjeta enfocada quede visible, asomando la siguiente. */
    function slideRow(rowEl, card) {
        var track = rowEl.querySelector('.row-track');
        var viewport = rowEl.querySelector('.row-viewport');
        var key = rowEl.getAttribute('data-row');
        var offset = S.rowOffsets[key] || 0;
        var margin = remPx() * 9;
        var left = card.offsetLeft;
        var right = left + card.offsetWidth;
        var width = viewport.clientWidth;

        if (right - offset > width - margin) offset = right - (width - margin);
        if (left - offset < margin) offset = left - margin;
        // Tope: la última tarjeta queda entera con el mismo margen que la primera (6rem).
        var cards = track.querySelectorAll('.card');
        var last = cards[cards.length - 1];
        var max = Math.max(0, last.offsetLeft + last.offsetWidth + remPx() * 6 - width);
        offset = Math.max(0, Math.min(offset, max));

        S.rowOffsets[key] = offset;
        track.setAttribute('data-offset', offset);
        setTranslate(track, -offset, 0);
        rowEl.classList.toggle('more-left', offset > 1);
        rowEl.classList.toggle('more-right', offset < max - 1);
    }

    /** Desplaza la lista de filas para que la fila enfocada quede arriba. */
    function slideRows(rowEl) {
        var list = app.querySelector('.rows');
        var inner = app.querySelector('.rows-inner');
        if (!list || !inner) return;
        var max = Math.max(0, inner.offsetHeight - list.clientHeight);
        var offset = rowEl ? Math.max(0, Math.min(rowEl.offsetTop, max)) : 0;
        setTranslate(inner, 0, -offset);
    }

    screens.catalog = {
        enter: function (arg) {
            var self = this;
            if (arg.reload || !S.catalog) {
                loading();
                this.fetch(function () { self.render(S.memory.catalog); });
            } else {
                this.render(S.memory.catalog);
                this.fetch(function (changed) { if (changed) self.render(Nav.currentKey()); });
            }
            clearTimer('poll');
            S.timers.poll = setInterval(function () {
                if (dialog) return;
                self.fetch(function (changed) { if (changed && S.screen === 'catalog') self.render(Nav.currentKey()); }, true);
            }, 60000);
        },
        fetch: function (callback, silent) {
            api('GET', catalogPath(), undefined, function (status, data) {
                if (S.screen !== 'catalog') return;
                if (status === 403 && S.adultCatalog) { lockAdult(); return; }
                if (status === 404) {
                    loadSession(function () { show('profiles'); });
                    return;
                }
                if (status !== 200) return;
                var changed = !S.catalog || S.catalog.version !== data.version;
                S.catalog = data;
                if (!S.adultCatalog) {
                    limitSync(data.screen_time);
                    if (timeIsUp()) { show('timeup'); return; }
                }
                callback(changed);
            }, { silent: !!silent });
        },
        render: function (focusKey) {
            S.rows = buildRows(S.catalog);

            var who = S.adultCatalog
                ? '<div class="who focusable" data-action="adultMenu" data-key="who">' + ICON_LOCK + '<span>' + esc(t('adultMenu.title')) + '</span></div>'
                : '<div class="who focusable" data-action="profiles" data-key="who"><img src="' + esc(K.avatar(S.profile.avatar)) + '" alt=""><span>' + esc(S.profile.name) + '</span><small>' + esc(t('catalog.change')) + '</small></div>';

            var html = '<div class="screen screen-catalog' + (S.adultCatalog ? ' with-banner' : '') + '">' +
                '<header class="catalog-header">' + who +
                    (S.adultCatalog ? '' : '<div class="btn btn-quiet btn-icon focusable" data-action="adult" data-key="adult" title="' + esc(t('adult.button')) + '">' + ICON_LOCK + '</div>') +
                '</header>' +
                (S.adultCatalog ? '<div class="adult-banner">' + esc(t('catalog.adultBanner')) + '</div>' : '') +
                '<div class="rows"><div class="rows-inner">' + this.rowsHtml() + '</div></div>' +
            '</div>';

            mount(html);

            // Cada fila recupera la posición en la que se dejó (sin animación al pintar).
            var rowEls = app.querySelectorAll('.row');
            app.querySelector('.screen-catalog').classList.add('no-anim');
            for (var r = 0; r < rowEls.length; r++) {
                var remembered = S.rowMemory[rowEls[r].getAttribute('data-row')];
                var card = (remembered && rowEls[r].querySelector('[data-key="' + remembered + '"]')) || rowEls[r].querySelector('.card');
                if (card) slideRow(rowEls[r], card);
            }

            Nav.onFocus = function (el) {
                var rowEl = K.closest(el, 'row');
                if (rowEl) {
                    S.rowMemory[rowEl.getAttribute('data-row')] = el.getAttribute('data-key');
                    slideRow(rowEl, el);
                    slideRows(rowEl);
                } else {
                    slideRows(null);
                }
            };

            if (!Nav.restore(focusKey)) {
                var firstCard = app.querySelector('.card.focusable');
                if (firstCard) Nav.focus(firstCard);
            }
            setTimeout(function () {
                var screen = app.querySelector('.screen-catalog');
                if (screen) screen.classList.remove('no-anim');
            }, 50);
        },
        rowsHtml: function () {
            if (!S.rows.length) {
                return '<div class="empty-catalog"><p class="empty-big">' + esc(t('catalog.empty')) + '</p><p class="muted">' + esc(t('catalog.emptyHint')) + '</p></div>';
            }
            var html = '';
            for (var r = 0; r < S.rows.length; r++) {
                var row = S.rows[r];
                html += '<section class="row" data-row="' + esc(row.key) + '">' +
                    '<h2 class="row-title">' + esc(row.title) + ' <span class="row-count">' + row.items.length + '</span></h2>' +
                    '<div class="row-viewport"><div class="row-track">';
                for (var i = 0; i < row.items.length; i++) {
                    var it = row.items[i];
                    // Como en YouTube: la duración abajo a la derecha (el tipo de contenido no le dice nada al peque).
                    var badge = it.duration ? '<span class="card-duration">' + esc(K.formatTime(it.duration)) + '</span>' : '';
                    html += '<div class="card focusable card-' + it.type + '" data-action="play" data-arg="' + it.id + '" data-key="c-' + it.id + '">' +
                        '<div class="card-cover">' +
                            (it.cover ? '<img src="' + esc(it.cover) + '" alt="">' : '<div class="card-placeholder">' + (it.type === 'audio' ? ICON_NOTE : ICON_PLAY) + '</div>') +
                            badge +
                        '</div>' +
                        '<div class="card-title">' + esc(it.title) + '</div>' +
                    '</div>';
                }
                html += '</div></div></section>';
            }
            return html;
        },
        /** Arriba/abajo saltan de fila en fila, volviendo a la última tarjeta usada en cada una. */
        moveRow: function (dir) {
            var rowEls = app.querySelectorAll('.row');
            if (!rowEls.length) return false;
            var current = Nav.current ? K.closest(Nav.current, 'row') : null;
            var index = -1;
            for (var r = 0; r < rowEls.length; r++) { if (rowEls[r] === current) index = r; }

            var target = null;
            if (index === -1) {
                if (dir === 'down') target = rowEls[0];
                else return false;
            } else {
                target = rowEls[index + (dir === 'down' ? 1 : -1)];
                if (!target) {
                    if (dir === 'up') Nav.focus(app.querySelector('.who'));
                    return true;
                }
            }
            var remembered = S.rowMemory[target.getAttribute('data-row')];
            Nav.focus((remembered && target.querySelector('[data-key="' + remembered + '"]')) || target.querySelector('.card'));
            return true;
        },
        leave: function () { clearTimer('poll'); },
        action: function (action, arg) {
            if (action === 'play') playContent(parseInt(arg, 10));
            else if (action === 'profiles') show('profiles');
            else if (action === 'adult') openAdult();
            else if (action === 'adultMenu') show('adultMenu');
        },
        key: function (name) {
            if (name === 'up' || name === 'down') return this.moveRow(name);
            if (name === 'back') {
                if (S.adultCatalog) show('adultMenu');
                else show('profiles');
                return true;
            }
            return false;
        }
    };

    // Las imágenes que no cargan se sustituyen por un marcador (sin manejadores en línea).
    document.addEventListener('error', function (e) {
        var img = e.target;
        if (img && img.tagName === 'IMG' && img.parentNode && img.parentNode.className === 'card-cover') {
            var ph = document.createElement('div');
            ph.className = 'card-placeholder';
            ph.innerHTML = ICON_PLAY;
            img.parentNode.replaceChild(ph, img);
        }
    }, true);

    /* ---------- Reproducción ---------- */

    function playContent(id) {
        var query = S.adultCatalog || !S.profile ? '' : '?profile=' + S.profile.id;
        api('GET', '/contents/' + id + '/play' + query, undefined, function (status, data) {
            if (status === 404) {
                openDialog({ kind: 'error', title: t('player.gone'), onClose: function () { show('catalog', { reload: true }); } });
                return;
            }
            if (status === 423 && data && data.error === 'time_up') { show('timeup'); return; }
            if (status !== 200) return;
            if (!S.adultCatalog) limitSync(data.screen_time);
            show('player', data);
        });
    }

    /** Con reproducción automática activada, el siguiente es el de la derecha en la misma fila. */
    function nextItem(currentId) {
        var found = rowsWithItem(currentId);
        return found ? found.row.items[found.index + 1] || null : null;
    }

    screens.player = {
        enter: function (item) {
            var self = this;
            S.playing = item;
            S.memory.catalog = 'c-' + item.id;
            mount('<div class="screen screen-player"></div>');
            var container = app.querySelector('.screen-player');
            watchStart(item);
            var opts = {
                onPlaying: watchPlaying,
                onEnded: function () { self.ended(item); },
                onExit: function () { self.exit(); },
                onError: function (info) { self.error(item, info); }
            };
            S.player = item.type === 'youtube' ? new K.YouTubePlayer(container, item, opts) : new K.MediaPlayer(container, item, opts);
        },
        ended: function (item) {
            var next = S.session && S.session.family && S.session.family.autoplay_next ? nextItem(item.id) : null;
            stopPlayer();
            if (next) { loading(); playContent(next.id); }
            else this.exit();
        },
        exit: function () {
            stopPlayer();
            show('catalog', {});
        },
        error: function (item, info) {
            var self = this;
            stopPlayer();
            if (info.youtube !== undefined) {
                var code = info.youtube;
                var title = code === 101 || code === 150 ? t('player.ytBlocked')
                    : code === 100 ? t('player.ytMissing') : t('player.ytFailed');
                if (code === 100 || code === 101 || code === 150) {
                    api('POST', '/contents/' + item.id + '/problem', { code: code }, null, { silent: true });
                }
                openDialog({ kind: 'error', title: title, text: t('player.ytFailedHint'), onClose: function () { show('catalog', { reload: true }); } });
                return;
            }
            // Fallo de vídeo/audio: ¿se ha retirado el contenido o no lo soporta la tele?
            var query = S.adultCatalog || !S.profile ? '' : '?profile=' + S.profile.id;
            api('GET', '/contents/' + item.id + '/play' + query, undefined, function (status) {
                openDialog({
                    kind: 'error',
                    title: status === 404 ? t('player.gone') : t('player.error'),
                    text: status === 404 ? '' : t('player.errorHint'),
                    onClose: function () { self.exit(); }
                });
            });
        },
        leave: function () { stopPlayer(); },
        key: function (name) {
            return S.player ? S.player.key(name) : false;
        }
    };

    /* ---------- Zona de adultos ---------- */

    function openAdult() {
        if (S.adult) { show('adultMenu'); return; }
        show('pin', { from: S.screen });
    }

    /** Cierra la zona de adultos; si se estaba dentro, vuelve a la selección de perfil. */
    function lockAdult(stay) {
        var wasInside = S.screen === 'adultMenu' || S.screen === 'pin' || (S.screen === 'catalog' && S.adultCatalog);
        if (S.adult) api('POST', '/adult/lock', {}, null, { silent: true });
        S.adult = null;
        if (S.adultCatalog) { S.adultCatalog = false; S.catalog = null; }
        if (!stay && wasInside) show('profiles');
    }

    screens.pin = {
        enter: function (arg) {
            this.from = arg.from || 'profiles';
            this.digits = '';
            this.lockedUntil = 0;
            var keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'del', '0', 'ok'];
            var pad = '';
            for (var i = 0; i < keys.length; i++) {
                var k = keys[i];
                var label = k === 'del' ? t('pin.delete') : k === 'ok' ? t('pin.ok') : k;
                pad += '<div class="pin-key focusable' + (k === 'ok' ? ' pin-ok' : '') + (k === '1' ? ' autofocus' : '') + '" data-action="pin" data-arg="' + k + '" data-key="k' + k + '">' + esc(label) + '</div>';
            }
            mount(
                '<div class="screen screen-pin">' +
                    '<div class="pin-side">' + ICON_LOCK + '<h1>' + esc(t('pin.title')) + '</h1><p class="muted">' + esc(t('pin.prompt')) + '</p>' +
                    '<div class="pin-dots"></div><p class="pin-message" role="alert"></p></div>' +
                    '<div class="pin-pad">' + pad + '</div>' +
                '</div>'
            );
            this.dots = app.querySelector('.pin-dots');
            this.message = app.querySelector('.pin-message');
            this.update();
            Nav.restore();
        },
        update: function () {
            var html = '';
            var n = Math.max(4, this.digits.length);
            for (var i = 0; i < n; i++) html += '<span class="pin-dot' + (i < this.digits.length ? ' filled' : '') + '"></span>';
            this.dots.innerHTML = html;
        },
        press: function (k) {
            if (new Date().getTime() < this.lockedUntil) return;
            if (k === 'del') this.digits = this.digits.slice(0, -1);
            else if (k === 'ok') { this.submit(); return; }
            else if (this.digits.length < 6) this.digits += k;
            this.message.textContent = '';
            this.update();
            if (this.digits.length === 6) this.submit();
        },
        submit: function () {
            var self = this;
            if (this.digits.length < 4) return;
            var pin = this.digits;
            this.digits = '';
            this.update();
            api('POST', '/adult/unlock', { pin: pin }, function (status, data) {
                if (S.screen !== 'pin') return;
                if (status === 200) {
                    S.adult = { token: data.adult_token, idle: data.idle_seconds || 120 };
                    S.lastKeyAt = new Date().getTime();
                    show('adultMenu');
                    return;
                }
                if (status === 423) {
                    self.lock(data.retry_after || 30);
                } else if (status === 409) {
                    self.message.textContent = t('pin.noPin');
                } else if (status === 422 && data.error === 'wrong_pin') {
                    self.message.textContent = data.attempts_left > 0 ? t('pin.wrong', { n: data.attempts_left }) : t('pin.wrongLast');
                } else {
                    self.message.textContent = data.message || t('error.generic');
                }
            });
        },
        lock: function (seconds) {
            var self = this;
            this.lockedUntil = new Date().getTime() + seconds * 1000;
            app.querySelector('.pin-pad').classList.add('locked');
            clearTimer('pinLock');
            function tick() {
                var left = Math.ceil((self.lockedUntil - new Date().getTime()) / 1000);
                if (left <= 0 || S.screen !== 'pin') {
                    clearTimer('pinLock');
                    var pad = app.querySelector('.pin-pad');
                    if (pad) pad.classList.remove('locked');
                    if (self.message) self.message.textContent = '';
                    return;
                }
                self.message.textContent = t('pin.locked', { s: left });
            }
            tick();
            S.timers.pinLock = setInterval(tick, 1000);
        },
        leave: function () { clearTimer('pinLock'); },
        action: function (action, arg) { if (action === 'pin') this.press(arg); },
        key: function (name, e) {
            if (name === 'digit') {
                this.press(K.keyDigit(e));
                // Tras teclear con los números del mando, OK confirma el PIN.
                Nav.focus(app.querySelector('.pin-ok'));
                return true;
            }
            if (name === 'back') {
                if (this.digits.length) { this.press('del'); return true; }
                show(this.from === 'catalog' && S.profile ? 'catalog' : this.from === 'timeup' ? 'timeup' : 'profiles', {});
                return true;
            }
            return false;
        }
    };

    screens.adultMenu = {
        enter: function () {
            var items = [
                ['all', t('adultMenu.all')],
                ['refresh', t('adultMenu.refresh')],
                ['info', t('adultMenu.info')],
                ['unlink', t('adultMenu.unlink')],
                ['exit', t('adultMenu.exit')]
            ];
            var html = '<div class="screen screen-adult"><div class="adult-menu">' + ICON_LOCK + '<h1>' + esc(t('adultMenu.title')) + '</h1>';
            for (var i = 0; i < items.length; i++) {
                html += '<div class="menu-item focusable' + (items[i][0] === 'unlink' ? ' menu-danger' : '') + '" data-action="menu" data-arg="' + items[i][0] + '" data-key="m-' + items[i][0] + '">' + esc(items[i][1]) + '</div>';
            }
            html += '</div><div class="adult-info">' +
                '<p>' + esc(t('adultMenu.manage')) + '</p><p class="pair-url">' + esc(serverHost()) + '</p>' +
                '<p class="muted">' + esc(t('adultMenu.autolock', { s: S.adult ? S.adult.idle : 120 })) + '</p>' +
            '</div></div>';
            mount(html);
            Nav.restore(S.memory.adultMenu);
        },
        action: function (action, arg) {
            if (action !== 'menu') return;
            if (arg === 'all') {
                S.adultCatalog = true;
                S.rowMemory = {};
                S.rowOffsets = {};
                S.memory.catalog = null;
                show('catalog', { reload: true });
            } else if (arg === 'refresh') {
                S.catalog = null;
                loadSession(function () { toast(t('adultMenu.refreshed')); });
            } else if (arg === 'info') {
                var d = S.session || {};
                openDialog({
                    title: t('adultMenu.info'),
                    html: '<dl class="info-list">' +
                        '<dt>' + esc(t('adultMenu.infoDevice')) + '</dt><dd>' + esc(d.device ? d.device.name : '') + '</dd>' +
                        '<dt>' + esc(t('adultMenu.infoFamily')) + '</dt><dd>' + esc(familyName()) + '</dd>' +
                        '<dt>' + esc(t('adultMenu.infoServer')) + '</dt><dd>' + esc(serverHost()) + '</dd>' +
                        '<dt>' + esc(t('adultMenu.infoVersion')) + '</dt><dd>' + esc(K.config.version + ' · ' + K.config.platform) + '</dd>' +
                    '</dl>'
                });
            } else if (arg === 'unlink') {
                openDialog({
                    kind: 'error',
                    title: t('adultMenu.unlink'),
                    text: t('adultMenu.unlinkConfirm'),
                    buttons: [
                        { label: t('adultMenu.unlink'), action: function () {
                            api('DELETE', '/adult/device', undefined, function (status) {
                                if (status === 200) {
                                    S.token = null; S.adult = null; S.session = null;
                                    K.store.set('token', null);
                                    show('pairing');
                                } else if (status === 403) {
                                    lockAdult();
                                }
                            });
                        } },
                        { label: t('common.cancel') }
                    ],
                    focus: 1
                });
            } else if (arg === 'exit') {
                lockAdult();
            }
        },
        key: function (name) {
            if (name === 'back') { lockAdult(); return true; }
            return false;
        }
    };

    // Bloqueo por inactividad de la zona de adultos (no mientras se reproduce algo).
    setInterval(function () {
        if (!S.adult || S.screen === 'player') return;
        if (new Date().getTime() - S.lastKeyAt > S.adult.idle * 1000) lockAdult();
    }, 5000);

    function pingAdult() {
        var now = new Date().getTime();
        if (!S.adult || now - S.lastAdultPing < 30000) return;
        S.lastAdultPing = now;
        api('GET', '/adult/status', undefined, function (status, data) {
            if (status === 200 && !data.unlocked && S.adult) lockAdult();
        }, { silent: true });
    }

    /* ---------- Entrada: mando, teclado y puntero ---------- */

    document.addEventListener('keydown', function (e) {
        var name = K.keyName(e);
        if (!name) return;
        S.lastKeyAt = new Date().getTime();
        pingAdult();
        e.preventDefault();

        if (dialog) {
            if (name === 'back') { if (!offline.visible) closeDialog(); return; }
            if (name === 'ok') { activate(Nav.current); return; }
            if (name === 'left' || name === 'right' || name === 'up' || name === 'down') Nav.move(name);
            return;
        }

        var screen = screens[S.screen];
        if (screen && screen.key && screen.key(name, e)) return;

        if (name === 'left' || name === 'right' || name === 'up' || name === 'down') Nav.move(name);
        else if (name === 'ok') activate(Nav.current);
    });

    function activate(el) {
        if (!el) return;
        var action = el.getAttribute('data-action');
        var arg = el.getAttribute('data-arg');
        if (action === 'dialog') { dialogAction(parseInt(arg, 10)); return; }
        var screen = screens[S.screen];
        if (screen && screen.action) screen.action(action, arg, el);
    }

    document.addEventListener('mouseover', function (e) {
        var el = K.closest(e.target, 'focusable');
        if (el && el !== Nav.current && (!dialog || K.closest(el, 'overlay'))) Nav.focus(el);
    });

    document.addEventListener('click', function (e) {
        var el = K.closest(e.target, 'focusable');
        if (!el) return;
        if (dialog && !K.closest(el, 'overlay')) return;
        S.lastKeyAt = new Date().getTime();
        Nav.focus(el);
        activate(el);
    });

    /* ---------- Arranque ---------- */

    function start() {
        loadSession(function () { show('profiles'); });
    }

    function boot() {
        // «Probar modo TV» desde el panel entrega el token en el fragmento de la URL.
        var match = window.location.hash.match(/token=([A-Za-z0-9]+)/);
        if (match) {
            K.store.set('token', match[1]);
            if (window.history && window.history.replaceState) window.history.replaceState(null, '', window.location.pathname + window.location.search);
            else window.location.hash = '';
        }

        S.token = K.store.get('token');
        if (!S.token) { show('pairing'); return; }
        loading();
        start();
    }

    boot();
})(window, document, window.KidsTV);
