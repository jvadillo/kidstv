@extends('layouts.panel')

@section('title', __('Categorías'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Categorías') }}</h1>
        <a href="{{ route('contents.index') }}" class="btn btn-ghost">{{ __('Volver a la biblioteca') }}</a>
    </div>
    <p class="muted">{{ __('En la tele, las categorías aparecen como pestañas en este orden.') }}</p>

    <form method="POST" action="{{ route('categories.store') }}" class="inline-form card">
        @csrf
        <div class="field grow">
            <label for="new-category">{{ __('Nueva categoría') }}</label>
            <input type="text" id="new-category" name="name" maxlength="40" required placeholder="{{ __('Por ejemplo: Canciones') }}">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary">{{ __('Añadir') }}</button>
    </form>

    @if ($categories->isEmpty())
        <p class="empty">{{ __('No hay categorías. Los contenidos aparecerán juntos en la tele.') }}</p>
    @else
        <ul class="card-list">
            @foreach ($categories as $category)
                <li class="card category-item">
                    <form method="POST" action="{{ route('categories.update', $category) }}" class="inline-form grow">
                        @csrf
                        @method('PUT')
                        <label class="sr-only" for="cat-{{ $category->id }}">{{ __('Nombre') }}</label>
                        <input type="text" id="cat-{{ $category->id }}" name="name" value="{{ $category->name }}" maxlength="40" required>
                        <button class="btn btn-sm btn-ghost">{{ __('Renombrar') }}</button>
                    </form>
                    <span class="muted small">{{ trans_choice(':count contenido|:count contenidos', $category->contents_count) }}</span>
                    <div class="row-actions">
                        @unless ($loop->first)
                            <form method="POST" action="{{ route('categories.move', $category) }}">
                                @csrf
                                <input type="hidden" name="direction" value="up">
                                <button class="btn btn-icon" aria-label="{{ __('Subir') }}">@include('partials.icon', ['name' => 'up'])</button>
                            </form>
                        @endunless
                        @unless ($loop->last)
                            <form method="POST" action="{{ route('categories.move', $category) }}">
                                @csrf
                                <input type="hidden" name="direction" value="down">
                                <button class="btn btn-icon" aria-label="{{ __('Bajar') }}">@include('partials.icon', ['name' => 'down'])</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('categories.destroy', $category) }}"
                              data-confirm="{{ __('¿Eliminar la categoría «:name»? Sus contenidos no se borran.', ['name' => $category->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger-ghost">{{ __('Eliminar') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
