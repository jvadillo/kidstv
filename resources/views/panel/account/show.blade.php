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
            <div class="field">
                <label for="youtube_controls">{{ __('Controles de YouTube en la tele') }}</label>
                <select id="youtube_controls" name="youtube_controls" aria-describedby="youtube_controls_hint">
                    <option value="auto" @selected(old('youtube_controls', $family->youtube_controls) === 'auto')>{{ __('Automáticos') }}</option>
                    <option value="always" @selected(old('youtube_controls', $family->youtube_controls) === 'always')>{{ __('Siempre visibles') }}</option>
                    <option value="hidden" @selected(old('youtube_controls', $family->youtube_controls) === 'hidden')>{{ __('Ocultos') }}</option>
                </select>
                <p id="youtube_controls_hint" class="hint">{{ __('La barra de KidsTV muestra pausa, avance y progreso debajo del vídeo. En automático aparece al usar el mando y se oculta tras unos segundos. Aunque la ocultes, el mando sigue funcionando. Se aplica en todas tus teles al abrir el siguiente vídeo.') }}</p>
            </div>
            @php($limitMode = old('daily_limit_mode', $family->daily_limit_mode))
            <fieldset class="field daily-limit" aria-describedby="daily_limit_hint">
                <legend>{{ __('Límite diario de tele') }}</legend>
                <p id="daily_limit_hint" class="hint">{{ __('Cuando quede poco tiempo, la tele avisa; al terminar, muestra una pantalla de descanso hasta el día siguiente. Se suma lo visto en todas las teles; lo que veas desde la Zona de adultos no cuenta.') }}</p>
                <label class="check">
                    <input type="radio" name="daily_limit_mode" value="off" class="daily-limit-mode" @checked($limitMode === 'off')>
                    <span>{{ __('Sin límite') }}</span>
                </label>
                <label class="check">
                    <input type="radio" name="daily_limit_mode" value="shared" class="daily-limit-mode" @checked($limitMode === 'shared')>
                    <span>
                        {{ __('Un tiempo compartido entre todos') }}
                        <span class="hint block">{{ __('Cuenta lo que ven todos los niños juntos: con 1 h, si uno ve 40 min, a los demás les quedan 20 min.') }}</span>
                    </span>
                </label>
                <div class="daily-limit-options daily-limit-shared">
                    @include('panel.account.limit-slider', [
                        'id' => 'daily_limit_step', 'name' => 'daily_limit_step', 'oldKey' => 'daily_limit_step',
                        'label' => __('Tiempo al día entre todos'), 'minutes' => $family->daily_limit_minutes,
                    ])
                </div>
                <label class="check">
                    <input type="radio" name="daily_limit_mode" value="per_profile" class="daily-limit-mode" @checked($limitMode === 'per_profile')>
                    <span>
                        {{ __('Un límite para cada niño') }}
                        <span class="hint block">{{ __('Cada niño tiene su propio tiempo al día, y lo que ve uno no cuenta para los demás.') }}</span>
                    </span>
                </label>
                <div class="daily-limit-options daily-limit-profiles">
                    @forelse ($family->profiles as $limitProfile)
                        @include('panel.account.limit-slider', [
                            'id' => 'profile_limit_'.$limitProfile->id, 'name' => 'profile_limit_steps['.$limitProfile->id.']',
                            'oldKey' => 'profile_limit_steps.'.$limitProfile->id, 'avatar' => $limitProfile->avatar,
                            'label' => $limitProfile->name, 'minutes' => $limitProfile->daily_limit_minutes,
                        ])
                    @empty
                        <p class="hint">{!! __('Aún no hay perfiles. <a href=":url">Crea el primero</a> para darle su tiempo.', ['url' => route('profiles.create')]) !!}</p>
                    @endforelse
                </div>
            </fieldset>
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
