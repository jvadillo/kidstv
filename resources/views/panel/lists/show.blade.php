@extends('layouts.panel')

{{-- Gestión de una lista propia o, sin $list, de los contenidos que no están en ninguna. --}}
@php($name = $list?->name ?? __('Sin lista'))

@section('title', $name)

@if ($contents->contains('status', \App\Models\Content::STATUS_PROCESSING))
    @push('head')<meta http-equiv="refresh" content="8">@endpush
@endif

@section('content')
    <a href="{{ route('contents.index') }}" class="back-link">@include('partials.icon', ['name' => 'left']) {{ __('Biblioteca') }}</a>

    <div class="page-head">
        <div class="page-head-title list-head-title">
            <h1>{{ $name }}</h1>
            @if ($list?->is_public)
                <span class="badge badge-public">{{ __('Publicada') }}</span>
            @endif
        </div>
        <div class="page-head-actions">
            <details class="menu" data-menu>
                <summary class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'plus']) {{ __('Añadir') }}</summary>
                <div class="menu-panel menu-panel-end">
                    @include('panel.contents.add-options', $list ? ['listId' => $list->id] : [])
                </div>
            </details>
            @if ($list)
                <details class="menu" data-menu @if ($errors->has('name')) open @endif>
                    <summary class="btn btn-icon" aria-label="{{ __('Opciones de la lista') }}" title="{{ __('Opciones de la lista') }}">@include('partials.icon', ['name' => 'more'])</summary>
                    <div class="menu-panel menu-panel-end">
                        <form method="POST" action="{{ route('lists.update', $list) }}" class="menu-form">
                            @csrf
                            @method('PUT')
                            <label for="list-name">{{ __('Nombre de la lista') }}</label>
                            <input type="text" id="list-name" name="name" value="{{ old('name', $list->name) }}" maxlength="40" required @error('name') aria-invalid="true" @enderror>
                            @error('name')<p class="error">{{ $message }}</p>@enderror
                            <button class="btn btn-primary btn-sm">{{ __('Renombrar') }}</button>
                        </form>
                        <form method="POST" action="{{ route('lists.destroy', $list) }}" class="menu-form"
                              data-confirm="{{ $list->is_public
                                  ? __('¿Eliminar la lista pública «:name»? Desaparecerá también de las videotecas que la usan. Sus contenidos no se borran.', ['name' => $list->name])
                                  : __('¿Eliminar la lista «:name»? Sus contenidos no se borran.', ['name' => $list->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger-ghost">@include('partials.icon', ['name' => 'trash']) {{ __('Eliminar la lista') }}</button>
                        </form>
                    </div>
                </details>
            @endif
        </div>
    </div>

    <p class="muted small">
        {{ trans_choice(':count contenido|:count contenidos', $contents->count()) }}
        · {{ $list ? __('Usa las flechas para ordenarlos: así aparecen en la tele.') : __('En la tele aparecen al final, en la fila «Otros».') }}
    </p>

    @if ($contents->isEmpty())
        <details class="add-placeholder" open>
            <summary>
                <span class="thumb">@include('partials.icon', ['name' => 'plus'])</span>
                <span class="content-meta">
                    <strong>{{ __('Añadir contenido') }}</strong>
                    <span class="muted small">{{ __('Vídeo de YouTube, vídeo o audio') }}</span>
                </span>
            </summary>
            <div class="add-placeholder-options">
                @include('panel.contents.add-options', $list ? ['listId' => $list->id] : [])
            </div>
        </details>
    @else
        <ul class="content-list">
            @foreach ($contents as $content)
                <li class="content-item">
                    <a href="{{ route('contents.edit', $content) }}" class="content-row">
                        @include('partials.content-thumb')
                        <div class="content-meta">
                            <strong>{{ $content->title }}</strong>
                            <span class="muted small">
                                {{ $content->typeLabel() }}
                                @if ($content->formattedDuration()) · {{ $content->formattedDuration() }} @endif
                                @unless ($content->isReady() && ! $content->isHidden()) · @include('partials.status-badge') @endunless
                            </span>
                            <span class="small profiles-inline">
                                @forelse ($content->profiles as $profile)
                                    @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 20])
                                @empty
                                    <span class="warn-text">{{ __('Sin perfiles: no se ve en la tele') }}</span>
                                @endforelse
                            </span>
                        </div>
                    </a>
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
                    <form method="POST" action="{{ route('contents.destroy', $content) }}"
                          data-confirm="{{ __('¿Eliminar «:title»? :extra', ['title' => $content->title, 'extra' => $content->file_path ? __('El archivo se borrará del servidor.') : '']) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-icon btn-icon-danger" aria-label="{{ __('Eliminar «:title»', ['title' => $content->title]) }}" title="{{ __('Eliminar') }}">@include('partials.icon', ['name' => 'trash'])</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
