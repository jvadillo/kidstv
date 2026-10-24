<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="robots" content="noindex, nofollow">
    <title>YouTube · KidsTV</title>
    <link rel="stylesheet" href="{{ asset('tv-bridge/youtube.css') }}">
</head>
<body>
    <div id="player" data-video-id="{{ $videoId }}" data-origin="{{ url('/') }}"></div>
    <script src="{{ asset('tv-bridge/youtube.js') }}?v={{ filemtime(public_path('tv-bridge/youtube.js')) }}"></script>
    <script src="https://www.youtube.com/iframe_api"></script>
</body>
</html>
