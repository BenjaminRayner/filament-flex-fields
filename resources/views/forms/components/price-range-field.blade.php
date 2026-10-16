@php
    $configuration = $field->getPriceRangeConfiguration();
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $initial = $configuration['initialState'];
    $span = $configuration['maxBound'] - $configuration['minBound'];
    $minimumPercent = ($initial['min'] - $configuration['minBound']) / $span * 100;
    $maximumPercent = ($initial['max'] - $configuration['minBound']) / $span * 100;
    $structure = array_diff_key($configuration, ['initialState' => true]);
    $structure['size'] = $getSize();
    $structure['variant'] = $getVariant();
    $structure['rounding'] = $getRounding();
    $structure['inputs'] = $shouldShowInputs();
    $structure['invalid'] = $errors->has($statePath);
    $structure['focusOutline'] = $shouldShowFocusOutline();
    $histogram = \Bjanczak\FilamentFlexFields\Support\PriceRange\Histogram::presentation($configuration['histogram']);
    $structure['labels'] = [$getMinInputLabel(), $getMaxInputLabel()];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'price-range', 'livewireKey' => $livewireKey, 'capabilities' => $shouldShowInputs() ? ['inputs'] : []])
    <div wire:ignore wire:key="{{ $livewireKey }}.range.{{ md5(serialize($structure)) }}"
        data-price-range-config="{{ json_encode(array_diff_key($configuration, ['histogram' => true, 'initialState' => true]), JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('price-range', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="priceRangeFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        @class(['fff-price-range', 'fff-price-range--'.$getSize(), 'fff-price-range--'.$getVariant(), 'is-disabled' => $configuration['disabled']])
        style="--fff-price-range-min-percent: {{ $minimumPercent }}; --fff-price-range-max-percent: {{ $maximumPercent }}; --fff-price-range-max-bars: {{ $histogram['count'] }}"
        role="group" aria-label="{{ $getLabel() }}">
        @include('filament-flex-fields::forms.components.price-range.chart')
        @include('filament-flex-fields::forms.components.price-range.track')
        @if ($shouldShowInputs())
            @include('filament-flex-fields::forms.components.price-range.inputs')
        @endif
    </div>
</x-dynamic-component>
