@extends('layouts.panel')

@section('title', __('Cuenta'))

@section('content')
    <div class="page-head">
        <h1>{{ __('Cuenta y seguridad') }}</h1>
    </div>

    <section class="section">
        <h2>{{ __('Preferencias') }}</h2>
        <form method="POST" action="{{ route('account.update') }}" class="stack card narrow">
            @csrf
            @method('PUT')
            <div class="field">
                <label for="name">{{ __('Tu nombre') }} <span class="optional">{{ __('(opcional)') }}</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="60">
            </div>
            <div class="field">
                <label for="family_name">{{ __('Nombre de la videoteca') }} <span class="optional">{{ __('(opcional)') }}</span></label>
                <input type="text" id="family_name" name="family_name" value="{{ old('family_name', $family->name) }}" maxlength="60" placeholder="{{ __('Por ejemplo: La tele de casa') }}">
            </div>
            <input type="hidden" name="autoplay_next" value="0">
            <label class="check">
                <input type="checkbox" name="autoplay_next" value="1" @checked($family->autoplay_next)>
                <span>
                    {{ __('Reproducir automáticamente el siguiente contenido') }}
                    <span class="hint block">{{ __('Desactivado (recomendado): al terminar un vídeo, la tele vuelve al catálogo.') }}</span>
                </span>
            </label>
            <div class="form-actions"><button class="btn btn-primary">{{ __('Guardar') }}</button></div>
        </form>
    </section>

    <section class="section">
        <h2>{{ __('Seguridad') }}</h2>
        <ul class="card-list">
            <li class="card settings-row">
                <div>
                    <strong>{{ __('PIN parental') }}</strong>
                    <span class="muted small">{{ $family->hasPin() ? __('Configurado. Protege la Zona de adultos en la tele.') : __('Sin configurar: la Zona de adultos de la tele no se puede abrir.') }}</span>
                </div>
                <a href="{{ route('account.pin') }}" class="btn btn-sm {{ $family->hasPin() ? 'btn-ghost' : 'btn-primary' }}">{{ $family->hasPin() ? __('Cambiar') : __('Crear PIN') }}</a>
            </li>
            <li class="card settings-row">
                <div>
                    <strong>{{ __('Correo') }}</strong>
                    <span class="muted small">{{ $user->email }}</span>
                </div>
                <a href="{{ route('account.email') }}" class="btn btn-sm btn-ghost">{{ __('Cambiar') }}</a>
            </li>
            <li class="card settings-row">
                <div>
                    <strong>{{ __('Televisores') }}</strong>
                    <span class="muted small">{{ __('Ver y desvincular las teles conectadas.') }}</span>
                </div>
                <a href="{{ route('devices.index') }}" class="btn btn-sm btn-ghost">{{ __('Gestionar') }}</a>
            </li>
        </ul>
        <p class="small muted">{{ __('Cambiar el PIN o el correo, exportar o borrar la cuenta te pedirá un código enviado a tu correo.') }}</p>
    </section>

    <section class="section">
        <div class="section-head">
            <h2>{{ __('Sesiones abiertas') }}</h2>
            @if ($sessions->count() > 1)
                <form method="POST" action="{{ route('account.sessions.revoke-others') }}" data-confirm="{{ __('¿Cerrar la sesión en todos los demás dispositivos?') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-ghost">{{ __('Cerrar las demás') }}</button>
                </form>
            @endif
        </div>
        <ul class="card-list">
            @foreach ($sessions as $session)
                <li class="card settings-row">
                    <div>
                        <strong>{{ $session->deviceLabel() }}</strong>
                        @if ($session->id === $currentSessionId)<span class="badge badge-ready">{{ __('Este navegador') }}</span>@endif
                        <span class="muted small block">{{ __('Último uso :time', ['time' => $session->last_used_at?->diffForHumans() ?? '—']) }} · {{ __('Caduca :time', ['time' => $session->expires_at->diffForHumans()]) }}</span>
                    </div>
                    <form method="POST" action="{{ route('account.sessions.revoke', $session) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-ghost">{{ $session->id === $currentSessionId ? __('Cerrar sesión') : __('Revocar') }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="section">
        <h2>{{ __('Tus datos') }}</h2>
        <div class="card stack-sm">
            <p>{{ __('Puedes descargar todos los datos de tu familia en un archivo, o eliminar la cuenta con todos sus perfiles, contenidos y archivos.') }}</p>
            <div class="form-actions">
                <a href="{{ route('account.export') }}" class="btn btn-ghost">{{ __('Exportar mis datos') }}</a>
                <a href="{{ route('account.delete') }}" class="btn btn-danger-ghost">{{ __('Eliminar cuenta') }}</a>
            </div>
            <p class="small muted"><a href="{{ route('privacy') }}">{{ __('Política de privacidad') }}</a> · <a href="{{ route('terms') }}">{{ __('Condiciones de uso') }}</a></p>
        </div>
    </section>

    <form method="POST" action="{{ route('logout') }}" class="section">
        @csrf
        <button class="btn btn-ghost btn-block">{{ __('Cerrar sesión en este navegador') }}</button>
    </form>
@endsection
