@extends('layouts.guest')

@section('title', __('Entrar'))

@section('content')
    <div class="auth-card">
        <h1>{{ __('Entra o crea tu cuenta') }}</h1>
        <p class="muted">{{ __('Escribe tu correo y te enviaremos un código de 6 cifras y un enlace para entrar. No necesitas contraseña.') }}</p>

        <form method="POST" action="{{ route('login.send') }}" class="stack" novalidate>
            @csrf
            <input type="hidden" name="form_token" value="{{ $formToken }}">
            <div class="hp" aria-hidden="true">
                <label for="website">{{ __('No rellenes este campo') }}</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
                <label for="email">{{ __('Correo electrónico') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="email" inputmode="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <button class="btn btn-primary btn-block">{{ __('Enviarme el código') }}</button>
        </form>

        <p class="small muted">{{ __('Si es tu primera vez, crearemos tu cuenta después de verificar el correo y aceptar las condiciones.') }}</p>
        <p class="center"><a href="{{ route('onboarding.profile') }}" class="btn btn-link">{{ __('¿Primera vez? Prepara la tele de tu peque sin registrarte') }}</a></p>
    </div>
@endsection
