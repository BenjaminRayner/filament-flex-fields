@php
    use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
    use Bjanczak\FilamentFlexFields\Support\RichEditorFlexIcons;

    $extraAttributeBag = $getExtraAttributeBag();
    $fieldWrapperView = $getFieldWrapperView();
    $isDisabled = $isDisabled();
    $label = $getLabel();
    $livewireKey = $getLivewireKey();
    $key = $getKey();
    $statePath = $getStatePath();
    $mentions = $getMentionsForJs();
    $toolbarButtons = $getToolbarButtons();
    $tools = $getTools();
    $floatingToolbars = $getFloatingToolbars();
    $linkProtocols = $getLinkProtocols();
    $fileAttachmentsMaxSize = $getFileAttachmentsMaxSize();
    $fileAttachmentsAcceptedFileTypes = $getFileAttachmentsAcceptedFileTypes();
    $wrapperClasses = $field->getWrapperClasses();
    $shouldShowFooter = $field->shouldShowRichEditorFooter();
    $shouldShowJsonBadge = $field->shouldShowJsonBadge();
    $jsonBadgeLabel = $field->getJsonBadgeLabel();
    $footerConfig = $field->getRichEditorFooterConfigForJs();
    $distractionFreeHiddenTools = $field->getDistractionFreeHiddenToolsForJs();
    $shouldEnableDistractionFree = $field->shouldEnableRichEditorDistractionFree();
    $editorSize = $field->getSize();
    $editorVariant = $field->getVariant();
    $isInvalid = $errors->has($statePath);
    $minHeight = $field->getMinHeight();
    $maxHeight = $field->getMaxHeight();
    $stickyOffset = method_exists($field, 'getStickyOffset') ? $field->getStickyOffset() : null;
    $stickyToolbar = method_exists($field, 'hasStickyToolbar') && $field->hasStickyToolbar();
    $minimalBlockControls = method_exists($field, 'hasMinimalCustomBlockControls') && $field->hasMinimalCustomBlockControls();
    $youtubeConfig = $field->getYoutubeConfigForJs();
    $initialDocument = \Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaVisibility::allows($field)
        ? $field->makeFlexRichContentRenderer(\Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableHydrator::resolveRenderedState($field))
            ->linkProtocols($linkProtocols)
            ->toHtml()
        : null;
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    label-tag="div"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($extraAttributeBag)
            ->class($wrapperClasses)
    "
>
    @include('filament-flex-fields::partials.load-stylesheet', ['component' => 'rich-editor-field',
        'livewireKey' => $getLivewireKey(),
    ])

    <div
        @style([
            "--fff-rich-editor-config-min-h: {$minHeight}" => filled($minHeight),
            "--fff-rich-editor-config-max-h: {$maxHeight}" => filled($maxHeight),
            "--fff-rich-editor-sticky-offset: {$stickyOffset}" => filled($stickyOffset),
        ])
        @class([
            'fff-rich-editor',
            'fi-fo-rich-editor',
            'fff-rich-editor--' . $editorSize,
            'fff-rich-editor--' . $editorVariant,
            'fff-rich-editor--sticky-toolbar' => $stickyToolbar,
            'is-disabled' => $isDisabled,
            'is-invalid' => $isInvalid,
        ])
    >
                <div
                    x-fff-load
                    x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-rich-editor', FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
                    x-data="flexRichEditorFormComponent({
                richEditorSrc: @js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('rich-editor', 'filament/forms')),
                acceptedFileTypes: @js($fileAttachmentsAcceptedFileTypes),
                acceptedFileTypesValidationMessage: @js($fileAttachmentsAcceptedFileTypes ? __('filament-forms::components.rich_editor.file_attachments_accepted_file_types_message', ['values' => implode(', ', $fileAttachmentsAcceptedFileTypes)]) : null),
                activePanel: @js($getActivePanel()),
                canAttachFiles: @js($hasFileAttachments()),
                deleteCustomBlockButtonIconHtml: @js(\Filament\Support\generate_icon_html(RichEditorFlexIcons::icon('clear_content'))->toHtml()),
                editCustomBlockButtonIconHtml: @js(\Filament\Support\generate_icon_html(RichEditorFlexIcons::icon('pencil'))->toHtml()),
                extensions: @js($getTipTapJsExtensions()),
                floatingToolbars: @js($floatingToolbars),
                getMentionLabelsUsing: async (mentions) => {
                    return await $wire.callSchemaComponentMethod(
                        @js($key),
                        'getMentionLabelsForJs',
                        { mentions },
                    )
                },
                getMentionSearchResultsUsing: async (query, char) => {
                    return await $wire.callSchemaComponentMethod(
                        @js($key),
                        'getMentionSearchResultsForJs',
                        { search: query, char },
                    )
                },
                hasResizableImages: @js($hasResizableImages()),
                hasMinimalCustomBlockControls: @js($minimalBlockControls),
                hasStickyToolbar: @js($stickyToolbar),
                isDisabled: @js($isDisabled),
                label: @js($label),
                isLiveDebounced: @js($isLiveDebounced()),
                isLiveOnBlur: @js($isLiveOnBlur()),
                key: @js($key),
                linkProtocols: @js($linkProtocols),
                liveDebounce: @js($getNormalizedLiveDebounce()),
                livewireId: @js($this->getId()),
                maxFileSize: @js($fileAttachmentsMaxSize),
                maxFileSizeValidationMessage: @js($fileAttachmentsMaxSize ? trans_choice('filament-forms::components.rich_editor.file_attachments_max_size_message', $fileAttachmentsMaxSize, ['max' => $fileAttachmentsMaxSize]) : null),
                mentions: @js($mentions),
                mergeTags: @js($getMergeTags()),
                noMergeTagSearchResultsMessage: @js($getNoMergeTagSearchResultsMessage()),
                placeholder: @js($getPlaceholder()),
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')", isOptimisticallyLive: false) }},
                statePath: @js($statePath),
                textColors: @js($getTextColorsForJs()),
                uploadingFileMessage: @js($getUploadingFileMessage()),
                footerConfig: @js($footerConfig),
                distractionFreeHiddenTools: @js($distractionFreeHiddenTools),
                pasteCleanupMode: @js($field->getPasteCleanupMode()),
                youtubeConfig: @js($youtubeConfig),
            })"
            x-bind:class="{
                'fff-rich-editor__root--uploading': isUploadingFile,
            }"
            x-effect="$el.closest('.fff-rich-editor').classList.toggle('fff-rich-editor--fullscreen', isRichEditorFullscreen)"
            wire:ignore
            wire:key="{{ $livewireKey }}.{{ substr(md5(serialize([$isDisabled, $editorSize, $editorVariant])), 0, 64) }}"
            class="fff-rich-editor__root"
        >
            @include('filament-flex-fields::forms.components.partials.rich-editor.toolbar')

            <div
                x-show="isUploadingFile"
                x-cloak
                class="fff-rich-editor__uploading fi-fo-rich-editor-uploading-file-message"
            >
                {{ \Filament\Support\generate_loading_indicator_html() }}

                <span>{{ $getUploadingFileMessage() }}</span>
            </div>

            <div
                x-show="! isUploadingFile && fileValidationMessage"
                x-cloak
                class="fff-rich-editor__validation fi-fo-rich-editor-file-validation-message"
            >
                <span x-text="fileValidationMessage"></span>
            </div>

            <div {{ $getExtraInputAttributeBag()->class(['fff-rich-editor__main', 'fi-fo-rich-editor-main']) }}>
                <div
                    class="fff-rich-editor__content fi-fo-rich-editor-content fi-prose"
                    x-ref="editor"
                    role="textbox"
                    aria-multiline="true"
                    @if (filled($label))
                        aria-labelledby="{{ $getId() }}-label"
                    @endif
                >
                    @if ($initialDocument !== null)
                        <div class="fff-rich-editor__initial-content" data-fff-rich-editor-initial aria-hidden="true" inert>{!! $initialDocument !!}</div>
                    @endif
                </div>

                @include('filament-flex-fields::forms.components.partials.rich-editor.image-actions')
                @include('filament-flex-fields::forms.components.partials.rich-editor.panels')
            </div>

            @include('filament-flex-fields::forms.components.partials.rich-editor.footer')
                </div>
    </div>
</x-dynamic-component>
