<div class="fff-date-segments" data-date-segments role="group" aria-label="{{ $getLabel() }}" aria-describedby="{{ $getId() }}.date-error">
    @foreach ($configuration['initialSegments'] as $group => $values)
        @if ($group > 0)<span class="fff-date-segments__literal" aria-hidden="true">{{ $configuration['rangeSeparator'] }}</span>@endif
        @foreach ($configuration['segmentPattern'] as $index => $segment)
            @if ($segment['type'] === 'literal')
                <span class="fff-date-segments__literal" aria-hidden="true">{{ $segment['token'] }}</span>
            @else
                <span class="fff-date-segments__part" data-date-segment data-fff-edit-intent data-group="{{ $group }}" data-segment="{{ $index }}"
                    data-empty="{{ $values[$index]['value'] === null ? 'true' : 'false' }}"
                    role="spinbutton" aria-describedby="{{ $getId() }}.date-error" tabindex="{{ $disabled ? '-1' : '0' }}"
                    contenteditable="{{ $disabled || $readOnly ? 'false' : 'true' }}" inputmode="{{ $segment['type'] === 'period' ? 'text' : 'numeric' }}" spellcheck="false" autocapitalize="off"
                    aria-label="{{ $segment['label'] }}{{ $configuration['mode'] === 'dateRange' ? ' — '.$configuration['labels'][$group === 0 ? 'range_start' : 'range_end'] : '' }}"
                    aria-valuemin="{{ $segment['min'] }}" aria-valuemax="{{ $segment['max'] }}"
                    @if ($values[$index]['value'] !== null) aria-valuenow="{{ $values[$index]['value'] }}" @endif
                    aria-valuetext="{{ $values[$index]['text'] }}" aria-disabled="{{ $disabled ? 'true' : 'false' }}" aria-readonly="{{ $readOnly ? 'true' : 'false' }}"
                    x-bind:aria-invalid="Boolean(inputError) || {{ $configuration['isInvalid'] ? 'true' : 'false' }}">{{ $values[$index]['text'] }}</span>
            @endif
        @endforeach
    @endforeach
</div>
