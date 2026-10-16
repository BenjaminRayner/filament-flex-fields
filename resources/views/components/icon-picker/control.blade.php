        <div wire:ignore wire:key="{{ $getLivewireKey() }}.icon.{{ hash('xxh128', serialize([array_diff_key($configuration, array_flip(['initialSelectedName', 'initialSelectedHtml'])), $field->getVariant()])) }}"
            @class(['fff-icon-select', 'fi-select-input' => !$inline, 'fff-icon-select--inline' => $inline]) x-fff-load
            x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('icon-picker-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="iconPickerFieldFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
            x-on:keydown="onHeadlessMenuKeydown($event)"
            x-on:click.outside="if (!isEventInsideHeadlessMenu($event)) comboboxCloseMenu({ immediate:true, reason:'outside' })">
            @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'icon-picker-field', 'livewireKey' => $getLivewireKey(), 'capabilities' => $inline ? ['inline'] : []])
            @if ($inline)
                <button type="button" id="{{ $controlId }}" class="fff-flex-text-input__action-btn" x-ref="headlessTrigger" data-fff-open-trigger
                    aria-label="{{ $getLabel() }}" aria-describedby="{{ $controlId }}-fff-icon-error" aria-haspopup="listbox" aria-expanded="false"
                    @disabled($locked) x-bind:disabled="disabled" x-bind:aria-expanded="comboboxOpen" x-bind:aria-controls="menuDomId + '-listbox'"
                    x-on:click="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event, {trigger:true})">
                    <span class="fff-icon-select__preview" x-show="iconHtml" x-html="iconHtml" @if ($initialHtml === '') style="display:none" @endif>{!! $initialHtml !!}</span>
                    <span x-show="!iconHtml" @if ($initialHtml !== '') style="display:none" @endif>
                        <x-filament::icon icon="hugeicons-geometric-shapes-01" class="fff-flex-text-input__action-icon" />
                    </span>
                </button>
            @else
            <div @class(['fi-select-input-ctn', 'fi-select-input-ctn-clearable' => $field->isClearable() && filled($initialName) && ! $locked]) x-bind:class="{ 'fi-select-input-ctn-clearable': hasClearableSelection() }">
                <button type="button" id="{{ $controlId }}" class="fi-select-input-btn" x-ref="headlessTrigger" data-fff-open-trigger
                    aria-label="{{ $getLabel() }}" aria-haspopup="listbox" aria-expanded="false"
                    @disabled($locked) x-bind:disabled="disabled" x-bind:aria-expanded="comboboxOpen" x-bind:aria-controls="menuDomId + '-listbox'"
                    x-on:click="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event, {trigger:true})">
                    <span class="fi-select-input-value-ctn"><span class="fi-select-input-value-label fff-icon-select__value">
                        <span class="fff-icon-select__preview" x-html="iconHtml">{!! $initialHtml !!}</span>
                        <span class="fff-icon-select__name" x-text="iconLabel || placeholder">{{ $initialName ?: $placeholder }}</span>
                    </span></span>
                    <span class="fff-select-trigger-chevron" aria-hidden="true"><x-filament::icon :icon="$field->getChevronIcon()" stroke-width="2" /></span>
                </button>
                @if ($field->isClearable())
                    <button type="button" class="fi-select-input-value-remove-btn" @if (blank($initialName) || $locked) style="display:none" @endif x-show="hasClearableSelection()"
                        x-on:click.stop="clearSelection()" aria-label="{{ __('filament-flex-fields::default.icon_picker.clear') }}">
                        <x-filament::icon :icon="$field->getClearIcon()" stroke-width="2" />
                    </button>
                @endif
            </div>
            @endif
            <x-filament-flex-fields::select-menu :control-id="$controlId" :label="$getLabel()" :searchable="true" :grid="$layout !== 'list'"
                option-view="filament-flex-fields::forms.components.partials.icon-select.option"
                loading-view="filament-flex-fields::forms.components.partials.icon-select.loading"
                load-more-view="filament-flex-fields::forms.components.partials.icon-select.loading-more"
                :renderer-data="['iconLayout' => $layout, 'iconPageSize' => $field->getPerPage(), 'iconColumns' => $field->getGridColumns()]"
                :panel-classes="['fff-icon-select__menu', 'fff-icon-select__menu--'.$layout]"
                :panel-style="'--fff-icon-columns: '.$field->getGridColumns()">
                <x-slot:toolbar>
                    @include('filament-flex-fields::forms.components.partials.icon-select.sets')
                    @if ($inline && $field->isClearable())
                        <button type="button" class="fff-icon-select__inline-clear" x-cloak x-show="state && !disabled" x-on:click.stop="clearSelection(); comboboxCloseMenu()">
                            <x-filament::icon :icon="$field->getClearIcon()" />
                            {{ __('filament-flex-fields::default.icon_picker.clear') }}
                        </button>
                    @endif
                </x-slot:toolbar>
            </x-filament-flex-fields::select-menu>
        </div>
