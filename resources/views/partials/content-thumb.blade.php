@php
    $src = $content->cover_path ? route('contents.cover', $content) : $content->youtubeThumbnail();
@endphp
<div class="thumb thumb-{{ $content->type }}">
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy">
    @else
        @include('partials.icon', ['name' => $content->type === 'audio' ? 'audio' : 'video'])
    @endif
    <span class="thumb-type">{{ $content->typeLabel() }}</span>
</div>
