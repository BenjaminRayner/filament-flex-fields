@php
    $runtime = $field->getStepperRuntimeConfiguration();
    $statePath = $runtime['statePath'];
    $liveDebounceMs = $runtime['liveDebounce'];
    $initialState = $getState();
    $initialMain = $field->formatDisplayMain($initialState);
    $initialSuffix = $field->getSuffixForValue($initialState);
    $initialSizer = $field->getInitialSizerText($initialState);
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($statePath).')';
    $stateBindingExpression = $liveDebounceMs !== null
        ? '$entangle('.\Illuminate\Support\Js::from($statePath).', false)'
        : $applyStateBindingModifiers($stateBinding);
    $buttons = [
        ['action' => 'decrement', 'canProperty' => 'canDecrement', 'canInitially' => $field->canStepValue($initialState, -1), 'icon' => $getDecrementIcon(), 'label' => __('filament-flex-fields::default.number_stepper_decrease')],
        ['action' => 'increment', 'canProperty' => 'canIncrement', 'canInitially' => $field->canStepValue($initialState, 1), 'icon' => $getIncrementIcon(), 'label' => __('filament-flex-fields::default.number_stepper_increase')],
    ];
    if ($isReversed()) {
        $buttons = array_reverse($buttons);
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component' => 'number-stepper',
        'livewireKey' => $getLivewireKey(),
    ])

    <div
        x-fff-load
        x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('number-stepper', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="numberStepperFormComponent({
            ...@js($runtime),
            state: $wire.{{ $stateBindingExpression }},
            liveDebounce: @js($liveDebounceMs),
        })"
        @class([
            'fff-number-stepper',
            'fff-number-stepper--'.$getSize(),
            'fff-number-stepper--'.$getVariant(),
            'is-disabled' => $runtime['disabled'],
            'is-reversed' => $isReversed(),
        ])
        role="group"
        aria-label="{{ $getLabel() }}"
        data-value="{{ is_scalar($initialState) ? $initialState : '' }}"
        x-bind:data-value="numericState ?? ''"
    >
        @include('filament-flex-fields::forms.components.partials.number-stepper-button', $buttons[0])
        @include('filament-flex-fields::forms.components.partials.number-stepper.display')
        @include('filament-flex-fields::forms.components.partials.number-stepper-button', $buttons[1])
    </div>
</x-dynamic-component>
