{{-- Control deslizante de minutos al día: va por posiciones de Family::DAILY_LIMIT_OPTIONS. --}}
@php
    $options = \App\Models\Family::DAILY_LIMIT_OPTIONS;
    $saved = array_search($minutes, $options, true);
    $step = (int) old($oldKey, $saved === false ? array_search(60, $options, true) : $saved);
@endphp
<div class="field daily-limit-slider">
    <label for="{{ $id }}">
        @isset($avatar)@include('partials.avatar', ['avatar' => $avatar, 'size' => 24])@endisset
        {{ $label }}: <output for="{{ $id }}" class="daily-limit-value">{{ format_minutes($options[$step]) }}</output>
    </label>
    <input type="range" id="{{ $id }}" name="{{ $name }}" min="0" max="{{ count($options) - 1 }}" step="1" value="{{ $step }}"
           data-minutes="{{ json_encode($options) }}" aria-valuetext="{{ format_minutes($options[$step]) }}">
    <div class="range-scale" aria-hidden="true"><span>{{ format_minutes($options[0]) }}</span><span>{{ format_minutes(end($options)) }}</span></div>
</div>
