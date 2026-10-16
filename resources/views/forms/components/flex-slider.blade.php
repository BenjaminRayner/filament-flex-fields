@php
    $runtime = $field->getSliderRuntimeConfiguration();
    $values = $runtime['initialNormalizedValues'];
    $ratios = $field->getInitialValueRatios();
    $segments = $field->getInitialFillSegments();
    $size = $field->getSize();
    $variant = $field->getVariant();
    $isRange = count($values) > 1;
    $isVertical = $runtime['isVertical'];
    $trackLabel = $field->getTrackLabel();
    $showValue = $field->shouldShowValue();
    $showScale = $runtime['hasTooltips'] && ! $isVertical && ! $runtime['elastic'];
    $showHeaderValue = $showValue && $field->getValuePlacement() === 'header';
    $showFooter = ! $showHeaderValue && $showValue && ! $isRange && ! $runtime['hasTooltips'] && ! $runtime['pipsMode'];
    $showHeaderLabel = filled($trackLabel) && ($showHeaderValue || $showScale || ! $showFooter);
    $tooltipSettings = $field->getTooltips();
    $showBubble = $runtime['hasTooltips'] || $runtime['elastic'];
    $dots = $field->getStepDotRatios();
    $pips = $field->getServerRenderedPips();
    $keyConfiguration = $runtime;
    unset($keyConfiguration['initialNormalizedValues']);
    $keyConfiguration['handleCount'] = count($values);
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($getStatePath()).')';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Center">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-slider', 'livewireKey' => $getLivewireKey()])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-slider', package: \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="flexSliderFormComponent({ ...@js($runtime), state: $wire.{{ $applyStateBindingModifiers($stateBinding) }}, tooltips: @js($getTooltipsForJs()), pipsFormatter: @js($getPipsFormatterForJs()), pipsFilter: @js($getPipsFilterForJs()) })"
        wire:ignore
        wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($keyConfiguration)) }}"
        @class([
            'fff-flex-slider', 'fff-flex-slider--'.$size, 'fff-flex-slider--'.$variant,
            'fff-flex-slider--circle' => $runtime['thumbStyle'] === 'circle',
            'fff-flex-slider--elastic' => $runtime['elastic'],
            'fff-flex-slider--vertical' => $isVertical,
            'fff-flex-slider--has-pips' => filled($runtime['pipsMode']),
            'fff-flex-slider--has-server-pips' => $runtime['serverRenderedPips'],
            'fff-flex-slider--has-step-dots' => $dots !== [],
            'fff-flex-slider--has-tooltips' => $showBubble,
            'fff-flex-slider--has-footer' => $showFooter,
            'fff-flex-slider--hide-thumb-until-interaction' => $field->shouldHideThumbUntilInteraction(),
            'fi-color-'.$field->getColor() => filled($field->getColor()) && blank($field->getFillColor()),
            'is-disabled' => $runtime['isDisabled'], 'is-range' => $isRange, 'is-rtl' => $runtime['isRtl'], 'is-ready',
        ])
        @style(['--fff-flex-slider-accent: '.$field->getFillColor().';' => filled($field->getFillColor())])
        x-bind:class="{ 'is-disabled': disabled, 'is-dragging': isDragging }"
        role="group" aria-label="{{ $getLabel() }}"
    >
        <div class="fff-flex-slider__control">
            @if ($showHeaderLabel || $showHeaderValue)
                <div class="fff-flex-slider__header">
                    @if ($showHeaderLabel)
                        <span class="fff-flex-slider__header-label">{{ $trackLabel }}</span>
                    @endif
                    @if ($showHeaderValue)
                        <span class="fff-flex-slider__header-value">
                            @foreach ($values as $numberIndex => $numberValue)
                                @if ($numberIndex > 0)
                                    <span class="fff-flex-slider__value-separator" aria-hidden="true">–</span>
                                @endif
                                @include('filament-flex-fields::forms.components.partials.flex-slider.number')
                            @endforeach
                        </span>
                    @endif
                </div>
            @endif
            <div class="fff-flex-slider__body">
                @if ($runtime['iconButton'] && $runtime['iconButton']['position'] === 'start')
                    @include('filament-flex-fields::forms.components.partials.flex-slider.icon-button')
                @elseif ($icon = $field->getStartIcon())
                    <span class="fff-flex-slider__side-icon fff-flex-slider__side-icon--start" data-slider-start-icon aria-hidden="true">{{ \Filament\Support\generate_icon_html($icon, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-slider__side-icon-svg', 'stroke-width' => 2])) }}</span>
                @endif
                @include('filament-flex-fields::forms.components.partials.flex-slider.rail')
                @if ($runtime['iconButton'] && $runtime['iconButton']['position'] === 'end')
                    @include('filament-flex-fields::forms.components.partials.flex-slider.icon-button')
                @elseif ($icon = $field->getEndIcon())
                    <span class="fff-flex-slider__side-icon fff-flex-slider__side-icon--end" data-slider-end-icon aria-hidden="true">{{ \Filament\Support\generate_icon_html($icon, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-slider__side-icon-svg', 'stroke-width' => 2])) }}</span>
                @endif
            </div>
            @include('filament-flex-fields::forms.components.partials.flex-slider.annotations')
        </div>
    </div>
</x-dynamic-component>
