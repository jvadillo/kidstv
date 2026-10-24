@extends('layouts.panel')

@section('title', __('Perfiles'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Perfiles infantiles') }}</h1>
        <a href="{{ route('profiles.create') }}" class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('Nuevo perfil') }}</a>
    </div>

    <p class="muted">{{ __('Cada perfil ve en la tele solo los contenidos que le asignes.') }}</p>

    @if ($profiles->isEmpty())
        <div class="empty-state">
            <p>{{ __('Todavía no hay perfiles. Crea uno para cada peque de la casa.') }}</p>
            <a href="{{ route('profiles.create') }}" class="btn btn-primary">{{ __('Crear el primer perfil') }}</a>
        </div>
    @else
        <ul class="card-list">
            @foreach ($profiles as $profile)
                <li>
                    <a href="{{ route('profiles.edit', $profile) }}" class="card card-link profile-card">
                        @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 72])
                        <div>
                            <strong class="big">{{ $profile->name }}</strong>
                            <span class="muted">{{ trans_choice(':count contenido asignado|:count contenidos asignados', $profile->contents_count) }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
