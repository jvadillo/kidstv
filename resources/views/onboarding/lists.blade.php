@extends('layouts.onboarding')

@section('title', __('¿Qué le gusta ver?'))
@section('step', '2')
@section('main-class', 'ob-main-wide')

@php
    $ranges = \App\Models\VideoList::AGE_RANGES;
    $url = fn (array $changes) => route('onboarding.lists', array_merge($filters, $changes));
    // Etapas de edad e idiomas de cada lista, para filtrar en el navegador sin recargar.
    $agesOf = fn ($list) => collect($ranges)->filter(fn ($range) => $list->suitsAges(...$range))->keys();
    $matches = fn ($list) => (! $filters['edad'] || $agesOf($list)->contains($filters['edad']))
        && (! $filters['idioma'] || in_array($filters['idioma'], $list->languageCodes(), true));
    // En las tarjetas el idioma va aparte: «Bluey (Español)» se queda en «Bluey».
    $title = fn ($list) => preg_replace('/\s*\([^)]*\)$/u', '', $list->name) ?: $list->name;
    $hours = function (int $seconds) {
        $minutes = intdiv($seconds, 60);

        return $minutes >= 60 ? __(':count h', ['count' => round($minutes / 60)]) : __(':count min', ['count' => max(1, $minutes)]);
    };
@endphp

@section('content')
    <form method="POST" action="{{ route('onboarding.lists.store') }}" class="ob-catalog" data-ob-catalog>
        @csrf
        <div class="ob-intro">
            <h1>{{ __('¿Qué le gusta ver?') }}</h1>
            <p class="muted">{{ __('Toca sus series favoritas para seleccionarlas. Podrá ver estas listas oficiales en la TV.') }}</p>
        </div>

        <div class="ob-filters">
            <nav class="filter-chips" aria-label="{{ __('Edad') }}">
                <a href="{{ $url(['edad' => '']) }}" class="filter-chip" data-ob-filter="edad" data-value="" @if (! $filters['edad']) aria-current="true" @endif>
                    <span>{{ __('Todas las edades') }}</span>
                </a>
                @foreach ($ranges as $key => $range)
                    <a href="{{ $url(['edad' => $key]) }}" class="filter-chip" data-ob-filter="edad" data-value="{{ $key }}" @if ($filters['edad'] === $key) aria-current="true" @endif>
                        <span>{{ \App\Models\VideoList::ageRangeLabel(...$range) }}</span>
                    </a>
                @endforeach
            </nav>
            @if ($languages->count() > 1)
                <nav class="filter-chips" aria-label="{{ __('Idioma') }}">
                    <a href="{{ $url(['idioma' => '']) }}" class="filter-chip" data-ob-filter="idioma" data-value="" @if (! $filters['idioma']) aria-current="true" @endif>
                        <span>{{ __('Todos los idiomas') }}</span>
                    </a>
                    @foreach ($languages as $code => $name)
                        <a href="{{ $url(['idioma' => $code]) }}" class="filter-chip" data-ob-filter="idioma" data-value="{{ $code }}" @if ($filters['idioma'] === $code) aria-current="true" @endif>
                            <span>{{ $name }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>

        @error('lists')<p class="error" role="alert">{{ $message }}</p>@enderror

        <ul class="ob-grid">
            @foreach ($lists as $list)
                @php $cover = $list->contents->first(); @endphp
                <li class="ob-card" data-ages="{{ $agesOf($list)->join(' ') }}" data-langs="{{ implode(' ', $list->languageCodes()) }}" @unless ($matches($list)) hidden @endunless>
                    <label>
                        <input type="checkbox" name="lists[]" value="{{ $list->id }}" class="sr-only" @checked(in_array($list->id, old('lists', $selected)))>
                        <span class="ob-card-art">
                            @if ($cover?->youtubeThumbnail())
                                <img src="{{ $cover->youtubeThumbnail() }}" alt="" loading="lazy" data-ob-thumb>
                            @endif
                            <span class="ob-card-lang">{{ strtoupper(implode(' · ', $list->languageCodes())) }}</span>
                            <span class="ob-card-check">@include('partials.icon', ['name' => 'check'])</span>
                        </span>
                        <span class="ob-card-body">
                            <strong>{{ $title($list) }}</strong>
                            <small>
                                {{ trans_choice(':count vídeo|:count vídeos', $list->contents_count) }} · {{ $hours((int) $list->contents_sum_duration) }}
                                @if ($list->ageLabel()) · {{ $list->ageLabel() }} @endif
                            </small>
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>

        <div class="empty-state" data-ob-empty @if ($lists->contains($matches)) hidden @endif>
            <p>{{ __('No hay series con estos filtros.') }}</p>
            <a href="{{ $url(['edad' => '', 'idioma' => '']) }}" class="btn btn-ghost" data-ob-clear>{{ __('Ver todas') }}</a>
        </div>

        <div class="ob-bar">
            <div class="ob-bar-inner">
                <div class="ob-bar-picked" aria-live="polite">
                    <span class="ob-stack" data-ob-stack aria-hidden="true"></span>
                    <span data-ob-count data-none="{{ __('Elige al menos una serie') }}" data-one="{{ __('1 serie elegida') }}" data-many="{{ __(':count series elegidas') }}">
                        {{ __('Elige al menos una serie') }}
                    </span>
                </div>
                <button class="btn btn-primary btn-lg" data-ob-next>{{ __('Finalizar') }} @include('partials.icon', ['name' => 'right'])</button>
            </div>
            <p class="hint">{{ __('Más adelante podrás crear tus listas y subir videos') }}</p>
        </div>
    </form>
@endsection
