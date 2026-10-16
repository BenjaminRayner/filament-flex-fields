@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
    $listHealth = ($actionGroup ?? 'all') === 'all' ? ' && !file.failed' : '';
@endphp
<div class="fff-flex-file-upload__actions">
        @if ($client)
            @if (($actionGroup ?? 'all') !== 'secondary')
            <button type="button" class="fff-flex-file-upload__action fff-flex-file-upload__action--retry" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.failed"
                x-on:click="fileAction(file.pondId, 'retry')" x-bind:disabled="isDisabled || queuePaused || !!file.leaving" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.retry') }}"><x-filament::icon :icon="FlexIcon::ArrowsRotateRight" />@if (($actionGroup ?? 'all') === 'all')<span class="fff-flex-file-upload__retry-label">{{ __('filament-flex-fields::default.file_upload.visual.retry_short') }}</span>@endif</button>
            @endif
            @if (($actionGroup ?? 'all') !== 'primary')
            @if ($field->hasFileViewer())
                <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.complete && !!file.preview && !file.failed"
                    x-on:click="fileAction(file.pondId, 'viewer')" x-bind:disabled="!!file.leaving || viewerPendingId !== null" x-bind:aria-busy="viewerPendingId === file.pondId"
                    aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.open') }}"><span class="fff-upload-action-spinner" x-show="viewerPendingId === file.pondId" aria-hidden="true"></span><span x-show="viewerPendingId !== file.pondId"><x-filament::icon icon="hugeicons-view" /></span></button>
            @endif
            @if ($field->hasFileMetadataEditor())
                <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.complete && !file.failed"
                    x-on:click="fileAction(file.pondId, 'metadata')" x-bind:disabled="isDisabled || !!file.leaving"
                    aria-label="{{ __('filament-flex-fields::default.file_upload.metadata.edit') }}"><x-filament::icon icon="hugeicons-file-edit" /></button>
            @endif
            @if ($field->isPausable())
            <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.progressVisible && !file.paused{{ $listHealth }}"
                x-on:click="fileAction(file.pondId, 'pause')" x-bind:disabled="isDisabled || !!file.leaving" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.pause') }}"><x-filament::icon :icon="FlexIcon::PauseFill" /></button>
            <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.paused && !queuePaused{{ $listHealth }}"
                x-on:click="fileAction(file.pondId, 'resume')" x-bind:disabled="isDisabled || !!file.leaving" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.resume') }}"><x-filament::icon icon="hugeicons-play" /></button>
            @endif
            @if ($isImageEditorExplicitlyEnabled && ! $isDisabled)
                <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="canEditImage(file){{ $listHealth }}"
                    x-on:click="fileAction(file.pondId, 'edit')" x-bind:disabled="isDisabled || file.loading" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.edit') }}">
                    <x-filament::icon icon="hugeicons-picasa" />
                </button>
            @endif
            @if ($isOpenable())
                <a class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.openUrl{{ $listHealth }}" x-bind:href="file.openUrl" target="_blank" rel="noopener noreferrer" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.open') }}"><x-filament::icon :icon="FlexIcon::ArrowUpRightFromSquare" /></a>
            @endif
            @if ($isDownloadable())
                <a class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.downloadUrl{{ $listHealth }}" x-bind:href="file.downloadUrl" download aria-label="{{ __('filament-flex-fields::default.file_upload.visual.download') }}"><x-filament::icon :icon="FlexIcon::ArrowDownToSquare" /></a>
            @endif
            @endif
            @if (($actionGroup ?? 'all') !== 'primary')
            @if (($actionGroup ?? 'all') === 'all')
            <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.uploading"
                x-on:click="fileAction(file.pondId, 'cancel')" x-bind:disabled="isDisabled" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.cancel') }}"><x-filament::icon icon="hugeicons-multiplication-sign" /></button>
            @endif
            <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="!file.paused && !file.failed && !file.complete && !file.uploading && !file.loading"
                x-on:click="fileAction(file.pondId, 'process')" x-bind:disabled="isDisabled" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.upload') }}"><x-filament::icon icon="hugeicons-upload-01" /></button>
            @endif
            @if (($actionGroup ?? 'all') !== 'secondary' && $isDeletable())
                <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="{{ ($actionGroup ?? 'all') === 'primary' ? 'file.complete && !file.failed' : '!file.uploading && !file.failed' }}" x-on:click="fileAction(file.pondId, 'remove')" x-bind:disabled="isDisabled || !!file.leaving" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.remove') }}"><x-filament::icon icon="hugeicons-multiplication-sign" /></button>
            @endif
            @if (($actionGroup ?? 'all') === 'secondary' && $isDeletable())
                <button type="button" class="fff-flex-file-upload__action" x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="file.failed" x-on:click="fileAction(file.pondId, 'remove')" x-bind:disabled="isDisabled || !!file.leaving" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.remove') }}"><x-filament::icon icon="hugeicons-multiplication-sign" /></button>
            @endif
            @if (($actionGroup ?? 'all') !== 'primary')
            @if ($isReorderable() && $presentation->layout !== \Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadLayout::Grid)
                <button type="button" class="fff-flex-file-upload__action fff-flex-file-upload__sort-handle" data-fff-sort-handle x-show="!file.failed && files.filter(item => !item.leaving).length > 1" x-bind:disabled="isDisabled || !!file.leaving" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.reorder') }}" aria-keyshortcuts="ArrowUp ArrowDown ArrowLeft ArrowRight"><x-filament::icon icon="hugeicons-drag-drop-vertical" /></button>
            @endif
            @endif
        @else
            @if (($actionGroup ?? 'all') !== 'secondary' && $isDeletable()) <button type="button" class="fff-flex-file-upload__action" disabled aria-label="{{ __('filament-flex-fields::default.file_upload.visual.remove') }}"><x-filament::icon icon="hugeicons-multiplication-sign" /></button> @endif
        @endif
    </div>
