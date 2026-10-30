<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="robots" content="noindex, nofollow">
    <title>YouTube · NokoPlay</title>
    <link rel="stylesheet" href="{{ asset('tv-bridge/youtube.css') }}?v={{ filemtime(public_path('tv-bridge/youtube.css')) }}">
</head>
<body>
    <div id="video-stage">
        <div id="player" data-video-id="{{ $videoId }}" data-origin="{{ url('/') }}" data-controls="{{ $controls }}"></div>
    </div>
    <div id="controls" role="group" aria-label="{{ __('Controles de reproducción') }}" hidden>
        <button id="rewind" type="button" aria-label="{{ __('Retroceder 10 segundos') }}" disabled>−10 s</button>
        <button id="toggle" type="button" data-play="{{ __('Reproducir') }}" data-pause="{{ __('Pausar') }}" disabled>{{ __('Reproducir') }}</button>
        <button id="forward" type="button" aria-label="{{ __('Avanzar 10 segundos') }}" disabled>+10 s</button>
        <span id="current">0:00</span>
        <input id="progress" type="range" min="0" max="1000" value="0" step="1" aria-label="{{ __('Posición del vídeo') }}" disabled>
        <span id="duration">--:--</span>
    </div>
    <script src="{{ asset('tv-bridge/youtube.js') }}?v={{ filemtime(public_path('tv-bridge/youtube.js')) }}"></script>
    <script src="https://www.youtube.com/iframe_api"></script>
</body>
</html>
