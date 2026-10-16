@php
    $panelMergeTags = $field->getMergeTags();
    $panelBlocks = method_exists($field, 'getGroupedCustomBlocks') ? $field->getGroupedCustomBlocks() : collect(['' => collect($field->getCustomBlocks())])->filter(fn ($blocks) => $blocks->isNotEmpty());
    $panelSearchable = method_exists($field, 'hasSearchableCustomBlocks') && $field->hasSearchableCustomBlocks();
    $panelGrid = method_exists($field, 'hasCustomBlocksGrid') && $field->hasCustomBlocksGrid();
    $panelSticky = method_exists($field, 'hasStickyPanels') && $field->hasStickyPanels();
@endphp
@if (! $isDisabled && ($panelBlocks->isNotEmpty() || count($panelMergeTags)))
    <aside x-show="isPanelActive()" x-cloak @class(['fff-rich-editor__panels', 'fff-rich-editor__panels--sticky' => $panelSticky])>
        @if ($panelBlocks->isNotEmpty())
            <div x-show="isPanelActive('customBlocks')" x-cloak>
                <div class="fff-rich-editor__panel-heading">
                    <span>{{ __('filament-forms::components.rich_editor.tools.custom_blocks') }}</span>
                    <button type="button" x-on:click="togglePanel()" aria-label="{{ __('filament-forms::components.rich_editor.actions.close_panel.label') }}"><x-filament::icon icon="hugeicons-cancel-01" /></button>
                </div>
                @if ($panelSearchable)
                    <input type="search" class="fff-rich-editor__panel-search" x-model="customBlockSearch" x-on:keydown.enter.prevent
                        aria-label="{{ __('filament-forms::components.rich_editor.custom_blocks.search_label') }}"
                        placeholder="{{ __('filament-forms::components.rich_editor.custom_blocks.search_prompt') }}" />
                @endif
                @foreach ($panelBlocks as $groupLabel => $blocks)
                    @if (filled($groupLabel))
                        <p class="fff-rich-editor__panel-group">{{ $groupLabel }}</p>
                    @endif
                    <div @class(['fff-rich-editor__panel-items', 'fff-rich-editor__panel-items--grid' => $panelGrid])>
                        @foreach ($blocks as $block)
                            <button type="button" draggable="true" data-block-id="{{ $block::getId() }}"
                                @if ($panelSearchable) x-show="matchesCustomBlockSearch(@js([$groupLabel, $block::getLabel()]))" @endif
                                x-on:click="$wire.mountAction('customBlock', { editorSelection, id: @js($block::getId()), mode: 'insert' }, { schemaComponent: @js($key) })"
                                x-on:dragstart="$event.dataTransfer.setData('customBlock', @js($block::getId()))">
                                <x-filament::icon icon="hugeicons-puzzle" /><span>{{ $block::getLabel() }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
                @if ($panelSearchable)
                    <p class="fff-rich-editor__panel-group" x-show="!matchesCustomBlockSearch(@js($panelBlocks->flatMap(fn ($blocks, $label) => [$label, ...$blocks->map(fn ($block) => $block::getLabel())->all()])->all()))" x-cloak>{{ __('filament-forms::components.rich_editor.custom_blocks.no_search_results_message') }}</p>
                @endif
            </div>
        @endif
        @if (count($panelMergeTags))
            <div x-show="isPanelActive('mergeTags')" x-cloak>
                <div class="fff-rich-editor__panel-heading">
                    <span>{{ __('filament-forms::components.rich_editor.tools.merge_tags') }}</span>
                    <button type="button" x-on:click="togglePanel()" aria-label="{{ __('filament-forms::components.rich_editor.actions.close_panel.label') }}"><x-filament::icon icon="hugeicons-cancel-01" /></button>
                </div>
                <div class="fff-rich-editor__panel-items">
                    @foreach ($panelMergeTags as $tagId => $tagLabel)
                        <button type="button" draggable="true" x-on:click="insertMergeTag(@js($tagId))" x-on:dragstart="$event.dataTransfer.setData('mergeTag', @js($tagId))"><x-filament::icon icon="hugeicons-tag-01" /><span data-type="mergeTag" data-id="{{ $tagId }}">{{ $tagLabel }}</span></button>
                    @endforeach
                </div>
            </div>
        @endif
    </aside>
@endif
