/*
 * Página puente del reproductor de YouTube para la app de TV (ES5).
 * Carga el reproductor oficial (IFrame API, modo de privacidad mejorada) y se comunica
 * con la app que la incrusta mediante postMessage. No intercambia datos sensibles.
 */
(function () {
    'use strict';

    var el = document.getElementById('player');
    var videoId = el.getAttribute('data-video-id');
    var player = null;

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
                controls: 1,
                rel: 0,
                fs: 0,
                playsinline: 1,
                iv_load_policy: 3,
                disablekb: 1,
                origin: el.getAttribute('data-origin')
            },
            events: {
                onReady: function () {
                    post('ready');
                    player.playVideo();
                },
                onStateChange: function (e) { post('state', e.data); },
                onError: function (e) { post('error', e.data); }
            }
        });
    };

    window.addEventListener('message', function (e) {
        var data = e.data;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch (err) { return; }
        }
        if (!data || data.kidstv !== 'cmd' || !player || !player.getPlayerState) return;

        switch (data.cmd) {
            case 'toggle':
                if (player.getPlayerState() === 1) player.pauseVideo(); else player.playVideo();
                break;
            case 'play': player.playVideo(); break;
            case 'pause': player.pauseVideo(); break;
            case 'seek':
                player.seekTo(Math.max(0, player.getCurrentTime() + (Number(data.value) || 0)), true);
                break;
        }
    });

    // Si la API de YouTube no carga (red, bloqueo), la app lo detecta por tiempo.
})();
