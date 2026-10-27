{{-- Una sola serie: barras con 2px de separación, aviso al pasar o enfocar, y tabla para lectores de pantalla. --}}
@php
    $max = max(1, max(array_column($bars, 'minutes')));
    // Escala redondeada (como mucho 4 pasos) para que las líneas de referencia tengan valores legibles.
    $step = collect([5, 10, 15, 30, 60, 120, 180, 300])->first(fn ($s) => ceil($max / $s) <= 4) ?? 300;
    $top = (int) (ceil($max / $step) * $step);
    // Etiquetas cortas del eje: «45 min», «2 h», «1 h 30».
    $axis = fn (int $m) => $m < 60 ? $m.' min' : intdiv($m, 60).' h'.($m % 60 ? ' '.$m % 60 : '');
@endphp
<figure class="chart activity-chart">
    <figcaption class="sr-only">{{ $title }}</figcaption>
    <div class="chart-plot" aria-hidden="true">
        <span class="chart-gridline" style="bottom: 100%"><span>{{ $axis($top) }}</span></span>
        <span class="chart-gridline" style="bottom: 50%"><span>{{ $axis(intdiv($top, 2)) }}</span></span>
        <span class="chart-gridline chart-baseline" style="bottom: 0"><span>0</span></span>
        <div class="chart-bars">
            @foreach ($bars as $bar)
                <div @class(['chart-col', 'is-future' => $bar['future']]) data-tip="{{ $bar['tip'] }}" tabindex="-1">
                    <span class="chart-bar" style="height: {{ round($bar['minutes'] / $top * 100, 2) }}%"></span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="chart-labels" aria-hidden="true">
        @foreach ($bars as $bar)
            <span @class(['is-future' => $bar['future']])>{{ $bar['label'] }}</span>
        @endforeach
    </div>
    <table class="sr-only">
        <caption>{{ $title }}</caption>
        <thead><tr><th scope="col">{{ __('Periodo') }}</th><th scope="col">{{ __('Minutos') }}</th></tr></thead>
        <tbody>
            @foreach ($bars as $bar)
                <tr><td>{{ $bar['name'] }}</td><td>{{ $bar['minutes'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</figure>
