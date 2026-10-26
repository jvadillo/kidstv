{{-- Minutos vistos por día: una sola serie, barras con tooltip y tabla equivalente para lectores de pantalla. --}}
@php
    $max = max(1, $daily->max());
    // Escala redondeada para que la línea de referencia tenga un valor legible.
    $step = $max <= 10 ? 5 : ($max <= 60 ? 15 : ($max <= 300 ? 60 : 300));
    $top = (int) (ceil($max / $step) * $step);
    $total = $daily->sum();
@endphp
<figure class="chart">
    <figcaption class="chart-head">
        <strong>{{ $title }}</strong>
        <span class="muted small">{{ __('Total: :total', ['total' => \App\Services\UsageStats::format($total)]) }}</span>
    </figcaption>
    @if ($total === 0)
        <p class="empty">{{ __('Todavía no hay minutos vistos en este periodo.') }}</p>
    @else
        <div class="chart-plot" aria-hidden="true">
            <span class="chart-gridline" style="bottom: 100%"><span>{{ \App\Services\UsageStats::format($top) }}</span></span>
            <span class="chart-gridline" style="bottom: 50%"><span>{{ \App\Services\UsageStats::format(intdiv($top, 2)) }}</span></span>
            <div class="chart-bars">
                @foreach ($daily as $day => $minutes)
                    @php $date = \Illuminate\Support\Carbon::parse($day); @endphp
                    <div class="chart-col" data-tip="{{ $date->translatedFormat('D j M') }} · {{ \App\Services\UsageStats::format($minutes) }}">
                        <span class="chart-bar" style="height: {{ round($minutes / $top * 100, 2) }}%"></span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="chart-axis small muted" aria-hidden="true">
            <span>{{ \Illuminate\Support\Carbon::parse($daily->keys()->first())->translatedFormat('j M') }}</span>
            <span>{{ __('Hoy') }}</span>
        </div>
        <table class="sr-only">
            <caption>{{ $title }}</caption>
            <thead><tr><th scope="col">{{ __('Día') }}</th><th scope="col">{{ __('Minutos') }}</th></tr></thead>
            <tbody>
                @foreach ($daily as $day => $minutes)
                    <tr><td>{{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('j F') }}</td><td>{{ $minutes }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</figure>
