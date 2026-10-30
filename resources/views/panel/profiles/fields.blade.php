<div class="field">
    <label for="name">{{ __('Apodo o nombre visible') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $profile->name) }}" maxlength="40" required
           autocomplete="off" @error('name') aria-invalid="true" @enderror>
    @error('name')<p class="error">{{ $message }}</p>@enderror
</div>

<fieldset class="field">
    <legend>{{ __('Edad') }} <span class="optional">{{ __('(para proponerte series de su edad)') }}</span></legend>
    <div class="chips">
        @foreach (\App\Models\VideoList::AGE_RANGES as $key => $range)
            <label class="chip chip-text">
                <input type="radio" name="age_range" value="{{ $key }}" @checked(old('age_range', $profile->age_range) === $key)>
                <span>{{ \App\Models\VideoList::ageRangeLabel(...$range) }}</span>
            </label>
        @endforeach
    </div>
    @error('age_range')<p class="error">{{ $message }}</p>@enderror
</fieldset>

<fieldset class="field">
    <legend>{{ __('Avatar') }}</legend>
    <div class="avatar-picker">
        @foreach (config('nokoplay.avatars') as $avatar)
            <label class="avatar-option">
                <input type="radio" name="avatar" value="{{ $avatar }}" @checked(old('avatar', $profile->avatar) === $avatar)>
                @include('partials.avatar', ['avatar' => $avatar, 'size' => 64])
                <span class="sr-only">{{ ucfirst($avatar) }}</span>
            </label>
        @endforeach
    </div>
    @error('avatar')<p class="error">{{ $message }}</p>@enderror
</fieldset>
