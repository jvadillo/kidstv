@extends('layouts.panel')

@section('title', __('Televisores'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Televisores') }}</h1>
        <a href="{{ route('devices.pair') }}" class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) {{ __('Conectar tele') }}</a>
    </div>

    @if ($devices->isEmpty())
        <div class="empty-state">
            <p>{{ __('Todavía no has conectado ningún televisor.') }}</p>
            <p class="muted">{{ __('Abre la app NokoPlay en la tele: mostrará un código. Luego pulsa «Conectar tele» aquí y escríbelo.') }}</p>
        </div>
    @else
        <ul class="card-list">
            @foreach ($devices as $device)
                <li class="card device-card">
                    <div class="device-head">
                        <span @class(['dot', 'dot-on' => $device->isOnline()]) aria-hidden="true"></span>
                        <div>
                            <strong class="big">{{ $device->name }}</strong>
                            <span class="muted small">
                                {{ $device->platformLabel() }} ·
                                {{ $device->isOnline() ? __('Conectado ahora') : ($device->last_seen_at ? __('Visto :time', ['time' => $device->last_seen_at->diffForHumans()]) : __('Aún no se ha conectado')) }}
                                · {{ __('Vinculado el :date', ['date' => $device->created_at->isoFormat('D MMM YYYY')]) }}
                            </span>
                        </div>
                    </div>
                    <div class="device-actions">
                        <form method="POST" action="{{ route('devices.update', $device) }}" class="inline-form">
                            @csrf
                            @method('PUT')
                            <label class="sr-only" for="dev-{{ $device->id }}">{{ __('Nombre') }}</label>
                            <input type="text" id="dev-{{ $device->id }}" name="name" value="{{ $device->name }}" maxlength="40" required>
                            <button class="btn btn-sm btn-ghost">{{ __('Renombrar') }}</button>
                        </form>
                        <form method="POST" action="{{ route('devices.destroy', $device) }}"
                              data-confirm="{{ __('¿Desvincular «:name»? Dejará de mostrar la videoteca y habrá que volver a conectarlo con un código.', ['name' => $device->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger-ghost">{{ __('Desvincular') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <section class="section card">
        <h2>{{ __('App para televisores LG (webOS)') }}</h2>
        <p>{{ __('La app de NokoPlay se instala en la tele en modo desarrollador (todavía no está en la tienda de LG). Las instrucciones paso a paso están en la ayuda.') }}</p>
        <div class="form-actions">
            <a href="{{ route('help.tv') }}" class="btn btn-ghost">{{ __('Cómo instalarla') }}</a>
            @if ($ipkAvailable)
                <a href="{{ asset(config('nokoplay.webos_ipk')) }}" class="btn btn-ghost" download>{{ __('Descargar paquete .ipk') }}</a>
            @endif
        </div>
        <p class="small muted">{{ __('También puedes usar cualquier navegador como «tele» con el botón «Probar modo TV».') }}</p>
    </section>
@endsection
