@extends('layouts.panel')

{{-- Lista pública añadida: sus vídeos los mantiene KidsTV; la familia solo elige quién la ve. --}}
@section('title', $list->displayName())

@section('content')
    <a href="{{ route('contents.index') }}" class="back-link">@include('partials.icon', ['name' => 'left']) {{ __('Biblioteca') }}</a>

    <div class="page-head">
        <div class="page-head-title list-head-title">
            <h1>{{ $list->displayName() }}</h1>
            <span class="badge badge-public">{{ __('Lista pública') }}</span>
        </div>
        <form method="POST" action="{{ route('lists.destroy', $list) }}"
              data-confirm="{{ __('¿Quitar «:name» de tu videoteca? Podrás volver a añadirla cuando quieras.', ['name' => $list->displayName()]) }}">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-danger-ghost">{{ __('Quitar de mi videoteca') }}</button>
        </form>
    </div>

    @if (! $list->isAvailable())
        <p class="empty"><span class="warn-text">{{ __('Ya no está disponible') }}</span></p>
    @else
        @if ($list->source->description)<p class="muted">{{ $list->source->description }}</p>@endif
        @include('panel.lists.public-meta', ['list' => $list])

        <form method="POST" action="{{ route('lists.profiles', $list) }}" class="card stack">
            @csrf
            @method('PUT')
            <fieldset class="field">
                <legend>{{ __('¿Quién la ve?') }}</legend>
                @if ($profiles->isEmpty())
                    <p class="hint">{!! __('Aún no hay perfiles. <a href=":url">Crea uno</a> para que esta lista aparezca en la tele.', ['url' => route('profiles.create')]) !!}</p>
                @else
                    <div class="chips">
                        @foreach ($profiles as $profile)
                            <label class="chip">
                                <input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}" @checked($list->profiles->contains('id', $profile->id))>
                                @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 24])
                                <span>{{ $profile->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>
            @if ($profiles->isNotEmpty())
                <div class="form-actions">
                    <button class="btn btn-primary btn-sm">{{ __('Guardar perfiles') }}</button>
                </div>
            @endif
        </form>

        <section class="section" aria-labelledby="videos">
            <h2 id="videos">{{ trans_choice(':count vídeo|:count vídeos', $contents->count()) }}</h2>
            <p class="hint">{{ __('Las listas públicas se mantienen al día solas: cuando se añaden o quitan vídeos, cambian también en tu tele.') }}</p>
            @if ($contents->isNotEmpty())
                <ul class="content-list">
                    @foreach ($contents as $content)
                        <li class="content-row">
                            @include('partials.content-thumb', ['coverUrl' => $content->cover_path ? route('lists.public.cover', [$list->source_id, $content]) : null])
                            <div class="content-meta">
                                <strong>{{ $content->title }}</strong>
                                <span class="muted small">
                                    {{ $content->typeLabel() }}
                                    @if ($content->formattedDuration()) · {{ $content->formattedDuration() }} @endif
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
@endsection
