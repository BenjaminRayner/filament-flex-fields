@php
    use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
    use Illuminate\Support\Js;
    $id = $getId();
    $tabs = $getVisibleTabs();
    $items = $getSegmentItems();
    $helperText = $getHelperText();
    $radius = match ($getRounding()) {
        'none' => '0px', 'native' => '.5rem', 'md' => '.75rem',
        'lg' => '1rem', 'xl' => '1.25rem', default => '9999px',
    };
@endphp

<div x-fff-load
    x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('segment-tabs', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="segmentTabsSchemaComponent({
        activeTabKey: @js($getActiveTabKey()),
        optionKeys: {{ Js::from(array_map(static fn ($item) => $item->key, $items)) }},
        isTabPersisted: @js($isTabPersisted() && filled($id)),
        tabPersistKey: @js($getTabPersistKey()),
        isTabPersistedInQueryString: @js($isTabPersistedInQueryString()),
        tabQueryStringKey: @js($getTabQueryStringKey()),
        livewireId: @js($schemaComponent->getLivewire()->getId()),
        schemaKey: @js($getRootContainer()->getKey()),
    })"
    wire:ignore.self
    {{ $attributes->merge(['id' => $id, 'wire:key' => $getLivewireKey().'.container'], escape: false)
        ->merge($getExtraAttributes(), escape: false)
        ->style(["--fff-tabs-radius: {$radius}"])
        ->class(['fff-segment-tabs']) }}
>
    <x-filament-flex-fields::assets :component="$getSegmentAssetComponent()"
        :consumer="$getLivewireKey()" :schema-component="$schemaComponent" />
    <x-filament-flex-fields::segment-control :items="$items" :identity="$getLivewireKey()" :selected="$getActiveTabKey()"
        :size="$getSize()" :variant="$getVariant()" :color="$getColor()"
        :separators="$hasSeparators()" :full-width="$isFullWidth()"
        :icon-only="$isIconOnly()" :expand-selected-label="$shouldExpandSelectedLabel()"
        :label="$getLabel()" role="tablist" class="fff-segment-tabs__control"
    />
    @foreach ($tabs as $tab)
        @if ($visibility = SegmentTabs::tabVisibilityExpression($tab))
            <div x-cloak x-show="{!! $visibility !!}">{{ $tab }}</div>
        @else
            {{ $tab }}
        @endif
    @endforeach
    @if (filled($helperText)) <div class="fff-segment-tabs__helper-text">{{ $helperText }}</div> @endif
</div>
