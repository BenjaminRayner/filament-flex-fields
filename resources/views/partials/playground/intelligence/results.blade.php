<div class="fff-intelligence-results" aria-live="polite" aria-atomic="true">
    <x-filament-flex-fields::assets component="field-intelligence" />
    <div class="fff-intelligence-status" data-valid="{{ $result->successful() ? 'true' : 'false' }}">
        <span class="fff-intelligence-status-dot"></span>
        <span>{{ $result->successful() ? 'Calculation ready' : 'Complete valid inputs to calculate' }}</span>
        <span class="fff-intelligence-count">{{ count($scenario->plan->formulas) }} connected outputs</span>
    </div>
    @if (! $result->successful())
        <ul class="fff-intelligence-errors">
            @foreach ($result->errors as $key => $message)
                <li><strong>{{ $scenario->inputs[$key]['label'] ?? 'Calculation' }}</strong> — {{ $message }}</li>
            @endforeach
        </ul>
    @endif
    <div class="fff-intelligence-output-grid">
        @foreach ($scenario->outputs as $key => $output)
            @php
                $value = $result->values[$key] ?? null;
                $formatted = $value === null ? '—' : match ($output['format'] ?? 'number') {
                    'money' => '€'.number_format($value / 100, 2),
                    'percent' => number_format($value, 2).'%',
                    'boolean' => $value != 0 ? 'Yes' : 'No',
                    default => number_format($value, 0),
                };
            @endphp
            <div class="fff-intelligence-output" wire:key="intelligence-output-{{ $scenario->id }}-{{ $key }}">
                <span>{{ $output['label'] }}</span>
                <strong>{{ $formatted }}</strong>
                <code>{{ $scenario->plan->formulas[$key] }}</code>
            </div>
        @endforeach
    </div>
    <details class="fff-intelligence-details">
        <summary>Calculation order</summary>
        <ol>@foreach ($scenario->plan->order() as $key)<li><code>{{ $key }}</code><span>{{ implode(' · ', $scenario->plan->dependencies()[$key]) }}</span></li>@endforeach</ol>
    </details>
</div>
