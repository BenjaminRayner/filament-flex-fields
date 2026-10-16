<div class="fff-flex-slider__track-stack">
    <div class="fff-flex-slider__rail is-ready" x-ref="track">
        <div class="fff-flex-slider__track-shell">
            <div class="fff-flex-slider__band" x-ref="band" aria-hidden="true">
                <div class="fff-flex-slider__track-bg"></div>
                @foreach ($segments as $segment)
                    <div class="fff-flex-slider__fill {{ $field->fillSegmentModifierClass($segment) }}" data-slider-fill style="{{ $field->fillSegmentVariables($segment) }}"></div>
                @endforeach
            </div>
            @if ($dots !== [])
                <div class="fff-flex-slider__step-dots" aria-hidden="true">
                    @foreach ($dots as $dot)
                        <span class="fff-flex-slider__step-dot" style="{{ $field->stepDotStyle($runtime['isRtl'] ? 1 - $dot : $dot) }}"></span>
                    @endforeach
                </div>
            @endif
            @foreach ($runtime['marks'] as $mark)
                @if ($runtime['thumbStyle'] === 'circle' || ($mark['value'] > $runtime['minValue'] && $mark['value'] < $runtime['maxValue']))
                <span class="fff-flex-slider__step-dot" data-slider-mark="{{ $mark['value'] }}" aria-hidden="true" style="{{ $field->stepDotStyle($runtime['isRtl'] ? 1 - $mark['ratio'] : $mark['ratio']) }}"></span>
                @endif
            @endforeach
            @foreach ($values as $index => $value)
                @php $position = '--fff-flex-slider-value-ratio: '.$field->formatRatio($runtime['isRtl'] ? 1 - $ratios[$index] : $ratios[$index]).';'; @endphp
                <div @class(['fff-flex-slider__thumb', 'fff-flex-slider__thumb--on-track', 'fff-flex-slider__thumb--single' => ! $isRange, 'fff-flex-slider__thumb--range' => $isRange, 'is-visible' => $isRange || ! $field->shouldHideThumbUntilInteraction()]) data-slider-thumb style="{{ $position }}" aria-hidden="true"></div>
                @php
                    $tooltipSetting = is_array($tooltipSettings) ? ($tooltipSettings[$index] ?? true) : $tooltipSettings;
                    $customTooltip = $tooltipSetting instanceof \Filament\Support\RawJs;
                @endphp
                @if ($showBubble && $tooltipSetting !== false)
                    <span class="fff-flex-slider__tooltip fff-flex-slider__floating-value" data-slider-tooltip style="{{ $position }}" aria-hidden="true">
                        @if ($customTooltip)
                            {{ $field->formatDisplayValue($value) }}
                        @else
                            @include('filament-flex-fields::forms.components.partials.flex-slider.number', ['numberIndex' => $index, 'numberValue' => $value])
                        @endif
                    </span>
                @endif
            @endforeach
        </div>
        <div x-ref="sliderHost" {{ $attributes->merge(['id' => $getId()], escape: false)->merge($getExtraAttributes(), escape: false)->class(['fff-flex-slider__host']) }}></div>
    </div>
</div>
