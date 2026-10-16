@if ($pips !== [])
    <div class="fff-flex-slider__pips" aria-hidden="true">
        @foreach ($pips as $pip)
            <div class="fff-flex-slider__pip" style="{{ $field->pipStyle($runtime['isRtl'] ? 1 - $pip['ratio'] : $pip['ratio']) }}">
                <span class="fff-flex-slider__pip-marker fff-flex-slider__pip-marker--{{ $pip['size'] }}"></span>
                @if (filled($pip['label']))<span class="fff-flex-slider__pip-value">{{ $pip['label'] }}</span>@endif
            </div>
        @endforeach
    </div>
@endif
@if ($runtime['marks'] !== [])
    <div class="fff-flex-slider__marks">
        @foreach ($runtime['marks'] as $mark)
            @if ($mark['label'] !== null)
                <button type="button" @class(['fff-flex-slider__mark-label', 'fff-flex-slider__mark-label--start' => ($runtime['isRtl'] ? 1 - $mark['ratio'] : $mark['ratio']) === 0.0, 'fff-flex-slider__mark-label--end' => ($runtime['isRtl'] ? 1 - $mark['ratio'] : $mark['ratio']) === 1.0]) x-bind:disabled="disabled" x-on:click="selectMark(@js($mark['value']))" style="--fff-flex-slider-mark-ratio: {{ $field->formatRatio($runtime['isRtl'] ? 1 - $mark['ratio'] : $mark['ratio']) }};">{{ $mark['label'] }}</button>
            @endif
        @endforeach
    </div>
@endif
@if ($showScale)
    <div class="fff-flex-slider__scale" aria-hidden="true">
        <span class="fff-flex-slider__scale-min">{{ $field->formatDisplayValue($field->getMinValue()) }}</span>
        <span class="fff-flex-slider__scale-max">{{ $field->formatDisplayValue($field->getMaxValue()) }}</span>
    </div>
@endif
@if ($showFooter)
    <div class="fff-flex-slider__footer">
        <span class="fff-flex-slider__footer-label">{{ $trackLabel }}</span>
        <span class="fff-flex-slider__footer-value fff-flex-slider__footer-value--{{ $field->getValuePosition() }}" >@include('filament-flex-fields::forms.components.partials.flex-slider.number', ['numberIndex' => 0, 'numberValue' => $values[0]])</span>
    </div>
@endif
@if ($showValue && $isRange && ! $showHeaderValue)
    <div class="fff-flex-slider__handle-values">
        @foreach ($values as $index => $value)
            <span class="fff-flex-slider__handle-value" data-slider-handle-value="{{ $index }}" style="--fff-flex-slider-value-ratio: {{ $field->formatRatio($runtime['isRtl'] ? 1 - $ratios[$index] : $ratios[$index]) }};">@include('filament-flex-fields::forms.components.partials.flex-slider.number', ['numberIndex' => $index, 'numberValue' => $value])</span>
        @endforeach
    </div>
@endif
