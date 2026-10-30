@extends('layouts.panel')

@section('title', __('Conectar televisión'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Conectar televisión') }}</h1>
    </div>

    <div class="pair-layout">
        <ol class="howto card">
            <li>{{ __('Abre la app NokoPlay en la tele (o la dirección /tv en su navegador).') }}</li>
            <li>{{ __('Aparecerá un código de 6 letras y números, y un código QR.') }}</li>
            <li>{{ __('Escanea el QR con este móvil o escribe aquí el código.') }}</li>
        </ol>

        <form method="POST" action="{{ route('devices.pair.store') }}" class="stack card">
            @csrf
            <div class="field">
                <label for="code">{{ __('Código que aparece en la tele') }}</label>
                <input type="text" id="code" name="code" value="{{ old('code', $code) }}" class="input-code input-code-alpha"
                       maxlength="8" required autocapitalize="characters" autocomplete="off" spellcheck="false"
                       @if (! $code) autofocus @endif @error('code') aria-invalid="true" @enderror>
                @error('code')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="name">{{ __('Nombre para este televisor') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', __('Tele del salón')) }}" maxlength="40" required
                       @if ($code) autofocus @endif>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary btn-block btn-lg">{{ __('Vincular televisor') }}</button>
            <p class="small muted">{{ __('El televisor solo podrá ver perfiles y reproducir contenidos de tu familia; nunca administrar la cuenta. Puedes desvincularlo cuando quieras.') }}</p>
        </form>
    </div>
@endsection
