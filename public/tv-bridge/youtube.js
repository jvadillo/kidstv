/*
 * Página puente del reproductor de YouTube para la app de TV (ES5).
 * Carga el reproductor oficial (IFrame API, modo de privacidad mejorada) y se comunica
 * con la app que la incrusta mediante postMessage. No intercambia datos sensibles.
 */
(function () {
    'use strict';

    var el = document.getElementById('player');
    var videoId = el.getAttribute('data-video-id');
    var mode = el.getAttribute('data-controls') || 'auto';
    var player = null;
    var ready = false;
    var scrubbing = false;
    var hideTimer = null;
    var progressTimer = null;
    var controls = document.getElementById('controls');
    var toggle = document.getElementById('toggle');
    var rewind = document.getElementById('rewind');
    var forward = document.getElementById('forward');
    var progress = document.getElementById('progress');
    var current = document.getElementById('current');
    var duration = document.getElementById('duration');

    function seconds(value) {
        value = Number(value);
        return isFinite(value) && value > 0 ? value : 0;
    }

    function formatTime(value) {
        value = Math.floor(seconds(value));
        var hours = Math.floor(value / 3600);
        var minutes = Math.floor(value / 60) % 60;
        var secs = value % 60;
        return (hours ? hours + ':' + (minutes < 10 ? '0' : '') : '') + minutes + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function showControls() {
        if (mode === 'hidden') return;
        controls.hidden = false;
        document.body.classList.add('controls-visible');
        clearTimeout(hideTimer);
        if (mode === 'auto' && ready && !scrubbing && player.getPlayerState() === 1) {
            hideTimer = setTimeout(function () {
                controls.hidden = true;
                document.body.classList.remove('controls-visible');
            }, 4000);
        }
    }

    function update() {
        if (!ready) return;
        var time = seconds(player.getCurrentTime());
        var length = seconds(player.getDuration());
        current.textContent = formatTime(time);
        duration.textContent = length ? formatTime(length) : '--:--';
        if (!scrubbing) progress.value = length ? Math.min(1000, time / length * 1000) : 0;
        progress.setAttribute('aria-valuetext', current.textContent + ' / ' + duration.textContent);
        progress.disabled = rewind.disabled = forward.disabled = !length;
        toggle.disabled = false;
        toggle.textContent = toggle.getAttribute(player.getPlayerState() === 1 ? 'data-pause' : 'data-play');
    }

    function seekTo(time) {
        var length = seconds(player.getDuration());
        if (!length || !isFinite(time)) return;
        player.seekTo(Math.max(0, Math.min(length, time)), true);
    }

    function command(cmd, value) {
        if (!ready) return;
        switch (cmd) {
            case 'toggle':
                if (player.getPlayerState() === 1) player.pauseVideo(); else player.playVideo();
                break;
            case 'play': player.playVideo(); break;
            case 'pause': player.pauseVideo(); break;
            case 'seek': seekTo(seconds(player.getCurrentTime()) + (Number(value) || 0)); break;
            case 'showControls': break;
            default: return;
        }
        update();
        showControls();
    }

    toggle.addEventListener('click', function () { command('toggle'); });
    rewind.addEventListener('click', function () { command('seek', -10); });
    forward.addEventListener('click', function () { command('seek', 10); });
    progress.addEventListener('input', function () {
        if (!ready) return;
        seekTo(Number(progress.value) / 1000 * seconds(player.getDuration()));
        showControls();
    });
    function startScrubbing() { scrubbing = true; showControls(); }
    function endScrubbing() {
        if (!scrubbing) return;
        scrubbing = false;
        update();
        showControls();
    }
    progress.addEventListener('mousedown', startScrubbing);
    progress.addEventListener('touchstart', startScrubbing);
    window.addEventListener('mouseup', endScrubbing);
    window.addEventListener('touchend', endScrubbing);
    window.addEventListener('touchcancel', endScrubbing);
    controls.addEventListener('mousemove', showControls);
    controls.addEventListener('focusin', showControls);

    showControls();

    function post(event, value) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage(JSON.stringify({ kidstv: 'yt', event: event, value: value }), '*');
        }
    }

    window.onYouTubeIframeAPIReady = function () {
        player = new window.YT.Player('player', {
            host: 'https://www.youtube-nocookie.com',
            videoId: videoId,
            width: '100%',
            height: '100%',
            playerVars: {
                autoplay: 1,
                controls: mode === 'hidden' ? 0 : 1,
                rel: 0,
                fs: 0,
                playsinline: 1,
                iv_load_policy: 3,
                disablekb: 1,
                origin: el.getAttribute('data-origin')
            },
            events: {
                onReady: function () {
                    ready = true;
                    update();
                    if (mode !== 'hidden') progressTimer = setInterval(update, 500);
                    showControls();
                    post('ready');
                    player.playVideo();
                },
                onStateChange: function (e) {
                    update();
                    showControls();
                    post('state', e.data);
                },
                onError: function (e) { post('error', e.data); }
            }
        });
    };

    window.addEventListener('message', function (e) {
        if (e.source !== window.parent) return;
        var data = e.data;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (err) { return; }
        }
        if (!data || data.kidstv !== 'cmd') return;
        command(data.cmd, data.value);
    });

    window.addEventListener('pagehide', function () {
        ready = false;
        clearTimeout(hideTimer);
        clearInterval(progressTimer);
    });

    // Si la API de YouTube no carga (red, bloqueo), la app lo detecta por tiempo.
})();
