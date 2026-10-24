@extends(auth()->check() ? 'layouts.panel' : 'layouts.guest')

@section('title', __('Privacidad'))

@section('content')
    <article class="prose">
        <h1>{{ __('Política de privacidad') }}</h1>
        <p class="muted small">{{ __('Versión 1.0 · Pendiente de revisión legal antes de abrir el servicio al público.') }}</p>

        <h2>{{ __('Qué datos tratamos') }}</h2>
        <ul>
            <li><strong>{{ __('Persona adulta:') }}</strong> {{ __('correo electrónico, nombre visible opcional, fecha de aceptación de las condiciones y datos técnicos de las sesiones (navegador, dirección IP y última actividad) para poder mostrarte y revocar tus sesiones.') }}</li>
            <li><strong>{{ __('Perfiles infantiles:') }}</strong> {{ __('solo un apodo y un avatar predefinido. No pedimos nombres completos, fechas de nacimiento ni fotografías.') }}</li>
            <li><strong>{{ __('Contenidos:') }}</strong> {{ __('los archivos que subes, sus portadas y datos (título, categoría, descripción) y los enlaces de YouTube que añades.') }}</li>
            <li><strong>{{ __('Televisores:') }}</strong> {{ __('nombre que les pones, tipo de dispositivo y fecha de última conexión.') }}</li>
        </ul>
        <p>{{ __('No registramos qué ve cada niño ni construimos perfiles de uso. No hay publicidad ni seguimiento comercial.') }}</p>

        <h2>{{ __('Para qué los usamos') }}</h2>
        <p>{{ __('Exclusivamente para prestar el servicio: identificarte por correo, guardar tu videoteca y mostrarla en los televisores que tú vinculas. La base legal es la ejecución del servicio que solicitas.') }}</p>

        <h2>{{ __('Dónde se guardan') }}</h2>
        <p>{{ __('En un servidor privado virtual (VPS) administrado por el responsable del servicio. No usamos servicios externos de identificación, bases de datos ni almacenamiento. El envío de los correos con códigos se realiza a través de un proveedor de envío de correo, que solo recibe tu dirección y el mensaje.') }}</p>
        <p>{{ __('Tus archivos son privados: solo se entregan a los televisores vinculados a tu familia mediante enlaces temporales firmados, y no se incluyen en buscadores ni índices públicos.') }}</p>

        <h2>{{ __('YouTube') }}</h2>
        <p>{{ __('Los vídeos de YouTube se reproducen con el reproductor oficial en modo de privacidad mejorada (youtube-nocookie.com). Al reproducirlos, el televisor se conecta directamente con YouTube (Google), que aplica su propia política de privacidad y puede mostrar elementos que no controlamos. Nunca descargamos ni copiamos esos vídeos.') }}</p>

        <h2>{{ __('Cuánto tiempo') }}</h2>
        <p>{{ __('Mientras mantengas la cuenta. Los códigos de acceso caducan en minutos y se eliminan en 24 horas; las sesiones caducan tras :days días sin uso. Al eliminar la cuenta borramos de inmediato perfiles, contenidos y archivos; las copias de seguridad del servidor, si existen, se sobrescriben en un plazo máximo de 30 días.', ['days' => config('kidstv.auth_session_days')]) }}</p>

        <h2>{{ __('Tus derechos') }}</h2>
        <p>{{ __('Desde «Cuenta» puedes exportar todos tus datos, cambiar tu correo y eliminar la cuenta. Para cualquier otra solicitud (acceso, rectificación, oposición, limitación) escribe al responsable del servicio. También puedes reclamar ante la Agencia Española de Protección de Datos.') }}</p>

        <h2>{{ __('Menores') }}</h2>
        <p>{{ __('La cuenta la crea y gestiona siempre una persona adulta responsable. Los niños no tienen credenciales ni pueden comunicarse con nadie a través del servicio. Verificar un correo no acredita por sí solo la patria potestad: al aceptar las condiciones declaras ser responsable de los menores que usarán la videoteca.') }}</p>
    </article>
@endsection
