@extends(auth()->check() ? 'layouts.panel' : 'layouts.guest')

@section('title', __('Ayuda con la tele'))

@section('content')
    <article class="prose">
        <h1>{{ __('Usar KidsTV en la tele') }}</h1>

        <h2>{{ __('Opción 1: app para LG webOS') }}</h2>
        <p>{{ __('La app todavía no está en la tienda de LG, así que se instala en «modo desarrollador». Solo hay que hacerlo una vez por tele (y renovar el modo desarrollador periódicamente).') }}</p>
        <ol>
            <li>{{ __('Crea una cuenta gratuita en webostv.developer.lge.com e inicia sesión con ella en la tele.') }}</li>
            <li>{{ __('En la tienda de la tele (LG Content Store) instala la app «Developer Mode», ábrela, activa «Dev Mode Status» y reinicia la tele.') }}</li>
            <li>{{ __('Vuelve a abrir «Developer Mode», activa «Key Server» y anota la IP y la frase de acceso (passphrase).') }}</li>
            <li>{{ __('Desde un ordenador en la misma red, instala el paquete kidstv-webos.ipk con las herramientas de LG (instrucciones detalladas en la documentación del proyecto, docs/webos.md).') }}</li>
            <li>{{ __('Abre KidsTV en la tele: verás un código. Escríbelo en «Teles → Conectar tele» desde tu móvil.') }}</li>
        </ol>
        <p><strong>{{ __('Importante:') }}</strong> {{ __('el modo desarrollador de LG caduca si no se renueva (el contador aparece en la app «Developer Mode»; se amplía pulsando «Extend»). Si caduca, la tele desinstala las apps instaladas así y habrá que repetir el paso 4.') }}</p>

        <h2>{{ __('Opción 2: cualquier navegador') }}</h2>
        <p>{{ __('Abre :url en un navegador (ordenador conectado a la tele, otra Smart TV o un móvil) y vincúlalo con el código. Desde el panel también puedes pulsar «Probar modo TV».', ['url' => url('/tv/')]) }}</p>

        <h2>{{ __('Manejo con el mando') }}</h2>
        <ul>
            <li>{{ __('Flechas para moverse, OK para elegir y Atrás para volver.') }}</li>
            <li>{{ __('Durante un vídeo: OK pausa o reanuda; izquierda y derecha retroceden o avanzan 10 segundos.') }}</li>
            <li>{{ __('La «Zona de adultos» pide el PIN parental, que se crea en «Cuenta».') }}</li>
        </ul>

        <h2>{{ __('Limitaciones conocidas') }}</h2>
        <ul>
            <li>{{ __('YouTube decide qué vídeos se pueden reproducir fuera de su web. Si un vídeo no está permitido, la tele lo indica y el contenido se marca como «No disponible» en el panel.') }}</li>
            <li>{{ __('El reproductor de YouTube puede mostrar anuncios, su logotipo o sugerencias, y no podemos quitarlos.') }}</li>
            <li>{{ __('Para vídeos propios, MP4 con H.264 y audio AAC es lo más compatible. Otros formatos pueden no reproducirse en televisores antiguos.') }}</li>
            <li>{{ __('Televisores LG muy antiguos (webOS 3 o anteriores) pueden no aceptar el certificado de seguridad o funcionar más lentos.') }}</li>
        </ul>
    </article>
@endsection
