{{-- Pestañas «Buscar» / «Pegar enlace» de la página de añadir vídeo de YouTube. --}}
<nav class="segmented" aria-label="{{ __('Cómo añadir el vídeo') }}">
    <a href="{{ route('contents.youtube.search', request()->only('from_welcome')) }}" @class(['active' => $active === 'search']) @if ($active === 'search') aria-current="page" @endif>
        {{ __('Buscar en YouTube') }}
    </a>
    <a href="{{ route('contents.youtube.create', request()->only('from_welcome')) }}" @class(['active' => $active === 'link']) @if ($active === 'link') aria-current="page" @endif>
        {{ __('Pegar enlace') }}
    </a>
</nav>
