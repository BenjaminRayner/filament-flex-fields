@php
    $searchId = $field->getId().'__location-search';
    $searchConfig = $field->getLocationSelectConfiguration($initialLabel ?? '');
    $searchConfig['clearable'] = $showClear ?? true;
    $searchPrefixIcon = method_exists($field, 'getPrefixIcon') ? $field->getPrefixIcon() : 'hugeicons-search-01';
    $searchClearIcon = $locationClearIcon ?? (method_exists($field, 'getClearIcon') ? $field->getClearIcon() : 'hugeicons-multiplication-sign');
    $searchConfig['labels']['search'] = $searchPlaceholder ?? $searchConfig['labels']['search'];
@endphp
<div wire:ignore
    x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('location-select', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="createLocationSelect({ ...@js($searchConfig), onSelect: result => selectSearchResult(result), onClear: () => {{ $clearLocationExpression }} })"
    x-effect="syncLocationLabel({{ $locationLabelExpression }})"
    x-on:keydown="onHeadlessMenuKeydown($event)"
    x-on:click.outside="if (!isEventInsideHeadlessMenu($event)) comboboxCloseMenu({reason:'outside'})"
    x-on:fff-parent-overlay-close.window="if ($event.target.contains(_pickerHostElement || $el)) comboboxCloseMenu({immediate:true,reason:'parent-close'})"
    class="fff-location-select fff-flex-text-input fff-flex-text-input--{{ $field->getSize() }} fff-flex-text-input--{{ $field->getVariant() }} has-focus-outline">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'location-select'])
    <div class="fff-flex-text-input__shell" x-ref="headlessTrigger">
        <div class="fff-flex-text-input__row"><div class="fff-flex-text-input__control">
            <x-filament::input.wrapper :prefix-icon="$searchPrefixIcon" :inline-prefix="true" class="fff-flex-text-input__wrapper">
                <x-filament-flex-fields::text-input.control id="{{ $searchId }}" type="text" maxlength="256"
                    :value="$initialLabel ?? ''" role="combobox" aria-label="{{ $searchConfig['labels']['search'] }}" aria-autocomplete="list" autocomplete="off" dir="auto"
                    :disabled="$searchConfig['disabled']" x-ref="headlessInlineSearchInput" x-bind:value="inlineSearchInputValue()" x-bind:disabled="disabled"
                    x-bind:aria-controls="menuDomId + '-listbox'" x-bind:aria-expanded="comboboxOpen" x-bind:aria-activedescendant="headlessActiveDescendant()"
                    x-on:input="onInlineSearchInput($event)" x-on:focus="onInlineSearchFocus()" x-on:blur="onInlineSearchBlur()"
                    x-on:keydown="onHeadlessControlKeydown($event)" placeholder="{{ $searchConfig['labels']['search'] }}" class="fi-input-has-inline-prefix" />
            </x-filament::input.wrapper>
        </div>
        @if (! $searchConfig['disabled'] && $searchConfig['clearable'])
            @if ($minimalClear ?? false)
                <button type="button" class="fff-location-select__clear" x-show="state !== null" @if (blank($initialLabel ?? '')) style="display:none" @endif x-on:click.stop="clearSelection()" aria-label="{{ __('filament-flex-fields::default.address_autocomplete.clear') }}">
                    <x-filament::icon :icon="$searchClearIcon" class="fff-flex-text-input__action-icon" />
                </button>
            @else
            <div class="fff-flex-text-input__action-group" x-show="state !== null" @if (blank($initialLabel ?? '')) style="display:none" @endif>
                <div class="fff-flex-text-input__action-item"><button type="button" class="fff-flex-text-input__action-btn" x-on:click.stop="clearSelection()" aria-label="{{ __('filament-flex-fields::default.address_autocomplete.clear') }}">
                    <x-filament::icon :icon="$searchClearIcon" class="fff-flex-text-input__action-icon" />
                </button></div>
            </div>
            @endif
        @endif
        </div>
    </div>
    <x-filament-flex-fields::select-menu :control-id="$searchId" :dynamic-id="true" :label="$searchConfig['labels']['search']" :searchable="true" :inline-search="true" />
</div>
