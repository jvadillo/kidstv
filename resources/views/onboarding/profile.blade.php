@extends('layouts.onboarding')

@section('title', __('¿Para quién es la tele?'))
@section('step', '1')

@section('content')
    <form method="POST" action="{{ route('onboarding.profile.store') }}" class="ob-step" novalidate>
        @csrf
        <div class="ob-intro">
            <h1>{{ __('¿Para quién es la tele?') }}</h1>
            <p class="muted">{{ __('En menos de 2 minutos tendrás su tele preparada con sus series favoritas.') }}</p>
        </div>

        <div class="field">
            <label for="name">{{ __('Su nombre o apodo') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $draft['name'] ?? '') }}" maxlength="40" required
                   class="ob-input" placeholder="{{ __('Por ejemplo, Nora') }}" autocomplete="off" autocapitalize="words"
                   @unless (old('name', $draft['name'] ?? null)) autofocus @endunless
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p class="error" id="name-error">{{ $message }}</p>@enderror
        </div>

        <div class="ob-actions">
            <button class="btn btn-primary btn-lg btn-block">{{ __('Continuar') }} @include('partials.icon', ['name' => 'right'])</button>
            <p class="hint center">{{ __('Solo lo verá tu familia. No pedimos fotos, apellidos ni fecha de nacimiento.') }}</p>
            @unless(session('onboarding_pairing'))
                <a href="{{ route('onboarding.tv') }}" class="btn btn-link">{{ __('Tengo un código en la tele') }}</a>
            @endunless
        </div>
    </form>
@endsection
