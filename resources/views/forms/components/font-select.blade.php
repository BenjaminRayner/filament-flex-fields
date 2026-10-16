@php
    $statePath = $getStatePath();
    $seed = $field->getFontSeed();
    $locked = $isDisabled() || $isReadOnly();
    $controlId = $getId();
    $previewSelectedFont = $field->isSelectedFontPreviewEnabled();
    $configuration = [
        'seed' => $seed, 'tabs' => $field->getFontCategoryTabs(), 'perPage' => $field->getFontPerPage(),
        'componentKey' => $getKey(), 'livewireId' => $field->getLivewire()->getId(), 'menuDomId' => $controlId.'-fff-headless-menu',
        'disabled' => $locked, 'clearable' => $isClearable(), 'placeholder' => $getPlaceholder(),
        'previewSelectedFont' => $previewSelectedFont,
        'labels' => ['search' => __('filament-flex-fields::font-select.search'), 'empty' => __('filament-flex-fields::font-select.empty'),
            'loading' => __('filament-flex-fields::default.select_field.searching'), 'more' => __('filament-flex-fields::default.icon_picker.load_more')],
    ];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-filament::input.wrapper :disabled="$locked" :valid="! $errors->has($statePath)"
        :inline-prefix="$isPrefixInline()" :inline-suffix="$isSuffixInline()"
        :prefix="$getPrefixLabel()" :prefix-icon="$getPrefixIcon()" :prefix-actions="$getPrefixActions()" :prefix-icon-color="$getPrefixIconColor()"
        :suffix="$getSuffixLabel()" :suffix-icon="$getSuffixIcon()" :suffix-actions="$getSuffixActions()" :suffix-icon-color="$getSuffixIconColor()"
        :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($field->getWrapperClasses())">
        <div wire:ignore wire:key="{{ $getLivewireKey() }}.font.{{ hash('xxh128', serialize([$field->getFontPolicy(), $field->getFontPreviewPolicy(), $previewSelectedFont, $field->getFontCategoryTabs(), $field->getFontPerPage(), \Bjanczak\FilamentFlexFields\Support\Fonts\Providers\GoogleFonts::revision()])) }}"
            class="fi-select-input fff-font-select" x-fff-load
            x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('font-select', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="fontSelectFormComponent({ ...@js($configuration), state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
            x-on:keydown="onHeadlessMenuKeydown($event)"
            x-on:click.outside="if (!isEventInsideHeadlessMenu($event)) comboboxCloseMenu({ immediate:true, reason:'outside' })">
            @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'font-select', 'livewireKey' => $getLivewireKey()])
            <div class="fi-select-input-ctn" x-bind:class="{'fi-select-input-ctn-clearable':clearable && state && !disabled}">
                <button type="button" id="{{ $controlId }}" class="fi-select-input-btn" x-ref="headlessTrigger" data-fff-open-trigger
                    aria-label="{{ $getLabel() }}" aria-haspopup="listbox" aria-expanded="false" @disabled($locked)
                    x-bind:disabled="disabled" x-bind:aria-expanded="comboboxOpen" x-bind:aria-controls="menuDomId + '-listbox'"
                    x-on:click="onHeadlessTriggerClick($event)" x-on:keydown="onHeadlessControlKeydown($event, {trigger:true})">
                    <span class="fi-select-input-value-ctn"><span class="fi-select-input-value-label"
                        x-bind:style="selectedFontPreviewStyle()" x-text="fontSelection?.label || placeholder">{{ $seed['label'] ?? $getPlaceholder() }}</span></span>
                </button>
                @if ($isClearable())
                    <button type="button" class="fi-select-input-value-remove-btn" x-cloak x-show="state && !disabled"
                        x-on:click.stop="clearSelection()" aria-label="{{ __('filament-flex-fields::font-select.clear') }}">
                        <x-filament::icon :icon="$field->getClearIcon()" />
                    </button>
                @endif
            </div>
            <x-filament-flex-fields::select-menu :control-id="$controlId" :label="$getLabel()" :searchable="true"
                option-view="filament-flex-fields::forms.components.partials.font-select.option"
                :panel-classes="['fff-font-select__menu']" :recycle-rows="true">
                <x-slot:toolbar>
                    @if ($field->getFontCategoryTabs() !== [])
                        <div class="fff-font-select__categories" x-on:keydown.enter.stop x-on:keydown.space.stop role="group" aria-label="{{ __('filament-flex-fields::font-select.category') }}">
                            @foreach ($field->getFontCategoryTabs() as $tab)
                                <x-filament::button color="primary" size="xs" class="fff-font-select__category" :x-bind:aria-pressed="'fontCategory === '.\Illuminate\Support\Js::from($tab['value'])"
                                    :x-on:click="'selectFontCategory('.\Illuminate\Support\Js::from($tab['value']).')'">{{ $tab['label'] }}</x-filament::button>
                            @endforeach
                        </div>
                    @endif
                    <button type="button" class="fff-font-select__retry" x-cloak x-show="hasFailedFontPreviews()" x-on:click="retryFontPreviews()">
                        <x-filament::icon icon="hugeicons-refresh" />{{ __('filament-flex-fields::font-select.retry') }}
                    </button>
                </x-slot:toolbar>
            </x-filament-flex-fields::select-menu>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
