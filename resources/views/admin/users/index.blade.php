@extends('layouts.panel')

@section('title', __('Usuarios'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Administración') }}</h1>
    </div>
    @include('admin.partials.nav')

    <form method="GET" class="filters" role="search" aria-label="{{ __('Buscar usuarios') }}">
        <input type="search" name="q" value="{{ $query }}" maxlength="100" placeholder="{{ __('Correo o nombre') }}" aria-label="{{ __('Correo o nombre') }}">
        <select name="orden" aria-label="{{ __('Ordenar') }}">
            @foreach (['recientes' => __('Altas recientes'), 'actividad' => __('Último acceso'), 'uso' => __('Más minutos (30 días)'), 'espacio' => __('Más espacio')] as $value => $label)
                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="estado" aria-label="{{ __('Estado') }}">
            <option value="">{{ __('Todas las cuentas') }}</option>
            <option value="bloqueadas" @selected($state === 'bloqueadas')>{{ __('Bloqueadas') }}</option>
            <option value="administradores" @selected($state === 'administradores')>{{ __('Administradores') }}</option>
        </select>
        <button class="btn btn-sm btn-ghost">{{ __('Filtrar') }}</button>
    </form>

    <p class="small muted">{{ trans_choice(':count cuenta|:count cuentas', $users->total()) }}</p>

    @if ($users->isEmpty())
        <p class="empty">{{ __('Ninguna cuenta coincide con la búsqueda.') }}</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Cuenta') }}</th>
                        <th scope="col">{{ __('Alta') }}</th>
                        <th scope="col">{{ __('Último acceso al panel') }}</th>
                        <th scope="col" class="num">{{ __('Perfiles') }}</th>
                        <th scope="col" class="num">{{ __('Contenidos') }}</th>
                        <th scope="col" class="num">{{ __('Teles') }}</th>
                        <th scope="col" class="num">{{ __('Espacio') }}</th>
                        <th scope="col" class="num">{{ __('Visto (30 días)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $user) }}"><strong>{{ $user->email }}</strong></a>
                                @if ($user->name)<span class="block muted small">{{ $user->name }}</span>@endif
                                @if ($user->isAdmin())<span class="badge badge-processing">{{ __('Admin') }}</span>@endif
                                @if ($user->isBlocked())<span class="badge badge-error">{{ __('Bloqueada') }}</span>@endif
                            </td>
                            <td>{{ $user->created_at?->translatedFormat('j M Y') }}</td>
                            <td>{{ $user->last_seen_at ? \Illuminate\Support\Carbon::parse($user->last_seen_at)->diffForHumans() : '—' }}</td>
                            <td class="num">{{ $user->profiles_count }}</td>
                            <td class="num">{{ $user->contents_count }}</td>
                            <td class="num">{{ $user->devices_count }}</td>
                            <td class="num">{{ format_bytes((int) $user->storage) }}</td>
                            <td class="num">{{ \App\Services\UsageStats::format((int) $user->minutes_30d) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <nav class="pager" aria-label="{{ __('Páginas') }}">
                @if ($users->previousPageUrl())<a class="btn btn-ghost" href="{{ $users->previousPageUrl() }}">{{ __('Anteriores') }}</a>@endif
                <span class="muted small pager-info">{{ __('Página :page de :pages', ['page' => $users->currentPage(), 'pages' => $users->lastPage()]) }}</span>
                @if ($users->nextPageUrl())<a class="btn btn-ghost" href="{{ $users->nextPageUrl() }}">{{ __('Siguientes') }}</a>@endif
            </nav>
        @endif
    @endif
@endsection
