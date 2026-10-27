{{-- Un contenido del ranking; la barra es proporcional al primero según el orden elegido. --}}
@php
    $value = $by === 'veces' ? $row->plays : $row->seconds;
@endphp
<li class="top-item">
    @include('partials.content-thumb', ['content' => $row->content, 'coverUrl' => $row->cover])
    <div class="top-meta">
        <strong class="top-title">{{ $row->content->title }}</strong>
        <span class="muted small">
            {{ \App\Services\ActivityReport::duration($row->seconds) }}
            · {{ trans_choice(':count vez|:count veces', $row->plays) }}
            · {{ $row->list }}
        </span>
        <span class="hbar" aria-hidden="true"><span style="width: {{ round($value / $max * 100, 1) }}%"></span></span>
    </div>
</li>
