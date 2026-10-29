{{-- Edad recomendada de una lista pública (obligatoria). Mismos parámetros que language-fields. --}}
@php
    $useOld = $old ?? true;
    $list = $list ?? null;
    $min = $useOld ? old('age_min', $list?->age_min) : $list?->age_min;
    $max = $useOld ? old('age_max', $list?->age_max) : $list?->age_max;
    $id = $id ?? 'age';
    $ages = range(\App\Models\VideoList::AGE_MIN, \App\Models\VideoList::AGE_MAX);
@endphp
<fieldset class="field">
    <legend>{{ __('Edad recomendada') }}</legend>
    <div class="age-range">
        <label for="{{ $id }}-min">{{ __('De') }}</label>
        <select id="{{ $id }}-min" name="age_min" required @if ($useOld) @error('age_min') aria-invalid="true" @enderror @endif>
            <option value="" disabled @selected($min === null)>–</option>
            @foreach ($ages as $age)
                <option value="{{ $age }}" @selected($min !== null && (int) $min === $age)>{{ $age }}</option>
            @endforeach
        </select>
        <label for="{{ $id }}-max">{{ __('a') }}</label>
        <select id="{{ $id }}-max" name="age_max" required @if ($useOld) @error('age_max') aria-invalid="true" @enderror @endif>
            <option value="" disabled @selected($max === null)>–</option>
            @foreach ($ages as $age)
                <option value="{{ $age }}" @selected($max !== null && (int) $max === $age)>{{ $age }}</option>
            @endforeach
        </select>
        <span>{{ __('años') }}</span>
    </div>
    @if ($useOld)
        @error('age_min')<p class="error">{{ $message }}</p>@enderror
        @error('age_max')<p class="error">{{ $message }}</p>@enderror
    @endif
</fieldset>
