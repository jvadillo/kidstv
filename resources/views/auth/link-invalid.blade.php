@extends('layouts.guest')

@section('title', __('Enlace no válido'))

@section('content')
    <div class="auth-card">
        <h1>{{ __('Este enlace ya no sirve') }}</h1>
        <p>{{ __('Los enlaces de acceso caducan a los :minutes minutos y solo se pueden usar una vez. También dejan de valer si pides un código nuevo.', ['minutes' => config('kidstv.login_token_ttl_minutes')]) }}</p>
        <a href="{{ route('login') }}" class="btn btn-primary btn-block">{{ __('Pedir un enlace nuevo') }}</a>
    </div>
@endsection
