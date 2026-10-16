<div class="fff-color-format-row">
@if ($colorEyedropperInFormat)
    @include('filament-flex-fields::forms.components.partials.color-picker.eyedropper')
@endif
<x-filament-flex-fields::select-control
    :options="array_map(static fn (string $format): array => ['value' => $format, 'label' => strtoupper($format)], $configuration['allowedFormats'])"
    state-expression="inputFormat" :initial-value="$configuration['format']"
    on-change="values => $dispatch('fff-color-format', { format: values[0] })"
    disabled-expression="readOnly" :label="__('filament-flex-fields::color-picker.format')" />
</div>
