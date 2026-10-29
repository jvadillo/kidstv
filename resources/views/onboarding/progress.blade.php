{{-- Las tres decisiones del asistente; el correo y la conexión forman parte de «Su tele». --}}
<ol class="ob-progress" aria-label="{{ __('Paso :step de 3', ['step' => $step]) }}">
    @foreach ([__('Para quién'), __('Qué ver'), __('Su tele')] as $i => $label)
        <li @class(['done' => $i + 1 < $step, 'current' => $i + 1 === $step]) @if ($i + 1 === $step) aria-current="step" @endif>
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
