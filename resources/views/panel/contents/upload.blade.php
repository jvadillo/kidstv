@extends('layouts.panel')

@section('title', $kind === 'audio' ? __('Subir audio') : __('Subir vídeo'))

@section('content')
    <div class="page-head">
        <h1>{{ $kind === 'audio' ? __('Subir audio') : __('Subir vídeo') }}</h1>
    </div>

    <form method="POST" action="#" enctype="multipart/form-data" class="stack card narrow" novalidate
          data-upload-form
          data-kind="{{ $kind }}"
          data-start-url="{{ route('uploads.start') }}"
          data-max-bytes="{{ $maxBytes }}"
          data-extensions="{{ implode(',', $extensions) }}">
        @csrf
        @if (request('from_welcome'))
            <input type="hidden" name="from_welcome" value="1">
        @endif

        <div class="field">
            <label for="file">{{ $kind === 'audio' ? __('Archivo de audio') : __('Archivo de vídeo') }}</label>
            <input type="file" id="file" data-upload-file required
                   accept="{{ $kind === 'audio' ? '.mp3,.m4a,audio/mpeg,audio/mp4' : '.mp4,.m4v,video/mp4' }}">
            <p class="hint">
                @if ($kind === 'audio')
                    {{ __('MP3 o M4A, hasta :max.', ['max' => format_bytes($maxBytes)]) }}
                @else
                    {{ __('MP4 (H.264 + AAC es lo más compatible con televisores), hasta :max.', ['max' => format_bytes($maxBytes)]) }}
                @endif
                {{ __('Espacio usado: :used de :quota.', ['used' => format_bytes($storageUsed), 'quota' => format_bytes($storageQuota)]) }}
            </p>
            <p class="error" data-upload-error role="alert" hidden></p>
        </div>

        @include('partials.content-fields')

        <div class="upload-progress" data-upload-progress hidden>
            <div class="meter"><span data-upload-bar style="width: 0%"></span></div>
            <p class="small" data-upload-status aria-live="polite"></p>
        </div>

        <div class="notice">
            <p>{{ __('Tus archivos se guardan de forma privada en nuestro servidor: solo los ven los televisores vinculados a tu familia. Sube únicamente contenido que tengas derecho a usar.') }}</p>
        </div>

        <noscript><p class="error">{{ __('Para subir archivos necesitas activar JavaScript.') }}</p></noscript>

        <div class="form-actions">
            <button class="btn btn-primary" data-upload-submit>{{ __('Subir') }}</button>
            <button type="button" class="btn btn-ghost" data-upload-cancel hidden>{{ __('Cancelar subida') }}</button>
            <a href="{{ is_numeric(request('lista')) ? route('lists.show', request('lista')) : route('contents.index') }}" class="btn btn-ghost" data-upload-back>{{ __('Volver') }}</a>
        </div>
    </form>
@endsection
