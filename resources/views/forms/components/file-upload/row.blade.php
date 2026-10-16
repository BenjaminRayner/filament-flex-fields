@if ($presentation->layout === \Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\UploadLayout::Grid)
    @include('filament-flex-fields::forms.components.file-upload.tile')
@else
<div class="fff-flex-file-upload__card" @if ($client) x-bind:class="{ 'is-failed': file.failed, 'is-leaving': !!file.leaving }" x-bind:data-fff-upload-id="file.id" @else data-fff-upload-id="{{ $file['id'] }}" @endif>
    @if (($field->hasUploadedFileClick() || $field->shouldPreviewFileOnClick()))
        <button type="button" class="fff-flex-file-upload__file-content fff-flex-file-upload__file-trigger"
            @if ($client) x-on:click="activateUploadedFile(file)" x-bind:disabled="isDisabled || !file.complete || file.failed || !!file.leaving || !!fileClickPending[file.pondId] || viewerPendingId === file.pondId || (fileClickPreview && !file.preview)" x-bind:aria-busy="!!fileClickPending[file.pondId] || viewerPendingId === file.pondId" @else disabled @endif>
    @else
        <div class="fff-flex-file-upload__file-content">
    @endif
    @if ($field->shouldShowFileIcon())
        <span class="fff-flex-file-upload__file-art">
            @if ($client)
                <img x-show="file.preview" x-bind:src="file.preview" x-bind:data-fff-viewer-file-key="file.viewerKey" alt="" />
                <img x-show="!file.preview" x-bind:src="fileIcon(file)" class="fff-flex-file-upload__file-icon" width="60" height="80" alt="" aria-hidden="true" />
            @elseif ($file['preview'])
                <img src="{{ $file['preview'] }}" alt="" />
            @else
                <img class="fff-flex-file-upload__file-icon" width="60" height="80" alt="" aria-hidden="true" src="{{ $fileIconUrls[\Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\FileIconCatalog::kind($file['extension'], $file['type'])] }}" />
            @endif
        </span>
    @endif
    <span class="fff-flex-file-upload__file-copy">
        <span class="fff-flex-file-upload__file-name" @if ($client) x-text="file.name" x-bind:title="file.name" @else title="{{ $file['name'] }}" @endif>
            @if (! $client) {{ $file['name'] }} @endif
        </span>
        <span class="fff-flex-file-upload__file-detail">
            <span @if ($client) x-text="formatBytes(file.bytes)" @endif>@if (! $client) {{ \Illuminate\Support\Number::fileSize($file['bytes']) }} @endif</span>
            @if ($client)
                <span x-show="file.uploading">· {{ __('filament-flex-fields::default.file_upload.visual.uploading') }} <span class="fff-flex-file-upload__inline-percentage" x-text="file.progress + '%'" aria-hidden="true"></span></span>
                <span x-show="file.loading">· {{ __('filament-flex-fields::default.file_upload.visual.loading') }}</span>
                <span x-show="file.failed">· {{ __('filament-flex-fields::default.file_upload.visual.failed') }}</span>
                <span x-show="['queued', 'processing', 'retrying'].includes(file.processingStatus)">· {{ __('filament-flex-fields::default.file_upload.visual.processing') }}</span>
                <span x-show="file.processingStatus === 'failed'">· {{ __('filament-flex-fields::default.file_upload.visual.processing_failed') }}</span>
                <span x-show="file.paused">· {{ __('filament-flex-fields::default.file_upload.visual.paused') }}</span>
            @elseif (in_array($file['processingStatus'] ?? null, ['queued', 'processing', 'retrying'], true))
                <span>· {{ __('filament-flex-fields::default.file_upload.visual.processing') }}</span>
            @elseif (($file['processingStatus'] ?? null) === 'failed')
                <span>· {{ __('filament-flex-fields::default.file_upload.visual.processing_failed') }}</span>
            @endif
        </span>
        @if ($client)
            <span class="fff-flex-file-upload__progress" x-show="file.uploading || file.loading" role="progressbar"
                x-bind:aria-label="file.name" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="file.loading ? null : file.progress">
                <span x-bind:style="{ transform: 'scaleX(' + (file.loading ? .2 : file.progressValue / 100) + ')' }"></span>
            </span>
        @endif
    </span>
    @if (($field->hasUploadedFileClick() || $field->shouldPreviewFileOnClick())) </button> @else </div> @endif
    @include('filament-flex-fields::forms.components.file-upload.actions', ['actionGroup' => 'all'])

</div>

@endif
