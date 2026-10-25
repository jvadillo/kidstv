@extends('layouts.panel')

@section('title', __('Bienvenida'))

@section('content')
    <div class="wizard">
        <ol class="wizard-steps" aria-label="{{ __('Progreso') }}">
            <li @class(['current' => $step === 1, 'done' => $step > 1])>{{ __('Perfil') }}</li>
            <li @class(['current' => $step === 2, 'done' => $step > 2])>{{ __('Contenido') }}</li>
            <li @class(['current' => $step === 3])>{{ __('¡A ver!') }}</li>
        </ol>

        @if ($step === 1)
            <h1>{{ __('¿Para quién es la videoteca?') }}</h1>
            <p class="muted">{{ __('Crea un perfil para cada peque. Solo necesitamos un apodo y un dibujo; nada de nombres completos ni fotos.') }}</p>

            <form method="POST" action="{{ route('profiles.store') }}" class="stack card">
                @csrf
                <input type="hidden" name="from_welcome" value="1">
                @include('panel.profiles.fields', ['profile' => new \App\Models\Profile(['avatar' => 'estrella'])])
                <button class="btn btn-primary btn-block btn-lg">{{ __('Crear perfil') }}</button>
            </form>
        @elseif ($step === 2)
            <h1>{{ __('Añade el primer contenido') }}</h1>
            <p class="muted">{{ __('Perfiles creados: :names. ¿Qué quieres añadir?', ['names' => $profiles->pluck('name')->implode(', ')]) }}</p>

            <div class="actions-grid actions-grid-3">
                <a href="{{ youtube_add_url(['from_welcome' => 1]) }}" class="action-tile tile-youtube">
                    @include('partials.icon', ['name' => 'youtube'])
                    <span>{{ __('Vídeo de YouTube') }}</span>
                    <small>{{ \App\Services\YouTubeSearch::enabled() ? __('Busca o pega un enlace') : __('Pega un enlace') }}</small>
                </a>
                <a href="{{ route('contents.upload.create', ['kind' => 'video', 'from_welcome' => 1]) }}" class="action-tile tile-video">
                    @include('partials.icon', ['name' => 'video'])
                    <span>{{ __('Subir vídeo') }}</span>
                    <small>MP4</small>
                </a>
                <a href="{{ route('contents.upload.create', ['kind' => 'audio', 'from_welcome' => 1]) }}" class="action-tile tile-audio">
                    @include('partials.icon', ['name' => 'audio'])
                    <span>{{ __('Subir audio') }}</span>
                    <small>MP3</small>
                </a>
            </div>

            <p class="center"><a href="{{ route('welcome', ['paso' => 3]) }}" class="btn btn-link">{{ __('Saltar por ahora') }}</a></p>
            <p class="small muted center">{{ __('Podrás añadir contenidos cuando quieras desde «Biblioteca» o con los botones del inicio.') }}</p>
        @else
            <h1>{{ __('¡Todo listo para probar!') }}</h1>
            <p class="muted">{{ __('No hace falta tele: abre el modo TV en este navegador y muévete con las flechas del teclado, Intro y Esc (o con el ratón).') }}</p>

            <div class="stack">
                <form method="POST" action="{{ route('devices.browser') }}" target="_blank">
                    @csrf
                    <button class="btn btn-accent btn-block btn-lg">@include('partials.icon', ['name' => 'tv']) {{ __('Probar modo TV en este navegador') }}</button>
                </form>
                <a href="{{ route('devices.pair') }}" class="btn btn-primary btn-block">{{ __('Conectar un televisor LG') }}</a>
                @unless ($hasPin)
                    <a href="{{ route('account.pin') }}" class="btn btn-ghost btn-block">@include('partials.icon', ['name' => 'lock']) {{ __('Crear el PIN parental') }}</a>
                @endunless
                <a href="{{ route('panel') }}" class="btn btn-link">{{ __('Ir al panel') }}</a>
            </div>
        @endif
    </div>
@endsection
