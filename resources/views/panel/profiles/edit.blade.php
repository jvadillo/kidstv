@extends('layouts.panel')

@section('title', $profile->name)

@section('content')
    <div class="page-head">
        <div class="page-head-title">
            @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 56])
            <h1>{{ $profile->name }}</h1>
        </div>
    </div>

    <section class="section">
        <h2>{{ __('¿Qué puede ver :name?', ['name' => $profile->name]) }}</h2>
        @if ($contents->isEmpty())
            <div class="empty-state">
                <p>{{ __('Tu biblioteca está vacía. Añade contenidos y vuelve aquí para asignarlos.') }}</p>
                <a href="{{ youtube_add_url() }}" class="btn btn-primary">{{ __('Añadir vídeo de YouTube') }}</a>
            </div>
        @else
            <form method="POST" action="{{ route('profiles.assign', $profile) }}" class="card">
                @csrf
                @method('PUT')
                <div class="checklist-tools">
                    <button type="button" class="btn btn-sm btn-ghost" data-check-all="content_ids[]">{{ __('Marcar todos') }}</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-check-none="content_ids[]">{{ __('Ninguno') }}</button>
                </div>
                <ul class="checklist">
                    @foreach ($contents as $content)
                        <li>
                            <label class="content-row content-row-check">
                                <input type="checkbox" name="content_ids[]" value="{{ $content->id }}" @checked(in_array($content->id, $assigned))>
                                @include('partials.content-thumb')
                                <span class="content-meta">
                                    <strong>{{ $content->title }}</strong>
                                    <span class="muted small">{{ $content->category?->name ?? __('Sin categoría') }}</span>
                                </span>
                                @unless ($content->isReady())
                                    @include('partials.status-badge')
                                @endunless
                            </label>
                        </li>
                    @endforeach
                </ul>
                <div class="form-actions sticky-actions">
                    <button class="btn btn-primary">{{ __('Guardar selección') }}</button>
                </div>
            </form>
        @endif
    </section>

    <section class="section">
        <h2>{{ __('Datos del perfil') }}</h2>
        <form method="POST" action="{{ route('profiles.update', $profile) }}" class="stack card narrow">
            @csrf
            @method('PUT')
            @include('panel.profiles.fields')
            <div class="form-actions">
                <button class="btn btn-primary">{{ __('Guardar') }}</button>
            </div>
        </form>
    </section>

    <section class="section">
        <form method="POST" action="{{ route('profiles.destroy', $profile) }}"
              data-confirm="{{ __('¿Eliminar el perfil de :name? Los contenidos seguirán en la biblioteca.', ['name' => $profile->name]) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-danger-ghost">{{ __('Eliminar perfil') }}</button>
        </form>
    </section>
@endsection
