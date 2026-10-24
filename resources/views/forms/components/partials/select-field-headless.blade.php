@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
    use Filament\Support\Enums\IconSize;

    $headlessSearchClearIconHtml = app(\Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon::class)->render(FlexIcon::CircleXmarkFill, size: IconSize::Small);
    $headlessSmartCreateIconHtml = app(\Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlIcon::class)->render(FlexIcon::Plus, size: IconSize::Small);
    $headlessComponentKey = $getKey();
    $headlessTriggerDir = $getExtraAttributeBag()->get('dir');
    $headlessInlineSearchSsrValue = ($isInlineSearch && $isSearchable && ! $isMultiple)
        ? ($isInitialTriggerPlaceholder ? '' : strip_tags($initialTriggerLabel))
        : null;
    $shouldDeferHeadlessAlpine = false;
    $showHeadlessStaticTriggerLabel = ! $isUserSelectField
        && ! ($isInlineSearch && $isSearchable && ! $isMultiple);
    $headlessTriggerHasClearableValue = $field->isClearable() && filled($state) && ! $isMultiple && ! $isDisabled;
    $headlessUsesInlineSearchControl = $isInlineSearch && $isSearchable;
    $headlessListboxId = $id.'-listbox';
    $headlessSearchId = $id.'-search';
    $headlessMenuDomId = $id.'-fff-headless-menu';
    if (! ($reuseInitialPresentation ?? false)) {
        $headlessAlpineConfig = $getHeadlessAlpineConfig();
    }
    $headlessTransportConfig = app(\Bjanczak\FilamentFlexFields\Support\Rendering\CatalogPayloadTransport::class)->configuration($field, $headlessAlpineConfig);
    $headlessStockMenu = in_array(get_class($field), [\Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField::class, \Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField::class, \Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField::class], true);
    $headlessMenuTransport = $headlessStockMenu
        ? app(\Bjanczak\FilamentFlexFields\Support\Select\Bootstrap\RenderedSelectMenu::class)->transport($field, get_defined_vars())
        : null;
@endphp

<div
    wire:ignore
    wire:key="{{ $livewireKey }}.headless.{{ substr(md5(serialize([
        $isDisabled,
        $isMultiple,
        $isSearchable,
        $getSize(),
        $getVariant(),
        $optionLayout,
        $usesRichOptionHtml,
    ])), 0, 64) }}"
    @class([
        'fff-select-field__shell',
        'fff-select-field__shell--headless',
        'fi-select-input',
    ])
>
    @include('filament-flex-fields::forms.components.partials.select-field.trigger-ssr')

    <x-filament-flex-fields::lazy-alpine-mount
        :eager="! $shouldDeferHeadlessAlpine"
        :mount-immediately="! $shouldDeferHeadlessAlpine"
        :mount-on-interaction="$shouldDeferHeadlessAlpine"
        :wrap-slot="false"
    >
    @if ($shouldDeferHeadlessAlpine)
        <template x-if="shouldMount">
            <div>
    @endif

        <div
            @if ($headlessTransportConfig['presentationConfigurationId'] ?? null) data-fff-control-configuration-id="{{ $headlessTransportConfig['presentationConfigurationId'] }}" @endif
            @if ($headlessMenuTransport['id'] ?? null) data-fff-portal-template-id="{{ $headlessMenuTransport['id'] }}" @endif
            x-fff-load
            x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="fffHeadlessSelectField({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
        }, $el)"
            x-on:keydown="onHeadlessMenuKeydown($event)"
            x-on:click.outside="if (!comboboxOpen || isEventInsideHeadlessMenu($event)) { return }; comboboxCloseMenu({ immediate: true, reason: 'outside' })"
            @class([
                'fff-select-field__interactive',
            ])
            {{
                $attributes
                    ->except(['id'])
                    ->merge($getExtraAlpineAttributes(), escape: false)
            }}
        >
        <template data-fff-select-configuration>{!! json_encode($headlessTransportConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</template>
        @include('filament-flex-fields::forms.components.partials.select-field.trigger')

        @if ($headlessStockMenu)
            <template x-if="headlessMenuMounted" data-fff-portal-template-owner>
                {!! $headlessMenuTransport['html'] !!}
            </template>
        @else
            @include('filament-flex-fields::forms.components.partials.select-field.menu-shell')
        @endif
        </div>

    @if ($shouldDeferHeadlessAlpine)
            </div>
        </template>
    @endif
    </x-filament-flex-fields::lazy-alpine-mount>
</div>
