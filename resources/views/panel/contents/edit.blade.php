@extends('layouts.panel')

@section('title', $content->title)

@if ($content->status === \App\Models\Content::STATUS_PROCESSING)
    @push('head')<meta http-equiv="refresh" content="6">@endpush
@endif

@section('content')
    <div class="page-head">
        <h1>{{ $content->title }}</h1>
        @include('partials.status-badge')
    </div>

    <div class="content-detail">
        <div class="content-preview">
            @if ($content->status === \App\Models\Content::STATUS_PROCESSING)
                <div class="processing">
                    <span class="spinner" aria-hidden="true"></span>
                    <p>{{ __('Estamos preparando el archivo para la tele. Esta página se actualizará sola.') }}</p>
                </div>
            @elseif ($content->isYouTube())
                <div class="video-frame">
                    <iframe src="https://www.youtube-nocookie.com/embed/{{ $content->youtube_id }}?rel=0" title="{{ $content->title }}"
                            allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
                <p class="small"><a href="https://www.youtube.com/watch?v={{ $content->youtube_id }}" target="_blank" rel="noopener noreferrer">{{ __('Ver en YouTube') }}</a></p>
            @elseif ($content->type === 'video' && $content->isReady())
                <video controls preload="metadata" @if ($content->cover_path) poster="{{ route('contents.cover', $content) }}" @endif>
                    <source src="{{ route('contents.file', $content) }}" type="{{ $content->mime }}">
                </video>
            @elseif ($content->type === 'audio' && $content->isReady())
                @include('partials.content-thumb')
                <audio controls preload="metadata" src="{{ route('contents.file', $content) }}"></audio>
            @else
                @include('partials.content-thumb')
            @endif

            @if ($content->status_message && ! $content->isHidden())
                <p @class(['notice', 'notice-error' => in_array($content->status, ['error', 'unavailable']), 'notice-info' => $content->isReady()])>{{ $content->status_message }}</p>
            @endif

            <dl class="facts">
                <dt>{{ __('Tipo') }}</dt><dd>{{ $content->typeLabel() }}</dd>
                @if ($content->formattedDuration())<dt>{{ __('Duración') }}</dt><dd>{{ $content->formattedDuration() }}</dd>@endif
                @if ($content->file_size)<dt>{{ __('Tamaño') }}</dt><dd>{{ format_bytes($content->file_size) }}</dd>@endif
                <dt>{{ __('Añadido') }}</dt><dd>{{ $content->created_at->isoFormat('D MMM YYYY') }}</dd>
            </dl>

            <div class="stack-sm">
                @if ($content->isReady())
                    <form method="POST" action="{{ route('contents.visibility', $content) }}">
                        @csrf
                        <input type="hidden" name="action" value="hide">
                        <button class="btn btn-ghost btn-block">{{ __('Ocultar temporalmente en la tele') }}</button>
                    </form>
                @elseif ($content->status === \App\Models\Content::STATUS_UNAVAILABLE)
                    <form method="POST" action="{{ route('contents.visibility', $content) }}">
                        @csrf
                        <input type="hidden" name="action" value="show">
                        <button class="btn btn-accent btn-block">{{ $content->isYouTube() ? __('Volver a comprobar y mostrar') : __('Mostrar de nuevo') }}</button>
                    </form>
                @endif
                @if ($content->file_path)
                    <a href="{{ route('contents.file', $content) }}" class="btn btn-ghost btn-block" download>{{ __('Descargar archivo') }}</a>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('contents.update', $content) }}" enctype="multipart/form-data" class="stack card">
            @csrf
            @method('PUT')
            @include('partials.content-fields')
            @if ($content->cover_path)
                <label class="check">
                    <input type="checkbox" name="remove_cover" value="1">
                    <span>{{ __('Quitar la portada actual') }}</span>
                </label>
            @endif
            <div class="form-actions">
                <button class="btn btn-primary">{{ __('Guardar cambios') }}</button>
                <a href="{{ $content->list_id ? route('lists.show', $content->list_id) : route('lists.unlisted') }}" class="btn btn-ghost">{{ __('Volver') }}</a>
            </div>
        </form>
    </div>

    <section class="section">
        <form method="POST" action="{{ route('contents.destroy', $content) }}"
              data-confirm="{{ __('¿Eliminar «:title»? :extra', ['title' => $content->title, 'extra' => $content->file_path ? __('El archivo se borrará del servidor.') : '']) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger-ghost">{{ __('Eliminar de la videoteca') }}</button>
        </form>
    </section>
@endsection
