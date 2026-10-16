<button type="button" class="fff-icon-select__option"
    x-bind:data-fff-virt-key="row.key" x-bind:id="headlessOptionDomId(row.key)"
    x-bind:data-value="row.option.value" x-bind:aria-label="row.option.label"
    role="option" x-bind:aria-selected="isOptionSelected(row.option.value) ? 'true' : 'false'"
    x-bind:disabled="disabled || isHeadlessOptionDisabled(row.option)"
    x-on:pointermove="onHeadlessOptionPointerMove($event, row.key)"
    x-on:mousedown.prevent
    x-on:click.stop.prevent="toggleOption(row.option.value)"
    x-on:keydown="onHeadlessControlKeydown($event)" x-bind:title="row.option.label">
    <span class="fff-icon-select__glyph" aria-hidden="true" x-effect="syncIconOptionPreview($el, row)"></span>
    <span class="fff-icon-select__label" x-text="row.option.label"></span>
</button>
