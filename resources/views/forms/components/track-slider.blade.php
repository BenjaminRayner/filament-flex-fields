@php
    $runtime = $field->getTrackRuntimeConfiguration();
    $percentage = \Bjanczak\FilamentFlexFields\Support\TrackSlider\TrackSliderValue::percentage($runtime['initial'], $runtime['min'], $runtime['max']);
    $displayValue = \Bjanczak\FilamentFlexFields\Support\TrackSlider\TrackSliderValue::format($runtime['initial'], $runtime['integer'], $runtime['decimalPlaces'], $runtime['suffix']);
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($getStatePath()).')';
    $identity = $runtime;
    unset($identity['initial']);
    $size = $getSize();
    $variant = $getVariant();
    $trackLabel = $getTrackLabel();
    $showOutput = $shouldShowOutput();
    $identity['presentation'] = [$size, $variant, $trackLabel, $showOutput, $getLabel()];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component' => 'track-slider',
        'livewireKey' => $getLivewireKey(),
    ])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('track-slider', package: \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="trackSliderFormComponent({ ...@js($runtime), state: $wire.{{ $applyStateBindingModifiers($stateBinding) }} })"
        wire:ignore
        wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($identity)) }}"
        @class([
            'fff-track-slider',
            'fff-track-slider--'.$size,
            'fff-track-slider--'.$variant,
            'is-disabled' => $runtime['disabled'],
        ])
        style="--fff-track-slider-position: {{ $percentage }}%;"
        role="group"
        aria-label="{{ $getLabel() }}"
    >
        @include('filament-flex-fields::forms.components.partials.track-slider.track')
    </div>
</x-dynamic-component>
