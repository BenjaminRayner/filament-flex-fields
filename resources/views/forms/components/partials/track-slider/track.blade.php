<div
    x-ref="track"
    class="fff-track-slider__track"
    role="slider"
    aria-label="{{ $getLabel() }}"
    aria-orientation="horizontal"
    aria-valuemin="{{ $runtime['min'] }}"
    aria-valuemax="{{ $runtime['max'] }}"
    aria-valuenow="{{ $runtime['initial'] }}"
    aria-valuetext="{{ $displayValue }}"
    aria-disabled="{{ $runtime['disabled'] ? 'true' : 'false' }}"
    tabindex="{{ $runtime['disabled'] ? -1 : 0 }}"
>
    <span class="fff-track-slider__fill" aria-hidden="true"></span>
    <span class="fff-track-slider__thumb" aria-hidden="true"></span>

    @if (filled($trackLabel))
        <span class="fff-track-slider__track-label" aria-hidden="true">{{ $trackLabel }}</span>
    @endif

    @if ($showOutput)
        <span class="fff-track-slider__output" aria-hidden="true">{{ $displayValue }}</span>
    @endif
</div>
