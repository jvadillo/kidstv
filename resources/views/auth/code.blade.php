@extends('layouts.guest')

@section('title', __('Revisa tu correo'))

@section('content')
    <div class="auth-card">
        <h1>{{ __('Revisa tu correo') }}</h1>
        <p>{!! __('Hemos enviado un código a <strong>:email</strong>. Escríbelo aquí o pulsa el botón del correo.', ['email' => e($email)]) !!}</p>
        <p class="small muted">{{ __('Caduca en :minutes minutos. Si no lo ves, mira en la carpeta de spam o promociones.', ['minutes' => config('kidstv.login_token_ttl_minutes')]) }}</p>

        <form method="POST" action="{{ route('login.verify') }}" class="stack">
            @csrf
            <div class="field">
                <label for="code">{{ __('Código de 6 cifras') }}</label>
                <input type="text" id="code" name="code" class="input-code" inputmode="numeric" autocomplete="one-time-code"
                       pattern="[0-9 ]*" maxlength="7" required autofocus @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')<p class="error" id="code-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary btn-block">{{ __('Entrar') }}</button>
        </form>

        <div class="auth-secondary">
            <form method="POST" action="{{ route('login.resend') }}">
                @csrf
                <button class="btn btn-link">{{ __('Enviarme otro código') }}</button>
            </form>
            <a href="{{ route('login') }}" class="btn btn-link">{{ __('Usar otro correo') }}</a>
        </div>
    </div>
@endsection
