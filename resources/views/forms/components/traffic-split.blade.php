@php
    $presentation = $field->getTrafficSplitPresentation();
    $configuration = [
        'segmentCount' => $presentation['count'],
        'minWeight' => $presentation['minimum'],
        'valueThreshold' => $presentation['threshold'],
        'disabled' => $presentation['disabled'],
        'labels' => $presentation['labels'],
        'lockedSegments' => $presentation['locked'],
    ];
    $identity = $presentation;
    unset($identity['weights']);
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($getStatePath()).')';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'traffic-split', 'livewireKey' => $getLivewireKey()])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('traffic-split', package: \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="trafficSplitFormComponent({ ...@js($configuration), initialWeights: @js($presentation['weights']), state: $wire.{{ $applyStateBindingModifiers($stateBinding, isOptimisticallyLive: false) }} })"
        wire:ignore
        wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($identity)) }}"
        id="{{ $getId() }}"
        @class(['fff-traffic-split', 'fff-traffic-split--'.$presentation['size'], 'fff-traffic-split--'.$presentation['variant'], 'is-disabled' => $presentation['disabled']])
        role="group"
        aria-label="{{ $getLabel() }}"
        aria-disabled="{{ $presentation['disabled'] ? 'true' : 'false' }}"
    >
        <div x-ref="track" class="fff-traffic-split__track">
            @include('filament-flex-fields::forms.components.partials.traffic-split.segments')
            @include('filament-flex-fields::forms.components.partials.traffic-split.handles')
        </div>
    </div>
</x-dynamic-component>
