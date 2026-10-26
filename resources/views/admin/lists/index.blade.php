@extends('layouts.panel')

@section('title', __('Listas públicas'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Administración') }}</h1>
    </div>
    @include('admin.partials.nav')

    <p class="muted">{{ __('Las listas públicas son listas de tu videoteca que cualquier familia puede añadir a la suya. Se añaden tal cual: cuando pones o quitas vídeos de la lista desde tu biblioteca, cambian también en todas las teles que la usan.') }}</p>

    <form method="POST" action="{{ route('admin.lists.store') }}" class="card stack">
        @csrf
        <h2>{{ __('Nueva lista pública') }}</h2>
        <div class="field">
            <label for="name">{{ __('Nombre') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="40" required placeholder="{{ __('Por ejemplo: Canciones en inglés') }}">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="description">{{ __('Descripción') }} <span class="optional">{{ __('(opcional, la ven las familias)') }}</span></label>
            <input type="text" id="description" name="description" value="{{ old('description') }}" maxlength="200">
        </div>
        <div><button class="btn btn-primary">{{ __('Crear lista pública') }}</button></div>
    </form>

    <section class="section">
        <h2>{{ __('Publicadas') }}</h2>
        @if ($public->isEmpty())
            <p class="empty">{{ __('Todavía no hay listas públicas.') }}</p>
        @else
            <ul class="card-list">
                @foreach ($public as $list)
                    @php $stats = $usage[$list->id] ?? null; $mine = $list->family_id === $familyId; @endphp
                    <li class="card stack">
                        <form method="POST" action="{{ route('admin.lists.update', $list) }}" class="admin-list-form">
                            @csrf
                            @method('PUT')
                            <div class="field">
                                <label for="name-{{ $list->id }}">{{ __('Nombre') }}</label>
                                <input type="text" id="name-{{ $list->id }}" name="name" value="{{ $list->name }}" maxlength="40" required>
                            </div>
                            <div class="field grow">
                                <label for="description-{{ $list->id }}">{{ __('Descripción') }}</label>
                                <input type="text" id="description-{{ $list->id }}" name="description" value="{{ $list->description }}" maxlength="200">
                            </div>
                            <button class="btn btn-sm btn-ghost">{{ __('Guardar') }}</button>
                        </form>
                        <p class="small muted">
                            {{ trans_choice(':count vídeo|:count vídeos', $list->contents_count) }}
                            · {{ trans_choice('en :count videoteca|en :count videotecas', $list->links_count) }}
                            · {{ __(':time vistos en 30 días', ['time' => \App\Services\UsageStats::format($stats->minutes ?? 0)]) }}
                            @if ($stats) · {{ trans_choice('por :count familia|por :count familias', $stats->families) }} @endif
                            @unless ($mine) · {{ __('de :email', ['email' => $list->family->users->first()?->email]) }} @endunless
                        </p>
                        <div class="form-actions">
                            @if ($mine)
                                <a href="{{ route('lists.show', $list) }}" class="btn btn-sm btn-primary">{{ __('Gestionar vídeos') }}</a>
                            @endif
                            <form method="POST" action="{{ route('admin.lists.unpublish', $list) }}"
                                  data-confirm="{{ trans_choice('¿Dejar de publicar «:name»? Desaparecerá de :count videoteca que la usa. La lista y sus vídeos siguen en la biblioteca de su dueño.|¿Dejar de publicar «:name»? Desaparecerá de las :count videotecas que la usan. La lista y sus vídeos siguen en la biblioteca de su dueño.', $list->links_count, ['name' => $list->name]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger-ghost">{{ __('Dejar de publicar') }}</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($private->isNotEmpty())
        <section class="section">
            <h2>{{ __('Publicar una de tus listas') }}</h2>
            <ul class="card-list">
                @foreach ($private as $list)
                    <li class="card list-entry">
                        <span class="grow"><strong>{{ $list->name }}</strong> <span class="muted small">{{ trans_choice(':count contenido|:count contenidos', $list->contents_count) }}</span></span>
                        <form method="POST" action="{{ route('admin.lists.publish', $list) }}"
                              data-confirm="{{ __('¿Publicar «:name»? Cualquier familia podrá añadirla y ver sus vídeos tal cual.', ['name' => $list->name]) }}">
                            @csrf
                            <button class="btn btn-sm btn-ghost">{{ __('Publicar') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
