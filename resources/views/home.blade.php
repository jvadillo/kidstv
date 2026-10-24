@extends('layouts.guest')

@section('title', __('Bienvenida'))

@section('content')
    <section class="hero">
        <h1>{{ __('La videoteca de tu familia, en la tele y sin sorpresas') }}</h1>
        <p class="lead">{{ __('Tú eliges los vídeos, audios y enlaces de YouTube. Tus hijos solo ven lo que has aprobado, con el mando a distancia y sin buscadores.') }}</p>
        <div class="hero-actions">
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">{{ __('Empezar con mi correo') }}</a>
        </div>
        <p class="muted small">{{ __('Sin contraseñas: te enviamos un código cada vez que entras desde un dispositivo nuevo.') }}</p>
    </section>

    <section class="features">
        <div class="feature">
            <h2>{{ __('Tú decides') }}</h2>
            <p>{{ __('Sube tus propios vídeos (MP4) y audios (MP3), o añade vídeos de YouTube con su enlace. Nada se comparte con otras familias.') }}</p>
        </div>
        <div class="feature">
            <h2>{{ __('Un perfil por peque') }}</h2>
            <p>{{ __('Cada perfil ve solo los contenidos que le asignas. Sin reproducción infinita ni recomendaciones.') }}</p>
        </div>
        <div class="feature">
            <h2>{{ __('Pensado para la tele') }}</h2>
            <p>{{ __('App para televisores LG (webOS) que se maneja con flechas y OK. La zona de adultos está protegida con PIN.') }}</p>
        </div>
    </section>
@endsection
