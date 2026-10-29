@extends('layouts.guest')

@section('title', __('Crear cuenta'))

@section('content')
    <div class="auth-card">
        <h1>{{ __('Correo verificado') }}</h1>
        <p>{!! __('Vamos a crear la videoteca de tu familia con <strong>:email</strong>.', ['email' => e($email)]) !!}</p>

        <form method="POST" action="{{ route('register.store') }}" class="stack">
            @csrf
            <div class="field">
                <label for="name">{{ __('¿Cómo te llamamos?') }} <span class="optional">{{ __('(opcional)') }}</span></label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="60" autocomplete="given-name">
                <p class="hint">{{ __('Solo se muestra en tu panel. Puedes cambiarlo después.') }}</p>
            </div>

            <div class="notice">
                <p><strong>{{ __('Antes de empezar') }}</strong></p>
                <ul>
                    <li>{{ __('Guardamos tu correo y lo que subas en nuestro propio servidor, solo para que funcione tu videoteca.') }}</li>
                    <li>{{ __('De los niños solo pedimos un apodo, un avatar y su etapa de edad. Nada de fotos, apellidos ni fechas de nacimiento.') }}</li>
                    <li>{{ __('Sin publicidad ni perfiles comerciales. Puedes exportar o borrar tus datos cuando quieras.') }}</li>
                </ul>
            </div>

            <label class="check">
                <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                <span>{!! __('He leído y acepto las <a href=":terms" target="_blank">condiciones de uso</a> y la <a href=":privacy" target="_blank">política de privacidad</a>, y soy una persona adulta responsable de los menores que usarán la videoteca.', ['terms' => route('terms'), 'privacy' => route('privacy')]) !!}</span>
            </label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror

            <button class="btn btn-primary btn-block btn-lg">{{ __('Crear mi videoteca') }}</button>
        </form>
    </div>
@endsection
