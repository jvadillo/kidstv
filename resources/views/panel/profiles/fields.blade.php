<div class="field">
    <label for="name">{{ __('Apodo o nombre visible') }}</label>
    <input type="text" id="name" name="name" value="{{ old('name', $profile->name) }}" maxlength="40" required
           autocomplete="off" @error('name') aria-invalid="true" @enderror>
    @error('name')<p class="error">{{ $message }}</p>@enderror
</div>

<fieldset class="field">
    <legend>{{ __('Avatar') }}</legend>
    <div class="avatar-picker">
        @foreach (config('kidstv.avatars') as $avatar)
            <label class="avatar-option">
                <input type="radio" name="avatar" value="{{ $avatar }}" @checked(old('avatar', $profile->avatar) === $avatar)>
                @include('partials.avatar', ['avatar' => $avatar, 'size' => 64])
                <span class="sr-only">{{ ucfirst($avatar) }}</span>
            </label>
        @endforeach
    </div>
    @error('avatar')<p class="error">{{ $message }}</p>@enderror
</fieldset>
