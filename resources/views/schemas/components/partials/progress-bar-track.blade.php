<div class="fff-progress-bar__track" aria-hidden="true">
    @if (filled($data['startMarker']))
        <span class="fff-progress-bar__marker fff-progress-bar__marker--start">{{ \Filament\Support\generate_icon_html($data['startMarker'], size: $markerIconSize) }}</span>
    @endif
    <span class="fff-progress-bar__rail-region">
        @if ($hasDashedRemainder)
            <span class="fff-progress-bar__rail fff-progress-bar__rail--route">
                <span class="fff-progress-bar__rail-fill" style="--fff-progress-bar-fill: {{ $percentage }}%"></span>
                <span class="fff-progress-bar__rail-remainder" style="--fff-progress-bar-fill: {{ $percentage }}%"></span>
            </span>
        @else
            <span class="fff-progress-bar__rail"><span class="fff-progress-bar__fill" style="--fff-progress-bar-fill: {{ $percentage }}%"></span></span>
        @endif
        @if (filled($data['currentMarker']) && ! $isIndeterminate)
            <span class="fff-progress-bar__marker fff-progress-bar__marker--current" style="--fff-progress-bar-marker-position: {{ $percentage }}%">{{ \Filament\Support\generate_icon_html($data['currentMarker'], size: $markerIconSize) }}</span>
        @endif
    </span>
    @if (filled($data['endMarker']))
        <span class="fff-progress-bar__marker fff-progress-bar__marker--end">{{ \Filament\Support\generate_icon_html($data['endMarker'], size: $markerIconSize) }}</span>
    @endif
</div>
