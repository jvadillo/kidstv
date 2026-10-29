{{-- Recordatorio de lo preparado mientras se pide el correo. --}}
@if (filled($draft['name'] ?? null))
    <div class="ob-summary">
        @include('partials.avatar', ['avatar' => $draft['avatar'] ?? 'estrella', 'size' => 48])
        <div>
            <strong>{{ __('La tele de :name', ['name' => $draft['name']]) }}</strong>
            <span class="small muted">{{ trans_choice(':count serie elegida|:count series elegidas', count($draft['lists'] ?? [])) }}</span>
        </div>
        <span class="ob-summary-check">@include('partials.icon', ['name' => 'check'])</span>
    </div>
@endif
