@php
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Filament\Support\Facades\FilamentAsset;
    $render = $field->getSlugRenderState();
    $options = $render->options;
    $labels = $options['labels'];
    $statePath = $getStatePath();
    $title = $field->getConfiguredTitleField();
    $size = $getSize();
    $variant = $getVariant();
    $hasError = $errors->has($statePath);
    $identity = $getLivewireKey();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @if ($title)<div class="fff-slug-field__title">{{ $title }}</div>@endif
    <div class="fff-slug-field__host">
        @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'slug-field', 'livewireKey' => $identity])
        <div wire:ignore wire:key="{{ $identity }}.{{ md5(serialize([$options['disabled'], $options['slugReadOnly'], $options['inlineEditing'], $size, $variant, $options['permalinkPreview']])) }}"
            x-fff-load x-fff-load-src="{{ FilamentAsset::getAlpineComponentSrc('slug-field', FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="slugFieldFormComponent({ ...@js($options), size: @js($size), focusOutline: @js($shouldShowFocusOutline()), initialInvalid: @js($hasError) })"
            @class(['fff-slug-field fff-flex-text-input', 'fff-slug-field--'.$size, 'fff-flex-text-input--'.$size,
                'fff-flex-text-input--'.$variant, 'is-disabled' => $options['disabled'],
                'is-read-only' => $options['slugReadOnly'], 'has-permalink' => $options['permalinkPreview'],
                'has-focus-outline' => $shouldShowFocusOutline()])
            x-bind:class="{ 'is-focused': isFocused, 'is-editing': mode === 'edit' }"
            role="group" aria-label="{{ $getLabel() }}">
            @include('filament-flex-fields::forms.components.slug.header')
            @include('filament-flex-fields::forms.components.slug.control')
            @include('filament-flex-fields::forms.components.slug.actions')
            <div id="{{ $getId() }}-slug-error" class="fff-slug-field__error" role="alert" x-cloak x-show="uniqueError" x-text="uniqueError"></div>
            <span class="sr-only" role="status" aria-live="polite" x-text="copyFeedback ? labels.copied : ''"></span>
        </div>
    </div>
</x-dynamic-component>
