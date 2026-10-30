@extends('layouts.panel')

@section('title', __('Inicio'))

@section('content')
    <div class="page-head">
        <h1>{{ $family->name ?: __('Tu videoteca') }}</h1>
    </div>

    <section class="actions-grid" aria-label="{{ __('Acciones rápidas') }}">
        <a href="{{ youtube_add_url() }}" class="action-tile tile-youtube">
            @include('partials.icon', ['name' => 'youtube'])
            <span>{{ __('Añadir vídeo de YouTube') }}</span>
        </a>
        <a href="{{ route('contents.upload.create', 'video') }}" class="action-tile tile-video">
            @include('partials.icon', ['name' => 'video'])
            <span>{{ __('Subir vídeo') }}</span>
        </a>
        <a href="{{ route('contents.upload.create', 'audio') }}" class="action-tile tile-audio">
            @include('partials.icon', ['name' => 'audio'])
            <span>{{ __('Subir audio') }}</span>
        </a>
        <a href="{{ route('devices.pair') }}" class="action-tile tile-tv">
            @include('partials.icon', ['name' => 'tv'])
            <span>{{ __('Conectar televisión') }}</span>
        </a>
    </section>

    @if ($profiles->isEmpty())
        <section class="card card-highlight setup-card">
            <div>
                <h2>{{ __('Prepara la tele de tu peque') }}</h2>
                <p class="muted">{{ __('Su nombre, sus series favoritas y listo: en menos de 2 minutos.') }}</p>
            </div>
            <a href="{{ route('onboarding.profile') }}" class="btn btn-primary">{{ __('Empezar') }} @include('partials.icon', ['name' => 'right'])</a>
        </section>
    @elseif ($devices->isEmpty())
        <section class="card card-highlight setup-card">
            <div>
                <h2>@include('partials.icon', ['name' => 'tv']) {{ __('Conecta la tele de :names', ['names' => $profiles->pluck('name')->join(', ', ' y ')]) }}</h2>
                <p class="muted">{{ __('Ya está preparada: solo falta abrir NokoPlay en la tele y escribir aquí su código.') }}</p>
            </div>
            <div class="form-actions">
                <a href="{{ route('onboarding.connect') }}" class="btn btn-accent">{{ __('Conectar mi tele') }}</a>
                <form method="POST" action="{{ route('devices.browser') }}" target="_blank">
                    @csrf
                    <button class="btn btn-ghost">{{ __('Probar en este navegador') }}</button>
                </form>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="section-head">
            <h2>{{ __('Perfiles') }}</h2>
            <a href="{{ route('profiles.create') }}" class="btn btn-sm btn-ghost">@include('partials.icon', ['name' => 'plus']) {{ __('Nuevo') }}</a>
        </div>
        @if ($profiles->isEmpty())
            <p class="empty">{{ __('Aún no hay perfiles infantiles.') }}</p>
        @else
            <div class="profile-row">
                @foreach ($profiles as $profile)
                    <a href="{{ route('profiles.edit', $profile) }}" class="profile-pill">
                        @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 64])
                        <strong>{{ $profile->name }}</strong>
                        <span class="muted small">{{ $profile->ageLabel() ?? trans_choice(':count contenido|:count contenidos', $profile->contents_count) }}</span>
                    </a>
                @endforeach
            </div>
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
            <p class="empty">{{ __('Ningún televisor conectado todavía. Puedes probar la videoteca desde este navegador con «Probar modo TV».') }}</p>
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

    @unless ($family->hasPin())
        <section class="card card-warning">
            <h2>@include('partials.icon', ['name' => 'lock']) {{ __('Configura el PIN parental') }}</h2>
            <p>{{ __('Protege la «Zona de adultos» del televisor para que los peques no puedan salir del modo infantil.') }}</p>
            <a href="{{ route('account.pin') }}" class="btn btn-primary">{{ __('Crear PIN') }}</a>
        </section>
    @endunless
@endsection
