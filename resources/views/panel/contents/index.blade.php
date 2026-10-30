@extends('layouts.panel')

@section('title', __('Biblioteca'))

@php
    $url = fn (array $changes) => route('contents.index', array_filter(array_merge($filters, $changes)));
    $filtered = $profile || $kind;
@endphp

@section('content')
    <div class="page-head">
        <h1>{{ __('Biblioteca') }}</h1>
        <div class="page-head-actions library-actions">
            <details class="menu" data-menu>
                <summary class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'plus'])<span class="sr-only">{{ __('Añadir') }}</span> {{ __('Lista') }}</summary>
                <div class="menu-panel">
                    <a href="{{ route('lists.public') }}" class="menu-item menu-item-2">
                        @include('partials.icon', ['name' => 'globe'])
                        <span><span class="block">{{ __('Añadir lista pública') }}</span><span class="muted small">{{ __('Preparada por NokoPlay, se mantiene al día sola') }}</span></span>
                    </a>
                    <a href="{{ route('lists.create') }}" class="menu-item menu-item-2">
                        @include('partials.icon', ['name' => 'lock'])
                        <span><span class="block">{{ __('Crear lista privada') }}</span><span class="muted small">{{ __('Con tus vídeos y audios') }}</span></span>
                    </a>
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
            <p>{{ $profile ? __(':name todavía no ve ninguna de estas listas.', ['name' => $profile->name]) : __('No hay listas de este tipo.') }}</p>
            <a href="{{ route('contents.index') }}" class="btn btn-ghost">{{ __('Ver todas las listas') }}</a>
        </div>
    @else
        <section class="section" aria-labelledby="tus-listas">
            <h2 id="tus-listas">{{ $profile ? __('Lo que ve :name', ['name' => $profile->name]) : __('Tus listas') }}</h2>
            <ul class="list-cards">
                @foreach ($cards as $card)
                    @php($list = $card['list'])
                    <li class="list-card-item">
                        @include('panel.lists.card', $card + [
                            'url' => route('lists.show', $list),
                            'name' => $list->displayName(),
                            'coverUrl' => $list->isLinked() && $card['first']?->cover_path ? route('lists.public.cover', [$list->source_id, $card['first']]) : null,
                        ])
                        {{-- Con un filtro las vecinas pueden estar ocultas: se ordena viendo todas. --}}
                        @unless ($filtered)
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
                        @endunless
                    </li>
                @endforeach
                @if ($unlisted)
                    <li class="list-card-item">
                        @include('panel.lists.card', $unlisted + ['list' => null, 'url' => route('lists.unlisted'), 'name' => __('Sin lista')])
                        {{-- En la tele siempre van al final: no se ordenan. --}}
                        @unless ($filtered)<div class="reorder" aria-hidden="true"></div>@endunless
                    </li>
                @endif
            </ul>
            @if ($filtered)
                <p class="hint list-cards-hint">{!! __('Para ordenar las listas, <a href=":url">vuelve a verlas todas</a>.', ['url' => route('contents.index')]) !!}</p>
            @elseif ($cards->count() > 1)
                <p class="hint list-cards-hint">{{ __('Usa las flechas para ordenar las listas: así aparecen en la tele.') }}</p>
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
@endsection
