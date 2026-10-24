@extends('layouts.panel')

@section('title', __('Confirma que eres tú'))

@section('content')
    <div class="auth-card">
        <h1>{{ __('Confirma que eres tú') }}</h1>
        <p>{{ __('Para cambiar el PIN, el correo o borrar la cuenta necesitamos comprobar de nuevo que controlas tu correo.') }}</p>

        @if (! $sent)
            <form method="POST" action="{{ route('confirm.send') }}">
                @csrf
                <button class="btn btn-primary btn-block">{!! __('Enviar un código a <strong>:email</strong>', ['email' => e($email)]) !!}</button>
            </form>
            @error('code')<p class="error">{{ $message }}</p>@enderror
        @else
            <form method="POST" action="{{ route('confirm.verify') }}" class="stack">
                @csrf
                <div class="field">
                    <label for="code">{{ __('Código de 6 cifras') }}</label>
                    <input type="text" id="code" name="code" class="input-code" inputmode="numeric" autocomplete="one-time-code"
                           maxlength="7" required autofocus @error('code') aria-invalid="true" @enderror>
                    @error('code')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-primary btn-block">{{ __('Confirmar') }}</button>
            </form>
            <form method="POST" action="{{ route('confirm.send') }}" class="auth-secondary">
                @csrf
                <button class="btn btn-link">{{ __('Enviarme otro código') }}</button>
            </form>
        @endif
        <a href="{{ route('account.show') }}" class="btn btn-link">{{ __('Cancelar') }}</a>
    </div>
@endsection
