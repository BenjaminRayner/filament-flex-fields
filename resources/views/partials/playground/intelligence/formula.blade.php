<div class="fff-intelligence-results" aria-live="polite" aria-atomic="true">
    <x-filament-flex-fields::assets component="field-intelligence" />
    <div class="fff-intelligence-status" data-valid="{{ $result->successful() ? 'true' : 'false' }}">
        <span class="fff-intelligence-status-dot"></span><span>{{ $result->successful() ? 'Valid expression' : 'Cannot calculate this expression' }}</span>
    </div>
    <div class="fff-intelligence-formula-result">
        <span>Result</span><strong>{{ $result->values['result'] ?? '—' }}</strong>
        <code>{{ $expression }}</code>
    </div>
    @if (! $result->successful())
        <ul class="fff-intelligence-errors">@foreach ($result->errors as $key => $message)<li><strong>{{ $key }}</strong> — {{ $message }}</li>@endforeach</ul>
    @endif
    <div class="fff-intelligence-meta"><span><strong>References</strong>{{ implode(', ', $references) ?: 'None — constant expression' }}</span><span><strong>Functions</strong>{{ implode(', ', $functions) ?: 'Arithmetic only' }}</span></div>
</div>
