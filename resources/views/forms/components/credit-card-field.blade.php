@php
    use Bjanczak\FilamentFlexFields\Support\CreditCardDisplay;

    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $previewState = $field->normalizeState(is_array($getState()) ? $getState() : []);
    $previewDigits = $previewState['number'];
    $previewBrand = CreditCardDisplay::brand($previewDigits);
    $previewLabels = ['name' => __('filament-flex-fields::default.credit_card.preview_name'), 'expiry' => __('filament-flex-fields::default.credit_card.expiry_placeholder')];
    $previewName = $previewState['name'] !== '' ? mb_strtoupper($previewState['name']) : $previewLabels['name'];
    $previewExpiry = $previewState['expiry'] !== '' ? $previewState['expiry'] : $previewLabels['expiry'];
    $previewNumber = CreditCardDisplay::maskedNumber($previewDigits);
    $inputNumber = CreditCardDisplay::formatNumberInput($previewDigits);
    $contactlessClipId = 'fff-credit-card-contactless-'.md5($getLivewireKey().$statePath);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'credit-card', 'livewireKey' => $getLivewireKey()])
    <div x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('credit-card', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="creditCardFormComponent({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            initial: @js($previewState), labels: @js($previewLabels), flipOnCvvFocus: @js($shouldFlipOnCvvFocus()),
            disabled: @js($isDisabled), readOnly: @js($isReadOnly()),
        })"
        @class(['fff-credit-card', 'fff-credit-card--'.$getSize(), 'fff-credit-card--'.$getVariant(),
            'is-disabled' => $isDisabled]) role="group" aria-label="{{ $getLabel() }}">
        @include('filament-flex-fields::forms.components.credit-card.preview')
        @include('filament-flex-fields::forms.components.credit-card.inputs')
    </div>
</x-dynamic-component>
