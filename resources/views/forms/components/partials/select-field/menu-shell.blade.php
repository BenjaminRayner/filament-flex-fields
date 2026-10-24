        <template x-teleport="body">
            <div
                x-ref="headlessMenu"
                @if ($panelStyle ?? null) style="{{ $panelStyle }}" @endif
                @if ($sharedMenuMarkup ?? false)
                    x-bind:id="menuDomId"
                    x-bind:data-fff-select-menu-owner="componentKey"
                @else
                    id="{{ $headlessMenuDomId }}"
                    @if ($dynamicId ?? false) x-bind:id="menuDomId" @endif
                    data-fff-select-menu-owner="{{ $headlessComponentKey }}"
                @endif
                x-show.important="comboboxOpen"
                x-bind:style="{ ...(menuHeight > 0 ? { '--fff-select-menu-height': menuHeight + 'px' } : {}), ...(menuMaxHeight > 0 ? { '--fff-select-menu-max-height': menuMaxHeight + 'px' } : {}) }"
                x-bind:aria-hidden="comboboxOpen ? 'false' : 'true'"
                x-bind:inert="!comboboxOpen"
                x-cloak
                x-on:click.stop
                x-on:keydown="onHeadlessMenuKeydown($event)"
                x-bind:class="{ 'is-positioned': menuReady }"
                @class([
                    'fi-dropdown-panel',
                    'fff-select-dropdown-panel',
                    'fff-select-headless-menu',
                    'fff-select-dropdown-panel--below',
                    'fff-teleported-menu',
                    'fff-select-dropdown-panel--dropdown-fixed',
                    'fff-select-dropdown-panel--overlay-scroll',
                    // Filament theme defaults to max-w-[14rem]! — kill it so matchTriggerWidth can work.
                    'fi-width-none',
                    ...($panelClasses ?? []),
                    'fff-select-dropdown-panel--layout-' . ($isUserSelectField ? 'list' : ($useRichListDropdownLayout ? 'list' : ($isGridLayout ? 'grid' : 'plain'))),
                    'fff-select-dropdown-panel--user-select' => $isUserSelectField,
                    'fff-select-dropdown-panel--country' => $field instanceof \Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField,
                    'fff-select-dropdown-panel--country-'.($field?->getSize() ?? 'md') => $field instanceof \Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField,
                    'fff-select-dropdown-panel--no-selection-indicator' => ! (($sharedMenuMarkup ?? false) ? $showSelectionIndicator : ($field?->showsSelectionIndicator() ?? true)),
                    'fff-select-dropdown-panel--description-trailing' => (($sharedMenuMarkup ?? false) ? $optionDescriptionPosition : $field?->getOptionDescriptionPosition()) === 'trailing',
                    'fi-select-input-ctn-option-labels-not-wrapped' => ! $canOptionLabelsWrap,
                ])
                x-bind:aria-label="{{ ($sharedMenuMarkup ?? false) ? 'menuLabel' : json_encode($fieldLabel ?? $statePath) }}"
            >
                @if ($isSearchable)
                    <div
                        class="fi-select-input-search-ctn fff-select-input-search-ctn"
                        @if ($isInlineSearch)
                            x-show="shouldShowMenuSearch()"
                            x-cloak
                        @endif
                    >
                        <div class="fff-select-search-control">
                        <input
                            type="search"
                            class="fi-input fi-select-input-search-input"
                            @if ($sharedMenuMarkup ?? false) x-bind:id="searchDomId" @else id="{{ $headlessSearchId }}" @endif
                            autocomplete="off"
                            @if ($sharedMenuMarkup ?? false) x-bind:aria-controls="listboxDomId" @else aria-controls="{{ $headlessListboxId }}" @endif
                            aria-autocomplete="list"
                            {{-- auto: Hebrew/Arabic → RTL caret; Latin queries keep LTR caret (forced dir=rtl put the caret on the wrong side for "teraz"). --}}
                            dir="auto"
                            x-model="comboboxQuery"
                            x-ref="headlessSearchInput"
                            role="combobox"
                            x-bind:aria-expanded="comboboxOpen ? 'true' : 'false'"
                            x-bind:aria-activedescendant="headlessActiveDescendant()"
                            x-on:keydown="onHeadlessControlKeydown($event)"
                            x-bind:placeholder="searchPrompt"
                        />
                        <button
                            type="button"
                            class="fff-select-search-clear-btn"
                            x-cloak
                            x-show="String(comboboxQuery ?? '').length > 0"
                            x-on:click.stop="comboboxClearSearch()"
                            x-bind:aria-label="@js(__('filament-flex-fields::default.select_field.clear_search'))"
                        >
                            {!! $headlessSearchClearIconHtml !!}
                        </button>
                        </div>
                    </div>
                @endif

                {{ $toolbar ?? '' }}
                <div class="fff-select-dropdown-scroller">
                <div
                    @class([
                        'fi-select-input-options-ctn',
                        'fi-dropdown-list' => $isGridLayout,
                    ])
                    @if ($sharedMenuMarkup ?? false)
                        x-bind:id="listboxDomId"
                    @else
                        id="{{ $headlessListboxId }}"
                        @if ($dynamicId ?? false) x-bind:id="menuDomId + '-listbox'" @endif
                    @endif
                    role="listbox"
                    x-bind:aria-multiselectable="multiple ? 'true' : null"
                    x-ref="headlessOptionsList"
                    x-bind:data-fff-virtualized="comboboxOpen && shouldVirtualizeDropdown() ? 'true' : null"
                    x-on:scroll.passive="onHeadlessOptionsScroll($event)"
                    x-bind:aria-label="{{ ($sharedMenuMarkup ?? false) ? 'menuLabel' : json_encode($fieldLabel ?? $statePath) }}"
                >
                @if ($field instanceof \Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryMapField)
                    <div class="fff-country-map__leading-options" data-fff-select-leading-content>
                        @include('filament-flex-fields::forms.components.partials.location.country-groups')
                        <div class="fi-dropdown-header">{{ __('filament-flex-fields::location.countries') }}</div>
                    </div>
                @endif
                    @include('filament-flex-fields::forms.components.partials.picker-request-error', [
                        'errorExpression' => 'searchError || optionsError',
                        'retryHandler' => 'retryHeadlessRequest()',
                        'busyExpression' => 'searchPending || optionsLoading || loadingMore || requestRetryRemaining > 0',
                        'cooldownExpression' => 'requestRetryRemaining',
                    ])
                    @include('filament-flex-fields::forms.components.partials.select-field.option-list')

                    @include('filament-flex-fields::forms.components.partials.select-field.empty-create')
                </div>
                    <div
                        class="fff-select-dropdown-scrollbar"
                        data-visible="false"
                        data-active="false"
                        aria-hidden="true"
                    >
                        <div class="fff-select-dropdown-scrollbar__thumb"></div>
                    </div>
            </div>
        </template>
