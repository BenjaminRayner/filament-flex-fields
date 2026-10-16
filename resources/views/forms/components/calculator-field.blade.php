<div data-fff-calculator-field data-fff-calculator-options="{{ json_encode($field->getCalculatorConfiguration(), JSON_THROW_ON_ERROR) }}"
    wire:key="{{ $getLivewireKey() }}.calculator" x-fff-load
    x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('calculator-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="calculatorFieldFormComponent()" x-on:fff-calculator-open.stop="openCalculator($event.detail.anchor)">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'calculator-field', 'livewireKey' => $getLivewireKey()])
    @include('filament-flex-fields::forms.components.flex-text-input-field')
    <div wire:ignore data-fff-calculator-portal-host></div>
    <template data-fff-calculator-template>
        @include('filament-flex-fields::forms.components.calculator.panel')
    </template>
</div>
