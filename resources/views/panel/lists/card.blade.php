{{-- Una lista en la biblioteca: portada del primer contenido (apilada si hay más), recuento, idioma y quién la ve. --}}
@php
    $videos = $count - $audios;
    $parts = array_filter([
        $videos ? trans_choice(':count vídeo|:count vídeos', $videos) : null,
        $audios ? trans_choice(':count audio|:count audios', $audios) : null,
    ]);
@endphp
<a href="{{ $url }}" class="list-card">
    <div @class(['list-thumb', 'list-thumb-stack' => $count > 1])>
        @if ($first)
            @include('partials.content-thumb', ['content' => $first, 'coverUrl' => $coverUrl ?? null])
        @else
            <span class="thumb">@include('partials.icon', ['name' => 'library'])</span>
        @endif
    </div>
    <div class="list-card-meta">
        <span class="list-card-title">
            <strong>{{ $name }}</strong>
            @if ($list?->isLinked())
                <span class="badge badge-public">{{ __('Lista pública') }}</span>
            @elseif ($list?->is_public)
                <span class="badge badge-public">{{ __('Publicada') }}</span>
            @endif
        </span>
        <span class="muted small">
            @if ($list && ! $list->isAvailable())
                <span class="warn-text">{{ __('Ya no está disponible') }}</span>
            @else
                {{ $parts ? implode(' · ', $parts) : __('Vacía') }}
                @if ($list?->languageNames()) · <span class="sr-only">{{ __('Idioma:') }}</span> {{ $list->languageNames() }}@endif
            @endif
        </span>
        @if ($profiles->isNotEmpty())
            <span class="profiles-inline">
                <span class="sr-only">{{ __('La ven: :names', ['names' => $profiles->pluck('name')->join(', ')]) }}</span>
                @foreach ($profiles as $profile)
                    @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 22])
                @endforeach
            </span>
        @elseif ($count > 0)
            <span class="small warn-text list-unassigned">@include('partials.icon', ['name' => 'info']) {{ __('Sin perfil asignado') }}</span>
        @endif
    </div>
    <span class="list-card-go">@include('partials.icon', ['name' => 'right'])</span>
</a>
