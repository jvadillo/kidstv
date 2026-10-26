@extends('layouts.panel')

@section('title', __('Buscar en YouTube'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Añadir vídeo de YouTube') }}</h1>
    </div>

    @include('panel.contents.youtube-tabs', ['active' => 'search'])

    @unless ($enabled)
        <div class="notice">
            <p><strong>{{ __('El buscador no está disponible todavía.') }}</strong></p>
            <p>{{ __('El administrador del servidor debe configurar una clave de la API de YouTube. Mientras tanto puedes añadir vídeos pegando su enlace.') }}</p>
            <a href="{{ route('contents.youtube.create', request()->only('from_welcome')) }}" class="btn btn-primary">{{ __('Pegar un enlace') }}</a>
        </div>
    @else
        <form method="GET" action="{{ route('contents.youtube.search') }}" class="yt-search card" role="search">
            @if (request('from_welcome'))
                <input type="hidden" name="from_welcome" value="1">
            @endif
            @if (request('lista'))
                <input type="hidden" name="lista" value="{{ request('lista') }}">
            @endif
            <div class="yt-search-row">
                <label for="q" class="sr-only">{{ __('Buscar en YouTube') }}</label>
                <input type="search" id="q" name="q" value="{{ $query }}" maxlength="100" placeholder="{{ __('Canciones infantiles, Peppa Pig, dinosaurios…') }}"
                       @if ($query === '') autofocus @endif enterkeyhint="search" autocomplete="off">
                <button class="btn btn-primary">{{ __('Buscar') }}</button>
            </div>
            <div class="yt-search-filters">
                <label for="duracion" class="sr-only">{{ __('Duración') }}</label>
                <select id="duracion" name="duracion">
                    @foreach (['any' => __('Cualquier duración'), 'short' => __('Menos de 4 min'), 'medium' => __('De 4 a 20 min'), 'long' => __('Más de 20 min')] as $value => $label)
                        <option value="{{ $value }}" @selected($duration === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <label for="idioma" class="sr-only">{{ __('Idioma preferido') }}</label>
                @php $mainCount = count(\App\Services\YouTubeSearch::MAIN_LANGUAGES); @endphp
                <select id="idioma" name="idioma" title="{{ __('Idioma preferido de los vídeos') }}">
                    @foreach (array_slice($languages, 0, $mainCount, true) as $code => $label)
                        <option value="{{ $code }}" @selected($language === $code)>{{ $label }}</option>
                    @endforeach
                    <optgroup label="{{ __('Otros idiomas') }}">
                        @foreach (array_slice($languages, $mainCount, null, true) as $code => $label)
                            <option value="{{ $code }}" @selected($language === $code)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                </select>
                <label class="check check-inline">
                    <input type="checkbox" name="ninos" value="1" @checked($kidsOnly)>
                    <span>{{ __('Solo vídeos marcados «para niños»') }}</span>
                </label>
            </div>
            <p class="hint">{{ __('Búsqueda segura estricta y solo vídeos que se pueden ver fuera de YouTube. YouTube da prioridad a los vídeos en el idioma elegido, aunque puede mostrar alguno en otro. Revisa cada vídeo antes de añadirlo: puedes verlo aquí mismo pulsando su miniatura.') }}</p>
        </form>

        @if ($error)
            <p class="notice notice-error" role="alert">{{ $error }}</p>
        @endif

        @if ($results !== null)
            @if (empty($results['items']))
                <div class="empty-state">
                    <p>{{ __('No hemos encontrado vídeos para «:q».', ['q' => $query]) }}</p>
                    <p class="muted">{{ $kidsOnly ? __('Prueba a quitar el filtro «para niños» o con otras palabras.') : __('Prueba con otras palabras o cambia la duración.') }}</p>
                </div>
            @else
                <form method="POST" action="{{ route('contents.youtube.store') }}" data-yt-add-form>
                    @csrf
                    <input type="hidden" name="from_search" value="1">

                    <fieldset class="card yt-add-settings">
                        <legend class="sr-only">{{ __('Opciones al añadir') }}</legend>
                        <div class="field">
                            <span class="label">{{ __('Al añadir, podrán verlo:') }}</span>
                            @if ($profiles->isEmpty())
                                <p class="hint">{!! __('Aún no hay perfiles. <a href=":url">Crea uno</a> para que los vídeos aparezcan en la tele.', ['url' => route('profiles.create')]) !!}</p>
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
                        </div>
                        <div class="field yt-add-list">
                            <label for="list_id">{{ __('Lista') }}</label>
                            <select id="list_id" name="list_id">
                                <option value="">{{ __('Sin lista') }}</option>
                                @foreach ($lists as $list)
                                    <option value="{{ $list->id }}" @selected((string) request('lista') === (string) $list->id)>{{ $list->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </fieldset>

                    <p class="error" data-yt-add-error role="alert" hidden></p>
                    @error('url')<p class="error" role="alert">{{ $message }}</p>@enderror

                    <ul class="yt-results">
                        @foreach ($results['items'] as $video)
                            @php $isAdded = in_array($video['id'], $added, true); @endphp
                            <li class="yt-result">
                                <button type="button" class="yt-thumb" data-preview="{{ $video['id'] }}" data-title="{{ $video['title'] }}"
                                        aria-label="{{ __('Ver «:title»', ['title' => $video['title']]) }}">
                                    <img src="{{ $video['thumbnail'] }}" alt="" loading="lazy" width="320" height="180">
                                    @if ($video['duration'])
                                        <span class="yt-duration">{{ \App\Services\YouTubeSearch::formatDuration($video['duration']) }}</span>
                                    @endif
                                    <span class="yt-play" aria-hidden="true">@include('partials.icon', ['name' => 'youtube'])</span>
                                </button>
                                <div class="yt-info">
                                    <strong class="yt-title">{{ $video['title'] }}</strong>
                                    <span class="muted small">{{ $video['channel'] }}</span>
                                    <span class="muted small">
                                        {{ \App\Services\YouTubeSearch::formatViews($video['views']) }}
                                        @if ($video['published_at']) · {{ \Illuminate\Support\Carbon::parse($video['published_at'])->diffForHumans() }} @endif
                                    </span>
                                    @if ($video['made_for_kids'])
                                        <span class="badge badge-ready">{{ __('Para niños') }}</span>
                                    @endif
                                </div>
                                <div class="yt-action">
                                    @if ($isAdded)
                                        <span class="btn btn-sm btn-added" aria-disabled="true">@include('partials.icon', ['name' => 'check']) {{ __('En tu videoteca') }}</span>
                                    @else
                                        <button class="btn btn-sm btn-primary" name="url" value="{{ $video['id'] }}">@include('partials.icon', ['name' => 'plus']) {{ __('Añadir') }}</button>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </form>

                <nav class="pager" aria-label="{{ __('Más resultados') }}">
                    @if ($results['prev'])
                        <a class="btn btn-ghost" href="{{ route('contents.youtube.search', array_filter(['q' => $query, 'duracion' => $duration, 'idioma' => $language, 'ninos' => $kidsOnly ?: null, 'pagina' => $results['prev'], 'from_welcome' => request('from_welcome'), 'lista' => request('lista')])) }}">{{ __('Anteriores') }}</a>
                    @endif
                    @if ($results['next'])
                        <a class="btn btn-ghost" href="{{ route('contents.youtube.search', array_filter(['q' => $query, 'duracion' => $duration, 'idioma' => $language, 'ninos' => $kidsOnly ?: null, 'pagina' => $results['next'], 'from_welcome' => request('from_welcome'), 'lista' => request('lista')])) }}">{{ __('Más resultados') }}</a>
                    @endif
                </nav>

                <p class="small muted yt-attribution">
                    {!! __('Resultados proporcionados por <a href=":yt" target="_blank" rel="noopener noreferrer">YouTube</a>. Los vídeos se reproducen con el reproductor oficial y pueden incluir anuncios o sugerencias.', ['yt' => 'https://www.youtube.com']) !!}
                </p>
            @endif
        @endif

        @if (request('from_welcome'))
            <p class="center"><a href="{{ route('welcome') }}" class="btn btn-accent">{{ __('Continuar con los primeros pasos') }}</a></p>
        @endif

        <dialog class="yt-dialog" data-yt-dialog aria-labelledby="yt-dialog-title">
            <div class="yt-dialog-head">
                <strong id="yt-dialog-title" data-yt-dialog-title></strong>
                <button type="button" class="btn btn-sm btn-ghost" data-yt-dialog-close>{{ __('Cerrar') }}</button>
            </div>
            <div class="video-frame">
                <iframe title="{{ __('Vista previa') }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        </dialog>
    @endunless
@endsection
