{{-- Reproducciones de la más reciente a la más antigua, con un encabezado por día. --}}
<ol class="timeline">
    @foreach ($plays as $play)
        @php
            $day = $play->created_at->toDateString();
            $end = $play->created_at->copy()->addSeconds($play->seconds);
        @endphp
        @if ($report->period !== 'dia' && $day !== $previousDay)
            <li class="timeline-day">{{ ucfirst($play->created_at->isToday() ? __('Hoy') : ($play->created_at->isYesterday() ? __('Ayer') : $play->created_at->translatedFormat('l j \d\e F'))) }}</li>
            @php($previousDay = $day)
        @endif
        <li class="timeline-item">
            <span class="timeline-dot" aria-hidden="true"></span>
            <div class="timeline-body">
                <strong class="timeline-title">{{ $play->content?->title ?? __('Contenido eliminado') }}</strong>
                <span class="muted small">
                    {{ $play->created_at->format('H:i') }}–{{ $end->format('H:i') }} · {{ \App\Services\ActivityReport::duration($play->seconds) }}
                </span>
                <span class="timeline-who small">
                    @if ($play->profile)
                        @include('partials.avatar', ['avatar' => $play->profile->avatar, 'size' => 20]) {{ $play->profile->name }}
                    @else
                        <span class="muted">{{ __('Sin perfil') }}</span>
                    @endif
                </span>
            </div>
        </li>
    @endforeach
</ol>
