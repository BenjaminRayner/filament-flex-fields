<button type="button" tabindex="-1" class="fff-font-select__option" x-bind:data-fff-virt-key="row.key" x-bind:id="headlessOptionDomId(row.key)"
    x-bind:data-highlighted="currentDisplayNavigationEntry()?.key === row.key" x-bind:data-value="row.option.value" x-bind:aria-label="row.option.label" role="option"
    x-bind:aria-selected="isOptionSelected(row.option.value) ? 'true' : 'false'" x-bind:disabled="disabled || isHeadlessOptionDisabled(row.option)"
    x-on:pointermove="onHeadlessOptionPointerMove($event, row.key)"
    x-on:pointerdown="captureFontOptionPress($event, row.option.value)" x-on:pointercancel="cancelFontOptionPress($event)" x-on:mousedown.prevent
    x-on:click.stop.prevent="selectPressedFontOption($event, row.option.value)" x-on:keydown="onHeadlessControlKeydown($event)">
    <span class="fff-font-select__specimen" x-bind:data-preview-status="fontPreview(row.option.font).status">
        <span aria-hidden="true" class="fff-font-select__sample" x-bind:style="fontPreviewStyle(row.option.font)" x-text="row.option.label"></span>
        <span class="fff-font-select__skeleton" aria-hidden="true"></span>
        <span aria-hidden="true" class="fff-font-select__fallback" x-text="row.option.label"></span>
    </span>
    <span class="fff-font-select__check" aria-hidden="true" x-show="isOptionSelected(row.option.value)"><x-filament::icon icon="hugeicons-tick-02" /></span>
</button>
