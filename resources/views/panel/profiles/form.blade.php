@extends('layouts.panel')

@section('title', __('Nuevo perfil'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Nuevo perfil infantil') }}</h1>
    </div>

    <form method="POST" action="{{ route('profiles.store') }}" class="stack card narrow">
        @csrf
        <p class="muted small">{{ __('No pedimos nombre completo, fecha de nacimiento ni foto: un apodo basta.') }}</p>
        @include('panel.profiles.fields')
        <div class="form-actions">
            <button class="btn btn-primary">{{ __('Crear perfil') }}</button>
            <a href="{{ route('profiles.index') }}" class="btn btn-ghost">{{ __('Cancelar') }}</a>
        </div>
    </form>
@endsection
