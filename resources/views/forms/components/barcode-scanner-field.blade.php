@php
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $configuration = $field->getAlpineConfiguration();
    $configuration['disabled'] = $isDisabled();
    $configuration['readOnly'] = $isReadOnly();
    $configuration['statePath'] = $statePath;
    $initialValue = \Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeStateNormalizer::extractValue($getState()) ?? '';
    $scanIcon = $getScanIcon();
    $iconIdentity = $scanIcon instanceof \BackedEnum ? $scanIcon->value : ($scanIcon instanceof \Illuminate\Contracts\Support\Htmlable ? $scanIcon->toHtml() : $scanIcon);
    $structure = [$iconIdentity, $configuration, $getSize(), $getInputVariant(), $getRounding(), $shouldShowFocusOutline(), $errors->has($statePath), $getPlaceholder(), (string) $getScanButtonLabel()];
    $dialogId = 'fff-barcode-camera-'.md5($livewireKey.serialize($structure));
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'barcode-scanner-field', 'livewireKey' => $livewireKey])
    <div wire:ignore wire:key="{{ $livewireKey }}.barcode.{{ md5(serialize($structure)) }}"
        data-barcode-dialog="{{ $dialogId }}" data-barcode-configuration="{{ json_encode($configuration, JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('barcode-scanner-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="barcodeScannerFieldFormComponent({ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        @class(['fff-barcode-scanner', 'fff-flex-text-input', 'has-actions', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getInputVariant(), 'fff-rounding-'.$getRounding(), 'has-focus-outline' => $shouldShowFocusOutline(), 'is-disabled' => $configuration['disabled'], 'is-invalid' => $errors->has($statePath)])>
        <x-filament-flex-fields::text-input.shell>
            <x-filament::input.wrapper :disabled="$configuration['disabled']" :valid="! $errors->has($statePath)" class="fff-flex-text-input__wrapper">
                <x-filament-flex-fields::text-input.control type="text" :value="$initialValue" :id="$getId()"
                    :disabled="$configuration['disabled']" :readonly="$configuration['readOnly'] || ! $configuration['allowManualInput']"
                    :placeholder="$getPlaceholder()" :aria-invalid="$errors->has($statePath) ? 'true' : null"
                    :aria-label="$getLabel()" maxlength="4096" autocomplete="off" autocapitalize="off" spellcheck="false"
                    data-barcode-input x-on:input="manualInput($event.target.value)" />
            </x-filament::input.wrapper>
            <x-slot:actions>
                <span class="fff-flex-text-input__action-item">
                    <button type="button" class="fff-flex-text-input__action-btn fff-flex-text-input__action-btn--scan"
                        data-barcode-trigger data-fff-open-trigger x-on:click="openScanner()" @disabled($configuration['disabled'] || $configuration['readOnly']) x-bind:disabled="disabled || readOnly"
                        aria-haspopup="dialog" aria-controls="{{ $dialogId }}" aria-label="{{ $getScanButtonLabel() }}" title="{{ $getScanButtonLabel() }}">
                        <x-filament::icon :icon="$scanIcon" stroke-width="2" />
                    </button>
                </span>
            </x-slot:actions>
        </x-filament-flex-fields::text-input.shell>
        @include('filament-flex-fields::forms.components.barcode-scanner.camera')
    </div>
</x-dynamic-component>
