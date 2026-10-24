@extends('layouts.guest')

@section('title', __('Entrar'))

@section('content')
    <div class="auth-card">
        <h1>{{ __('¡Ya casi estás!') }}</h1>
        <p>{!! __('Vas a entrar como <strong>:email</strong> en este navegador.', ['email' => e($email)]) !!}</p>
        <form method="POST" action="{{ route('login.link.consume', $token) }}">
            @csrf
            <button class="btn btn-primary btn-block btn-lg">{{ __('Entrar en mi videoteca') }}</button>
        </form>
        <p class="small muted">{{ __('Este enlace solo funciona una vez. Si no has pedido entrar, cierra esta página.') }}</p>
    </div>
@endsection
