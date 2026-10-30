/*
 * NokoPlay TV · reproductores (vídeo/audio propios y YouTube).
 * Interfaz común: new Player(contenedor, item, opciones) → { key(nombre), destroy() }.
 */
(function (window, document, K) {
    'use strict';

    var t = K.t;
    var esc = K.esc;

    var ICON_PLAY = '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>';
    var ICON_PAUSE = '<svg viewBox="0 0 24 24"><path d="M7 5h4v14H7zM13 5h4v14h-4z" fill="currentColor"/></svg>';

    /* ---------- Vídeo y audio propios ---------- */

    K.MediaPlayer = function (container, item, opts) {
        var isAudio = item.type === 'audio';
        var hideTimer = null;
        var destroyed = false;

        container.innerHTML =
            '<div class="player ' + (isAudio ? 'player-audio' : 'player-video') + '">' +
                (isAudio
                    ? '<div class="audio-stage">' +
                        '<div class="audio-cover">' + (item.cover ? '<img src="' + esc(item.cover) + '" alt="">' : '<div class="audio-cover-empty"></div>') + '</div>' +
                        '<h1 class="audio-title">' + esc(item.title) + '</h1>' +
                      '</div><audio preload="auto"></audio>'
                    : '<video preload="auto"' + (item.cover ? ' poster="' + esc(item.cover) + '"' : '') + '></video>') +
                '<div class="player-spinner"><div class="spinner"></div></div>' +
                '<div class="player-controls visible">' +
                    (isAudio ? '' : '<div class="player-title">' + esc(item.title) + '</div>') +
                    '<div class="player-bar">' +
                        '<div class="player-button">' + ICON_PAUSE + '</div>' +
                        '<div class="player-time player-current">0:00</div>' +
                        '<div class="player-progress"><div class="player-progress-fill"></div><div class="player-progress-knob"></div></div>' +
                        '<div class="player-time player-duration">0:00</div>' +
                    '</div>' +
                    '<div class="player-hint">' + esc(t('player.back')) + '</div>' +
                '</div>' +
            '</div>';

        var media = container.querySelector(isAudio ? 'audio' : 'video');
        var controls = container.querySelector('.player-controls');
        var button = container.querySelector('.player-button');
        var fill = container.querySelector('.player-progress-fill');
        var knob = container.querySelector('.player-progress-knob');
        var current = container.querySelector('.player-current');
        var durationEl = container.querySelector('.player-duration');
        var spinner = container.querySelector('.player-spinner');

        function playing(value) {
            if (!destroyed && opts.onPlaying) opts.onPlaying(value);
        }

        function showControls() {
            controls.classList.add('visible');
            clearTimeout(hideTimer);
            if (!isAudio && !media.paused) {
                hideTimer = setTimeout(function () { controls.classList.remove('visible'); }, 4000);
            }
        }

        function update() {
            var d = media.duration || 0;
            var pct = d > 0 ? Math.min(100, media.currentTime / d * 100) : 0;
            fill.style.width = pct + '%';
            knob.style.left = pct + '%';
            current.textContent = K.formatTime(media.currentTime);
            if (d && isFinite(d)) durationEl.textContent = K.formatTime(d);
        }

        function seek(delta) {
            if (!media.duration || !isFinite(media.duration)) return;
            media.currentTime = Math.max(0, Math.min(media.duration - 0.5, media.currentTime + delta));
            update();
            showControls();
        }

        function toggle() {
            if (media.paused) { tryPlay(); } else { media.pause(); }
            showControls();
        }

        function tryPlay() {
            var p = media.play();
            if (p && typeof p.catch === 'function') {
                p.catch(function () { button.innerHTML = ICON_PLAY; controls.classList.add('visible'); });
            }
        }

        media.addEventListener('timeupdate', update);
        media.addEventListener('loadedmetadata', update);
        media.addEventListener('waiting', function () { spinner.classList.add('visible'); playing(false); });
        media.addEventListener('playing', function () { spinner.classList.remove('visible'); button.innerHTML = ICON_PAUSE; showControls(); playing(true); });
        media.addEventListener('pause', function () { button.innerHTML = ICON_PLAY; showControls(); playing(false); });
        media.addEventListener('ended', function () { playing(false); if (!destroyed) opts.onEnded(); });
        media.addEventListener('error', function () {
            playing(false);
            if (destroyed) return;
            spinner.classList.remove('visible');
            opts.onError({ media: media.error ? media.error.code : 0 });
        });

        spinner.classList.add('visible');
        media.src = item.url;
        tryPlay();
        showControls();

        // Pausa si la app pasa a segundo plano (p. ej. al pulsar Home en el mando).
        function onVisibility() { if (document.hidden && !media.paused) media.pause(); }
        document.addEventListener('visibilitychange', onVisibility);

        // Con el dedo, el primer toque solo muestra los controles; tocar la barra salta a ese punto.
        container.querySelector('.player').addEventListener('click', function (e) {
            var bar = K.closest(e.target, 'player-progress');
            if (bar) {
                var rect = bar.getBoundingClientRect();
                if (rect.width > 0) seek((e.clientX - rect.left) / rect.width * (media.duration || 0) - media.currentTime);
                return;
            }
            if (K.touching && !isAudio && !controls.classList.contains('visible')) { showControls(); return; }
            toggle();
        });

        return {
            key: function (name) {
                switch (name) {
                    case 'ok': case 'playpause': toggle(); return true;
                    case 'play': tryPlay(); showControls(); return true;
                    case 'pause': media.pause(); return true;
                    case 'left': seek(-10); return true;
                    case 'right': seek(10); return true;
                    case 'rw': seek(-30); return true;
                    case 'ff': seek(30); return true;
                    case 'up': case 'down': showControls(); return true;
                    case 'back': case 'stop': opts.onExit(); return true;
                }
                return false;
            },
            destroy: function () {
                destroyed = true;
                clearTimeout(hideTimer);
                document.removeEventListener('visibilitychange', onVisibility);
                try { media.pause(); media.removeAttribute('src'); media.load(); } catch (e) { /* nada */ }
                container.innerHTML = '';
            }
        };
    };

    /* ---------- YouTube (reproductor oficial a través de la página puente) ---------- */

    K.YouTubePlayer = function (container, item, opts) {
        var origin = (item.youtube_player.match(/^https?:\/\/[^\/]+/) || [''])[0];
        var ready = false;
        var destroyed = false;

        container.innerHTML =
            '<div class="player player-youtube">' +
                '<iframe class="yt-frame" src="' + esc(item.youtube_player) + '" allow="autoplay; encrypted-media" tabindex="-1" frameborder="0"></iframe>' +
                '<div class="player-spinner visible"><div class="spinner"></div><p>' + esc(t('player.ytLoading')) + '</p></div>' +
                '<div class="yt-notice visible">' + esc(t('player.ytNotice')) + (K.pointerUi ? '' : ' · ' + esc(t('player.back'))) + '</div>' +
            '</div>';

        var frame = container.querySelector('.yt-frame');
        var spinner = container.querySelector('.player-spinner');
        var notice = container.querySelector('.yt-notice');
        var noticeTimer = setTimeout(function () { notice.classList.remove('visible'); }, 6000);

        var timeout = setTimeout(function () {
            if (!ready && !destroyed) opts.onError({ youtube: 'timeout' });
        }, 25000);

        function send(cmd, value) {
            try {
                frame.contentWindow.postMessage(JSON.stringify({ nokoplay: 'cmd', cmd: cmd, value: value }), origin || '*');
            } catch (e) { /* nada */ }
        }

        function onMessage(e) {
            if (destroyed || e.source !== frame.contentWindow) return;
            var data = e.data;
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (err) { return; }
            }
            if (!data || data.nokoplay !== 'yt') return;

            if (data.event === 'ready') {
                ready = true;
                clearTimeout(timeout);
                spinner.classList.remove('visible');
            } else if (data.event === 'state') {
                // Estados del reproductor de YouTube: 1 = reproduciendo, 0 = terminado.
                if (opts.onPlaying) opts.onPlaying(data.value === 1);
                if (data.value === 0) opts.onEnded();
            } else if (data.event === 'error') {
                clearTimeout(timeout);
                opts.onError({ youtube: data.value });
            }
        }

        // Si el puntero del mando (Magic Remote) pulsa dentro del iframe, se recupera el foco
        // para que las teclas (sobre todo Atrás) sigan llegando a la app.
        function onBlur() { setTimeout(function () { try { window.focus(); } catch (e) { /* nada */ } }, 60); }

        window.addEventListener('message', onMessage);
        window.addEventListener('blur', onBlur);

        return {
            key: function (name) {
                switch (name) {
                    case 'ok': case 'playpause': send('toggle'); return true;
                    case 'play': send('play'); return true;
                    case 'pause': send('pause'); return true;
                    case 'left': case 'rw': send('seek', name === 'rw' ? -30 : -10); return true;
                    case 'right': case 'ff': send('seek', name === 'ff' ? 30 : 10); return true;
                    case 'up': case 'down':
                        send('showControls');
                        notice.classList.add('visible');
                        clearTimeout(noticeTimer);
                        noticeTimer = setTimeout(function () { notice.classList.remove('visible'); }, 4000);
                        return true;
                    case 'back': case 'stop': opts.onExit(); return true;
                }
                return false;
            },
            destroy: function () {
                destroyed = true;
                clearTimeout(timeout);
                clearTimeout(noticeTimer);
                window.removeEventListener('message', onMessage);
                window.removeEventListener('blur', onBlur);
                frame.src = 'about:blank';
                container.innerHTML = '';
            }
        };
    };
})(window, document, window.NokoPlay);
