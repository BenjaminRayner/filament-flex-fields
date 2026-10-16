        <div
            @class([
                'fi-select-input-ctn',
                'fi-select-input-ctn-clearable' => $headlessTriggerHasClearableValue,
                'fi-select-input-ctn-option-labels-not-wrapped' => ! $canOptionLabelsWrap,
            ])
            x-ref="headlessTriggerCtn"
            @if ($field->isClearable() && ! $isMultiple && ! $isDisabled)
                x-bind:class="{
                    'fi-select-input-ctn-clearable': hasClearableSelection(),
                }"
            @endif
        >
            <button
                type="button"
                class="fi-select-input-btn"
                x-ref="headlessTrigger" data-fff-open-trigger
                @if (! $headlessUsesInlineSearchControl)
                    id="{{ $id }}"
                @endif
                x-bind:disabled="disabled"
                @disabled($isDisabled)
                x-bind:aria-expanded="comboboxOpen ? 'true' : 'false'"
                aria-haspopup="listbox"
                aria-controls="{{ $headlessListboxId }}"
                @if ($isAutofocused && ! $headlessUsesInlineSearchControl)
                    autofocus
                @endif
                x-bind:class="{ 'fi-select-input-btn--search-active': inlineSearch && searchable && comboboxOpen, 'is-loading': shouldShowHeadlessTriggerLoading() }"
                x-on:click="onHeadlessTriggerClick($event)"
                x-on:keydown="onHeadlessControlKeydown($event, { trigger: true })"
                x-bind:aria-activedescendant="headlessActiveDescendant()"
            >
                @if ($showInlineFieldLabel)
                    <span class="fff-select-inline-field-label">{{ $fieldLabel }}</span>
                @endif

                <span class="fi-select-input-value-ctn">
                    @if ($isItemCardVariant && ! $isMultiple)
                        <span
                            @class([
                                'fi-select-input-value-label' => filled($state),
                                'fi-select-input-placeholder' => blank($state),
                            ])
                            x-bind:class="{
                                'fi-select-input-value-label': isTriggerLabelSelected(),
                                'fi-select-input-placeholder': ! isTriggerLabelSelected(),
                            }"
                            x-text="triggerLabelHtml()"
                        >{{ $itemCardInitialTriggerLabel }}</span>
                    @else
                    <template x-if="! isUserSelectField && multiple && selectedChips().length > 0">
                        <span
                            class="fi-select-input-value-badges-ctn"
                            x-ref="headlessBadgesCtn"
                            @if ($isReorderable && ! $isDisabled)
                                x-sortable
                                data-sortable-animation-duration="150"
                                x-on:end.stop="reorderSelectedChips($event)"
                                x-on:click.stop
                                x-on:mousedown.stop
                            @endif
                        >
                            <template x-for="(chip, chipIndex) in selectedChips()" x-bind:key="`${chip.value}-${chipIndex}`">
                                <span
                                    @class([
                                        'fi-badge fi-size-md',
                                        'fi-reorderable' => $isReorderable && ! $isDisabled,
                                    ])
                                    x-bind:data-value="chip.value"
                                    x-bind:class="{ 'fff-select-entity-mention-chip': chip.isEntityMention }"
                                    @if ($isReorderable && ! $isDisabled)
                                        x-bind:x-sortable-item="chipIndex"
                                        x-sortable-handle
                                    @endif
                                >
                                    <span class="fi-badge-label-ctn">
                                        <template x-if="isHtmlAllowed">
                                            <span
                                                class="fi-badge-label"
                                                x-bind:class="{ 'fi-wrapped': canOptionLabelsWrap }"
                                                x-html="chip.label"
                                            ></span>
                                        </template>
                                        <template x-if="! isHtmlAllowed">
                                            <span
                                                class="fi-badge-label"
                                                x-bind:class="{ 'fi-wrapped': canOptionLabelsWrap }"
                                                x-text="chip.label"
                                            ></span>
                                        </template>
                                    </span>
                                    <button
                                        type="button"
                                        class="fi-badge-delete-btn"
                                        x-bind:disabled="disabled"
                                        x-on:click.stop="comboboxDeselectValue(chip.value)"
                                        x-bind:aria-label="'Remove ' + plainOptionLabel(chip.value)"
                                    >
                                        {!! $chipRemoveIconHtml !!}
                                    </button>
                                </span>
                            </template>
                        </span>
                    </template>

                    @if ($showHeadlessStaticTriggerLabel)
                        <span
                            @class([
                                'fi-select-input-value-label' => ! $isInitialTriggerPlaceholder,
                                'fi-select-input-placeholder' => $isInitialTriggerPlaceholder,
                            ])
                            x-show="! isUserSelectField && (! multiple || selectedChips().length === 0)"
                            x-bind:class="{
                                'fi-select-input-value-label': isTriggerLabelSelected(),
                                'fi-select-input-placeholder': ! isTriggerLabelSelected(),
                            }"
                            @if ($isHtmlAllowed)
                                x-html="triggerLabelHtml()"
                            @else
                                x-text="triggerLabelHtml()"
                            @endif
                        >
                            @if ($isHtmlAllowed && ! $isInitialTriggerPlaceholder)
                                {!! $initialTriggerLabel !!}
                            @else
                                {{ $initialTriggerLabel }}
                            @endif
                        </span>
                    @endif

                    <template x-if="isUserSelectField && isTriggerLabelSelected() && ! (inlineSearch && searchable && ! multiple)">
                        <span class="fi-select-input-value-label" x-html="triggerLabelHtml()"></span>
                    </template>
                    <template x-if="isUserSelectField && ! isTriggerLabelSelected() && ! (inlineSearch && searchable && ! multiple)">
                        <span class="fi-select-input-placeholder" x-text="triggerLabelHtml()"></span>
                    </template>
                    @endif
                </span>

                @if ($isInlineSearch && $isSearchable)
                    <span class="fff-select-inline-search-ctn" x-on:click.stop x-on:mousedown.stop>
                        <input
                            type="text"
                            role="combobox"
                            autocomplete="off"
                            class="fi-input"
                            id="{{ $id }}"
                            aria-controls="{{ $headlessListboxId }}"
                            aria-autocomplete="list"
                            @if ($isAutofocused)
                                autofocus
                            @endif
                            dir="auto"
                            x-ref="headlessInlineSearchInput"
                            x-model="comboboxQuery"
                            x-on:input="onInlineSearchClearedIfEmpty($event)"
                            x-on:focus="onInlineSearchFocus()"
                            x-on:blur="onInlineSearchBlur()"
                            x-on:keydown="onHeadlessControlKeydown($event)"
                            x-bind:aria-activedescendant="headlessActiveDescendant()"
                            x-bind:readonly="inlineSearchInputReadonly()"
                            x-bind:placeholder="inlineSearchInputPlaceholder()"
                            x-bind:disabled="disabled"
                            x-bind:aria-expanded="comboboxOpen ? 'true' : 'false'"
                        />
                    </span>
                @endif

                @if ($isItemCardVariant && ! $isMultiple)
                    <span class="fff-select-item-card-trigger__chevron" aria-hidden="true">{!! $chevronIconHtml !!}</span>
                @else
                    <span class="fff-select-trigger-chevron" aria-hidden="true">{!! $chevronIconHtml !!}</span>
                @endif

                <span
                    class="fff-select-trigger-loading-indicator"
                    x-show="shouldShowHeadlessTriggerLoading()"
                    x-cloak
                    aria-hidden="true"
                >
                    <x-filament::loading-indicator class="fff-select-trigger-loading-indicator__spinner" />
                </span>
            </button>

            @if ($field->isClearable() && ! $isMultiple && ! $isDisabled)
                <button
                    type="button"
                    class="fi-select-input-value-remove-btn"
                    @if (blank($state)) style="display:none" @endif
                    x-show="hasClearableSelection()"
                    x-on:click.stop="clearSelection()"
                    x-bind:aria-label="'Clear selection'"
                >
                    {!! $clearIconHtml !!}
                </button>
            @endif
        </div>
