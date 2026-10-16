                    <div x-show="shouldShowHeadlessDropdownOptions()" class="fff-select-headless-options-shell">
                        <div
                            class="fff-select-headless-options-root"
                            x-bind:class="{ 'fff-select-headless-options-root--with-separators': optionGroupSeparators }"
                            x-bind:style="comboboxVirtualListStyle()"
                        >
                            {{-- Stateless fixed-height renderers can reuse slots; logical row IDs remain data-owned. --}}
                            <template
                                @if ($headlessRecycleRows ?? false)
                                    x-for="(row, rowSlotIndex) in comboboxFilteredDropdownRows()" :key="row.type + ':' + rowSlotIndex"
                                @else
                                    x-for="row in comboboxFilteredDropdownRows()" :key="row.key"
                                @endif
                            >
                                <div
                                    class="fff-select-headless-dropdown-row"
                                    x-bind:data-row-type="row.type"
                                >
                                    <template x-if="row.type === 'section' || row.type === 'group-header'">
                                        <div
                                            x-bind:data-fff-virt-key="row.key"
                                            class="fi-dropdown-header"
                                            x-bind:class="{ 'fff-select-smart-section': row.type === 'section' }"
                                            x-text="row.label"
                                        ></div>
                                    </template>

                                    <template x-if="row.type === 'separator'">
                                        <div x-bind:data-fff-virt-key="row.key" class="fff-select-option-group-separator" role="separator" aria-hidden="true"></div>
                                    </template>

                                    <template x-if="row.type === 'create'">
                                        <button
                                            type="button"
                                            x-bind:data-fff-virt-key="row.key"
                                            class="fi-select-input-option fff-select-smart-create"
                                            x-bind:id="headlessOptionDomId(row.key)"
                                            aria-selected="false"
                                            x-on:pointermove="onHeadlessOptionPointerMove($event, row.key)"
                                            role="option"
                                            x-on:mousedown.prevent
                                            x-on:click.stop.prevent="selectCreateOption(String(comboboxQuery ?? '').trim() || row.value)"
                                        >
                                            <span>
                                                <span class="fff-select-smart-create__content">
                                                    <span class="fff-select-smart-create__icon" aria-hidden="true">{!! $headlessSmartCreateIconHtml !!}</span>
                                                    <span class="fff-select-headless-option-label fff-select-smart-create__label" x-html="smartCreateRowHtml()"></span>
                                                </span>
                                            </span>
                                        </button>
                                    </template>

                                    <template x-if="row.type === 'option'">
                                        @include($headlessOptionView ?? 'filament-flex-fields::forms.components.partials.select-field-headless-option')
                                    </template>
                                </div>
                            </template>

                            <div
                                x-show="shouldShowMaxItemsMessage()"
                                x-cloak
                                class="fff-select-max-items-message"
                                role="status"
                                aria-live="polite"
                                x-text="maxItemsMessage"
                            ></div>

                        </div>

                        @include('filament-flex-fields::forms.components.partials.select-field.load-more')

                        {{-- Outside the virtualized root so end inset is not mixed into virtual paddingBottom. --}}
                        <div class="fff-select-dropdown-list-end-spacer" aria-hidden="true"></div>
                    </div>
