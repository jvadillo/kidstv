<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#2b2560">
    @stack('head')
    <title>@yield('title', __('Videoteca familiar')) · NokoPlay</title>
    <meta name="application-name" content="NokoPlay">
    <link rel="icon" href="{{ asset('tv/img/icon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('tv/img/apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('styles')
    <script src="{{ asset('js/panel.js') }}?v={{ filemtime(public_path('js/panel.js')) }}" defer></script>
</head>
<body class="@yield('body-class')">
    @yield('body')
</body>
</html>
