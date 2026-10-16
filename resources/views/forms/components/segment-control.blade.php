@php
    use Illuminate\Support\Js;
    $statePath = $getStatePath();
    $items = $getSegmentItems();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-filament-flex-fields::assets component="segment-control" :consumer="$getLivewireKey()" :schema-component="$field" />
    <div @class(['fff-segment-field', 'fff-segment-field--full' => $isFullWidth()]) x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('segment-control', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="segmentControlFormComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            optionKeys: {{ Js::from(array_map(static fn ($item) => $item->key, $items)) }},
            disabled: @js($isDisabled()),
        })"
    >
        <x-filament-flex-fields::segment-control :items="$items" :identity="$getLivewireKey()" :selected="$getState()"
            :size="$getSize()" :variant="$getVariant()" :color="$getColor()"
            :separators="$hasSeparators()" :full-width="$isFullWidth()"
            :icon-only="$isIconOnly()" :expand-selected-label="$shouldExpandSelectedLabel()"
            :disabled="$isDisabled()" :label="$getLabel()"
        />
    </div>
</x-dynamic-component>
