@props(['configuration', 'synchronize' => '', 'label'])
@php($runtimePlan = \Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan::for('flex-slider'))
<div class="fff-flex-slider fff-flex-slider--sm fff-flex-slider--primary is-ready" x-id="['runtime-slider']"
    x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-slider', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="flexSliderFormComponent({ autoFill: true, behavior: 'tap', decimalPlaces: null,
        isVertical: false, isRtl: false, locale: 'en', nonLinearPoints: {}, fillTrack: [true, false],
        rangePadding: null, minDifference: null, maxDifference: null, tooltips: false, prefix: '', suffix: '',
        hideThumbUntilInteraction: false, ...{{ $configuration }}, handleLabel: {{ $label }} })" x-init="{{ $synchronize }}"
    x-bind:class="{ 'is-dragging': isDragging, 'is-disabled': disabled }"
    x-on:mouseenter="isHovered = true" x-on:mouseleave="isHovered = false; isThumbHovered = false"
    x-on:mousemove="updateThumbHover($event)" role="group" x-bind:aria-label="{{ $label }}">
    @include('filament-flex-fields::partials.emit-assets', [
        'stylesheets' => $runtimePlan->stylesheets, 'chunks' => $runtimePlan->chunks,
        'consumerComponent' => 'flex-slider', 'consumerExpression' => "\$id('runtime-slider')",
    ])
    <div class="fff-flex-slider__control"><div class="fff-flex-slider__track-stack"><div class="fff-flex-slider__rail is-ready" x-ref="track">
        <div class="fff-flex-slider__track-shell" aria-hidden="true">
            <div class="fff-flex-slider__track-bg"></div>
            <div class="fff-flex-slider__fill fff-flex-slider__fill--from-min" x-bind:class="fillSegmentClass(0)" x-bind:style="fillSegmentStyle(0)"></div>
            <div class="fff-flex-slider__thumb fff-flex-slider__thumb--on-track fff-flex-slider__thumb--single is-visible" x-bind:style="valueRatioStyle(0)"></div>
        </div>
        <div x-ref="sliderHost" class="fff-flex-slider__host" x-bind:aria-label="{{ $label }}"></div>
    </div></div></div>
</div>
