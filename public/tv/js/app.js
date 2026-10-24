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

    var S = {
        token: null,
        session: null,
        profile: null,
        catalog: null,
        tab: 'all',
        adult: null,
        adultCatalog: false,
        screen: null,
        memory: {},
        player: null,
        playing: null,
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
        if (S.player) { S.player.destroy(); S.player = null; }
        S.playing = null;
    }

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
            this.render();
            this.schedule();
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
                    html += '<div class="profile-card focusable" data-action="profile" data-arg="' + p.id + '" data-key="p-' + p.id + '">' +
                        '<img class="profile-avatar" src="' + esc(K.avatar(p.avatar)) + '" alt="">' +
                        '<span class="profile-name">' + esc(p.name) + '</span></div>';
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
            callback(before !== JSON.stringify(data));
        }, { silent: !!silent });
    }

    function openProfile(id) {
        var profiles = (S.session && S.session.profiles) || [];
        for (var i = 0; i < profiles.length; i++) {
            if (profiles[i].id === id) {
                if (!S.profile || S.profile.id !== id || S.adultCatalog) { S.tab = 'all'; S.memory.catalog = null; S.catalog = null; }
                // Elegir un perfil infantil cierra la zona de adultos.
                lockAdult(true);
                S.profile = profiles[i];
                K.store.set('profile', String(id));
                show('catalog', { reload: true });
                return;
            }
        }
    }

    /* ---------- Catálogo ---------- */

    function catalogPath() {
        return S.adultCatalog ? '/adult/catalog' : '/profiles/' + S.profile.id + '/catalog';
    }

    function visibleItems() {
        var items = (S.catalog && S.catalog.items) || [];
        if (S.tab === 'all') return items;
        var out = [];
        for (var i = 0; i < items.length; i++) {
            if (String(items[i].category_id) === S.tab) out.push(items[i]);
        }
        return out;
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
                callback(changed);
            }, { silent: !!silent });
        },
        render: function (focusKey) {
            var cats = S.catalog.categories || [];
            var validTab = S.tab === 'all';
            for (var c = 0; c < cats.length; c++) { if (String(cats[c].id) === S.tab) validTab = true; }
            if (!validTab) S.tab = 'all';

            var who = S.adultCatalog
                ? '<div class="who focusable" data-action="adultMenu" data-key="who">' + ICON_LOCK + '<span>' + esc(t('adultMenu.title')) + '</span></div>'
                : '<div class="who focusable" data-action="profiles" data-key="who"><img src="' + esc(K.avatar(S.profile.avatar)) + '" alt=""><span>' + esc(S.profile.name) + '</span><small>' + esc(t('catalog.change')) + '</small></div>';

            var html = '<div class="screen screen-catalog">' +
                '<header class="catalog-header">' + who +
                    '<div class="tabs scroll-x"><div class="tabs-inner">' + this.tabsHtml(cats) + '</div></div>' +
                    (S.adultCatalog ? '' : '<div class="btn btn-quiet btn-icon focusable" data-action="adult" data-key="adult" title="' + esc(t('adult.button')) + '">' + ICON_LOCK + '</div>') +
                '</header>' +
                (S.adultCatalog ? '<div class="adult-banner">' + esc(t('catalog.adultBanner')) + '</div>' : '') +
                '<div class="grid scroll-y"><div class="grid-inner"></div></div>' +
            '</div>';

            mount(html);
            this.renderGrid();

            var self = this;
            Nav.onFocus = function (el) {
                if (el.getAttribute('data-action') === 'tab') {
                    var tab = el.getAttribute('data-arg');
                    if (tab !== S.tab) {
                        S.tab = tab;
                        var tabs = app.querySelectorAll('.tab');
                        for (var i = 0; i < tabs.length; i++) tabs[i].classList.toggle('active', tabs[i] === el);
                        self.renderGrid();
                    }
                }
            };

            if (!Nav.restore(focusKey)) {
                var firstCard = app.querySelector('.card.focusable');
                if (firstCard) Nav.focus(firstCard);
            }
        },
        tabsHtml: function (cats) {
            var html = '<div class="tab focusable' + (S.tab === 'all' ? ' active' : '') + '" data-action="tab" data-arg="all" data-key="tab-all">' + esc(t('catalog.all')) + '</div>';
            for (var i = 0; i < cats.length; i++) {
                var id = String(cats[i].id);
                html += '<div class="tab focusable' + (S.tab === id ? ' active' : '') + '" data-action="tab" data-arg="' + esc(id) + '" data-key="tab-' + esc(id) + '">' + esc(cats[i].name) + '</div>';
            }
            return html;
        },
        renderGrid: function () {
            var grid = app.querySelector('.grid-inner');
            var items = visibleItems();
            if (!items.length) {
                grid.innerHTML = '<div class="empty-catalog"><p class="empty-big">' + esc(t('catalog.empty')) + '</p><p class="muted">' + esc(t('catalog.emptyHint')) + '</p></div>';
                return;
            }
            var html = '';
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                var badge = it.type === 'youtube' ? '<span class="card-badge badge-yt">' + esc(t('type.youtube')) + '</span>'
                    : it.type === 'audio' ? '<span class="card-badge badge-audio">' + ICON_NOTE + '</span>' : '';
                html += '<div class="card focusable card-' + it.type + '" data-action="play" data-arg="' + it.id + '" data-key="c-' + it.id + '">' +
                    '<div class="card-cover">' +
                        (it.cover ? '<img src="' + esc(it.cover) + '" alt="">' : '<div class="card-placeholder">' + (it.type === 'audio' ? ICON_NOTE : ICON_PLAY) + '</div>') +
                        badge +
                    '</div>' +
                    '<div class="card-title">' + esc(it.title) + '</div>' +
                '</div>';
            }
            grid.innerHTML = html;
            grid.parentNode.scrollTop = 0;
        },
        leave: function () { clearTimer('poll'); },
        action: function (action, arg) {
            if (action === 'play') playContent(parseInt(arg, 10));
            else if (action === 'profiles') show('profiles');
            else if (action === 'adult') openAdult();
            else if (action === 'adultMenu') show('adultMenu');
        },
        key: function (name) {
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
            if (status !== 200) return;
            show('player', data);
        });
    }

    function nextItem(currentId) {
        var items = visibleItems();
        for (var i = 0; i < items.length - 1; i++) {
            if (items[i].id === currentId) return items[i + 1];
        }
        return null;
    }

    screens.player = {
        enter: function (item) {
            var self = this;
            S.playing = item;
            S.memory.catalog = 'c-' + item.id;
            mount('<div class="screen screen-player"></div>');
            var container = app.querySelector('.screen-player');
            var opts = {
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
                show(this.from === 'catalog' && S.profile ? 'catalog' : 'profiles', {});
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
                S.tab = 'all';
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
