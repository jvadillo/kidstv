@extends('layouts.panel')

@section('title', __('Cambiar correo'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Cambiar correo') }}</h1>
    </div>

    <div class="stack narrow">
        <form method="POST" action="{{ route('account.email.send') }}" class="stack card">
            @csrf
            <p class="muted">{!! __('Ahora entras con <strong>:email</strong>. Te enviaremos un código a la nueva dirección para comprobar que es tuya.', ['email' => e($user->email)]) !!}</p>
            <div class="field">
                <label for="email">{{ __('Nuevo correo') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $pendingEmail) }}" required autocomplete="email"
                       @error('email') aria-invalid="true" @enderror>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn {{ $pendingEmail ? 'btn-ghost' : 'btn-primary' }}">{{ $pendingEmail ? __('Reenviar código') : __('Enviar código') }}</button>
        </form>

        @if ($pendingEmail)
            <form method="POST" action="{{ route('account.email.confirm') }}" class="stack card">
                @csrf
                <div class="field">
                    <label for="code">{{ __('Código enviado a :email', ['email' => $pendingEmail]) }}</label>
                    <input type="text" id="code" name="code" class="input-code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus
                           @error('code') aria-invalid="true" @enderror>
                    @error('code')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-primary">{{ __('Confirmar nuevo correo') }}</button>
            </form>
        @endif

        <a href="{{ route('account.show') }}" class="btn btn-link">{{ __('Volver') }}</a>
    </div>
@endsection
