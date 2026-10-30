@extends('layouts.panel')

@section('title', __('Perfiles'))

@section('content')
    <div class="page-head profiles-head">
        <h1>{{ __('Perfiles') }}</h1>
        <a href="{{ route('profiles.create') }}" class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('Nuevo perfil') }}</a>
    </div>

    <p class="muted">{{ __('Cada perfil ve en la tele solo los contenidos que le asignes.') }}</p>

    @if ($profiles->isEmpty())
        <div class="empty-state">
            <p>{{ __('Todavía no hay perfiles. Crea uno para cada peque de la casa.') }}</p>
            <a href="{{ route('profiles.create') }}" class="btn btn-primary">{{ __('Crear el primer perfil') }}</a>
        </div>
    @else
        @php($nameCounts = $profiles->countBy(fn ($profile) => mb_strtolower(trim($profile->name))))
        @php($lastByName = $profiles->keyBy(fn ($profile) => mb_strtolower(trim($profile->name))))
        <ul class="card-list profile-cards">
            @foreach ($profiles as $profile)
                @php($assignedCount = $profile->assignedContentCount())
                <li @class(['card', 'profile-detail-card', 'profile-empty' => ! $assignedCount])>
                    <a href="{{ route('profiles.edit', $profile) }}" class="card-link profile-card">
                        @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 64])
                        <div class="grow">
                            <strong class="profile-name">{{ $profile->name }}</strong>
                            <span @class(['profile-count', 'profile-count-empty' => ! $assignedCount])>
                                @if ($assignedCount)
                                    {{ trans_choice(':count contenido|:count contenidos', $assignedCount) }}
                                @else
                                    @if ($profile->ageLabel()){{ $profile->ageLabel() }} · @endif{{ __('sin contenidos') }}
                                @endif
                            </span>
                        </div>
                        @include('partials.icon', ['name' => 'right'])
                    </a>
                    @unless ($assignedCount)
                        <a href="{{ route('profiles.edit', $profile) }}" class="btn btn-accent btn-block profile-assign">{{ __('Asignar contenidos') }}</a>
                    @endunless
                    @if ($nameCounts[mb_strtolower(trim($profile->name))] > 1 && $lastByName[mb_strtolower(trim($profile->name))]->id === $profile->id)
                        <p class="duplicate-name muted">@include('partials.icon', ['name' => 'info']) <span>{{ __('Hay varios perfiles llamados :name. Cambia el nombre de uno para distinguirlos.', ['name' => $profile->name]) }}</span></p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
