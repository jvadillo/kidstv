{{-- Aviso «Viendo ahora»: panel.js lo actualiza cada minuto mientras la página está abierta. --}}
@php
    // Con «Todos» se dice quién; las apps anteriores a la 1.4.0 no envían el perfil.
    $names = $profile ? collect() : $watching->pluck('profile.name')->filter()->unique()->values();
    $titles = $watching->filter(fn ($play) => $play->content)
        ->map(fn ($play) => ($profile || ! $play->profile ? '' : $play->profile->name.': ').$play->content->title)
        ->unique()->implode("\n");
@endphp
<span class="watching" data-watching>
    @if ($watching->isNotEmpty())
        <span class="badge badge-live" @if ($titles !== '') title="{{ $titles }}" @endif>
            <span class="live-dot" aria-hidden="true"></span>
            {{ $names->isEmpty() ? __('Viendo ahora') : __('Viendo ahora: :names', ['names' => $names->join(', ', ' '.__('y').' ')]) }}
        </span>
    @endif
</span>
