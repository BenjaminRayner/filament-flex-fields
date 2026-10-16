@foreach (range(0, $presentation['count'] - 2) as $index)
    @php
        $before = array_sum(array_slice($presentation['weights'], 0, $index));
        $position = $before + $presentation['weights'][$index];
        $locked = in_array($index, $presentation['locked'], true) || in_array($index + 1, $presentation['locked'], true);
        $blocked = $presentation['disabled'] || $locked;
    @endphp
    <div
        @class(['fff-traffic-split__handle', 'is-locked' => $locked])
        data-traffic-handle="{{ $index }}"
        style="inset-inline-start: {{ $position }}%"
        role="separator"
        tabindex="{{ $blocked ? -1 : 0 }}"
        aria-orientation="vertical"
        aria-disabled="{{ $blocked ? 'true' : 'false' }}"
        aria-valuenow="{{ $position }}"
        aria-valuemin="{{ $before + $presentation['minimum'] }}"
        aria-valuemax="{{ $before + $presentation['weights'][$index] + $presentation['weights'][$index + 1] - $presentation['minimum'] }}"
        aria-valuetext="{{ $presentation['labels'][$index].' '.$presentation['weights'][$index].'%, '.$presentation['labels'][$index + 1].' '.$presentation['weights'][$index + 1].'%' }}"
        aria-label="{{ __('filament-flex-fields::default.traffic_split_resize', ['from' => $presentation['labels'][$index], 'to' => $presentation['labels'][$index + 1]]) }}"
    >
        <span class="fff-traffic-split__handle-bar" aria-hidden="true"></span>
    </div>
@endforeach
