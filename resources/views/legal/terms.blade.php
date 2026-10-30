@extends(auth()->check() ? 'layouts.panel' : 'layouts.guest')

@section('title', __('Condiciones'))

@section('content')
    <article class="prose">
        <h1>{{ __('Condiciones de uso') }}</h1>
        <p class="muted small">{{ __('Versión 1.0 · Pendiente de revisión legal antes de abrir el servicio al público.') }}</p>

        <h2>{{ __('El servicio') }}</h2>
        <p>{{ __('NokoPlay es una videoteca familiar privada: una persona adulta selecciona vídeos, audios y enlaces de YouTube y los ofrece a sus hijos en el televisor. Es un servicio gratuito, en fase inicial, que se ofrece tal cual y sin garantía de disponibilidad continua.') }}</p>

        <h2>{{ __('Tu cuenta') }}</h2>
        <ul>
            <li>{{ __('Debes ser mayor de edad y responsable de los menores que usen la videoteca.') }}</li>
            <li>{{ __('Accedes con tu correo: protege el acceso a tu correo y cierra las sesiones de dispositivos que no reconozcas.') }}</li>
            <li>{{ __('Eres responsable de los contenidos que añades y de lo que ven los menores a tu cargo.') }}</li>
        </ul>

        <h2>{{ __('Contenidos') }}</h2>
        <ul>
            <li>{{ __('Sube solo archivos que tengas derecho a usar de forma privada en tu familia. Están prohibidos los contenidos ilícitos.') }}</li>
            <li>{{ __('Hay límites de tamaño por archivo y de espacio total por familia, que se indican al subir.') }}</li>
            <li>{{ __('Los contenidos de YouTube se rigen por las condiciones de YouTube. No garantizamos que un vídeo siga disponible ni que no muestre anuncios, logotipos o sugerencias.') }}</li>
        </ul>

        <h2>{{ __('Uso aceptable') }}</h2>
        <p>{{ __('No intentes acceder a datos de otras familias, automatizar registros ni sobrecargar el servicio. Podemos suspender cuentas que incumplan estas condiciones.') }}</p>

        <h2>{{ __('Baja') }}</h2>
        <p>{{ __('Puedes eliminar tu cuenta en cualquier momento desde «Cuenta». Se borrarán tus perfiles, contenidos y archivos.') }}</p>
    </article>
@endsection
