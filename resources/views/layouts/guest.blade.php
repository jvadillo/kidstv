@extends('layouts.base')

@section('body-class', 'guest')

@section('body')
    <header class="guest-header">
        <a href="{{ route('home') }}" class="brand">
            @include('partials.brand')
        </a>
    </header>
    <main class="guest-main">
        @include('partials.flash')
        @yield('content')
    </main>
    <footer class="site-footer">
        <a href="{{ route('privacy') }}">{{ __('Privacidad') }}</a>
        <a href="{{ route('terms') }}">{{ __('Condiciones') }}</a>
        <a href="{{ route('help.tv') }}">{{ __('Ayuda con la tele') }}</a>
    </footer>
@endsection
