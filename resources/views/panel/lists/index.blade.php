@extends('layouts.panel')

@section('title', __('Listas'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Listas') }}</h1>
        <a href="{{ route('contents.index') }}" class="btn btn-ghost">{{ __('Volver a la biblioteca') }}</a>
    </div>
    <p class="muted">{{ __('En la tele, cada lista es una fila de vídeos, en este orden. Dentro de cada fila, el orden es el de la biblioteca.') }}</p>

    <form method="POST" action="{{ route('lists.store') }}" class="inline-form card">
        @csrf
        <div class="field grow">
            <label for="new-list">{{ __('Nueva lista') }}</label>
            <input type="text" id="new-list" name="name" maxlength="40" required placeholder="{{ __('Por ejemplo: Canciones') }}">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary">{{ __('Añadir') }}</button>
    </form>

    @if ($publicCount > 0)
        <a href="{{ route('lists.public') }}" class="card card-link public-lists-link">
            @include('partials.icon', ['name' => 'globe'])
            <span class="grow">
                <strong class="block">{{ __('Listas públicas') }}</strong>
                <span class="muted small">{{ trans_choice('Hay :count lista preparada por KidsTV que puedes añadir a tu videoteca.|Hay :count listas preparadas por KidsTV que puedes añadir a tu videoteca.', $publicCount) }}</span>
            </span>
            <span class="btn btn-sm btn-ghost">{{ __('Ver') }}</span>
        </a>
    @endif

    @if ($lists->isEmpty())
        <p class="empty">{{ __('No hay listas. Los contenidos aparecerán juntos en la tele.') }}</p>
    @else
        <ul class="card-list">
            @foreach ($lists as $list)
                <li class="card list-item">
                    <div class="list-entry">
                        @if ($list->isLinked())
                            <div class="grow list-linked-name">
                                <strong>{{ $list->displayName() }}</strong>
                                <span class="badge badge-public">{{ __('Lista pública') }}</span>
                            </div>
                            <span class="muted small">
                                @if ($list->isAvailable())
                                    {{ trans_choice(':count vídeo|:count vídeos', $list->source->contents_count) }}
                                @else
                                    <span class="warn-text">{{ __('Ya no está disponible') }}</span>
                                @endif
                            </span>
                        @else
                            <form method="POST" action="{{ route('lists.update', $list) }}" class="inline-form grow">
                                @csrf
                                @method('PUT')
                                <label class="sr-only" for="list-{{ $list->id }}">{{ __('Nombre') }}</label>
                                <input type="text" id="list-{{ $list->id }}" name="name" value="{{ $list->name }}" maxlength="40" required>
                                <button class="btn btn-sm btn-ghost">{{ __('Renombrar') }}</button>
                            </form>
                            <span class="muted small">
                                {{ trans_choice(':count contenido|:count contenidos', $list->contents_count) }}
                                @if ($list->is_public) · <span class="badge badge-public">{{ __('Publicada') }}</span> @endif
                            </span>
                        @endif
                        <div class="row-actions">
                            @unless ($loop->first)
                                <form method="POST" action="{{ route('lists.move', $list) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="up">
                                    <button class="btn btn-icon" aria-label="{{ __('Subir') }}">@include('partials.icon', ['name' => 'up'])</button>
                                </form>
                            @endunless
                            @unless ($loop->last)
                                <form method="POST" action="{{ route('lists.move', $list) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="down">
                                    <button class="btn btn-icon" aria-label="{{ __('Bajar') }}">@include('partials.icon', ['name' => 'down'])</button>
                                </form>
                            @endunless
                            @if ($list->isLinked())
                                <form method="POST" action="{{ route('lists.destroy', $list) }}"
                                      data-confirm="{{ __('¿Quitar «:name» de tu videoteca? Podrás volver a añadirla cuando quieras.', ['name' => $list->displayName()]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger-ghost">{{ __('Quitar') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('lists.destroy', $list) }}"
                                      data-confirm="{{ $list->is_public
                                          ? __('¿Eliminar la lista pública «:name»? Desaparecerá también de las videotecas que la usan. Sus contenidos no se borran.', ['name' => $list->name])
                                          : __('¿Eliminar la lista «:name»? Sus contenidos no se borran.', ['name' => $list->name]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger-ghost">{{ __('Eliminar') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if ($list->isLinked() && $list->isAvailable())
                        <form method="POST" action="{{ route('lists.profiles', $list) }}" class="list-profiles">
                            @csrf
                            @method('PUT')
                            @if ($profiles->isEmpty())
                                <p class="hint">{!! __('Aún no hay perfiles. <a href=":url">Crea uno</a> para que esta lista aparezca en la tele.', ['url' => route('profiles.create')]) !!}</p>
                            @else
                                <span class="label small">{{ __('La ven:') }}</span>
                                <div class="chips">
                                    @foreach ($profiles as $profile)
                                        <label class="chip">
                                            <input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}" @checked($list->profiles->contains('id', $profile->id))>
                                            @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 24])
                                            <span>{{ $profile->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="form-actions">
                                    <button class="btn btn-sm btn-ghost">{{ __('Guardar perfiles') }}</button>
                                    <a href="{{ route('lists.public.show', $list->source_id) }}" class="btn btn-sm btn-link">{{ __('Ver vídeos') }}</a>
                                </div>
                            @endif
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($lists->contains(fn ($list) => $list->isLinked()))
            <p class="hint">{{ __('Las listas públicas se mantienen al día solas: cuando se añaden o quitan vídeos, cambian también en tu tele.') }}</p>
        @endif
    @endif
@endsection
