@extends('layouts.panel')

@section('title', $user->email)

@php
    $fmt = fn (int $minutes) => \App\Services\UsageStats::format($minutes);
    $typeLabels = ['youtube' => 'YouTube', 'video' => __('Vídeos propios'), 'audio' => __('Audios')];
    $isSelf = auth()->user()->is($user);
@endphp

@section('content')
    <div class="page-head">
        <h1 class="admin-user-title">{{ $user->email }}</h1>
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">{{ __('Todos los usuarios') }}</a>
    </div>
    @include('admin.partials.nav')

    <p>
        @if ($user->name)<strong>{{ $user->name }}</strong> · @endif
        <span class="muted">{{ __('Alta el :date', ['date' => $user->created_at?->translatedFormat('j \d\e F \d\e Y')]) }}</span>
        @if ($user->isAdmin()) <span class="badge badge-processing">{{ __('Admin') }}</span>@endif
        @if ($user->isBlocked()) <span class="badge badge-error">{{ __('Bloqueada desde el :date', ['date' => $user->blocked_at->translatedFormat('j M Y')]) }}</span>@endif
    </p>

    <section class="stat-grid" aria-label="{{ __('Uso') }}">
        <div class="stat">
            <span class="stat-label">{{ __('Visto en 7 días') }}</span>
            <strong class="stat-value">{{ $fmt($minutes['week']) }}</strong>
            <span class="stat-sub">{{ __('30 días: :month · total: :total', ['month' => $fmt($minutes['month']), 'total' => $fmt($minutes['total'])]) }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">{{ __('Último acceso al panel') }}</span>
            <strong class="stat-value stat-value-sm">{{ $lastSeen ? \Illuminate\Support\Carbon::parse($lastSeen)->diffForHumans() : __('Nunca') }}</strong>
            <span class="stat-sub">{{ trans_choice(':count sesión abierta|:count sesiones abiertas', $sessions) }}</span>
        </div>
        <div class="stat">
            <span class="stat-label">{{ __('Contenidos') }}</span>
            <strong class="stat-value">{{ $contents->sum() }}</strong>
            <span class="stat-sub">
                {{ collect($typeLabels)->map(fn ($label, $type) => $label.': '.($contents[$type] ?? 0))->implode(' · ') }}
            </span>
        </div>
        <div class="stat">
            <span class="stat-label">{{ __('Espacio') }}</span>
            <strong class="stat-value stat-value-sm">{{ format_bytes($storage) }}</strong>
            @php $pct = $quota > 0 ? min(100, round($storage / $quota * 100)) : 0; @endphp
            <span class="stat-sub">{{ __(':pct % de :quota', ['pct' => $pct, 'quota' => format_bytes($quota)]) }}</span>
        </div>
    </section>

    <section class="section card">
        @include('admin.partials.daily-chart', ['daily' => $daily, 'title' => __('Minutos vistos por día (últimos 30 días)')])
    </section>

    <div class="admin-columns">
        <section class="card">
            <h2>{{ __('Perfiles') }}</h2>
            @if ($profiles->isEmpty())
                <p class="muted">{{ __('Sin perfiles infantiles.') }}</p>
            @else
                <ul class="device-list">
                    @foreach ($profiles as $profile)
                        <li>
                            @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 28])
                            <strong class="grow">{{ $profile->name }}</strong>
                            <span class="muted small">{{ trans_choice(':count contenido asignado|:count contenidos asignados', $profile->contents_count) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($byType->isNotEmpty())
                <h3 class="small">{{ __('Minutos por tipo (30 días)') }}</h3>
                <dl class="facts">
                    @foreach ($byType as $type => $value)
                        <dt>{{ $typeLabels[$type] ?? $type }}</dt><dd>{{ $fmt($value) }}</dd>
                    @endforeach
                </dl>
            @endif
        </section>

        <section class="card">
            <h2>{{ __('Televisores') }}</h2>
            @if ($devices->isEmpty())
                <p class="muted">{{ __('Ninguno vinculado.') }}</p>
            @else
                <ul class="device-list">
                    @foreach ($devices as $device)
                        <li>
                            <span @class(['dot', 'dot-on' => $device->isOnline()]) aria-hidden="true"></span>
                            <strong class="grow">{{ $device->name }}</strong>
                            <span class="muted small">{{ $device->platformLabel() }} · {{ $device->last_seen_at ? __('visto :time', ['time' => $device->last_seen_at->diffForHumans()]) : __('sin conectar') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <h3 class="small">{{ __('Listas') }}</h3>
            <p class="muted small">
                {{ $lists->map(fn ($list) => $list->displayName().($list->isLinked() ? ' ('.__('pública').')' : ''))->implode(' · ') ?: __('Sin listas') }}
            </p>
        </section>
    </div>

    @unless ($isSelf)
        <section class="section">
            <h2>{{ __('Acciones') }}</h2>
            <div class="card stack">
                <div class="settings-row">
                    <div>
                        <strong>{{ $user->isBlocked() ? __('Cuenta bloqueada') : __('Bloquear la cuenta') }}</strong>
                        <span class="muted small">{{ __('Una cuenta bloqueada no puede entrar en el panel y sus teles dejan de mostrar la videoteca. No se borra nada.') }}</span>
                    </div>
                    <form method="POST" action="{{ route($user->isBlocked() ? 'admin.users.unblock' : 'admin.users.block', $user) }}"
                          @unless ($user->isBlocked()) data-confirm="{{ __('¿Bloquear la cuenta de :email? Se cerrarán sus sesiones y sus teles dejarán de funcionar.', ['email' => $user->email]) }}" @endunless>
                        @csrf
                        @if ($user->isBlocked()) @method('DELETE') @endif
                        <button class="btn btn-sm {{ $user->isBlocked() ? 'btn-primary' : 'btn-danger-ghost' }}">{{ $user->isBlocked() ? __('Desbloquear') : __('Bloquear') }}</button>
                    </form>
                </div>
                <div class="settings-row">
                    <div>
                        <strong>{{ __('Administrador') }}</strong>
                        <span class="muted small">{{ __('Puede ver este panel, gestionar cuentas y publicar listas.') }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.users.admin', $user) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="is_admin" value="{{ $user->isAdmin() ? 0 : 1 }}">
                        <button class="btn btn-sm btn-ghost">{{ $user->isAdmin() ? __('Quitar permisos') : __('Hacer administrador') }}</button>
                    </form>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="card card-danger stack section">
                @csrf
                @method('DELETE')
                <div>
                    <strong>{{ __('Eliminar la cuenta') }}</strong>
                    <p class="muted small">{{ __('Borra la cuenta y todos los datos de su familia: perfiles, contenidos y archivos subidos, listas y teles. No se puede deshacer.') }}</p>
                </div>
                <div class="field">
                    <label for="confirmation">{{ __('Escribe :email para confirmar', ['email' => $user->email]) }}</label>
                    <input type="text" id="confirmation" name="confirmation" autocomplete="off" required>
                    @error('confirmation')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div><button class="btn btn-danger">{{ __('Eliminar definitivamente') }}</button></div>
            </form>
        </section>
    @else
        <p class="notice">{{ __('Es tu propia cuenta: gestiónala desde «Cuenta».') }}</p>
    @endunless
@endsection
