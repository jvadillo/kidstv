{{-- Primeros pasos: pantalla completa, sin la navegación del panel, para ir directo a la tele. --}}
@extends('layouts.base')

@section('body-class', 'onboarding')

@section('body')
    <header class="ob-header">
        <a href="{{ route('home') }}" class="brand">
            @include('partials.brand')
        </a>
        @hasSection('step')
            @include('onboarding.progress', ['step' => (int) $__env->yieldContent('step')])
        @endif
        @auth
            <a href="{{ route('panel') }}" class="ob-header-link">{{ __('Ir al panel') }}</a>
        @else
            <a href="{{ route('login') }}" class="ob-header-link">{{ __('Ya tengo cuenta') }}</a>
        @endauth
    </header>

    <main id="main" class="ob-main @yield('main-class')">
        @include('partials.flash')
        @yield('content')
    </main>
@endsection
