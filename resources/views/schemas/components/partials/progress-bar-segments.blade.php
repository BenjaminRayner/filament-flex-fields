<div class="fff-progress-bar__segmented">
    <div
        class="fff-progress-bar__segment-track"
        aria-hidden="true"
    >
        @foreach ($segments as $index => $segment)
            @php($segmentFill = $data['fills'][$index])

            <div @class([
                'fff-progress-bar__segment',
                'is-complete' => $segment['state'] === 'complete',
                'is-active' => $segment['state'] === 'active',
                'is-pending' => $segment['state'] === 'pending',
            ])>
                <span class="fff-progress-bar__segment-rail" aria-hidden="true">
                    <span
                        class="fff-progress-bar__segment-fill"
                        style="--fff-progress-bar-segment-fill: {{ $segmentFill }}%"
                    ></span>

                    @if ($segment['state'] === 'active' && $data['segmentThumb'])
                        <span
                            class="fff-progress-bar__segment-dot"
                            style="--fff-progress-bar-segment-dot-position: {{ $segmentFill }}%"
                            aria-hidden="true"
                        ></span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>

    @if ($data['hasLabels'])
        <div class="fff-progress-bar__segment-labels">
            @foreach ($segments as $segment)
                <div @class([
                    'fff-progress-bar__segment-label-column',
                    'is-complete' => $segment['state'] === 'complete',
                    'is-active' => $segment['state'] === 'active',
                    'is-pending' => $segment['state'] === 'pending',
                ])>
                    @if (filled($segment['label']) || filled($segment['icon']))
                        <div class="fff-progress-bar__segment-heading">
                            @if (filled($segment['icon']))
                                <span class="fff-progress-bar__segment-icon" aria-hidden="true">
                                    {{ \Filament\Support\generate_icon_html($segment['icon'], size: $segmentIconSize) }}
                                </span>
                            @endif

                            @if (filled($segment['label']))
                                <span class="fff-progress-bar__segment-title">{{ $segment['label'] }}</span>
                            @endif
                        </div>
                    @endif

                    @if (filled($segment['description']))
                        <span class="fff-progress-bar__segment-description">{{ $segment['description'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
