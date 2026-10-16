<div class="fff-price-range__slider">
    <div class="fff-price-range__track" data-price-track>
        <span class="fff-price-range__track-rail" aria-hidden="true"></span>
        <span class="fff-price-range__track-fill" data-price-fill style="inset-inline-start: {{ $minimumPercent }}%; width: {{ $maximumPercent - $minimumPercent }}%" aria-hidden="true"></span>
        @foreach (['min' => $getMinInputLabel(), 'max' => $getMaxInputLabel()] as $edge => $label)
            <button type="button" class="fff-price-range__thumb" data-price-thumb="{{ $edge }}" role="slider"
                style="inset-inline-start: {{ $edge === 'min' ? $minimumPercent : $maximumPercent }}%"
                @disabled($configuration['disabled'] || $configuration['readOnly'])
                aria-valuemin="{{ $edge === 'min' ? $configuration['minBound'] : $initial['min'] }}"
                aria-valuemax="{{ $edge === 'max' ? $configuration['maxBound'] : $initial['max'] }}"
                aria-valuenow="{{ $initial[$edge] }}" aria-valuetext="{{ $configuration['prefix'] }}{{ $field->formatNumberForLocale((float) $initial[$edge], $configuration['integer'] ? 0 : $configuration['decimalPlaces']) }}"
                aria-label="{{ $label }}" aria-orientation="horizontal"></button>
        @endforeach
    </div>
</div>
