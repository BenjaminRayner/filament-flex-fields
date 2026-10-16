@php
    $presentation = $field->getNpsPresentation();
    $variant = $presentation['variant'];
    $size = $presentation['size'];
    $usesIndicator = $variant === 'pills';
    $configuration = [
        'options' => array_map(static fn (array $option): array => ['value' => $option['value'], 'disabled' => $option['disabled']], $presentation['options']),
        'disabled' => $presentation['disabled'],
        'required' => $presentation['required'],
    ];
    $identity = $presentation;
    unset($identity['focus']);
    foreach ($identity['options'] as &$item) { unset($item['selected']); }
    unset($item);
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($getStatePath()).')';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'nps-field', 'livewireKey' => $getLivewireKey()])
    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('nps-field', package: \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="npsFieldFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers($stateBinding) }} })"
        wire:ignore
        wire:key="{{ $getLivewireKey() }}.{{ hash('xxh128', serialize($identity)) }}"
        id="{{ $getId() }}"
        @class([
            'fff-nps-field', 'fff-nps-field--'.$size, 'fff-nps-field--variant-'.$variant,
            'fff-nps-field--color-coded' => $presentation['colorCoded'],
            'fff-nps-field--full-width',
            'fff-segment-control w-full' => $usesIndicator,
            'is-disabled' => $presentation['disabled'],
            'fi-color-primary' => ! $presentation['colorCoded'],
        ])
        role="radiogroup"
        aria-label="{{ $getLabel() }}"
        aria-required="{{ $presentation['required'] ? 'true' : 'false' }}"
        aria-disabled="{{ $presentation['disabled'] ? 'true' : 'false' }}"
    >
        @include('filament-flex-fields::forms.components.partials.nps-field.track')
        @include('filament-flex-fields::forms.components.partials.nps-field.extremes')
    </div>
</x-dynamic-component>
