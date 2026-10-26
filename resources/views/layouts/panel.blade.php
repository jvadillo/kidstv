@extends('layouts.base')

@section('body-class', 'panel')

@php
    $nav = [
        ['panel', 'panel', __('Inicio'), 'home'],
        ['contents.index', 'contents.*', __('Biblioteca'), 'library'],
        ['profiles.index', 'profiles.*', __('Perfiles'), 'kids'],
        ['devices.index', 'devices.*', __('Teles'), 'tv'],
        ['account.show', 'account.*', __('Cuenta'), 'user'],
    ];
    if (auth()->user()?->isAdmin()) {
        $nav[] = ['admin.dashboard', 'admin.*', __('Admin'), 'shield'];
    }
@endphp

@section('body')
    <a class="skip-link" href="#main">{{ __('Saltar al contenido') }}</a>
    <header class="topbar">
        <a href="{{ route('panel') }}" class="brand">
            <img src="{{ asset('img/icon.svg') }}" alt="" width="34" height="34">
            <span>KidsTV</span>
        </a>
        <nav class="topnav" aria-label="{{ __('Secciones') }}">
            @foreach ($nav as [$route, $pattern, $label])
                <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('devices.browser') }}" class="topbar-try" target="_blank">
            @csrf
            <button class="btn btn-sm btn-accent" title="{{ __('Abre la videoteca infantil en este navegador, como si fuera una tele') }}">{{ __('Probar modo TV') }}</button>
        </form>
    </header>

    <main id="main" class="container">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="site-footer">
        <a href="{{ route('privacy') }}">{{ __('Privacidad') }}</a>
        <a href="{{ route('terms') }}">{{ __('Condiciones') }}</a>
        <a href="{{ route('help.tv') }}">{{ __('Ayuda con la tele') }}</a>
    </footer>

    <nav class="tabbar" aria-label="{{ __('Secciones') }}" style="--tabs: {{ count($nav) }}">
        @foreach ($nav as [$route, $pattern, $label, $icon])
            <a href="{{ route($route) }}" @class(['active' => request()->routeIs($pattern)])>
                @include('partials.icon', ['name' => $icon])
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
@endsection
