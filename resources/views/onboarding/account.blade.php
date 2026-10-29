@extends('layouts.onboarding')

@section('title', __('Guarda su tele'))
@section('step', '3')

@section('content')
    <div class="ob-step">
        @include('onboarding.summary')

        <div class="ob-intro">
            <h1>{{ ($draft['intent'] ?? null) === 'connect' ? __('Último paso antes de conectarla') : __('Guarda la tele de :name', ['name' => $draft['name']]) }}</h1>
            <p class="muted">{{ __('Escribe tu correo: te enviaremos un código de 6 cifras para guardar su tele y conectarla desde cualquier televisor. No necesitas contraseña.') }}</p>
        </div>

        <form method="POST" action="{{ route('login.send') }}" class="stack" novalidate>
            @csrf
            <input type="hidden" name="onboarding" value="1">
            <input type="hidden" name="form_token" value="{{ $formToken }}">
            <div class="hp" aria-hidden="true">
                <label for="website">{{ __('No rellenes este campo') }}</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
                <label for="email">{{ __('Tu correo electrónico') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="ob-input"
                       autocomplete="email" inputmode="email" placeholder="{{ __('nombre@correo.com') }}"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <label class="check small">
                <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                <span>{!! __('Acepto las <a href=":terms" target="_blank">condiciones de uso</a> y la <a href=":privacy" target="_blank">política de privacidad</a>, y soy una persona adulta responsable de los menores que usarán la videoteca.', ['terms' => route('terms'), 'privacy' => route('privacy')]) !!}</span>
            </label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror

            <button class="btn btn-primary btn-lg btn-block">{{ __('Enviarme el código') }}</button>
        </form>

        <p class="hint center">{{ __('¿Ya tienes cuenta? Escribe el mismo correo y añadiremos a :name a tu videoteca.', ['name' => $draft['name']]) }}</p>
        <p class="center"><a href="{{ route('onboarding.preview') }}" class="btn btn-link">@include('partials.icon', ['name' => 'left']) {{ __('Volver a su tele') }}</a></p>
    </div>
@endsection
