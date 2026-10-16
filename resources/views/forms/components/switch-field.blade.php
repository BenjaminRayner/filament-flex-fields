@php
    $presentation = $field->getSwitchPresentation();
    $statePath = $getStatePath();
    $checkedAttribute = $presentation['checked'] ? 'true' : 'false';
    $stateBinding = '$entangle('.\Illuminate\Support\Js::from($statePath).')';
    $configuration = \Illuminate\Support\Arr::only($presentation, ['disabled', 'ripple', 'onClasses', 'offClasses']);
    $identity = \Illuminate\Support\Arr::except($presentation, ['checked', 'onIcon', 'offIcon', 'label']);
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    :has-inline-label="$presentation['inlineLabel'] ? false : $field->hasInlineLabel()"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Center"
>
    @if ($presentation['inlineLabel'])
        @if ($presentation['labelPosition'] === 'end')
            <x-slot name="labelSuffix">
                @include('filament-flex-fields::forms.components.partials.switch.root')
            </x-slot>
        @else
            <x-slot name="labelPrefix">
                @include('filament-flex-fields::forms.components.partials.switch.root')
            </x-slot>
        @endif
    @else
        @include('filament-flex-fields::forms.components.partials.switch.root')
    @endif
</x-dynamic-component>
