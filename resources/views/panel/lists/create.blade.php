@extends('layouts.panel')

{{-- Nueva lista privada: nombre e idioma; los contenidos se añaden después, desde la propia lista. --}}
@section('title', __('Nueva lista privada'))

@section('content')
    <a href="{{ route('contents.index') }}" class="back-link">@include('partials.icon', ['name' => 'left']) {{ __('Biblioteca') }}</a>

    <div class="page-head">
        <h1>{{ __('Nueva lista privada') }}</h1>
    </div>
    <p class="muted">{{ __('Solo la ve tu familia. En la tele será una fila más, con los vídeos y audios que le pongas.') }}</p>

    <form method="POST" action="{{ route('lists.store') }}" class="card stack">
        @csrf
        <div class="field">
            <label for="name">{{ __('Nombre de la lista') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="40" required autofocus
                   placeholder="{{ __('Por ejemplo: Canciones') }}" @error('name') aria-invalid="true" @enderror>
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        @include('panel.lists.language-fields', ['selected' => [$defaultLanguage]])
        <div class="form-actions">
            <button class="btn btn-primary">{{ __('Crear lista') }}</button>
            <span class="hint">{{ __('Después podrás añadirle vídeos de YouTube, vídeos o audios.') }}</span>
        </div>
    </form>
@endsection
