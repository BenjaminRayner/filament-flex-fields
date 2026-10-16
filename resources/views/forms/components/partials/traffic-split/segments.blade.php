<div class="fff-traffic-split__segments">
    @foreach ($presentation['weights'] as $index => $weight)
        <div class="fff-traffic-split__segment-wrap" data-traffic-segment="{{ $index }}" style="width: {{ $weight }}%">
            @if ($index > 0)
                <div class="fff-traffic-split__gap" aria-hidden="true"></div>
            @endif
            <div @class(['fff-traffic-split__segment', 'is-locked' => in_array($index, $presentation['locked'], true)])>
                <span class="fff-traffic-split__segment-label" title="{{ $presentation['labels'][$index] }}">{{ $presentation['labels'][$index] }}</span>
                <span class="fff-traffic-split__segment-value" data-traffic-value @if ($weight < $presentation['threshold']) hidden @endif>{{ $weight }}%</span>
            </div>
            @if ($index < $presentation['count'] - 1)
                <div class="fff-traffic-split__gap" aria-hidden="true"></div>
            @endif
        </div>
    @endforeach
</div>
