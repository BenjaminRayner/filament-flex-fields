@php
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Filament\Support\Facades\FilamentAsset;
    $statePath = $getStatePath();
    $disabled = $isDisabled();
    $reorderable = ! $disabled && $isReorderable();
    $initialTags = $field->getInitialTagsForSsr();
    $selectConfiguration = $field->getTagSelectConfiguration();
    $structuralConfiguration = array_diff_key($selectConfiguration, array_flip(['initialState', 'initialOptionLabels', 'state']));
    $count = count($initialTags).($getMaxTags() === null ? '' : '/'.$getMaxTags());
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'tags-field', 'livewireKey' => $getLivewireKey()])
    <div wire:ignore wire:key="{{ $getLivewireKey() }}.{{ md5(serialize([$disabled, $getSize(), $getVariant(), $structuralConfiguration])) }}">
        <div x-fff-load x-fff-load-src="{{ FilamentAsset::getAlpineComponentSrc('tags-field', FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="tagsFieldFormComponent({ select: { ...@js($selectConfiguration), state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} },
                splitKeys: @js($getSplitKeys()), duplicateInsensitive: @js($isDuplicateInsensitive()), suggestionsOnly: @js($isSuggestionsOnly()),
                tagPrefix: @js($getTagPrefix() ?? ''), tagSuffix: @js($getTagSuffix() ?? ''),
                tagAddedMessage: @js(__('filament-forms::components.tags_input.tag_added')),
                tagRemovedMessage: @js(__('filament-forms::components.tags_input.tag_removed')) })"
            x-on:click.outside="if (comboboxOpen && !isEventInsideHeadlessMenu($event)) comboboxCloseMenu({ immediate: true, reason: 'outside' })"
            x-on:fff-parent-overlay-close.window="if ($event.target.contains(_pickerHostElement || $el)) comboboxCloseMenu({ immediate: true, reason: 'parent-close' })"
            {{ $getExtraAlpineAttributeBag() }}
            @class([...$getWrapperClasses(), 'is-disabled' => $disabled, 'has-focus-outline' => $shouldShowFocusOutline()])>
            <div x-ref="headlessTrigger" @class(['fff-flex-text-input__shell', 'is-invalid' => $errors->has($statePath)])>
                <div class="fff-flex-text-input__row">
                    @include('filament-flex-fields::forms.components.partials.tags.affix', ['position' => 'prefix'])
                    <div class="fff-flex-text-input__control">
                        <x-filament-flex-fields::text-input.control
                            :id="$getId()" :placeholder="$getPlaceholder()" type="text" autocomplete="off" dir="auto"
                            :disabled="$disabled" :autofocus="$isAutofocused()"
                            x-ref="headlessInlineSearchInput" data-fff-open-trigger role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
                            x-bind:aria-expanded="comboboxOpen" aria-controls="{{ $getId() }}-listbox"
                            x-bind:aria-activedescendant="headlessActiveDescendant()" x-bind:disabled="disabled"
                            x-model="comboboxQuery" x-on:focus="onTagFocus()" x-on:input="onTagInput($event)"
                            x-on:blur="onTagBlur($event)" x-on:keydown="onTagKeydown($event)" x-on:paste="onTagPaste($event)"
                            :attributes="$getExtraInputAttributeBag()" />
                    </div>
                    @include('filament-flex-fields::forms.components.partials.tags.affix', ['position' => 'suffix'])
                </div>
            </div>
            <template x-if="headlessMenuMounted">
                <x-filament-flex-fields::select-menu :control-id="$getId()" :label="$getLabel()" :searchable="true" :inline-search="true" />
            </template>
            <div class="fff-tags-field__chip-mount" x-show="comboboxSelectedValues.length > 0" @if (! count($initialTags)) style="display: none" @endif>
                <div class="fff-tags-field__tags fff-tags-field__tags-ssr" aria-hidden="true">
                    @foreach ($initialTags as $tag)
                        <span @class(['fff-tags-field__tag', 'is-reorderable' => $reorderable])>
                            <span class="fff-tags-field__tag-label">{{ $field->getTagDisplayLabel($tag) }}</span>
                            <span class="fff-tags-field__tag-remove">@include('filament-flex-fields::forms.components.partials.tag-pill-remove-icon')</span>
                        </span>
                    @endforeach
                </div>
                <div class="fff-tags-field__tags fff-tags-field__tags-live" x-show="comboboxSelectedValues.length > 0"
                    @if ($reorderable) x-sortable data-sortable-animation-duration="{{ $getReorderAnimationDuration() }}" x-on:end.stop="reorderTags($event)" @endif>
                    <template x-for="(tag, index) in comboboxSelectedValues" x-bind:key="tag">
                        <span @class(['fff-tags-field__tag', 'is-reorderable' => $reorderable])
                            @if ($reorderable) x-bind:x-sortable-item="index" x-sortable-handle @endif>
                            <span class="fff-tags-field__tag-label" x-text="displayLabel(tag)"></span>
                            <button type="button" class="fff-tags-field__tag-remove" x-on:click.stop="deleteTag(tag)" @disabled($disabled)
                                x-bind:aria-label="@js(__('filament-forms::components.tags_input.actions.delete.label')) + ': ' + tag">
                                @include('filament-flex-fields::forms.components.partials.tag-pill-remove-icon')
                            </button>
                        </span>
                    </template>
                </div>
            </div>
            @if ($shouldShowTagCount()) <div class="fff-tags-field__meta" x-text="tagCountLabel()">{{ $count }}</div> @endif
            <div class="fff-tags-field__meta" x-cloak x-show="shouldShowMaxItemsMessage()" role="status" x-text="maxItemsMessage"></div>
            <div class="fi-sr-only" aria-live="polite" x-text="tagAnnouncement"></div>
        </div>
    </div>
</x-dynamic-component>
