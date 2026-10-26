@extends('layouts.panel')

@section('title', __('Biblioteca'))

@if ($contents->contains('status', \App\Models\Content::STATUS_PROCESSING))
    @push('head')<meta http-equiv="refresh" content="8">@endpush
@endif

@php
    // Filtrando por una lista, lo que se añada desde la cabecera va a esa lista.
    $filterList = is_numeric($filters['list'] ?? null) ? $filters['list'] : null;
@endphp

@section('content')
    <div class="page-head">
        <h1>{{ __('Biblioteca') }}</h1>
        <div class="page-head-actions">
            <details class="menu" data-menu @if ($errors->has('name')) open @endif>
                <summary class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'plus']) {{ __('Añadir lista') }}</summary>
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
                <summary class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'video']) {{ __('Añadir vídeo') }}</summary>
                <div class="menu-panel">
                    @include('panel.contents.add-options', ['listId' => $filterList, 'noAudio' => true])
                </div>
            </details>
            <a href="{{ route('contents.upload.create', ['kind' => 'audio'] + ($filterList ? ['lista' => $filterList] : [])) }}" class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'audio']) {{ __('Añadir audio') }}</a>
        </div>
    </div>

    <form method="GET" class="filters" aria-label="{{ __('Filtrar') }}">
        <select name="type" aria-label="{{ __('Tipo') }}">
            <option value="">{{ __('Todos los tipos') }}</option>
            <option value="youtube" @selected(($filters['type'] ?? '') === 'youtube')>YouTube</option>
            <option value="video" @selected(($filters['type'] ?? '') === 'video')>{{ __('Vídeos propios') }}</option>
            <option value="audio" @selected(($filters['type'] ?? '') === 'audio')>{{ __('Audios') }}</option>
        </select>
        <select name="list" aria-label="{{ __('Lista') }}">
            <option value="">{{ __('Todas las listas') }}</option>
            @foreach ($lists as $list)
                <option value="{{ $list->id }}" @selected(($filters['list'] ?? '') == $list->id)>{{ $list->name }}</option>
            @endforeach
            <option value="none" @selected(($filters['list'] ?? '') === 'none')>{{ __('Sin lista') }}</option>
        </select>
        <select name="profile" aria-label="{{ __('Perfil') }}">
            <option value="">{{ __('Todos los perfiles') }}</option>
            @foreach ($profiles as $profile)
                <option value="{{ $profile->id }}" @selected(($filters['profile'] ?? '') == $profile->id)>{{ $profile->name }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="{{ __('Estado') }}">
            <option value="">{{ __('Cualquier estado') }}</option>
            @foreach (['ready' => __('Listo'), 'processing' => __('Procesando'), 'unavailable' => __('No disponible'), 'error' => __('Error')] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-ghost">{{ __('Filtrar') }}</button>
        @if ($filtered)
            <a href="{{ route('contents.index') }}" class="btn btn-sm btn-link">{{ __('Quitar filtros') }}</a>
        @endif
    </form>

    <p class="small muted">
        <a href="{{ route('lists.index') }}">{{ __('Gestionar listas') }}</a>
        @unless ($filtered) · {{ __('Usa las flechas para ordenar las listas y lo que hay en cada una: así aparecen en la tele.') }} @endunless
    </p>

    @if ($blocks->isEmpty())
        <div class="empty-state">
            @if ($filtered)
                <p>{{ __('Ningún contenido coincide con los filtros.') }}</p>
            @else
                <p>{{ __('Tu biblioteca está vacía.') }}</p>
                <p class="muted">{{ __('Crea una lista o empieza pegando un enlace de YouTube; también puedes subir un vídeo MP4 o un audio MP3 desde tu móvil u ordenador.') }}</p>
                <a href="{{ youtube_add_url() }}" class="btn btn-primary">{{ __('Añadir vídeo de YouTube') }}</a>
            @endif
        </div>
    @else
        <div class="library-blocks">
            @foreach ($blocks as $block)
                @php
                    $list = $block['list'];
                    $items = $block['contents'];
                    $headingId = 'lista-'.($list?->id ?? 'ninguna');
                @endphp
                <section class="library-block" aria-labelledby="{{ $headingId }}">
                    <div class="library-block-head">
                        <h2 id="{{ $headingId }}">{{ $list ? $list->displayName() : __('Sin lista') }}</h2>
                        @if ($list?->isLinked())
                            <span class="badge badge-public">{{ __('Lista pública') }}</span>
                        @elseif ($list?->is_public)
                            <span class="badge badge-public">{{ __('Publicada') }}</span>
                        @endif
                        @unless ($list?->isLinked())
                            <span class="muted small">{{ trans_choice(':count contenido|:count contenidos', $items->count()) }}</span>
                        @endunless

                        <div class="row-actions library-block-actions">
                            @if ($list && ! $list->isLinked() && $items->isNotEmpty())
                                <details class="menu" data-menu>
                                    <summary class="btn btn-icon" aria-label="{{ __('Añadir a «:name»', ['name' => $list->name]) }}" title="{{ __('Añadir contenido') }}">@include('partials.icon', ['name' => 'plus'])</summary>
                                    <div class="menu-panel menu-panel-end">
                                        @include('panel.contents.add-options', ['listId' => $list->id])
                                    </div>
                                </details>
                            @endif
                            @if ($list && ! $filtered)
                                <form method="POST" action="{{ route('lists.move', $list) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button class="btn btn-icon" @disabled($list->is($allLists->first())) aria-label="{{ __('Subir la lista «:name»', ['name' => $list->displayName()]) }}">@include('partials.icon', ['name' => 'up'])</button>
                                </form>
                                <form method="POST" action="{{ route('lists.move', $list) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button class="btn btn-icon" @disabled($list->is($allLists->last())) aria-label="{{ __('Bajar la lista «:name»', ['name' => $list->displayName()]) }}">@include('partials.icon', ['name' => 'down'])</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($list?->isLinked())
                        <p class="empty small">
                            @if ($list->isAvailable())
                                {{ trans_choice(':count vídeo, se mantiene al día sola.|:count vídeos, se mantiene al día sola.', $list->source->contents_count) }}
                                @if ($list->profiles->isEmpty())
                                    <span class="warn-text">{{ __('Ningún perfil la ve todavía.') }}</span>
                                @endif
                                <a href="{{ route('lists.public.show', $list->source_id) }}">{{ __('Ver vídeos') }}</a> ·
                                <a href="{{ route('lists.index') }}">{{ __('Elegir quién la ve') }}</a>
                            @else
                                <span class="warn-text">{{ __('Ya no está disponible') }}</span>
                            @endif
                        </p>
                    @elseif ($items->isEmpty())
                        <details class="add-placeholder">
                            <summary>
                                <span class="thumb">@include('partials.icon', ['name' => 'plus'])</span>
                                <span class="content-meta">
                                    <strong>{{ __('Añadir contenido') }}</strong>
                                    <span class="muted small">{{ __('Vídeo de YouTube, vídeo o audio') }}</span>
                                </span>
                            </summary>
                            <div class="add-placeholder-options">
                                @include('panel.contents.add-options', ['listId' => $list->id])
                            </div>
                        </details>
                    @else
                        <ul class="content-list">
                            @foreach ($items as $content)
                                <li class="content-item">
                                    <a href="{{ route('contents.edit', $content) }}" class="content-row">
                                        @include('partials.content-thumb')
                                        <div class="content-meta">
                                            <strong>{{ $content->title }}</strong>
                                            <span class="muted small">
                                                {{ $content->typeLabel() }}
                                                @if ($content->formattedDuration()) · {{ $content->formattedDuration() }} @endif
                                            </span>
                                            <span class="small profiles-inline">
                                                @forelse ($content->profiles as $profile)
                                                    @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 20])
                                                @empty
                                                    <span class="warn-text">{{ __('Sin perfiles: no se ve en la tele') }}</span>
                                                @endforelse
                                            </span>
                                        </div>
                                        @include('partials.status-badge')
                                    </a>
                                    @unless ($filtered)
                                        <div class="reorder">
                                            <form method="POST" action="{{ route('contents.move', $content) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button class="btn btn-icon" @disabled($loop->first) aria-label="{{ __('Subir «:title»', ['title' => $content->title]) }}">@include('partials.icon', ['name' => 'up'])</button>
                                            </form>
                                            <form method="POST" action="{{ route('contents.move', $content) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button class="btn btn-icon" @disabled($loop->last) aria-label="{{ __('Bajar «:title»', ['title' => $content->title]) }}">@include('partials.icon', ['name' => 'down'])</button>
                                            </form>
                                        </div>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                        @unless ($list)
                            <p class="hint library-block-hint">{{ __('En la tele aparecen al final, en la fila «Otros».') }}</p>
                        @endunless
                    @endif
                </section>
            @endforeach
        </div>
    @endif
@endsection
