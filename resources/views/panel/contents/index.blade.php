@extends('layouts.panel')

@section('title', __('Biblioteca'))

@if ($contents->contains('status', \App\Models\Content::STATUS_PROCESSING))
    @push('head')<meta http-equiv="refresh" content="8">@endpush
@endif

@section('content')
    <div class="page-head">
        <h1>{{ __('Biblioteca') }}</h1>
        <div class="page-head-actions">
            <a href="{{ route('contents.youtube.create') }}" class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'youtube']) {{ __('YouTube') }}</a>
            <a href="{{ route('contents.upload.create', 'video') }}" class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'video']) {{ __('Vídeo') }}</a>
            <a href="{{ route('contents.upload.create', 'audio') }}" class="btn btn-primary btn-sm">@include('partials.icon', ['name' => 'audio']) {{ __('Audio') }}</a>
        </div>
    </div>

    <form method="GET" class="filters" aria-label="{{ __('Filtrar') }}">
        <select name="type" aria-label="{{ __('Tipo') }}">
            <option value="">{{ __('Todos los tipos') }}</option>
            <option value="youtube" @selected(($filters['type'] ?? '') === 'youtube')>YouTube</option>
            <option value="video" @selected(($filters['type'] ?? '') === 'video')>{{ __('Vídeos propios') }}</option>
            <option value="audio" @selected(($filters['type'] ?? '') === 'audio')>{{ __('Audios') }}</option>
        </select>
        <select name="category" aria-label="{{ __('Categoría') }}">
            <option value="">{{ __('Todas las categorías') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
            @endforeach
            <option value="none" @selected(($filters['category'] ?? '') === 'none')>{{ __('Sin categoría') }}</option>
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
        <a href="{{ route('categories.index') }}">{{ __('Gestionar categorías') }}</a>
        @unless ($filtered) · {{ __('Usa las flechas para cambiar el orden en que aparecen en la tele.') }} @endunless
    </p>

    @if ($contents->isEmpty())
        <div class="empty-state">
            @if ($filtered)
                <p>{{ __('Ningún contenido coincide con los filtros.') }}</p>
            @else
                <p>{{ __('Tu biblioteca está vacía.') }}</p>
                <p class="muted">{{ __('Empieza pegando un enlace de YouTube o sube un vídeo MP4 o un audio MP3 desde tu móvil u ordenador.') }}</p>
                <a href="{{ route('contents.youtube.create') }}" class="btn btn-primary">{{ __('Añadir vídeo de YouTube') }}</a>
            @endif
        </div>
    @else
        <ul class="content-list">
            @foreach ($contents as $content)
                <li class="content-item">
                    <a href="{{ route('contents.edit', $content) }}" class="content-row">
                        @include('partials.content-thumb')
                        <div class="content-meta">
                            <strong>{{ $content->title }}</strong>
                            <span class="muted small">
                                {{ $content->category?->name ?? __('Sin categoría') }}
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
    @endif
@endsection
