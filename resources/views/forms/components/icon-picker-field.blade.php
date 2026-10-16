@php
    $inline = $field->isInlineTrigger();
    $statePath = $getStatePath();
    $initialName = is_string($getState()) ? $getState() : null;
    $initialHtml = filled($initialName) ? $field->renderIconHtml($initialName) : '';
    $layout = $field->getSearchResultsLayout();
    $locked = $isDisabled() || $isReadOnly();
    $placeholder = $getPlaceholder() ?: __('filament-flex-fields::default.icon_picker.placeholder');
    $controlId = $getId();
    $configuration = [
        'catalogRevision' => $field->getIconPickerConfigurationFingerprint(), 'inline' => $inline,
        'componentKey' => $getKey(), 'livewireId' => $field->getLivewire()->getId(), 'menuDomId' => $controlId.'-fff-headless-menu',
        'initialSelectedName' => $initialName, 'initialSelectedHtml' => $initialHtml,
        'availableSets' => $field->getAvailableSetsForJs(), 'layout' => $layout,
        'gridColumns' => $field->getGridColumns(), 'perPage' => $field->getPerPage(),
        'preload' => $field->shouldPreload(), 'closeOnSelect' => $field->shouldCloseOnSelect(), 'readOnly' => $locked,
        'clearable' => $field->isClearable(), 'placeholder' => $placeholder,
        'labels' => ['search' => __('filament-flex-fields::default.icon_picker.search'),
            'loading' => __('filament-flex-fields::default.select_field.searching'),
            'noResults' => __('filament-flex-fields::default.icon_picker.no_results'),
            'loadMore' => __('filament-flex-fields::default.icon_picker.load_more')],
    ];
@endphp
@if ($inline)
    <div class="fff-flex-text-input__action-item">
        @include('filament-flex-fields::components.icon-picker.control')
    </div>
@else
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-filament::input.wrapper
        :disabled="$locked" :valid="! $errors->has($statePath)"
        :inline-prefix="$isPrefixInline()" :inline-suffix="$isSuffixInline()"
        :prefix="$getPrefixLabel()" :prefix-icon="$getPrefixIcon()" :prefix-actions="$getPrefixActions()" :prefix-icon-color="$getPrefixIconColor()"
        :suffix="$getSuffixLabel()" :suffix-icon="$getSuffixIcon()" :suffix-actions="$getSuffixActions()" :suffix-icon-color="$getSuffixIconColor()"
        :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($field->getWrapperClasses())">
        @include('filament-flex-fields::components.icon-picker.control')
    </x-filament::input.wrapper>
</x-dynamic-component>

@endif
