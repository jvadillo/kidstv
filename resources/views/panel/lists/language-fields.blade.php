{{--
    Idiomas de una lista (uno o varios, obligatorio): los habituales a la vista y el resto en
    «Más idiomas». $selected: códigos marcados; $id: prefijo si hay varios formularios en la página;
    $old: false para no recuperar lo enviado (formularios de edición repetidos).
--}}
@php
    $options = \App\Models\VideoList::languageOptions();
    $main = array_intersect_key($options, array_flip(\App\Models\VideoList::MAIN_LANGUAGES));
    $more = array_diff_key($options, $main);
    $selected = ($old ?? true) ? (array) old('languages', $selected ?? []) : ($selected ?? []);
    $id = $id ?? 'languages';
@endphp
<fieldset class="field" aria-describedby="{{ $id }}-hint">
    <legend>{{ __('Idioma') }}</legend>
    <p class="hint" id="{{ $id }}-hint">{{ __('Marca uno o varios: el idioma en el que hablan o cantan los vídeos.') }}</p>
    <div class="chips">
        @foreach ($main as $code => $name)
            <label class="chip chip-text">
                <input type="checkbox" name="languages[]" value="{{ $code }}" @checked(in_array($code, $selected, true))>
                <span>{{ $name }}</span>
            </label>
        @endforeach
    </div>
    <details class="more-options" @if (array_intersect($selected, array_keys($more))) open @endif>
        <summary>{{ __('Más idiomas') }}</summary>
        <div class="chips">
            @foreach ($more as $code => $name)
                <label class="chip chip-text">
                    <input type="checkbox" name="languages[]" value="{{ $code }}" @checked(in_array($code, $selected, true))>
                    <span>{{ $name }}</span>
                </label>
            @endforeach
        </div>
    </details>
    @if ($old ?? true)
        @error('languages')<p class="error">{{ $message }}</p>@enderror
        @error('languages.*')<p class="error">{{ $message }}</p>@enderror
    @endif
</fieldset>
