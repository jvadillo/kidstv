@extends('layouts.onboarding')

@section('title', __('Conectar la tele'))
@section('step', '3')

@section('content')
    <div class="ob-step">
        <div class="ob-intro">
            <p class="ob-kicker">@include('partials.icon', ['name' => 'check']) {{ $profile ? __('Tele de :name guardada', ['name' => $profile->name]) : __('Cuenta creada') }}</p>
            <h1>{{ __('Conecta tu tele') }}</h1>
        </div>

        <ol class="ob-howto">
            <li>
                <strong>{{ __('Abre KidsTV en la tele') }}</strong>
                <span class="small muted">{!! __('Con la app para televisores LG o, en cualquier Smart TV, desde su navegador en <strong>:url</strong>.', ['url' => e(preg_replace('#^https?://#', '', url('/tv')))]) !!}
                    <a href="{{ route('help.tv') }}" target="_blank">{{ __('Ayuda') }}</a></span>
            </li>
            <li>
                <strong>{{ __('Escribe el código que aparece en la pantalla') }}</strong>
                <span class="small muted">{{ __('O escanea con el móvil el código QR de la tele.') }}</span>
            </li>
        </ol>

        <form method="POST" action="{{ route('devices.pair.store') }}" class="stack card">
            @csrf
            <input type="hidden" name="from_onboarding" value="1">
            <div class="field">
                <label for="code">{{ __('Código de la tele') }}</label>
                <input type="text" id="code" name="code" value="{{ old('code') }}" class="input-code input-code-alpha"
                       maxlength="8" required autocapitalize="characters" autocomplete="off" spellcheck="false" placeholder="ABC123"
                       @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                @error('code')<p class="error" id="code-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="name">{{ __('Nombre de esta tele') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', __('Tele del salón')) }}" maxlength="40" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary btn-lg btn-block">@include('partials.icon', ['name' => 'tv']) {{ __('Conectar') }}</button>
        </form>

        <div class="ob-or"><span>{{ __('o') }}</span></div>

        <form method="POST" action="{{ route('devices.browser') }}" target="_blank">
            @csrf
            <button class="btn btn-ghost btn-block">{{ __('Probarla ahora en este navegador') }}</button>
        </form>
        <p class="center"><a href="{{ route('panel') }}" class="btn btn-link">{{ __('Lo haré más tarde') }}</a></p>
    </div>
@endsection
