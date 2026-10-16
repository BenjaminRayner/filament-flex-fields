@if ($usesAutoPillCount)
    <div class="fff-progress-bar__pills fff-progress-bar__pills--auto" aria-hidden="true"
        style="--fff-progress-bar-fill: {{ $percentage }}%; --fff-progress-bar-gradient-from: {{ \Bjanczak\FilamentFlexFields\Support\ProgressColor::css($data['gradientFrom']) }}; --fff-progress-bar-gradient-to: {{ \Bjanczak\FilamentFlexFields\Support\ProgressColor::css($data['gradientTo']) }};">
        <span class="fff-progress-bar__auto-pill-fill"></span>
    </div>
@else
    @php
        $pillGradientFrom = \Bjanczak\FilamentFlexFields\Support\ProgressColor::css($data['gradientFrom']);
        $pillGradientTo = \Bjanczak\FilamentFlexFields\Support\ProgressColor::css($data['gradientTo']);
    @endphp
    <div class="fff-progress-bar__pills fff-progress-bar__pills--fixed" aria-hidden="true">
        @for ($pillIndex = 0; $pillIndex < $pillCount; $pillIndex++)
            @php
                $pillColor = null;
                if ($pillIndex < $activePillCount) {
                    $startPercent = $activePillCount <= 1 ? 100 : round(100 * (1 - $pillIndex / ($activePillCount - 1)), 5);
                    $pillColor = "color-mix(in srgb, {$pillGradientFrom} {$startPercent}%, {$pillGradientTo})";
                }
            @endphp
            <span @class(['fff-progress-bar__pill', 'is-active' => filled($pillColor)]) @if (filled($pillColor)) style="--fff-progress-bar-pill-color: {{ $pillColor }}" @endif></span>
        @endfor
    </div>
@endif
