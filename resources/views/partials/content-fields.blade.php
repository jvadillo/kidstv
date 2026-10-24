{{-- Campos comunes de un contenido: título, categoría, perfiles autorizados, descripción y portada. --}}
@php
    $selectedProfiles = collect(old('profile_ids', isset($content) ? $content->profiles->pluck('id')->all() : $profiles->pluck('id')->all()))->map(fn ($id) => (int) $id);
    $selectedCategory = old('category_id', $content->category_id ?? null);
@endphp

<div class="field">
    <label for="title">{{ __('Título') }} @if ($titleOptional ?? false)<span class="optional">{{ __('(si lo dejas vacío usaremos el de YouTube)') }}</span>@endif</label>
    <input type="text" id="title" name="title" value="{{ old('title', $content->title ?? '') }}" maxlength="120"
           @unless ($titleOptional ?? false) required @endunless @error('title') aria-invalid="true" @enderror>
    @error('title')<p class="error">{{ $message }}</p>@enderror
</div>

<div class="field">
    <label for="category_id">{{ __('Categoría') }}</label>
    <select id="category_id" name="category_id">
        <option value="">{{ __('Sin categoría') }}</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) $selectedCategory === (string) $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <p class="hint"><a href="{{ route('categories.index') }}">{{ __('Gestionar categorías') }}</a></p>
    @error('category_id')<p class="error">{{ $message }}</p>@enderror
</div>

<fieldset class="field">
    <legend>{{ __('¿Quién puede verlo?') }}</legend>
    @if ($profiles->isEmpty())
        <p class="hint">{!! __('Todavía no hay perfiles infantiles. <a href=":url">Crea uno</a> para poder asignarle contenidos.', ['url' => route('profiles.create')]) !!}</p>
    @else
        <div class="chips">
            @foreach ($profiles as $profile)
                <label class="chip">
                    <input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}" @checked($selectedProfiles->contains($profile->id))>
                    @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 28])
                    <span>{{ $profile->name }}</span>
                </label>
            @endforeach
        </div>
        <p class="hint">{{ __('Si no marcas ninguno, el contenido queda guardado pero no aparece en la tele.') }}</p>
    @endif
    @error('profile_ids.*')<p class="error">{{ $message }}</p>@enderror
</fieldset>

<div class="field">
    <label for="description">{{ __('Descripción') }} <span class="optional">{{ __('(opcional)') }}</span></label>
    <textarea id="description" name="description" rows="2" maxlength="1000">{{ old('description', $content->description ?? '') }}</textarea>
</div>

<div class="field">
    <label for="cover">{{ __('Portada') }} <span class="optional">{{ __('(opcional)') }}</span></label>
    <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
    <p class="hint">{{ $coverHint ?? __('JPG, PNG o WebP, máximo :max MB. Si no subes ninguna, usaremos una imagen del propio contenido.', ['max' => config('kidstv.max_cover_mb')]) }}</p>
    @error('cover')<p class="error">{{ $message }}</p>@enderror
</div>
