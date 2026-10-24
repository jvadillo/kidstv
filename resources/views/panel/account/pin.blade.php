@extends('layouts.panel')

@section('title', __('PIN parental'))

@section('content')
    <div class="page-head">
        <h1>{{ $hasPin ? __('Cambiar el PIN parental') : __('Crear el PIN parental') }}</h1>
    </div>

    <form method="POST" action="{{ route('account.pin.update') }}" class="stack card narrow">
        @csrf
        @method('PUT')
        <p class="muted">{{ __('El PIN abre la «Zona de adultos» del televisor. Elige uno que los peques no conozcan y evita 1234 o fechas de cumpleaños.') }}</p>
        <div class="field">
            <label for="pin">{{ __('Nuevo PIN (4 a 6 cifras)') }}</label>
            <input type="password" id="pin" name="pin" class="input-code" inputmode="numeric" pattern="[0-9]*" minlength="4" maxlength="6"
                   autocomplete="new-password" required autofocus @error('pin') aria-invalid="true" @enderror>
            @error('pin')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="pin_confirmation">{{ __('Repite el PIN') }}</label>
            <input type="password" id="pin_confirmation" name="pin_confirmation" class="input-code" inputmode="numeric" pattern="[0-9]*"
                   minlength="4" maxlength="6" autocomplete="new-password" required>
        </div>
        <p class="small muted">{{ __('En la tele, tras 3 intentos fallidos hay que esperar (30 s, 1 min, 2 min…). Nunca mostramos el PIN: si lo olvidas, crea uno nuevo aquí.') }}</p>
        <div class="form-actions">
            <button class="btn btn-primary">{{ __('Guardar PIN') }}</button>
            <a href="{{ route('account.show') }}" class="btn btn-ghost">{{ __('Cancelar') }}</a>
        </div>
    </form>
@endsection
