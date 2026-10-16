<div class="fff-intelligence-results" aria-live="polite">
    <x-filament-flex-fields::assets component="field-intelligence" />
    <div class="fff-intelligence-status" data-valid="{{ $error === null ? 'true' : 'false' }}"><span class="fff-intelligence-status-dot"></span><span>{{ $error === null ? 'Acyclic graph — ready' : 'Graph rejected' }}</span></div>
    @if ($error !== null)
        <p class="fff-intelligence-errors">{{ $error }}</p>
        @if ($cycle !== [])<p class="fff-intelligence-annotation">Cycle members: <strong>{{ implode(' → ', $cycle) }}</strong></p>@endif
    @endif
    <div class="fff-intelligence-output-grid">
        @foreach ($formulas as $key => $expression)
            <div class="fff-intelligence-output"><span>{{ $key }}</span><strong>{{ $values[$key] ?? '—' }}</strong><code>{{ $expression }}</code>
                @if (array_key_exists($key, $changed))<small>Price 120 → 150: {{ $changed[$key] }}</small>@elseif ($error === null)<small>Unchanged — not recalculated</small>@endif
            </div>
        @endforeach
    </div>
    @if ($order !== [])<p class="fff-intelligence-annotation">Execution order: <strong>{{ implode(' → ', $order) }}</strong></p>@endif
</div>
