@extends('layouts.panel')

@section('title', __('Administración'))

@php
    $fmt = fn (int $minutes) => \App\Services\UsageStats::format($minutes);
    $typeLabels = ['youtube' => 'YouTube', 'video' => __('Vídeos propios'), 'audio' => __('Audios')];
@endphp

@section('content')
    <div class="page-head">
        <h1>{{ __('Administración') }}</h1>
    </div>
    @include('admin.partials.nav')

    <section class="stat-grid" aria-label="{{ __('Cifras principales') }}">
        <div class="stat">
            <span class="stat-label">{{ __('Minutos vistos hoy') }}</span>
            <strong class="stat-value">{{ $fmt($minutes['today']) }}</strong>
            <span class="stat-sub">{{ __('7 días: :week · 30 días: :month', ['week' => $fmt($minutes['week']), 'month' => $fmt($minutes['month'])]) }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">{{ __('Cuentas') }}</span>
            <strong class="stat-value">{{ number_format($users['total'], 0, ',', '.') }}</strong>
            <span class="stat-sub">{{ __('+:new7 en 7 días · +:new30 en 30 días', $users) }}@if ($users['blocked']) · {{ trans_choice(':count bloqueada|:count bloqueadas', $users['blocked']) }}@endif</span>
        </div>
        <div class="stat">
            <span class="stat-label">{{ __('Familias activas') }}</span>
            <strong class="stat-value">{{ number_format($activeFamilies7, 0, ',', '.') }}</strong>
            <span class="stat-sub">{{ __('han visto algo en 7 días (:month en 30 días)', ['month' => $activeFamilies30]) }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">{{ __('Televisores') }}</span>
            <strong class="stat-value">{{ number_format($devices['total'], 0, ',', '.') }}</strong>
            <span class="stat-sub">{{ __(':active conectados en 7 días', ['active' => $devices['active7']]) }}</span>
        </div>
    </section>

    <section class="section card">
        @include('admin.partials.daily-chart', ['daily' => $daily, 'title' => __('Minutos vistos por día (últimos 30 días)')])
    </section>

    <div class="admin-columns">
        <section class="card">
            <h2>{{ __('Minutos por tipo (30 días)') }}</h2>
            @if ($byType->isEmpty())
                <p class="muted">{{ __('Sin datos todavía.') }}</p>
            @else
                <dl class="facts">
                    @foreach ($byType as $type => $value)
                        <dt>{{ $typeLabels[$type] ?? $type }}</dt><dd>{{ $fmt($value) }}</dd>
                    @endforeach
                </dl>
            @endif
        </section>

        <section class="card">
            <h2>{{ __('Biblioteca') }}</h2>
            <dl class="facts">
                @foreach ($typeLabels as $type => $label)
                    <dt>{{ $label }}</dt><dd>{{ number_format($contents[$type] ?? 0, 0, ',', '.') }}</dd>
                @endforeach
                <dt>{{ __('Espacio usado') }}</dt><dd>{{ format_bytes($storage) }}</dd>
                @foreach ($devices['byPlatform'] as $platform => $count)
                    <dt>{{ __('Teles :platform', ['platform' => (new \App\Models\Device(['platform' => $platform]))->platformLabel()]) }}</dt><dd>{{ $count }}</dd>
                @endforeach
            </dl>
        </section>
    </div>

    <section class="section">
        <div class="section-head">
            <h2>{{ __('Listas públicas') }}</h2>
            <a href="{{ route('admin.lists.index') }}" class="btn btn-sm btn-ghost">{{ __('Gestionar') }}</a>
        </div>
        @if ($publicLists->isEmpty())
            <p class="empty">{{ __('Aún no hay listas públicas. Crea una para que las familias puedan añadirla a su videoteca.') }}</p>
        @else
            <p class="muted small">{{ trans_choice(':count lista pública|:count listas públicas', $publicLists->count()) }} · {{ trans_choice('añadida :count vez|añadidas :count veces', $publicLists->sum('links_count')) }}</p>
            @if ($topPublic->isNotEmpty())
                <div class="table-wrap">
                    <table class="table">
                        <caption class="sr-only">{{ __('Vídeos de listas públicas más vistos (30 días)') }}</caption>
                        <thead>
                            <tr><th scope="col">{{ __('Vídeo más visto (30 días)') }}</th><th scope="col" class="num">{{ __('Tiempo') }}</th><th scope="col" class="num">{{ __('Reproducciones') }}</th><th scope="col" class="num">{{ __('Familias') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($topPublic as $row)
                                <tr>
                                    <td>{{ $row->title }}</td>
                                    <td class="num">{{ $fmt(intdiv((int) $row->seconds, 60)) }}</td>
                                    <td class="num">{{ $row->plays }}</td>
                                    <td class="num">{{ $row->families }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </section>

    <p class="hint">{{ __('El tiempo visto lo informan los televisores mientras reproducen; las teles con una versión antigua de la app no lo envían hasta que se actualizan.') }}</p>
@endsection
