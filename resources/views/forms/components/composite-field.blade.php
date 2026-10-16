<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="fff-composite-field" {{ $getExtraAttributeBag() }}>
        <x-filament-flex-fields::assets :component="$getAssetComponent()" :capabilities="$getAssetCapabilities()" :consumer="$getLivewireKey()" :schema-component="$field" />
        <x-filament-flex-fields::schema :component="$field" />
    </div>
</x-dynamic-component>
