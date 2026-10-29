@extends('layouts.onboarding')

@section('title', __('Su tele'))
@section('step', '3')
@section('main-class', 'ob-main-wide')

@php
    $avatar = $draft['avatar'] ?? 'estrella';
    $videos = $rows->sum('contents_count');
    $duration = fn (?int $s) => $s ? ($s >= 3600 ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60) : sprintf('%d:%02d', intdiv($s, 60), $s % 60)) : null;
@endphp

@section('content')
    <div class="ob-preview">
        <div class="ob-intro center">
            <p class="ob-kicker">@include('partials.icon', ['name' => 'check']) {{ __('¡Ya la tienes preparada!') }}</p>
            <h1>{{ __('Esta es la tele de :name', ['name' => $draft['name']]) }}</h1>
            <p class="muted">{{ __('Así la verá en casa: solo las series que has elegido, sin buscador ni vídeos sugeridos. Solo falta conectarla para empezar a ver.') }}</p>
        </div>

        {{-- Réplica de la app de TV (mismas proporciones, a escala): «la tele de su hijo». --}}
        <figure class="tv-mock" data-tv-preview>
            <div class="tv-mock-screen">
                <div class="tvp" role="img" aria-label="{{ __('Vista previa de la tele de :name con :count series', ['name' => $draft['name'], 'count' => $rows->count()]) }}">
                    <div class="tvp-header">
                        <div class="tvp-who">
                            <img src="{{ asset('avatars/'.$avatar.'.svg') }}" alt="">
                            <span>{{ $draft['name'] }}</span>
                            <small>{{ __('Cambiar') }}</small>
                        </div>
                        <div class="tvp-lock">@include('partials.icon', ['name' => 'lock'])</div>
                    </div>
                    <div class="tvp-rows">
                        @foreach ($rows->take(4) as $row)
                            <section class="tvp-row">
                                <h2 class="tvp-row-title">{{ $row->name }} <span>{{ $row->contents_count }}</span></h2>
                                <div class="tvp-track">
                                    @foreach ($row->contents->take(7) as $content)
                                        <div @class(['tvp-card', 'focused' => $loop->parent->first && $loop->first])>
                                            <div class="tvp-cover">
                                                @if ($content->youtubeThumbnail())<img src="{{ $content->youtubeThumbnail() }}" alt="">@endif
                                                @if ($duration($content->duration))<span>{{ $duration($content->duration) }}</span>@endif
                                            </div>
                                            <div class="tvp-title">{{ $content->title }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="tv-mock-stand" aria-hidden="true"></div>
            <figcaption class="small muted">
                {{ trans_choice(':count serie|:count series', $rows->count()) }} · {{ trans_choice(':count vídeo|:count vídeos', $videos) }} ·
                <a href="{{ route('onboarding.lists') }}">{{ __('Cambiar series') }}</a>
            </figcaption>
        </figure>

        <form method="POST" action="{{ route('onboarding.choose') }}" class="ob-final">
            @csrf
            <button name="next" value="connect" class="btn btn-accent btn-lg btn-block">@include('partials.icon', ['name' => 'tv']) {{ session('onboarding_pairing') ? __('Guardar y conectar esta tele') : __('Conectar mi tele') }}</button>
            @unless(session('onboarding_pairing'))
            <button name="next" value="later" class="btn btn-ghost btn-lg btn-block">{{ __('Lo haré más tarde') }}</button>
            @endunless
            @if ($guest)
                <p class="hint center">{{ __('Para guardarla solo te pediremos tu correo. Sin contraseñas.') }}</p>
            @endif
        </form>
    </div>
@endsection
