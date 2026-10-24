@extends('layouts.guest')

@section('title', $title)

@section('content')
    <div class="auth-card">
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-block">{{ __('Volver al inicio') }}</a>
    </div>
@endsection
