@php
    // Los vídeos de listas públicas son de otra familia: su portada llega por otra ruta ($coverUrl).
    $src = $content->cover_path ? ($coverUrl ?? route('contents.cover', $content)) : $content->youtubeThumbnail();
@endphp
<div class="thumb thumb-{{ $content->type }}">
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy">
    @else
        @include('partials.icon', ['name' => $content->type === 'audio' ? 'audio' : 'video'])
    @endif
    <span class="thumb-type">{{ $content->typeLabel() }}</span>
</div>
