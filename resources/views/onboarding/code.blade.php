@extends('layouts.onboarding')

@section('title', __('Revisa tu correo'))
@section('step', '3')

@section('content')
    <div class="ob-step">
        @include('onboarding.summary')

        <div class="ob-intro">
            <h1>{{ __('Revisa tu correo') }}</h1>
            <p class="muted">{!! __('Hemos enviado un código de 6 cifras a <strong>:email</strong>. Escríbelo aquí o pulsa el botón del correo.', ['email' => e($email)]) !!}</p>
        </div>

        <form method="POST" action="{{ route('login.verify') }}" class="stack" data-code-form>
            @csrf
            <div class="field">
                <label for="code" class="sr-only">{{ __('Código de 6 cifras') }}</label>
                <input type="text" id="code" name="code" class="input-code ob-code" inputmode="numeric" autocomplete="one-time-code"
                       pattern="[0-9 ]*" maxlength="7" required autofocus placeholder="······" data-code-input
                       @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')<p class="error" id="code-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary btn-lg btn-block">{{ __('Guardar su tele') }}</button>
        </form>

        <p class="small muted center">{{ __('Caduca en :minutes minutos. Si no lo ves, mira en la carpeta de spam o promociones.', ['minutes' => config('nokoplay.login_token_ttl_minutes')]) }}</p>
        <div class="auth-secondary">
            <form method="POST" action="{{ route('login.resend') }}">
                @csrf
                <button class="btn btn-link">{{ __('Enviarme otro código') }}</button>
            </form>
            <a href="{{ route('onboarding.account') }}" class="btn btn-link">{{ __('Usar otro correo') }}</a>
        </div>
    </div>
@endsection
