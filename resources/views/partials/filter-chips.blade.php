{{--
    Fila de chips de filtro (una sola opción activa), como en las apps móviles: se desliza
    en horizontal si no cabe. $items: [['url', 'label', 'current', 'avatar' => opcional]].
--}}
<nav class="filter-chips" aria-label="{{ $label }}">
    @foreach ($items as $item)
        <a href="{{ $item['url'] }}" @class(['filter-chip', 'filter-chip-avatar' => isset($item['avatar'])]) @if ($item['current']) aria-current="true" @endif>
            @isset($item['avatar'])
                @include('partials.avatar', ['avatar' => $item['avatar'], 'size' => 26])
            @elseif ($item['current'])
                @include('partials.icon', ['name' => 'check'])
            @endisset
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
