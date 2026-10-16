@php
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $preview = $field->getLinkPreviewRenderState(\Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableHydrator::resolveRenderedState($field));
    $configuration = $preview->configuration;
    $prefixLabel = $getPrefix();
    $suffixLabel = $getSuffix();
    $visitIcon = $getVisitIcon() ?? \Bjanczak\FilamentFlexFields\Support\FlexIcon::Paperclip;
    $showTooltips = false;
    $initialPreview = $configuration['initialPreview'] ?? [];
    $hasInitialPreview = $initialPreview !== [];
    $hasError = $errors->has($statePath);
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'link-preview-field', 'assetSchemaComponent' => $field, 'livewireKey' => $livewireKey])
    <div wire:ignore wire:key="{{ $livewireKey }}.{{ $preview->fingerprint() }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('link-preview-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="linkPreviewFieldFormComponent({ ...{{ \Illuminate\Support\Js::from($configuration) }}, state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        @class(['fff-link-preview', 'fff-flex-text-input', 'fff-link-preview--'.$preview->size, 'fff-flex-text-input--'.$preview->size,
            'fff-link-preview--'.$preview->variant, 'fff-flex-text-input--'.$preview->variant, 'fff-rounding-'.$preview->rounding,
            'fff-link-preview--layout-'.$configuration['previewLayout'], 'has-focus-outline' => $shouldShowFocusOutline(),
            'is-disabled' => $configuration['disabled'], 'is-read-only' => $configuration['readOnly']])
        role="group" aria-label="{{ $getLabel() }}">
        <x-filament-flex-fields::text-input.shell :class="$hasError ? 'is-invalid' : null">
            <x-filament::input.wrapper :disabled="$configuration['disabled']" :valid="! $hasError"
                :inline-prefix="filled($prefixLabel)" :inline-suffix="filled($suffixLabel)" :prefix="$prefixLabel" :suffix="$suffixLabel" class="fff-flex-text-input__wrapper">
                <x-filament-flex-fields::text-input.control :type="filled($prefixLabel) ? 'text' : 'url'" inputmode="url" autocomplete="url"
                    :id="$getId()" :value="$preview->displayValue" :placeholder="$getPlaceholder()"
                    :disabled="$configuration['disabled']" :readonly="$configuration['readOnly']" :aria-invalid="$hasError ? 'true' : null"
                    :class="(filled($prefixLabel) ? 'fi-input-has-inline-prefix ' : '').(filled($suffixLabel) ? 'fi-input-has-inline-suffix' : '')"
                    maxlength="2048" autocapitalize="off" spellcheck="false" x-ref="input"
                    x-on:input="onInput($event.target.value)" x-on:blur="onBlur()" />
            </x-filament::input.wrapper>
        </x-filament-flex-fields::text-input.shell>
        @include('filament-flex-fields::forms.components.link-preview.card')
        <p class="fff-link-preview__error" x-show="error" style="display:none" x-text="error" role="alert"></p>
    </div>
</x-dynamic-component>
