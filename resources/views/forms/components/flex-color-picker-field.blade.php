@php
    $colorConfiguration = $field->getPickerConfiguration();
    $colorStatePath = $getStatePath();
    $colorBinding = '$wire.'.$applyStateBindingModifiers("\$entangle('{$colorStatePath}')");
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($field->getWrapperClasses())">
    <x-filament-flex-fields::color-picker :configuration="$colorConfiguration" :schema-component="$field" :initial-value="$getState()" :state-expression="$colorBinding" :read-only="$isDisabled() || $isReadOnly()" :label="$getLabel()" :size="$field->getSize()" :variant="$field->getVariant()" :rounding="$field->getRounding()" :focus-outline="$field->shouldShowFocusOutline()" />
</x-dynamic-component>
