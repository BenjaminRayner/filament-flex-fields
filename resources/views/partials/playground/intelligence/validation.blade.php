<div class="fff-intelligence-results" aria-live="polite">
    <x-filament-flex-fields::assets component="field-intelligence" />
    <div class="fff-intelligence-status" data-valid="{{ $result->successful() ? 'true' : 'false' }}"><span class="fff-intelligence-status-dot"></span><span>{{ $result->successful() ? 'Accepted — result calculated on the server' : 'Rejected — no output can be saved' }}</span></div>
    <div class="fff-intelligence-formula-result"><span>Submitted payload</span><code>{{ $payload }}</code><strong>{{ $result->values['result'] ?? '—' }}</strong></div>
    @if (! $result->successful())<ul class="fff-intelligence-errors">@foreach ($result->errors as $key => $message)<li><strong>{{ $key }}</strong> — {{ $message }}</li>@endforeach</ul>@endif
</div>
