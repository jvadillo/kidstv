@extends('layouts.onboarding')

@section('title', __('¿Cuántos años tiene?'))
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
    <form method="POST" action="{{ route('onboarding.age.store') }}" class="ob-step" novalidate>
        @csrf
        <div class="ob-intro">
            <h1>{{ __('¿Cuántos años tiene?') }}</h1>
            <p class="muted">{{ __('Así podremos proponerte series adecuadas para su edad.') }}</p>
        </div>

        <fieldset class="field">
            <legend class="sr-only">{{ __('¿Cuántos años tiene?') }}</legend>
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
            <a href="{{ route('onboarding.profile') }}" class="btn btn-link">@include('partials.icon', ['name' => 'left']) {{ __('Volver') }}</a>
        </div>
    </form>
@endsection
