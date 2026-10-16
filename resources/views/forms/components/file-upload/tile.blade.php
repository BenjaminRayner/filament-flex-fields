<div @class(['fff-flex-file-upload__tile', 'has-preview' => ! $client && (bool) ($file['preview'] ?? null)]) role="group"
    @if ($client && $isReorderable() && ! $isDisabled) data-fff-sort-handle @if (! ($field->hasUploadedFileClick() || $field->shouldPreviewFileOnClick())) tabindex="0" @endif aria-description="{{ __('filament-flex-fields::default.file_upload.visual.reorder') }}" aria-keyshortcuts="ArrowUp ArrowDown ArrowLeft ArrowRight" @endif
    @if ($client) x-bind:class="{ 'is-uploading': file.uploading, 'has-progress': file.progressVisible, 'is-loading': file.loading, 'is-failed': file.failed, 'has-preview': !!file.preview, 'is-leaving': !!file.leaving, 'is-viewer-pending': viewerPendingId === file.pondId }" x-bind:data-fff-upload-id="file.id" x-bind:data-fff-upload-new="!file.id.startsWith('stored:') || null" x-bind:title="file.name" x-bind:aria-label="file.name"
    @else data-fff-upload-id="{{ $file['id'] }}" title="{{ $file['name'] }}" aria-label="{{ $file['name'] }}" @endif>
    <div class="fff-flex-file-upload__tile-face">
        @if ($client)
            <span class="sr-only" role="status" x-show="['queued', 'processing', 'retrying'].includes(file.processingStatus)">{{ __('filament-flex-fields::default.file_upload.visual.processing') }}</span>
            <span class="sr-only" role="status" x-show="file.failed">{{ __('filament-flex-fields::default.file_upload.visual.failed') }}</span>
            <img class="fff-flex-file-upload__tile-image" x-show="file.preview" x-bind:src="file.preview" x-bind:data-fff-viewer-file-key="file.viewerKey" alt="" />
        @elseif ($file['preview'])
            <img class="fff-flex-file-upload__tile-image" src="{{ $file['preview'] }}" alt="" />
        @endif
        <img class="fff-flex-file-upload__tile-glyph" width="60" height="80" alt="" aria-hidden="true"
            @if ($client) x-show="!file.preview" x-bind:src="fileIcon(file)"
            @else src="{{ $fileIconUrls[\Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\FileIconCatalog::kind($file['extension'], $file['type'])] }}" @endif />
        <span class="fff-flex-file-upload__tile-name" @if ($client) x-text="file.name" @endif>@if (! $client) {{ $file['name'] }} @endif</span>
        @if (($field->hasUploadedFileClick() || $field->shouldPreviewFileOnClick()))
            <button type="button" class="fff-flex-file-upload__tile-trigger" @if ($isReorderable() && ! $isDisabled) data-fff-sort-handle @endif
                @if ($client) x-bind:aria-label="file.name" x-on:click="activateUploadedFile(file)" x-bind:disabled="isDisabled || !file.complete || file.failed || !!file.leaving || !!fileClickPending[file.pondId] || viewerPendingId === file.pondId || (fileClickPreview && !file.preview)" x-bind:aria-busy="!!fileClickPending[file.pondId] || viewerPendingId === file.pondId" @else aria-label="{{ $file['name'] }}" disabled @endif></button>
        @endif
        <div class="fff-flex-file-upload__tile-primary">
            @if ($field->getGridActions() === 'menu' && $client)
                <div x-show="hasFileMenu(file)">
                    @include('filament-flex-fields::forms.components.file-upload.tile-menu')
                </div>
                <div x-show="!hasFileMenu(file)">
                    @include('filament-flex-fields::forms.components.file-upload.actions', ['actionGroup' => 'primary'])
                    <button type="button" class="fff-flex-file-upload__action" x-show="file.uploading" x-on:click="fileAction(file.pondId, 'cancel')" x-bind:disabled="isDisabled" aria-label="{{ __('filament-flex-fields::default.file_upload.visual.cancel') }}"><x-filament::icon icon="hugeicons-multiplication-sign" /></button>
                </div>
            @else
                @include('filament-flex-fields::forms.components.file-upload.actions', ['actionGroup' => 'primary'])
            @endif
        </div>
        @if ($client)
            <span class="fff-flex-file-upload__tile-percentage" x-text="file.progress + '%'" aria-hidden="true"></span>
            <svg viewBox="0 0 116 116" class="fff-flex-file-upload__tile-progress" role="progressbar" x-bind:aria-label="file.name"
                aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="file.progress" x-bind:aria-hidden="!file.progressVisible">
                <path d="M58 2.5H92A21.5 21.5 0 0 1 113.5 24V92A21.5 21.5 0 0 1 92 113.5H24A21.5 21.5 0 0 1 2.5 92V24A21.5 21.5 0 0 1 24 2.5Z" pathLength="100"
                    x-bind:stroke-dasharray="file.progressValue + ' 100'"></path>
            </svg>
        @endif
    </div>
    @if ($field->getGridActions() !== 'menu' && ($client || ($isImageEditorExplicitlyEnabled && ! $isDisabled) || $field->hasFileMetadataEditor() || $field->hasFileViewer() || $isOpenable() || $isDownloadable() || $isReorderable()))
        <div class="fff-flex-file-upload__tile-secondary" @if ($client) x-transition:enter="fff-upload-action-enter" x-transition:enter-start="fff-upload-action-hidden" x-transition:enter-end="fff-upload-action-visible" x-transition:leave="fff-upload-action-leave" x-transition:leave-start="fff-upload-action-visible" x-transition:leave-end="fff-upload-action-hidden" x-show="!file.leaving && ({{ (($isImageEditorExplicitlyEnabled && ! $isDisabled) || $field->hasFileMetadataEditor() || $field->hasFileViewer() || $isOpenable() || $isDownloadable()) ? 'true' : 'false' }} || file.failed || ({{ $field->isPausable() ? 'true' : 'false' }} && (file.progressVisible || (file.paused && !queuePaused))) || ({{ $field->shouldUploadInstantly() ? 'false' : 'true' }} && !file.complete && !file.uploading && !file.loading))" @endif>
            @include('filament-flex-fields::forms.components.file-upload.actions', ['actionGroup' => 'secondary'])
        </div>
    @endif
</div>
