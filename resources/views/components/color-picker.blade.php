@props(['configuration', 'stateExpression', 'onChange' => 'null', 'externalExpression' => null, 'readOnly' => false, 'label' => null, 'size' => 'md', 'variant' => 'primary', 'rounding' => 'lg', 'focusOutline' => true, 'initialValue' => null, 'schemaComponent' => null, 'activeExpression' => null])
@php($initialColor = \Bjanczak\FilamentFlexFields\Support\ColorPicker\ColorValue::valid($initialValue, $configuration['alphaEnabled']) ? $initialValue : 'transparent')
<div wire:ignore x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-color-picker', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="flexColorPickerFormComponent({ ...@js($configuration), state: {!! $stateExpression !!}, readOnly: @js($readOnly), onChange: {!! $onChange !!} })"
    @if ($activeExpression) x-effect="if (!({!! $activeExpression !!})) closePanel()" @endif
    @if ($externalExpression) x-init="$watch(() => {!! $externalExpression !!}, value => receiveExternalColor(value))" @endif
    class="fff-flex-color-picker fff-flex-text-input {{ $focusOutline ? 'has-focus-outline' : '' }} fff-flex-text-input--{{ $size }} fff-flex-text-input--{{ $variant }} fff-rounding-{{ $rounding }} {{ $configuration['trigger'] !== 'field' ? 'fff-color-compact' : '' }} {{ $configuration['trigger'] === 'swatch' ? 'fff-color-swatch-only' : '' }} {{ $configuration['inline'] ? 'fff-color-inline' : '' }}"
    role="group" aria-label="{{ $label ?? __('filament-flex-fields::color-picker.value') }}">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'flex-color-picker', 'capabilities' => $configuration['showFormatSelector'] ? ['format-selector'] : [], 'assetSchemaComponent' => $schemaComponent])
    @if (!$configuration['inline'])
        <button x-ref="trigger" type="button" class="fff-color-trigger fff-flex-text-input__shell" x-on:click="togglePanel()" @disabled($readOnly) x-bind:disabled="readOnly" x-bind:aria-expanded="panelOpen" aria-haspopup="dialog" aria-label="{{ $label ?? __('filament-flex-fields::color-picker.value') }}">
            <span class="fff-flex-text-input__row">
                @if ($configuration['trigger'] === 'icon')
                    <span style="color: {{ $initialColor }}" class="fff-color-icon" x-bind:style="{ color: solidColor }"><x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::Palette" /></span>
                @else
                    <span style="--fff-color-swatch: {{ $initialColor }}" class="fff-color-swatch" x-bind:style="{ '--fff-color-swatch': state ? swatchColor : 'transparent' }"></span>
                @endif
                @if ($configuration['trigger'] === 'field' && $configuration['showValue'])
                    <span class="fff-color-trigger-value" x-text="state || '—'">{{ is_string($initialValue) && $initialValue !== '' ? $initialValue : '—' }}</span>
                @endif
            </span>
        </button>
    @endif
    @if ($configuration['inline'])
        @include('filament-flex-fields::forms.components.partials.color-picker.panel', ['colorInline' => true])
    @else
        <template x-teleport="body">
            @include('filament-flex-fields::forms.components.partials.color-picker.panel', ['colorInline' => false])
        </template>
    @endif
</div>
