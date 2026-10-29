@extends('layouts.onboarding')

@section('title', __('¿Para quién es la tele?'))
@section('step', '1')

@php
    $stages = [
        '0-3' => __('Bebés y primera infancia'),
        '3-6' => __('Preescolar'),
        '7-12' => __('Primaria y más'),
    ];
    $age = old('age', $draft['age'] ?? null);
@endphp

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

        <fieldset class="field">
            <legend>{{ __('¿Cuántos años tiene?') }}</legend>
            <div class="age-options">
                @foreach ($ages as $key => $range)
                    <label class="age-option age-option-{{ $loop->iteration }}">
                        <input type="radio" name="age" value="{{ $key }}" required @checked($age === $key)>
                        <span class="age-option-years" aria-hidden="true">{{ $range[1] >= \App\Models\VideoList::AGE_MAX ? $range[0].'+' : $range[0].'–'.$range[1] }}</span>
                        <span class="age-option-text">
                            <strong>{{ \App\Models\VideoList::ageRangeLabel(...$range) }}</strong>
                            <small>{{ $stages[$key] ?? '' }}</small>
                        </span>
                        <span class="age-option-check">@include('partials.icon', ['name' => 'check'])</span>
                    </label>
                @endforeach
            </div>
            @error('age')<p class="error">{{ $message }}</p>@enderror
        </fieldset>

        <div class="ob-actions">
            <button class="btn btn-primary btn-lg btn-block">{{ __('Continuar') }} @include('partials.icon', ['name' => 'right'])</button>
            <p class="hint center">{{ __('Solo lo verá tu familia. No pedimos fotos, apellidos ni fecha de nacimiento.') }}</p>
        </div>
    </form>
@endsection
