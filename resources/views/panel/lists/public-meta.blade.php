{{-- Idioma y edad recomendada de una lista pública (o de una enlazada, los de su original). --}}
@if ($list->languageNames() || $list->ageLabel())
    <p class="list-tags">
        @if ($list->languageNames())
            <span class="tag">@include('partials.icon', ['name' => 'globe'])<span class="sr-only">{{ __('Idioma:') }}</span> {{ $list->languageNames() }}</span>
        @endif
        @if ($list->ageLabel())
            <span class="tag">@include('partials.icon', ['name' => 'kids'])<span class="sr-only">{{ __('Edad recomendada:') }}</span> {{ $list->ageLabel() }}</span>
        @endif
    </p>
@endif
