@extends('layouts.panel')

@section('title', __('Biblioteca'))

@php
    $url = fn (array $changes) => route('contents.index', array_filter(array_merge($filters, $changes)));
    $filtered = $profile || $kind || $query !== '';
@endphp

@section('content')
    <div class="library-page">
    <div class="page-head"><h1>{{ __('Biblioteca') }}</h1></div>

    <form method="GET" action="{{ route('contents.index') }}" class="library-search" role="search">
        <label for="library-search" class="sr-only">{{ __('Buscar listas, vídeos o audios') }}</label>
        @if ($profile)<input type="hidden" name="perfil" value="{{ $profile->id }}">@endif
        @if ($kind)<input type="hidden" name="tipo" value="{{ $kind }}">@endif
        <button type="submit" aria-label="{{ __('Buscar en la biblioteca') }}">@include('partials.icon', ['name' => 'search'])</button>
        <input type="search" id="library-search" name="q" value="{{ $query }}" maxlength="100" placeholder="{{ __('Buscar listas, vídeos o audios') }}">
    </form>
    @if ($query !== '')
        <p class="small muted">{{ __('Resultados para «:query»', ['query' => $query]) }} · <a class="search-clear" href="{{ $url(['q' => null]) }}">{{ __('Borrar búsqueda') }}</a></p>
    @endif

    @if (! $empty && (auth()->user()->isAdmin() || $profiles->count() > 1))
        <div class="library-filters">
            @if (auth()->user()->isAdmin())
                <nav class="segmented segmented-block" aria-label="{{ __('Tipo de lista') }}">
                    @foreach (['' => __('Todas'), 'publicas' => __('Públicas'), 'privadas' => __('Privadas')] as $key => $label)
                        <a href="{{ $url(['tipo' => $key ?: null]) }}" @class(['active' => $kind === ($key ?: null)]) @if ($kind === ($key ?: null)) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            @endif
            @if ($profiles->count() > 1)
                @include('partials.filter-chips', [
                    'label' => __('Filtrar por perfil'),
                    'items' => collect([['url' => $url(['perfil' => null]), 'label' => __('Todos'), 'current' => ! $profile]])
                        ->concat($profiles->map(fn ($option) => [
                            'url' => $url(['perfil' => $option->id]), 'label' => $option->name,
                            'avatar' => $option->avatar, 'current' => $profile?->id === $option->id,
                        ])),
                ])
            @endif
        </div>
    @endif

    @if ($empty)
        <div class="empty-state">
            <p>{{ __('Tu biblioteca está vacía.') }}</p>
            <p class="muted">{{ __('Crea una lista o empieza pegando un enlace de YouTube; también puedes subir un vídeo MP4 o un audio MP3 desde tu móvil u ordenador.') }}</p>
            <a href="{{ youtube_add_url() }}" class="btn btn-primary">{{ __('Añadir vídeo de YouTube') }}</a>
        </div>
    @elseif ($cards->isEmpty() && ! $unlisted)
        <div class="empty-state">
            <p>{{ $query !== '' ? __('No hay listas ni contenidos que coincidan con tu búsqueda.') : ($profile ? __(':name todavía no ve ninguna de estas listas.', ['name' => $profile->name]) : __('No hay listas de este tipo.')) }}</p>
            <a href="{{ route('contents.index') }}" class="btn btn-ghost">{{ __('Ver todas las listas') }}</a>
        </div>
    @else
        <section class="section" aria-labelledby="tus-listas">
            <div class="section-head">
                <h2 id="tus-listas">{{ $profile ? __('Lo que ve :name', ['name' => $profile->name]) : __('Tus listas') }}</h2>
                <span class="muted small">{{ trans_choice(':count lista|:count listas', $cards->count()) }}</span>
            </div>
            <ul class="list-cards" @unless($filtered) data-sortable-lists @endunless>
                @foreach ($cards as $card)
                    @php($list = $card['list'])
                    <li class="list-card-item" data-list-id="{{ $list->id }}">
                        @include('panel.lists.card', $card + [
                            'url' => route('lists.show', $list),
                            'name' => $list->displayName(),
                            'coverUrl' => $list->isLinked() && $card['first']?->cover_path ? route('lists.public.cover', [$list->source_id, $card['first']]) : null,
                        ])
                        @unless ($filtered)
                            <details class="menu list-order-menu" data-menu>
                                <summary class="btn btn-icon drag-handle" aria-label="{{ __('Ordenar la lista «:name»', ['name' => $list->displayName()]) }}" aria-describedby="list-order-help" data-move-url="{{ route('lists.move', $list) }}">@include('partials.icon', ['name' => 'grip'])</summary>
                                <div class="menu-panel menu-panel-end">
                                    <form method="POST" action="{{ route('lists.move', $list) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="up">
                                        <button class="btn btn-text btn-block" @disabled($loop->first) aria-label="{{ __('Subir la lista «:name»', ['name' => $list->displayName()]) }}">{{ __('Subir lista') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('lists.move', $list) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="down">
                                        <button class="btn btn-text btn-block" @disabled($loop->last) aria-label="{{ __('Bajar la lista «:name»', ['name' => $list->displayName()]) }}">{{ __('Bajar lista') }}</button>
                                    </form>
                                </div>
                            </details>
                        @endunless
                    </li>
                @endforeach
                @if ($unlisted)
                    <li class="list-card-item">
                        @include('panel.lists.card', $unlisted + ['list' => null, 'url' => route('lists.unlisted'), 'name' => __('Sin lista')])
                    </li>
                @endif
            </ul>
            @if ($filtered)
                <p class="hint list-cards-hint">{!! __('Para ordenar las listas, <a href=":url">vuelve a verlas todas</a>.', ['url' => route('contents.index')]) !!}</p>
            @else
                <p id="list-order-help" class="hint list-cards-hint">{{ __('Arrastra el asa para ordenar las listas o púlsala para subir y bajar. Así aparecen en la tele.') }}</p>
                <p class="sr-only" role="status" data-sort-status></p>
            @endif
        </section>
    @endif

    @if ($publicCount > 0)
        <a href="{{ route('lists.public') }}" class="card card-link public-lists-link">
            @include('partials.icon', ['name' => 'globe'])
            <span class="grow">
                <strong class="block">{{ __('Listas públicas') }}</strong>
                <span class="muted small">{{ trans_choice('Hay :count lista preparada por NokoPlay que puedes añadir a tu videoteca.|Hay :count listas preparadas por NokoPlay que puedes añadir a tu videoteca.', $publicCount) }}</span>
            </span>
            <span class="btn btn-sm btn-ghost">{{ __('Ver') }}</span>
        </a>
    @endif

    <details class="menu library-add" data-menu>
        <summary class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('Añadir') }}</summary>
        <div class="menu-panel menu-panel-end">
            <a href="{{ route('lists.public') }}" class="menu-item">@include('partials.icon', ['name' => 'globe']) {{ __('Añadir lista pública') }}</a>
            <a href="{{ route('lists.create') }}" class="menu-item">@include('partials.icon', ['name' => 'lock']) {{ __('Crear lista privada') }}</a>
            @include('panel.contents.add-options')
        </div>
    </details>
    </div>
@endsection
