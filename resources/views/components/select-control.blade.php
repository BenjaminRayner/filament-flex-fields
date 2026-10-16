@props(['options' => [], 'optionsExpression' => null, 'placeholder' => null, 'focusOutline' => true, 'disabledInitially' => false, 'field' => null, 'stateExpression' => 'null', 'onChange' => 'null', 'label', 'initialValue' => null, 'disabledExpression' => 'false', 'size' => 'sm', 'variant' => 'soft', 'static' => false, 'dropdownMinWidth' => 120, 'matchTriggerWidth' => true, 'controlId' => null])
@php
    $stableControlId = $controlId;
    $controlId ??= $field?->getId() ?? 'fff-select-control-' . \Illuminate\Support\Str::uuid();
    // Inline controls supply their complete local choice set, not a server search page.
    $controlConfig = $field?->getHeadlessAlpineConfig() ?? [
        'options' => $options,
        'optionsLimit' => 0,
        'virtualizeThreshold' => 50,
        'searchable' => false,
        'placeholder' => $placeholder ?? '',
        'dropdownMinWidth' => $dropdownMinWidth,
        'matchTriggerWidth' => $matchTriggerWidth,
        'keepSelectedOptionsInDropdown' => true,
        'canOptionLabelsWrap' => false,
    ];
@endphp
<div
    @if ($stableControlId !== null)
        wire:key="fff-inline-select-{{ $stableControlId }}"
    @endif
    @unless ($static)
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="fffHeadlessSelectField({ ...@js($controlConfig), state: {!! $stateExpression !!}, onChange: {!! $onChange !!}, disabled: {!! $disabledExpression !!}, componentKey: @js($field?->getKey() ?? $controlId), menuDomId: @js($controlId . '-fff-headless-menu-') + $id('fff-inline-select') })"
        x-effect="disabled = {!! $disabledExpression !!}; @if ($optionsExpression !== null) const next = {!! $optionsExpression !!}; if (JSON.stringify(options) !== JSON.stringify(next)) { options = next; syncEngineOptions() } @endif"
        x-init="$watch(() => {!! $stateExpression !!}, value => { state = value })"
        x-on:keydown="onHeadlessMenuKeydown($event)"
        x-on:fff-parent-overlay-close.window="if ($event.target.contains(_pickerHostElement || $el)) comboboxCloseMenu({ immediate: true, reason: 'parent-close' })"
        x-on:click.outside="if (!isEventInsideHeadlessMenu($event)) comboboxCloseMenu({ immediate: true, reason: 'outside' })"
    @else
        aria-hidden="true" inert
    @endunless
    class="fff-select-control fff-flex-text-input fff-flex-text-input--{{ $size }} fff-flex-text-input--{{ $variant }} {{ $focusOutline ? 'has-focus-outline' : '' }}">
    @unless ($static)
        @include('filament-flex-fields::partials.load-stylesheet', ['component' => $field?->getAssetComponent() ?? 'select-field'])
        @if ($field) {!! $field->getSelectCatalogMarkup() !!} @endif
    @endunless
    @isset($trigger)
        {{ $trigger }}
    @else
    <div class="fff-flex-text-input__shell"><div class="fff-flex-text-input__row">
        <button type="button" @unless ($static) id="{{ $controlId }}" @endunless class="fi-input fff-flex-text-input__input fff-select-control__trigger" role="combobox" aria-label="{{ $label }}" aria-haspopup="listbox"
            @if ($static)
                disabled
            @else
                x-ref="headlessTrigger" data-fff-open-trigger @disabled($disabledInitially)
                x-bind:aria-controls="menuDomId + '-listbox'" x-bind:disabled="disabled" x-bind:aria-expanded="comboboxOpen"
                x-bind:aria-activedescendant="headlessActiveDescendant()" x-on:click="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event)"
            @endif>
            <span @unless ($static) @if ($controlConfig['isHtmlAllowed'] ?? false) x-html="triggerLabelHtml()" @else x-text="triggerLabelHtml()" @endif @endunless>{{ $initialValue ?? '' }}</span>
            <x-filament::icon icon="flexhugeicons-unfold-more" />
        </button>
    </div></div>
    @endisset
    @unless ($static)
        <x-filament-flex-fields::select-menu :dynamic-id="true" :field="$field" :control-id="$controlId" :label="$label" />
    @endunless
</div>
