@extends('layouts.panel')

@section('title', $list->name)

@section('content')
    <div class="page-head">
        <h1>{{ $list->name }}</h1>
        <a href="{{ route('lists.public') }}" class="btn btn-ghost">{{ __('Todas las listas públicas') }}</a>
    </div>
    @if ($list->description)<p class="muted">{{ $list->description }}</p>@endif

    @if ($link)
        <div class="notice notice-info">
            <p>{!! __('Esta lista ya está en tu videoteca. <a href=":url">Elige qué perfiles la ven</a>.', ['url' => route('lists.show', $link)]) !!}</p>
        </div>
    @else
        <form method="POST" action="{{ route('lists.public.subscribe', $list) }}" class="card stack">
            @csrf
            <fieldset class="field">
                <legend>{{ __('¿Quién podrá verla?') }}</legend>
                @if ($profiles->isEmpty())
                    <p class="hint">{!! __('Aún no hay perfiles. <a href=":url">Crea uno</a> para que la lista aparezca en la tele.', ['url' => route('profiles.create')]) !!}</p>
                @else
                    <div class="chips">
                        @foreach ($profiles as $profile)
                            <label class="chip">
                                <input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}" checked>
                                @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 28])
                                <span>{{ $profile->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('profile_ids.*')<p class="error">{{ $message }}</p>@enderror
            </fieldset>
            <div class="form-actions">
                <button class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('Añadir a mi videoteca') }}</button>
                <span class="hint">{{ __('Se añade tal cual: no podrás quitar ni añadir vídeos a esta lista, pero sí quitarla entera cuando quieras.') }}</span>
            </div>
        </form>
    @endif

    <section class="section">
        <h2>{{ trans_choice(':count vídeo|:count vídeos', $contents->count()) }}</h2>
        @if ($contents->isEmpty())
            <p class="empty">{{ __('Esta lista todavía no tiene vídeos.') }}</p>
        @else
            <ul class="content-list">
                @foreach ($contents as $content)
                    <li class="content-row">
                        @include('partials.content-thumb', ['coverUrl' => $content->cover_path ? route('lists.public.cover', [$list, $content]) : null])
                        <div class="content-meta">
                            <strong>{{ $content->title }}</strong>
                            <span class="muted small">
                                {{ $content->typeLabel() }}
                                @if ($content->formattedDuration()) · {{ $content->formattedDuration() }} @endif
                            </span>
                        </div>
                        @if ($content->isYouTube())
                            <a href="https://www.youtube.com/watch?v={{ $content->youtube_id }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-ghost">{{ __('Ver en YouTube') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
