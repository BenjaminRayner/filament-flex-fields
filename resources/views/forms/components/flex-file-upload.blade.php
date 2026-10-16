@php
    use Filament\Support\Enums\Alignment;
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
    use Bjanczak\FilamentFlexFields\Support\Segments\SegmentItem;
    $id = $getId();
    $key = $getKey();
    $statePath = $getStatePath();
    $livewireKey = $getLivewireKey();
    $isDisabled = $isDisabled();
    $isMultiple = $isMultiple();
    $isAvatar = $isAvatar();
    $avatarPlaceholder = $field->getDefaultAvatar();
    $hasImageEditor = $hasImageEditor();
    $isImageEditorExplicitlyEnabled = $isImageEditorExplicitlyEnabled();
    $hasCircleCropper = $hasCircleCropper();
    $automaticallyCropImagesAspectRatio = $getAutomaticallyCropImagesAspectRatio();
    $automaticallyResizeImagesHeight = $getAutomaticallyResizeImagesHeight();
    $automaticallyResizeImagesWidth = $getAutomaticallyResizeImagesWidth();
    $alignment = $getAlignment() ?? Alignment::Start;
    if (is_string($alignment)) $alignment = Alignment::tryFrom($alignment) ?? $alignment;
    $presentation = $field->getUploadPresentation();
    $uploadRequirements = $field->getUploadRequirements();
    $initialIndex = $field->getUploadedFiles() ?? [];
    $initialFiles = $presentation->files($initialIndex, $isPreviewable(), $shouldAppendFiles());
    $runtimeConfig = $field->getFlexFileUploadAlpineConfiguration();
    $flexConfig = [...$runtimeConfig, 'initialFiles' => $initialFiles];
    // State snapshots hydrate new owners; they must not restart an existing owner.
    $presentationKey = $livewireKey.'.'.hash('sha256', json_encode($runtimeConfig, JSON_THROW_ON_ERROR)).'.presentation';
    $fileIconUrls = $flexConfig['fileIcons']['urls'];
    $placeholder = $field->getEffectivePlaceholder();
    $hasUploadSourceTabs = $field->hasUploadSourceTabs();
    $webcamModalId = $flexConfig['webcamModalId'];
    $sourceItems = array_map(fn ($source) => new SegmentItem($source->value,
        $field->getUploadSourceTabLabel($source), $field->getUploadSourceTabIcon($source),
        panelId: $id.'-source-'.$source->value), $field->getEnabledUploadSources());
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag()->merge(['data-fff-local-paint' => '']))">
    <x-filament-flex-fields::assets component="flex-file-upload" :consumer="$livewireKey"
        :schema-component="$field" :capabilities="$hasUploadSourceTabs ? ['sources'] : []" />
    <x-filament-flex-fields::lazy-alpine-mount :eager="true">
        <div x-fff-load
            wire:key="{{ $presentationKey }}"
            x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('flex-file-upload', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            data-fff-upload-config="{{ json_encode($flexConfig, JSON_THROW_ON_ERROR) }}"
            data-fff-viewer-owner="{{ $key }}"
            x-data="flexFileUploadFormComponent(JSON.parse($el.dataset.fffUploadConfig))"
            x-on:register-file-pond="registerFilePond($event.detail)"
            @class($field->getWrapperClasses())
            style="--fff-upload-columns: {{ $presentation->gridColumns }}; --fff-upload-avatar-size: {{ $presentation->avatarSize }}px; --fff-upload-tile-size: {{ $presentation->gridTileSize }}px;"
        >
            <input x-ref="flexPicker" type="file" hidden aria-hidden="true" tabindex="-1" wire:ignore
                accept="{{ implode(',', $getAcceptedFileTypes() ?? []) }}" @disabled($isDisabled) @if ($isMultiple) multiple @endif
                x-on:change="selectPickedFiles($event.target)" />
            @if ($field->hasFileViewer())
                <template data-fff-viewer-entry-chrome>
                    <div class="fff-upload-viewer-entry__chrome">
                        @include('filament-flex-fields::forms.components.file-upload.viewer-chrome', ['pending' => true])
                        @include('filament-flex-fields::forms.components.file-upload.viewer-navigation', ['pending' => true])
                    </div>
                    @if ($field->hasFileViewerMetadata())
                        <div class="fff-upload-viewer-entry__information">
                            <div class="fff-upload-viewer-entry__loading" role="status" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.loading') }}">
                                <span class="fff-upload-viewer-entry__spinner" aria-hidden="true"></span>
                            </div>
                        </div>
                    @endif
                </template>
            @endif
            @if ($hasUploadSourceTabs)
                <x-filament-flex-fields::segment-control :items="$sourceItems" :selected="$flexConfig['defaultUploadSource']" :retain-intent="true" :identity="$livewireKey"
                    :size="$getSize()" :variant="$field->getUploadSourceTabsVariant()" :color="$field->getUploadSourceTabsColor()"
                    :separators="false" :full-width="true" :disabled="$isDisabled" role="tablist"
                    :label="__('filament-flex-fields::default.file_upload.visual.heading')" />
            @endif
            <div id="{{ $id }}-source-file" class="fff-flex-file-upload__panel"
                @if ($hasUploadSourceTabs) role="tabpanel" aria-labelledby="{{ $id }}-source-file-trigger" x-show="isUploadSource('file')" @endif
            >
                @include('filament-flex-fields::forms.components.file-upload.dropzone')
            </div>
            @include('filament-flex-fields::forms.components.file-upload.native-transport')
            @if ($flexConfig['allowUrlUpload'])
                <div id="{{ $id }}-source-url" class="fff-flex-file-upload__panel" role="tabpanel"
                    aria-labelledby="{{ $id }}-source-url-trigger" x-show="isUploadSource('url')" x-cloak>
                    @include('filament-flex-fields::forms.components.file-upload.url')
                </div>
            @endif
            @if ($flexConfig['allowWebcamUpload'])
                <div id="{{ $id }}-source-webcam" class="fff-flex-file-upload__panel" role="tabpanel"
                    aria-labelledby="{{ $id }}-source-webcam-trigger" x-show="isUploadSource('webcam')" x-cloak>
                    @include('filament-flex-fields::forms.components.file-upload.webcam')
                    @include('filament-flex-fields::forms.components.partials.flex-file-upload-webcam-modal')
                </div>
            @endif
            @if (! $isAvatar) @include('filament-flex-fields::forms.components.file-upload.files') @endif
            @if ($isAvatar && $field->hasFileMetadataEditor())
                <button type="button" class="fff-flex-file-upload__queue-action" x-show="files[0]?.complete" x-cloak
                    x-on:click="fileAction(files[0].pondId, 'metadata')" x-bind:disabled="isDisabled">
                    <x-filament::icon icon="hugeicons-file-edit" />{{ __('filament-flex-fields::default.file_upload.metadata.edit') }}
                </button>
            @endif
            <p class="fff-flex-file-upload__error" x-show="actionError || transportError" x-text="actionError || transportError" x-cloak role="alert"></p>
            @if ($field->isPausable() || $flexConfig['showUploadSummary'] || $flexConfig['remainingSlotsTemplate'])
                <div class="fff-flex-file-upload__footer">
                    @if ($flexConfig['showUploadSummary'] || $flexConfig['remainingSlotsTemplate'])
                        <div class="fff-flex-file-upload__summary" aria-live="polite">
                            <span x-show="summaryLabel" x-text="summaryLabel" x-cloak></span><span class="fff-flex-file-upload__remaining" x-text="remainingSlotsLabel">{{ $field->getRemainingSlotsLabel() }}</span>
                        </div>
                    @endif
                    @include('filament-flex-fields::forms.components.file-upload.queue')
                </div>
            @endif
        </div>
    </x-filament-flex-fields::lazy-alpine-mount>
</x-dynamic-component>
