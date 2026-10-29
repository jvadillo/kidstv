@extends('layouts.onboarding')
@section('title', __('Conecta y guarda tu tele'))
@section('step', '3')
@section('content')
    <form method="POST" action="{{ route('onboarding.tv.attach') }}" class="ob-step">
        @csrf
        <div class="ob-intro">
            <h1>{{ __('Conecta y guarda tu tele') }}</h1>
            <p class="muted">{{ __('Comprueba que tu tele muestra el código :code.', ['code' => $code]) }}</p>
        </div>
        @if (!empty($draft['lists']))
            <label class="check"><input type="radio" name="choice" value="add" @checked(old('choice', 'add') === 'add')>
                <span>{{ __('Añadir a :name y sus series a mi cuenta', ['name' => $draft['name']]) }}</span></label>
        @endif
        @if ($family->profiles()->exists())
            <label class="check"><input type="radio" name="choice" value="existing" @checked(old('choice', empty($draft['lists']) ? 'existing' : 'add') === 'existing')>
                <span>{{ __('Usar los perfiles y las listas que ya tengo en mi cuenta') }}</span></label>
            <p class="hint">{{ __('Si eliges tus perfiles actuales, no se añadirá el perfil preparado en esta tele.') }}</p>
        @endif
        @error('choice')<p class="error">{{ $message }}</p>@enderror
        @if (!$family->hasPin())
            <p>{{ __('Crea un PIN para entrar en la Zona de adultos de la tele. El correo te permitirá recuperarlo.') }}</p>
            <div class="field"><label for="pin">{{ __('PIN de 4 a 6 cifras') }}</label><input id="pin" name="pin" type="password" inputmode="numeric" minlength="4" maxlength="6" autocomplete="new-password" required></div>
            <div class="field"><label for="pin_confirmation">{{ __('Repite el PIN') }}</label><input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" minlength="4" maxlength="6" autocomplete="new-password" required></div>
            @error('pin')<p class="error">{{ $message }}</p>@enderror
        @else
            <p>{{ __('En la tele usarás el PIN que ya tiene tu familia.') }}</p>
        @endif
        @error('code')<p class="error">{{ $message }}</p>@enderror
        <button class="btn btn-primary btn-lg">{{ __('Guardar y conectar tele') }}</button>
        <a href="{{ route('onboarding.profile') }}">{{ __('Preparar o cambiar el perfil') }}</a>
        <a href="{{ route('onboarding.tv') }}">{{ __('Introducir un nuevo código de la tele') }}</a>
    </form>
@endsection
