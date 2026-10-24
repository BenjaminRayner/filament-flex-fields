<button
    type="button"
    class="fi-select-input-option"
    x-bind:class="{
        'fi-selected': isOptionSelectedInDropdown(headlessOptionValue(row.option)),
        'fi-disabled': isHeadlessOptionDisabled(row.option),
    }"
    x-bind:data-value="headlessOptionValue(row.option)"
    x-bind:data-fff-virt-key="row.key"
    role="option"
    x-bind:id="headlessOptionDomId(row.key)"
    x-bind:aria-selected="isOptionSelected(headlessOptionValue(row.option)) ? 'true' : 'false'"
    x-bind:aria-disabled="isHeadlessOptionDisabled(row.option) ? 'true' : 'false'"
    x-bind:disabled="isHeadlessOptionDisabled(row.option)"
    {{-- Preserve search focus on press; commit after the completed click so it cannot hit a modal backdrop. --}}
    x-on:mousedown.prevent
    x-on:click.stop.prevent="toggleOption(headlessOptionValue(row.option))"
    x-on:pointermove="onHeadlessOptionPointerMove($event, row.key)"
>
    @if ($field instanceof \Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField)
        <span class="fff-country-map-option-row">
            <x-filament-flex-fields::selection-indicator x-bind:data-checked="isOptionSelected(headlessOptionValue(row.option)) ? 'true' : 'false'" />
            <span class="fff-select-headless-option-label" x-html="optionDropdownLabel(row.option)"></span>
        </span>
    @elseif ($isUserSelectField)
        <template x-if="! isOptionSelectedInDropdown(headlessOptionValue(row.option))">
            <span x-html="optionDropdownLabel(row.option)"></span>
        </template>

        <template x-if="isOptionSelectedInDropdown(headlessOptionValue(row.option))">
            <span class="fff-select-option-selected-row">
                <span
                    class="fff-select-option-selected-row__label"
                    x-html="optionDropdownLabel(row.option)"
                ></span>
                <span
                    class="fff-select-option-selected-check"
                    aria-hidden="true"
                    data-visible="true"
                >
                    <svg class="fff-select-option-selected-check__svg" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 17 18" focusable="false">
                        <polyline points="1 9 7 14 15 4"></polyline>
                    </svg>
                </span>
            </span>
        </template>
    @elseif (! (($sharedMenuMarkup ?? false) ? $showSelectionIndicator : ($field?->showsSelectionIndicator() ?? true)))
        <span class="fff-select-headless-option-label" x-html="optionDropdownLabel(row.option)"></span>
    @else
        <template x-if="! isOptionSelectedInDropdown(headlessOptionValue(row.option))">
            <span>
                @include('filament-flex-fields::forms.components.partials.select-field.option-label')
            </span>
        </template>

        <template x-if="isOptionSelectedInDropdown(headlessOptionValue(row.option))">
            <span class="fff-select-option-selected-row">
                <span class="fff-select-option-selected-row__label">
                    @include('filament-flex-fields::forms.components.partials.select-field.option-label')
                </span>

                @if ($isGridLayout)
                    <span
                        class="fff-select-option-selected-check"
                        aria-hidden="true"
                        x-html="selectedOptionCheckIconHtml"
                    ></span>
                @else
                    <span
                        class="fff-select-option-selected-check"
                        aria-hidden="true"
                        data-visible="true"
                    >
                        <svg class="fff-select-option-selected-check__svg" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 17 18" focusable="false">
                            <polyline points="1 9 7 14 15 4"></polyline>
                        </svg>
                    </span>
                @endif
            </span>
        </template>
    @endif
</button>
