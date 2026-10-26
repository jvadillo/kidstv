@extends('layouts.panel')

@section('title', __('Biblioteca'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Biblioteca') }}</h1>
        <div class="page-head-actions library-actions">
            <details class="menu" data-menu @if ($errors->has('name')) open @endif>
                <summary class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'plus'])<span class="sr-only">{{ __('Añadir') }}</span> {{ __('Lista') }}</summary>
                <div class="menu-panel">
                    <form method="POST" action="{{ route('lists.store') }}" class="menu-form">
                        @csrf
                        <label for="new-list">{{ __('Nombre de la lista') }}</label>
                        <input type="text" id="new-list" name="name" value="{{ old('name') }}" maxlength="40" required
                               placeholder="{{ __('Por ejemplo: Canciones') }}" @error('name') aria-invalid="true" @enderror>
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn btn-primary btn-sm">{{ __('Crear lista') }}</button>
                    </form>
                </div>
            </details>
            <details class="menu" data-menu>
                <summary class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'plus'])<span class="sr-only">{{ __('Añadir') }}</span> {{ __('Vídeo') }}</summary>
                <div class="menu-panel">
                    @include('panel.contents.add-options', ['noAudio' => true])
                </div>
            </details>
            <a href="{{ route('contents.upload.create', ['kind' => 'audio']) }}" class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'plus'])<span class="sr-only">{{ __('Añadir') }}</span> {{ __('Audio') }}</a>
        </div>
    </div>

    @if ($cards->isEmpty() && ! $unlisted)
        <div class="empty-state">
            <p>{{ __('Tu biblioteca está vacía.') }}</p>
            <p class="muted">{{ __('Crea una lista o empieza pegando un enlace de YouTube; también puedes subir un vídeo MP4 o un audio MP3 desde tu móvil u ordenador.') }}</p>
            <a href="{{ youtube_add_url() }}" class="btn btn-primary">{{ __('Añadir vídeo de YouTube') }}</a>
        </div>
    @else
        <section class="section" aria-labelledby="tus-listas">
            <h2 id="tus-listas">{{ __('Tus listas') }}</h2>
            <ul class="list-cards">
                @foreach ($cards as $card)
                    @php($list = $card['list'])
                    <li class="list-card-item">
                        @include('panel.lists.card', $card + [
                            'url' => route('lists.show', $list),
                            'name' => $list->displayName(),
                            'coverUrl' => $list->isLinked() && $card['first']?->cover_path ? route('lists.public.cover', [$list->source_id, $card['first']]) : null,
                        ])
                        <div class="reorder">
                            <form method="POST" action="{{ route('lists.move', $list) }}">
                                @csrf
                                <input type="hidden" name="direction" value="up">
                                <button class="btn btn-icon" @disabled($loop->first) aria-label="{{ __('Subir la lista «:name»', ['name' => $list->displayName()]) }}">@include('partials.icon', ['name' => 'up'])</button>
                            </form>
                            <form method="POST" action="{{ route('lists.move', $list) }}">
                                @csrf
                                <input type="hidden" name="direction" value="down">
                                <button class="btn btn-icon" @disabled($loop->last) aria-label="{{ __('Bajar la lista «:name»', ['name' => $list->displayName()]) }}">@include('partials.icon', ['name' => 'down'])</button>
                            </form>
                        </div>
                    </li>
                @endforeach
                @if ($unlisted)
                    <li class="list-card-item">
                        @include('panel.lists.card', $unlisted + ['list' => null, 'url' => route('lists.unlisted'), 'name' => __('Sin lista')])
                        {{-- En la tele siempre van al final: no se ordenan. --}}
                        <div class="reorder" aria-hidden="true"></div>
                    </li>
                @endif
            </ul>
            @if ($cards->count() > 1)
                <p class="hint list-cards-hint">{{ __('Usa las flechas para ordenar las listas: así aparecen en la tele.') }}</p>
            @endif
        </section>
    @endif

    @if ($publicCount > 0)
        <a href="{{ route('lists.public') }}" class="card card-link public-lists-link">
            @include('partials.icon', ['name' => 'globe'])
            <span class="grow">
                <strong class="block">{{ __('Listas públicas') }}</strong>
                <span class="muted small">{{ trans_choice('Hay :count lista preparada por KidsTV que puedes añadir a tu videoteca.|Hay :count listas preparadas por KidsTV que puedes añadir a tu videoteca.', $publicCount) }}</span>
            </span>
            <span class="btn btn-sm btn-ghost">{{ __('Ver') }}</span>
        </a>
    @endif
@endsection
