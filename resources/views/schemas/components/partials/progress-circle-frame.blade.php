<div class="fff-progress-circle__frame">
    @if (in_array($contentLayout, ['left', 'right'], true))
        <div class="fff-progress-circle__side-content fff-progress-circle__side-content--{{ $contentLayout }}">
            @if (filled($data['label']))<span class="fff-progress-circle__side-label">{{ $data['label'] }}</span>@endif
            @if ($data['showValue'] && filled($data['fraction']))<span class="fff-progress-circle__side-fraction">{{ $data['fraction'] }}</span>@endif
            @if ($data['showValue'])<span class="fff-progress-circle__side-value">{{ $data['value'] }}</span>@endif
        </div>
    @elseif ($contentLayout === 'above' && filled($data['label']))
        <div class="fff-progress-circle__above-content"><span class="fff-progress-circle__above-label">{{ $data['label'] }}</span></div>
    @endif
    <div class="fff-progress-circle__ring" style="--fff-progress-circle-view-height: {{ $metrics['viewBoxHeight'] }}; --fff-progress-circle-semicircle-floor-inset: {{ $metrics['semicircleFloorInsetPercent'] }}%;">
        <svg class="fff-progress-circle__svg" viewBox="{{ $metrics['viewBox'] }}" aria-hidden="true" focusable="false">
            @if ($hasGradient || $hasTrackGradient)
                <defs>
                    @foreach ([$hasGradient ? [$metrics['gradientId'], $gradientFrom, $gradientTo] : null, $hasTrackGradient ? [$metrics['trackGradientId'], $trackGradientFrom, $trackGradientTo] : null] as $gradient)
                        @if ($gradient)
                            <linearGradient id="{{ $gradient[0] }}" gradientUnits="userSpaceOnUse" x1="{{ $metrics['gradientX1'] }}" y1="{{ $metrics['gradientY1'] }}" x2="{{ $metrics['gradientX2'] }}" y2="{{ $metrics['gradientY2'] }}">
                                <stop offset="0%" stop-color="{{ \Bjanczak\FilamentFlexFields\Support\ProgressColor::css($gradient[1]) }}" /><stop offset="100%" stop-color="{{ \Bjanczak\FilamentFlexFields\Support\ProgressColor::css($gradient[2]) }}" />
                            </linearGradient>
                        @endif
                    @endforeach
                </defs>
            @endif
            <g transform="rotate({{ $metrics['rotation'] }} {{ $metrics['centerX'] }} {{ $metrics['centerY'] }})" fill="none" stroke-width="{{ $metrics['strokeWidth'] }}">
                <circle class="fff-progress-circle__track" cx="{{ $metrics['centerX'] }}" cy="{{ $metrics['centerY'] }}" r="{{ $metrics['radius'] }}" stroke-dasharray="{{ $metrics['arcLength'] }} {{ $metrics['gapLength'] }}" @if ($hasTrackGradient) style="stroke:url(#{{ $metrics['trackGradientId'] }})" @endif />
                <circle class="fff-progress-circle__fill" cx="{{ $metrics['centerX'] }}" cy="{{ $metrics['centerY'] }}" r="{{ $metrics['radius'] }}" style="--fff-progress-circle-arc-length: {{ $metrics['arcLength'] }}; --fff-progress-circle-circumference: {{ $metrics['circumference'] }}; --fff-progress-circle-fill-ratio: {{ $progress->ratio }}; @if ($hasGradient) stroke:url(#{{ $metrics['gradientId'] }}); @endif" />
            </g>
        </svg>
        <div class="fff-progress-circle__center">
            @if ($data['paused'])
                <div class="fff-progress-circle__paused-stack">
                    <span class="fff-progress-circle__paused-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($getPausedIcon(), size: $pausedIconSize) }}</span>
                </div>
            @elseif ($contentLayout === 'center' || $contentLayout === 'above')
                @if ($data['showValue'] && (filled($data['display']) || ! filled($data['fraction'])))<span class="fff-progress-circle__display-value">{{ $data['value'] }}</span>@endif
                @if ($data['showValue'] && filled($data['fraction']))<span class="fff-progress-circle__fraction">{{ $data['fraction'] }}</span>@endif
                @if ($contentLayout === 'center' && filled($data['label']) && ! $hasBelowLabel)<span class="fff-progress-circle__label">{{ $data['label'] }}</span>@endif
            @endif
        </div>
    </div>
    @if ($hasBelowLabel)
        <div class="fff-progress-circle__below-content"><span class="fff-progress-circle__below-label">{{ $data['label'] }}</span></div>
    @endif
</div>
