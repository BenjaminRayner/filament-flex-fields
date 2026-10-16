@php
    use Filament\Support\Enums\Alignment;
@endphp
{{-- One native Filament transport; optional FLEX editor composes its image engine. --}}
            <div
            x-fff-load
            x-fff-load-src="{{ $hasImageEditor && ! $isDisabled
                ? \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('file-upload-editor-transport', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME)
                : \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('file-upload-transport', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="{{ $hasImageEditor && ! $isDisabled ? 'fileUploadEditorTransport' : 'fileUploadTransport' }}({
                        nativeComponentSrc: @js(\Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('file-upload', 'filament/forms')),
                        editorFailureMessage: @js(__('filament-flex-fields::default.file_upload.editor.failure')),
                        imageEditorMaxPixels: @js(min($field->getImageLimits()->sourcePixels, intdiv($field->getImageLimits()->workingBytes, 32))),
                        imageEditorMaxOutputPixels: @js(min($field->getImageLimits()->outputPixels, intdiv($field->getImageLimits()->workingBytes, 32))),
                        getImageEditorSourceUsing: (fileKey) => $wire.callSchemaComponentMethod(@js($key), 'getImageEditorSource', { fileKey }),
                        captureImageEditorFileUsing: (fileKey) => $wire.callSchemaComponentMethod(@js($key), 'captureImageEditorFile', { fileKey }),
                        completeImageEditorReplacementUsing: (fileKey, replacedKey, metadata) => $wire.callSchemaComponentMethod(@js($key), 'completeImageEditorReplacement', { fileKey, replacedKey, metadata }),
                        reconcileImageEditorReplacementUsing: (fileKey, replacedKey) => $wire.callSchemaComponentMethod(@js($key), 'reconcileImageEditorReplacement', { fileKey, replacedKey }),
                        acceptedFileTypes: @js($getAcceptedFileTypes()),
                        automaticallyCropImagesAspectRatio: @js($automaticallyCropImagesAspectRatio),
                        automaticallyOpenImageEditorForAspectRatio: @js($getAutomaticallyOpenImageEditorForAspectRatio()),
                        automaticallyResizeImagesMode: @js($getAutomaticallyResizeImagesMode()),
                        automaticallyResizeImagesHeight: @js($automaticallyResizeImagesHeight),
                        automaticallyResizeImagesWidth: @js($automaticallyResizeImagesWidth),
                        cancelUploadUsing: (fileKey) => {
                            $wire.cancelUpload(`{{ $statePath }}.${fileKey}`)
                        },
                        canEditSvgs: @js($canEditSvgs()),
                        confirmSvgEditingMessage: @js(__('filament-forms::components.file_upload.editor.svg.messages.confirmation')),
                        deleteUploadedFileUsing: async (fileKey) => {
                            return await $wire.callSchemaComponentMethod(
                                @js($key),
                                'deleteUploadedFile',
                                { fileKey },
                            )
                        },
                        disabledSvgEditingMessage: @js(__('filament-forms::components.file_upload.editor.svg.messages.disabled')),
                        getUploadedFilesUsing: (() => {
                            let initial = @js($initialIndex);
                            return async () => {
                            if (initial !== null) { const files = initial; initial = null; return files }
                            return await Livewire.fireAction(
                                $wire.__instance,
                                'callSchemaComponentMethod',
                                [@js($key), 'getUploadedFiles'],
                                { async: true },
                            )
                            }
                        })(),
                        hasCircleCropper: @js($hasCircleCropper),
                        hasImageEditor: @js($hasImageEditor),
                        imageEditorEmptyFillColor: @js($getImageEditorEmptyFillColor()),
                        imageEditorMode: @js($getImageEditorMode()),
                        imageEditorViewportHeight: @js($getImageEditorViewportHeight()),
                        imageEditorViewportWidth: @js($getImageEditorViewportWidth()),
                        imagePreviewHeight: @js($getImagePreviewHeight()),
                        isAvatar: false,
                        isDeletable: @js($isDeletable()),
                        isDisabled: @js($isDisabled),
                        isDownloadable: false,
                        isImageEditorExplicitlyEnabled: @js($isImageEditorExplicitlyEnabled),
                        isMultiple: @js($isMultiple),
                        isOpenable: false,
                        isPasteable: @js($isPasteable()),
                        isPreviewable: false,
                        isReorderable: @js($isReorderable()),
                        isSvgEditingConfirmed: @js($isSvgEditingConfirmed()),
                        itemPanelAspectRatio: @js($getItemPanelAspectRatio()),
                        loadingIndicatorPosition: @js($getLoadingIndicatorPosition()),
                        locale: @js(app()->getLocale()),
                        maxFiles: @js($maxFiles = $getMaxFiles()),
                        maxFilesValidationMessage: @js($maxFiles ? trans_choice('validation.max.array', $maxFiles, ['attribute' => $getValidationAttribute(), 'max' => $maxFiles]) : null),
                        maxParallelUploads: @js($getMaxParallelUploads()),
                        maxSize: @js(($size = $getMaxSize()) ? "{$size}KB" : null),
                        mimeTypeMap: @js($getMimeTypeMap()),
                        minSize: @js(($size = $getMinSize()) ? "{$size}KB" : null),
                        panelAspectRatio: @js($getPanelAspectRatio()),
                        panelLayout: @js($getPanelLayout()),
                        placeholder: '',
                        removeUploadedFileButtonPosition: @js($getRemoveUploadedFileButtonPosition()),
                        removeUploadedFileUsing: async (fileKey) => {
                            return await $wire.callSchemaComponentMethod(
                                @js($key),
                                'removeUploadedFile',
                                { fileKey },
                            )
                        },
                        reorderUploadedFilesUsing: async (fileKeys) => {
                            return await $wire.callSchemaComponentMethod(
                                @js($key),
                                'reorderUploadedFiles',
                                { fileKeys },
                            )
                        },
                        shouldAppendFiles: @js($shouldAppendFiles()),
                        shouldAutomaticallyUpscaleImagesWhenResizing: @js($shouldAutomaticallyUpscaleImagesWhenResizing()),
                        shouldOrientImageFromExif: @js($shouldOrientImagesFromExif()),
                        shouldTransformImage: @js($automaticallyCropImagesAspectRatio || $automaticallyResizeImagesHeight || $automaticallyResizeImagesWidth),
                        state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                        uploadButtonPosition: @js($getUploadButtonPosition()),
                        uploadingMessage: @js($getUploadingMessage()),
                        uploadProgressIndicatorPosition: @js($getUploadProgressIndicatorPosition()),
                        uploadUsing: (fileKey, file, success, error, progress) => {
                            $wire.upload(
                                `{{ $statePath }}.${fileKey}`,
                                file,
                                () => {
                                    success(fileKey)
                                },
                                error,
                                (progressEvent) => {
                                    progress(true, progressEvent.detail.progress, 100)
                                },
                            )
                        },
                    })"
            wire:ignore
            wire:key="{{ $livewireKey }}.upload.{{
                substr(md5(serialize([
                    $isDisabled,
                ])), 0, 64)
            }}"
            {{
                $attributes
                    ->merge([
                        'aria-labelledby' => "{$id}-label",
                        'id' => $id,
                        'role' => 'presentation',
                    ], escape: false)
                    ->merge($getExtraAlpineAttributes(), escape: false)
                    ->class([
                        'fff-flex-file-upload__engine',
                        'fi-fo-file-upload',
                        ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : $alignment,
                    ])
            }}
            x-effect="$dispatch('register-file-pond', { component: $data, pond: pond, ready: !!pond && !isInitializing, error: error })"
        >
            <x-filament-flex-fields::assets
                :component="$hasImageEditor && ! $isDisabled ? 'file-upload-editor-transport' : 'file-upload-transport'"
                :consumer="$livewireKey.'.transport'" :schema-component="$field" />
            <div class="fi-fo-file-upload-input-ctn fff-flex-file-upload__transport" aria-hidden="true" inert>
                <input
                    x-ref="input"
                    {{
                        $getExtraInputAttributeBag()
                            ->merge([
                                'aria-labelledby' => "{$id}-label",
                                'disabled' => $isDisabled,
                                'multiple' => $isMultiple,
                                'type' => 'file',
                            ], escape: false)
                    }}
                />
            </div>

            <div
                x-show="error"
                x-text="error"
                x-cloak
                aria-hidden="true"
                class="fi-fo-file-upload-error-message"
            ></div>

            @if ($hasImageEditor && (! $isDisabled))
                @include('filament-flex-fields::forms.components.partials.flex-file-upload-image-editor')
            @endif
        </div>{{-- .fff-flex-file-upload__live --}}
