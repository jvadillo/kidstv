{{-- Opciones para añadir contenido; con $listId, el formulario sale con esa lista ya elegida. --}}
@php($query = isset($listId) ? ['lista' => $listId] : [])
<a href="{{ youtube_add_url($query) }}" class="menu-item">@include('partials.icon', ['name' => 'youtube']) {{ __('Vídeo de YouTube') }}</a>
<a href="{{ route('contents.upload.create', ['kind' => 'video'] + $query) }}" class="menu-item">@include('partials.icon', ['name' => 'video']) {{ __('Subir vídeo') }}</a>
@unless ($noAudio ?? false)
    <a href="{{ route('contents.upload.create', ['kind' => 'audio'] + $query) }}" class="menu-item">@include('partials.icon', ['name' => 'audio']) {{ __('Subir audio') }}</a>
@endunless
