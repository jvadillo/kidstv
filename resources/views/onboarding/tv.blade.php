@extends('layouts.onboarding')
@section('title', __('Prepara tu tele'))
@section('content')
    <form method="POST" action="{{ route('onboarding.tv.begin') }}" class="ob-step">
        @csrf
        <div class="ob-intro">
            <h1>{{ __('Prepara tu tele') }}</h1>
            <p class="muted">{{ __('Confirma el código que aparece en tu tele. Al terminar, se conectará automáticamente.') }}</p>
        </div>
        <div class="field">
            <label for="code">{{ __('Código de la tele') }}</label>
            <input id="code" name="code" value="{{ old('code', $code) }}" maxlength="12" autocomplete="off" autocapitalize="characters" required class="ob-input">
            @error('code')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary btn-lg" name="mode" value="prepare">{{ __('Preparar o guardar esta tele') }}</button>
        <button class="btn btn-ghost" name="mode" value="existing">{{ __('Ya tengo cuenta') }}</button>
    </form>
@endsection
