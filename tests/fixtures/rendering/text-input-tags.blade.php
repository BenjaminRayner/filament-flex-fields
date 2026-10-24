@php
    $presentation = $field->getTextInputRenderState();
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $isDisabled = $isDisabled();
    $isReadOnly = $isReadOnly();
    $isPasswordRevealable = $isPasswordRevealable();
    $isPrefixInline = $isPrefixInline();
    $isSuffixInline = $isSuffixInline();
    $prefixActions = $presentation->prefixActions;
    $suffixActions = $presentation->suffixActions;
    $options = $presentation->options;
    $persistentTools = $options['emojiPicker'] || $options['speechDictation'] || $options['iconPicker'] || $suffixActions !== [];
    $initialToolsVisible = $persistentTools || ($options['clearable'] && $presentation->value !== '');
    $meta = $options['showCharacterCounter'] || $options['showPasswordStrength'] || $options['readingTime'];
@endphp
<x-filament-flex-fields::field-wrapper :component="$getFieldWrapperView()" :field="$field"
    :inline-label-vertical-alignment="\Filament\Support\Enums\VerticalAlignment::Center"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component' => 'flex-text-input', 'livewireKey' => $livewireKey, 'capabilities' => $presentation->capabilities,
    ])
    <div wire:key="{{ $livewireKey }}.control"
        data-tools-visibility="{{ $options['toolsVisibility'] }}" dir="{{ $options['textDirection'] }}" data-fff-text-input data-fff-native-edit-owner data-fff-text-input-options="{{ json_encode($options, JSON_THROW_ON_ERROR) }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-text-input', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="flexTextInputFormComponent()"
        @class([
            'fff-flex-text-input', 'fff-flex-text-input--'.$getSize(), 'fff-flex-text-input--'.$getVariant(),
            'fff-rounding-'.$getRounding(), 'is-disabled' => $isDisabled, 'is-read-only' => $isReadOnly,
            'has-actions' => $initialToolsVisible, 'has-inline-loading' => $options['loading'],
            'has-counter' => $options['showCharacterCounter'], 'has-meta' => $meta,
            'has-password-strength' => $options['showPasswordStrength'], 'has-dictation' => $options['speechDictation'],
            'has-emoji-picker' => $options['emojiPicker'], 'has-focus-outline' => $shouldShowFocusOutline(),
        ]) x-bind:class="{ 'has-actions': shouldShowActionGroup }" role="group" aria-label="{{ $getLabel() }}">
        <x-filament-flex-fields::text-input.shell actions-visible="shouldShowActionGroup" :actions-initially-visible="$initialToolsVisible">
                    <x-filament::input.wrapper :disabled="$isDisabled" :inline-prefix="$isPrefixInline" :inline-suffix="$isSuffixInline"
                        :prefix="$getPrefixLabel()" :prefix-actions="$prefixActions" :prefix-icon="$getPrefixIcon()" :prefix-icon-color="$getPrefixIconColor()"
                        :suffix="$getSuffixLabel()" :suffix-actions="[]" :suffix-icon="$getSuffixIcon()" :suffix-icon-color="$getSuffixIconColor()"
                        :valid="! $errors->has($statePath)" x-on:focus-input.stop="focusInputFromAffix()" class="fff-flex-text-input__wrapper">
                        <x-filament-flex-fields::text-input.control :interactive="true" :attributes="$presentation->inputAttributes->merge(['aria-invalid' => $errors->has($statePath) ? 'true' : null])->class([
                            'fi-input-has-inline-prefix' => $isPrefixInline && (count($prefixActions) || $getPrefixIcon() || filled($getPrefixLabel())),
                            'fi-input-has-inline-suffix' => $isSuffixInline && ($getSuffixIcon() || filled($getSuffixLabel())),
                            'fi-revealable' => $isPasswordRevealable,
                        ])" />
                    </x-filament::input.wrapper>
            @if ($options['loading'])
                <span class="fff-flex-text-input__loading-indicator fff-flex-text-input__loading-indicator--inline"
                    wire:loading.delay.class="is-visible" wire:target="{{ $getLoadingWireTargets() }}" aria-hidden="true">
                    {{ \Filament\Support\generate_icon_html('hugeicons-loading-03', size: \Filament\Support\Enums\IconSize::Small, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'fff-flex-text-input__loading-spinner'])) }}
                </span>
            @endif
                @if ($presentation->tools)
                    <x-slot:actions>
                        @include('filament-flex-fields::components.text-input.actions')
                    </x-slot:actions>
                @endif
        </x-filament-flex-fields::text-input.shell>
        @include('filament-flex-fields::components.text-input.feedback')
    </div>
    @if ($getDatalistOptions())
        <datalist id="{{ $getId() }}-list">
            @foreach ($getDatalistOptions() as $option)<option value="{{ $option }}"></option>@endforeach
        </datalist>
    @endif
</x-filament-flex-fields::field-wrapper>
