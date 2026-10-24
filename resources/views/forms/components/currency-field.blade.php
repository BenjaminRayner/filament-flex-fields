@php
    use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Currency\CurrencySelect;
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Filament\Support\Facades\FilamentAsset;
    $statePath = $getStatePath();
    $disabled = $isDisabled();
    $readOnly = $isReadOnly();
    $selectable = $hasCurrencySelect();
    $size = $getSize();
    $variant = $getVariant();
    $initial = $field->getInitialDisplay();
    $runtime = $field->getCurrencyRuntimeConfiguration();
    $placeholder = $getPlaceholder() ?? __('filament-flex-fields::default.currency.placeholder');
    $currencySelect = $selectable ? CurrencySelect::forCurrency($field) : null;
    $currencySelectConfig = $currencySelect ? app(\Bjanczak\FilamentFlexFields\Support\Rendering\CatalogPayloadTransport::class)->configuration($currencySelect, $currencySelect->getHeadlessAlpineConfig()) : [];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'currency-field', 'livewireKey' => $getLivewireKey()])
    <div wire:ignore wire:key="{{ $getLivewireKey() }}.{{ md5(serialize([$disabled, $readOnly, $size, $variant, $selectable])) }}"
        x-fff-load x-fff-load-src="{{ FilamentAsset::getAlpineComponentSrc('currency-field', FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="currencyFieldFormComponent({ ...@js($runtime),
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
        })"
        x-on:fff-currency-change.stop="selectCurrency($event.detail)"
        @class(['fff-currency-field fff-flex-text-input', 'fff-currency-field--'.$size, 'fff-flex-text-input--'.$size,
            'fff-currency-field--'.$variant, 'fff-flex-text-input--'.$variant, 'is-disabled' => $disabled,
            'is-read-only' => $readOnly, 'has-focus-outline' => $shouldShowFocusOutline()])
        x-bind:class="{ 'is-focused': isFocused }" role="group" aria-label="{{ $getLabel() }}">
        <div @class(['fff-currency-field__shell fff-flex-text-input__shell', 'is-invalid' => $errors->has($statePath)])>
            <div class="fff-currency-field__row fff-flex-text-input__row">
                @if ($currencySelect)
                    <div class="fff-currency-field__currency"
                        @if ($currencySelectConfig['presentationConfigurationId'] ?? null) data-fff-control-configuration-id="{{ $currencySelectConfig['presentationConfigurationId'] }}" @endif
                        x-fff-load x-fff-load-src="{{ FilamentAsset::getAlpineComponentSrc('select-field', FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
                        x-data="fffHeadlessSelectField({ ...@js($currencySelectConfig),
                            state: currencyState.currency, onChange: values => $dispatch('fff-currency-change', values[0]) }, $el)"
                        x-init="$watch('currencyState.currency', value => { state = value })"
                        x-on:click.outside="if (!$refs.headlessMenu?.contains($event.target)) comboboxCloseMenu()">
                        {!! $currencySelect->getSelectCatalogMarkup() !!}
                        <button type="button" class="fff-currency-field__currency-trigger" x-ref="headlessTrigger" data-fff-open-trigger
                            x-on:click.stop="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event)"
                            x-bind:disabled="disabled" x-bind:aria-expanded="comboboxOpen" aria-haspopup="listbox"
                            aria-label="{{ $currencySelect->getLabel() }}" @disabled($disabled || $readOnly)>
                            <span class="fff-currency-field__currency-code" x-text="currencyState.currency">{{ $initial['currencyCode'] }}</span>
                            <x-filament::icon icon="hugeicons-arrow-down-01" class="fff-currency-field__currency-chevron" />
                        </button>
                        <x-filament-flex-fields::select-menu :owner-field="$field" :field="$currencySelect" />
                    </div>
                @endif
                @include('filament-flex-fields::forms.components.currency.amount-editor')
            </div>
        </div>
    </div>
</x-dynamic-component>
