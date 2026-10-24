@extends('layouts.panel')

@section('title', __('Eliminar cuenta'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Eliminar cuenta') }}</h1>
    </div>

    <form method="POST" action="{{ route('account.destroy') }}" class="stack card narrow card-danger">
        @csrf
        @method('DELETE')
        <p><strong>{{ __('Esto no se puede deshacer.') }}</strong> {{ __('Se borrarán ahora mismo:') }}</p>
        <ul>
            <li>{{ __('Tu cuenta y tus sesiones.') }}</li>
            <li>{{ trans_choice(':count perfil infantil|:count perfiles infantiles', $profileCount) }}</li>
            <li>{{ trans_choice(':count contenido, con sus archivos de vídeo y audio|:count contenidos, con sus archivos de vídeo y audio', $contentCount) }}</li>
            <li>{{ trans_choice('La vinculación de :count televisor|La vinculación de :count televisores', $deviceCount) }}</li>
        </ul>
        <p class="small muted">{{ __('Si quieres conservar una copia, exporta antes tus datos y descarga los archivos que te interesen.') }}</p>
        <div class="field">
            <label for="confirmation">{{ __('Escribe ELIMINAR para confirmar') }}</label>
            <input type="text" id="confirmation" name="confirmation" autocomplete="off" required @error('confirmation') aria-invalid="true" @enderror>
            @error('confirmation')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="form-actions">
            <button class="btn btn-danger">{{ __('Eliminar definitivamente') }}</button>
            <a href="{{ route('account.show') }}" class="btn btn-ghost">{{ __('Cancelar') }}</a>
        </div>
    </form>
@endsection
