@props(['configuration', 'synchronize' => '', 'label'])
@php($runtimePlan = \Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan::for('select-field'))
<div class="fff-select-field fff-select-field--sm fff-select-field--secondary" x-id="['runtime-select']"
    x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('select-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="fffHeadlessSelectField({ componentKey: $id('runtime-select'), menuDomId: $id('runtime-select') + '-menu', searchable: false, dropdownMinWidth: 180, ...{{ $configuration }} })"
    x-init="{{ $synchronize }}" x-on:keydown="onHeadlessMenuKeydown($event)"
    x-on:click.outside="if (!isEventInsideHeadlessMenu($event)) comboboxCloseMenu({ immediate: true, reason: 'outside' })">
    @include('filament-flex-fields::partials.emit-assets', [
        'stylesheets' => $runtimePlan->stylesheets, 'chunks' => $runtimePlan->chunks,
        'consumerComponent' => 'select-field', 'consumerExpression' => "\$id('runtime-select')",
    ])
    <div class="fff-select-field__shell fff-select-field__shell--headless fi-select-input">
        <div class="fi-select-input-ctn" x-ref="headlessTriggerCtn">
            <button type="button" class="fi-select-input-btn" x-ref="headlessTrigger" data-fff-open-trigger
                x-bind:disabled="disabled" aria-haspopup="listbox" x-bind:aria-label="{{ $label }}"
                x-bind:aria-controls="$id('runtime-select') + '-listbox'" x-bind:aria-expanded="comboboxOpen"
                x-bind:aria-activedescendant="headlessActiveDescendant()" x-on:click="onHeadlessTriggerClick($event)"
                x-on:keydown="onHeadlessControlKeydown($event, { trigger: true })">
                <span class="fi-select-input-value-ctn"><span class="fi-select-input-value-label" x-text="triggerLabelHtml()"></span></span>
                <span class="fi-select-input-affixes"><span class="fi-select-input-append"><x-filament::icon icon="hugeicons-arrow-down-01" /></span></span>
            </button>
        </div>
    </div>
    <template x-teleport="body">
        <div x-ref="headlessMenu" x-show.important="comboboxOpen" x-cloak x-bind:inert="!comboboxOpen"
            x-bind:id="$id('runtime-select') + '-menu'" x-bind:data-fff-select-menu-owner="$id('runtime-select')"
            x-bind:class="{ 'is-positioned': menuReady }" x-on:click.stop x-on:keydown="onHeadlessMenuKeydown($event)"
            class="fi-dropdown-panel fff-select-dropdown-panel fff-select-headless-menu fff-teleported-menu fff-select-dropdown-panel--dropdown-fixed fff-select-dropdown-panel--overlay-scroll fff-select-dropdown-panel--layout-plain fi-width-none">
            <div class="fff-select-dropdown-scroller"><div class="fi-select-input-options-ctn" x-ref="headlessOptionsList"
                x-bind:id="$id('runtime-select') + '-listbox'" role="listbox" x-bind:aria-label="{{ $label }}">
                <template x-for="row in comboboxFilteredDropdownRows()" :key="row.key">
                    <button type="button" class="fi-select-input-option" role="option" x-bind:id="headlessOptionDomId(row.key)"
                        x-bind:data-fff-virt-key="row.key" x-bind:data-value="headlessOptionValue(row.option)"
                        x-bind:aria-selected="isOptionSelected(headlessOptionValue(row.option))"
                        x-bind:class="{ 'fi-selected': isOptionSelected(headlessOptionValue(row.option)) }"
                        x-on:mousedown.prevent x-on:pointermove="onHeadlessOptionPointerMove($event, row.key)"
                        x-on:click.stop.prevent="toggleOption(headlessOptionValue(row.option))">
                        <span class="fff-select-headless-option-label" x-text="optionDropdownLabel(row.option)"></span>
                    </button>
                </template>
            </div></div>
        </div>
    </template>
</div>
