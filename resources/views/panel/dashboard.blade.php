@extends('layouts.panel')

@section('title', __('Inicio'))

@section('content')
    <div class="page-head">
        <h1>{{ auth()->user()->name ? __('Hola, :name', ['name' => auth()->user()->name]) : __('Hola') }}</h1>
    </div>
    <p class="dashboard-intro muted">{{ $profiles->isNotEmpty() ? __('Gestiona lo que ven :names en la tele.', ['names' => $profiles->pluck('name')->unique()->join(', ', ' y ')]) : __('Prepara su videoteca y elige lo que ven en la tele.') }}</p>

    {{-- Aviso para instalar la app: lo muestra panel.js solo si se puede y no se ha descartado hace poco. --}}
    <section class="install-banner" data-pwa-install data-pwa-dismissable hidden aria-labelledby="install-title">
        <img class="install-banner-icon" src="{{ asset('img/pwa/icon-192.png') }}" alt="" width="44" height="44">
        <div class="grow">
            <strong id="install-title">{{ __('Instala NokoPlay en este dispositivo') }}</strong>
            <span class="muted small" data-pwa-hint="button" hidden>{{ __('Ábrelo con un toque desde la pantalla de inicio.') }}</span>
            <span class="muted small" data-pwa-hint="ios" hidden>{{ __('Pulsa Compartir y elige «Añadir a pantalla de inicio».') }}</span>
            <span class="muted small" data-pwa-hint="menu" hidden>{{ __('En el menú del navegador, elige «Instalar app» o «Añadir a pantalla de inicio».') }}</span>
            <button type="button" class="btn btn-sm btn-primary" data-pwa-install-button hidden>{{ __('Instalar') }}</button>
        </div>
        <button type="button" class="install-banner-close" data-pwa-dismiss aria-label="{{ __('Ahora no') }}" title="{{ __('Ahora no') }}">@include('partials.icon', ['name' => 'close'])</button>
    </section>

    {{-- Primeros pasos: se deduce de los datos y desaparece solo al completarlo. El siguiente paso es el único destacado. --}}
    @if ($nextStep)
        @php $doneCount = collect($steps)->where('done', true)->count(); @endphp
        <section class="getting-started" aria-labelledby="gs-title">
            <div class="gs-head">
                <h2 id="gs-title">{{ $doneCount === 0 ? __('Vamos a preparar la tele') : __('Casi lista') }}</h2>
                <span class="gs-count">{{ __(':done de :total', ['done' => $doneCount, 'total' => count($steps)]) }}</span>
            </div>
            <div class="meter" role="meter" aria-valuemin="0" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $doneCount }}" aria-label="{{ __('Pasos completados') }}">
                <span style="width: {{ round($doneCount / count($steps) * 100) }}%"></span>
            </div>
            <ol class="gs-steps">
                @foreach ($steps as $i => $step)
                    @php $isNext = $step['key'] === $nextStep['key']; @endphp
                    <li @class(['gs-step', 'is-done' => $step['done'], 'is-next' => $isNext])>
                        @if ($isNext)
                            <span class="gs-mark" aria-hidden="true">{{ $i + 1 }}</span>
                            <div class="gs-body">
                                <strong>{{ $step['title'] }}</strong>
                                <p class="muted">{{ $step['hint'] }}</p>
                                <div class="gs-actions">
                                    <a href="{{ $step['href'] }}" class="btn btn-primary">{{ $step['cta'] }} @include('partials.icon', ['name' => 'right'])</a>
                                    @if ($step['key'] === 'tv')
                                        <form method="POST" action="{{ route('devices.browser') }}" target="_blank" data-tv-launch>
                                            @csrf
                                            <button class="btn btn-ghost">{{ __('Probar en este navegador') }}<span class="sr-only"> {{ __('(se abre en otra pestaña)') }}</span></button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @elseif ($step['done'])
                            <span class="gs-mark" aria-hidden="true">@include('partials.icon', ['name' => 'check'])</span>
                            <div class="gs-body"><span>{{ $step['title'] }}</span><span class="sr-only"> — {{ __('hecho') }}</span></div>
                        @else
                            <a href="{{ $step['href'] }}" class="gs-link">
                                <span class="gs-mark" aria-hidden="true">{{ $i + 1 }}</span>
                                <span class="grow">{{ $step['title'] }}</span>
                                @include('partials.icon', ['name' => 'right'])
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <h2 class="quick-actions-title">{{ __('Acciones rápidas') }}</h2>
    <section class="actions-grid dashboard-actions" aria-label="{{ __('Acciones rápidas') }}">
        <a href="{{ route('lists.public') }}" class="action-tile tile-series">
            @include('partials.icon', ['name' => 'library'])
            <span>{{ __('Series') }}</span>
        </a>
        <a href="{{ youtube_add_url() }}" class="action-tile tile-youtube">
            @include('partials.icon', ['name' => 'youtube'])
            <span>{{ __('YouTube') }}</span>
        </a>
        <a href="{{ route('contents.upload.create', 'video') }}" class="action-tile tile-video">
            @include('partials.icon', ['name' => 'video'])
            <span>{{ __('Subir vídeo') }}</span>
        </a>
        <a href="{{ route('contents.upload.create', 'audio') }}" class="action-tile tile-audio">
            @include('partials.icon', ['name' => 'audio'])
            <span>{{ __('Subir audio') }}</span>
        </a>
        <a href="{{ route('activity') }}" class="action-tile tile-activity">
            @include('partials.icon', ['name' => 'chart'])
            <span>{{ __('Actividad') }}</span>
        </a>
        <a href="{{ route('account.show') }}#limite" class="action-tile tile-limit">
            @include('partials.icon', ['name' => 'clock'])
            <span>{{ __('Tiempo de tele') }}</span>
        </a>
    </section>

    <section class="section">
        <div class="section-head">
            <h2>{{ __('Perfiles') }}</h2>
            <a href="{{ route('profiles.create') }}" class="btn btn-sm btn-text">@include('partials.icon', ['name' => 'plus']) {{ __('Nuevo') }}</a>
        </div>
        @if ($profiles->isEmpty())
            <p class="empty">{{ __('Aún no hay perfiles infantiles.') }}</p>
        @else
            <ul class="card-list dashboard-profiles">
                @foreach ($profiles as $profile)
                    @php $assignedCount = $profile->assignedContentCount(); @endphp
                    <li>
                        <a href="{{ route('profiles.edit', $profile) }}" class="card card-link compact-profile">
                            @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 48])
                            <span class="grow">
                                <strong>{{ $profile->name }}</strong>
                                @if ($profile->ageLabel())<span class="muted"> · {{ $profile->ageLabel() }}</span>@endif
                                <span class="muted block">{{ $assignedCount ? trans_choice(':count contenido|:count contenidos', $assignedCount) : __('Sin contenidos todavía') }}</span>
                            </span>
                            @if ($assignedCount)
                                @include('partials.icon', ['name' => 'right'])
                            @else
                                <span class="assign-label">{{ __('Asignar') }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="section">
        <div class="section-head">
            <h2>{{ __('Últimos añadidos') }}</h2>
            <a href="{{ route('contents.index') }}" class="btn btn-sm btn-ghost">{{ __('Ver biblioteca') }}</a>
        </div>
        @if ($recent->isEmpty() && $linkedCount > 0)
            <p class="empty">{!! trans_choice('Aún no has añadido vídeos propios. La serie que has elegido está en la <a href=":url">Biblioteca</a>.|Aún no has añadido vídeos propios. Las :count series que has elegido están en la <a href=":url">Biblioteca</a>.', $linkedCount, ['url' => e(route('contents.index'))]) !!}</p>
        @elseif ($recent->isEmpty())
            <p class="empty">{{ __('Tu biblioteca está vacía. Usa los botones de arriba para añadir el primer contenido.') }}</p>
        @else
            <ul class="content-list">
                @foreach ($recent as $content)
                    <li>
                        <a href="{{ route('contents.edit', $content) }}" class="content-row">
                            @include('partials.content-thumb')
                            <div class="content-meta">
                                <strong>{{ $content->title }}</strong>
                                <span class="muted small">{{ $content->list?->name ?? __('Sin lista') }}</span>
                            </div>
                            @include('partials.status-badge')
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="section">
        <div class="section-head">
            <h2>{{ __('Televisores') }}</h2>
            <a href="{{ route('devices.index') }}" class="btn btn-sm btn-ghost">{{ __('Gestionar') }}</a>
        </div>
        @if ($devices->isEmpty())
            <p class="empty">{{ __('Ningún televisor conectado todavía. Puedes probar la videoteca desde este navegador con «Modo TV».') }}</p>
        @else
            <ul class="device-list">
                @foreach ($devices as $device)
                    <li>
                        <span @class(['dot', 'dot-on' => $device->isOnline()]) aria-hidden="true"></span>
                        <strong>{{ $device->name }}</strong>
                        <span class="muted small">
                            {{ $device->isOnline() ? __('Conectado ahora') : ($device->last_seen_at ? __('Visto :time', ['time' => $device->last_seen_at->diffForHumans()]) : __('Aún no se ha conectado')) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="section">
        <h2>{{ __('Espacio') }}</h2>
        @php $pct = $storageQuota > 0 ? min(100, round($storageUsed / $storageQuota * 100)) : 0; @endphp
        <div class="meter" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}" aria-label="{{ __('Espacio usado') }}">
            <span style="width: {{ $pct }}%"></span>
        </div>
        <p class="small muted">{{ __(':used de :quota usados por tus vídeos y audios.', ['used' => format_bytes($storageUsed), 'quota' => format_bytes($storageQuota)]) }}</p>
    </section>
@endsection
