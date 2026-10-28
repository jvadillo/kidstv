@extends('layouts.panel')

@section('title', __('Actividad'))

@use('App\Services\ActivityReport')

@php
    $url = fn (array $changes = []) => route('activity', array_filter(array_merge($query, $changes), fn ($v) => $v !== null));
    $total = $report->totalSeconds();
    $periods = ['dia' => __('Día'), 'semana' => __('Semana'), 'mes' => __('Mes')];
@endphp

@section('content')
<div class="activity-page">
    <header class="activity-head">
        <div class="activity-title">
            <h1>{{ __('Actividad') }} @include('panel.activity.watching')</h1>
            <p class="muted">{{ $profile ? __('Qué y cuánto ve :name en la tele', ['name' => $profile->name]) : __('Qué y cuánto ven en la tele') }}</p>
        </div>

        <details class="menu activity-profile" data-menu>
            <summary class="btn btn-ghost" aria-label="{{ __('Elegir perfil') }}">
                @if ($profile)
                    @include('partials.avatar', ['avatar' => $profile->avatar, 'size' => 28])
                    <span class="activity-profile-name">{{ $profile->name }}</span>
                @else
                    <span class="avatar-all">@include('partials.icon', ['name' => 'kids'])</span>
                    <span class="activity-profile-name">{{ __('Todos') }}</span>
                @endif
                @include('partials.icon', ['name' => 'down'])
            </summary>
            <div class="menu-panel menu-panel-end">
                <a href="{{ $url(['perfil' => null]) }}" class="menu-item" @if (! $profile) aria-current="true" @endif>
                    <span class="avatar-all">@include('partials.icon', ['name' => 'kids'])</span> {{ __('Todos') }}
                </a>
                @foreach ($profiles as $option)
                    <a href="{{ $url(['perfil' => $option->id]) }}" class="menu-item" @if ($profile?->id === $option->id) aria-current="true" @endif>
                        @include('partials.avatar', ['avatar' => $option->avatar, 'size' => 28]) {{ $option->name }}
                    </a>
                @endforeach
            </div>
        </details>
    </header>

    <nav class="segmented segmented-block" aria-label="{{ __('Periodo') }}">
        @foreach ($periods as $key => $label)
            {{-- Al cambiar de periodo se vuelve al actual: «Día» es hoy, «Mes» este mes. --}}
            <a href="{{ $url(['periodo' => $key === 'semana' ? null : $key, 'fecha' => null]) }}" @class(['active' => $report->period === $key]) @if ($report->period === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Tiempo de pantalla --}}
    <section class="card activity-card" id="tiempo" aria-labelledby="tiempo-title">
        <div class="activity-card-head">
            <h2 id="tiempo-title">{{ __('Tiempo de pantalla') }}</h2>
            <span class="muted small">{{ $report->label() }}</span>
        </div>
        <p class="activity-total">{{ ActivityReport::duration($total) }}</p>
        <dl class="activity-metrics">
            @foreach ($report->metrics() as $metric)
                <div><dt>{{ $metric['label'] }}</dt><dd>{{ $metric['value'] }}</dd></div>
            @endforeach
        </dl>

        @include('panel.activity.bars', ['bars' => $report->bars(), 'title' => $report->period === 'mes' ? __('Media diaria por semana') : __('Minutos vistos')])

        <div class="period-nav">
            @if ($report->hasPrevious())
                <a href="{{ $url(['fecha' => $report->previousDate()]) }}#tiempo" class="btn btn-sm btn-ghost">@include('partials.icon', ['name' => 'left']) <span class="period-nav-text">{{ __('Anterior') }}</span></a>
            @else
                <span class="btn btn-sm btn-ghost" aria-disabled="true">@include('partials.icon', ['name' => 'left']) <span class="period-nav-text">{{ __('Anterior') }}</span></span>
            @endif
            <span class="period-nav-label small">{{ $report->dates() }}</span>
            @if ($report->hasNext())
                <a href="{{ $url(['fecha' => $report->nextDate()]) }}#tiempo" class="btn btn-sm btn-ghost"><span class="period-nav-text">{{ __('Siguiente') }}</span> @include('partials.icon', ['name' => 'right'])</a>
            @else
                <span class="btn btn-sm btn-ghost" aria-disabled="true"><span class="period-nav-text">{{ __('Siguiente') }}</span> @include('partials.icon', ['name' => 'right'])</span>
            @endif
        </div>
    </section>

    {{-- Lo que más ve --}}
    @php
        $tops = ['tiempo' => $report->top('tiempo'), 'veces' => $report->top('veces')];
    @endphp
    <section class="card activity-card top-card" aria-labelledby="top-title">
        <div class="activity-card-head">
            <h2 id="top-title">{{ $profile ? __('Lo que más ve') : __('Lo que más ven') }}</h2>
        </div>
        @if ($tops['tiempo']->isEmpty())
            <p class="empty">{{ __('Nada visto en este periodo.') }}</p>
        @else
            <fieldset class="segmented segmented-block segmented-radio">
                <legend class="sr-only">{{ __('Ordenar') }}</legend>
                <input type="radio" name="top-orden" id="orden-tiempo" value="tiempo" checked>
                <label for="orden-tiempo">{{ __('Por tiempo') }}</label>
                <input type="radio" name="top-orden" id="orden-veces" value="veces">
                <label for="orden-veces">{{ __('Por veces') }}</label>
            </fieldset>
            @foreach ($tops as $by => $rows)
                @php
                    $max = max(1, $by === 'veces' ? $rows->max('plays') : $rows->max('seconds'));
                @endphp
                <div class="top-ranking" data-orden="{{ $by }}">
                    <ol class="top-list">
                        @foreach ($rows->take(3) as $row)
                            @include('panel.activity.top-row')
                        @endforeach
                    </ol>
                    @if ($rows->count() > 3)
                        <details class="top-more">
                            <summary class="btn btn-sm btn-ghost">{{ __('Ver los :n siguientes', ['n' => $rows->count() - 3]) }} @include('partials.icon', ['name' => 'down'])</summary>
                            <ol class="top-list" start="4">
                                @foreach ($rows->slice(3) as $row)
                                    @include('panel.activity.top-row')
                                @endforeach
                            </ol>
                        </details>
                    @endif
                </div>
            @endforeach
        @endif
    </section>

    {{-- Cuándo lo ha visto --}}
    @php
        $timeline = $report->timeline();
    @endphp
    <section class="card activity-card" aria-labelledby="when-title">
        <div class="activity-card-head">
            <h2 id="when-title">{{ $profile ? __('Cuándo lo ha visto') : __('Cuándo lo han visto') }}</h2>
        </div>
        @if ($timeline->isEmpty())
            <p class="empty">{{ __('Nada visto en este periodo.') }}</p>
        @else
            @include('panel.activity.timeline', ['plays' => $timeline->take(8), 'previousDay' => null])
            @if ($timeline->count() > 8)
                <details class="top-more">
                    <summary class="btn btn-sm btn-ghost">{{ __('Ver más') }} @include('partials.icon', ['name' => 'down'])</summary>
                    @include('panel.activity.timeline', ['plays' => $timeline->slice(8), 'previousDay' => $timeline[7]->created_at->toDateString()])
                    @if ($report->timelineTotal() > $timeline->count())
                        <p class="muted small">{{ __('Se muestran los :n más recientes de :total.', ['n' => $timeline->count(), 'total' => $report->timelineTotal()]) }}</p>
                    @endif
                </details>
            @endif
        @endif
    </section>

    {{-- Tiempo por listas --}}
    @php
        $lists = $report->lists();
        $listMax = max(1, $lists->max('seconds') ?? 0);
    @endphp
    <section class="card activity-card" aria-labelledby="lists-title">
        <div class="activity-card-head">
            <h2 id="lists-title">{{ __('Tiempo por listas') }}</h2>
        </div>
        @if ($lists->isEmpty())
            <p class="empty">{{ __('Nada visto en este periodo.') }}</p>
        @else
            <ol class="bar-list">
                @foreach ($lists as $list)
                    <li>
                        <div class="bar-list-row">
                            <strong>{{ $list->name }}</strong>
                            <span class="small">{{ ActivityReport::duration($list->seconds) }} <span class="muted">· {{ round($list->seconds / max(1, $total) * 100) }} %</span></span>
                        </div>
                        <span class="hbar" aria-hidden="true"><span style="width: {{ round($list->seconds / $listMax * 100, 1) }}%"></span></span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <details class="card activity-help">
        <summary><strong>{{ __('Cómo se mide la actividad') }}</strong> @include('partials.icon', ['name' => 'down'])</summary>
        <div class="stack-sm">
            <p>{{ __('Mientras un vídeo o un audio avanza de verdad, la tele envía cada 30 segundos cuánto lleva visto y qué perfil lo está viendo. Las pausas, la carga y los menús no cuentan. Si se manipulara la tele, nunca se cuenta más tiempo del que ha pasado.') }}</p>
            <p>{{ __('Cada reproducción cuenta en el día y la hora en que empezó, aunque termine más tarde. Las semanas van de lunes a domingo y los días, de medianoche a medianoche.') }}</p>
            <p>{{ __('La media diaria divide el tiempo entre los días del periodo que ya han empezado (si es la semana o el mes en curso, solo hasta hoy). En el mes, cada barra es la media al día de esa semana.') }}</p>
            <p>{{ __('«Viendo ahora» aparece mientras un vídeo avanza en alguna tele: desaparece poco después de pausarlo o terminarlo.') }}</p>
            <p>{{ __('Una sesión son los vídeos seguidos en la misma tele y del mismo niño: si pasan más de :n minutos entre uno y otro, empieza otra.', ['n' => ActivityReport::SESSION_GAP_MINUTES]) }}</p>
            <p>{{ __('No cuenta lo que se ve desde la Zona de adultos. Lo visto antes del 27 de octubre de 2026, o en teles con la app de LG anterior a la 1.4.0, no tiene perfil: aparece en «Todos», pero no en cada niño.') }}</p>
            <p>{{ __('Los datos se guardan 12 meses y solo los ves tú. Puedes descargarlos desde Cuenta → Exportar mis datos.') }}</p>
        </div>
    </details>
</div>
@endsection
