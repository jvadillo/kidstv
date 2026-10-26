@extends('layouts.panel')

@section('title', __('Añadir vídeo de YouTube'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Añadir vídeo de YouTube') }}</h1>
    </div>

    @if ($searchEnabled)
        @include('panel.contents.youtube-tabs', ['active' => 'link'])
    @endif

    <form method="POST" action="{{ route('contents.youtube.store') }}" enctype="multipart/form-data" class="stack card narrow"
          data-youtube-form data-preview-url="{{ route('contents.youtube.preview') }}">
        @csrf
        @if (request('from_welcome') || old('from_welcome'))
            <input type="hidden" name="from_welcome" value="1">
        @endif

        <div class="field">
            <label for="url">{{ __('Enlace del vídeo') }}</label>
            <input type="url" id="url" name="url" value="{{ old('url') }}" required autofocus inputmode="url"
                   placeholder="https://www.youtube.com/watch?v=…" data-youtube-url @error('url') aria-invalid="true" @enderror>
            <p class="hint">{{ __('En la app o web de YouTube: Compartir → Copiar enlace, y pégalo aquí.') }}</p>
            @error('url')<p class="error">{{ $message }}</p>@enderror
            <p class="error" data-youtube-error hidden></p>
        </div>

        <div class="yt-preview" data-youtube-preview hidden>
            <img src="" alt="" data-youtube-thumb>
            <div>
                <strong data-youtube-title></strong>
                <span class="muted small" data-youtube-author></span>
                <span class="badge badge-ready">{{ __('Se puede insertar') }}</span>
            </div>
        </div>

        @include('partials.content-fields', ['titleOptional' => true, 'coverHint' => __('Si no subes ninguna, usaremos la miniatura de YouTube.')])

        <div class="notice notice-info">
            <p><strong>{{ __('Sobre YouTube') }}</strong></p>
            <p>{{ __('El vídeo se reproduce con el reproductor oficial de YouTube (modo de privacidad mejorada) y nunca se copia a nuestro servidor. YouTube puede mostrar anuncios, su logotipo o sugerencias al final, y no podemos quitarlos. Si el propietario borra el vídeo o desactiva la inserción, dejará de funcionar.') }}</p>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary">{{ __('Añadir a la videoteca') }}</button>
            <a href="{{ is_numeric(request('lista')) ? route('lists.show', request('lista')) : route('contents.index') }}" class="btn btn-ghost">{{ __('Cancelar') }}</a>
        </div>
    </form>
@endsection
