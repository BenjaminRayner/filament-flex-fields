@if ($isAvatar)
    @include('filament-flex-fields::forms.components.file-upload.avatar')
@else
    <div @class(['fff-flex-file-upload__source-card fff-flex-file-upload__dropzone', 'fff-flex-file-upload__dropzone--compact' => $field->shouldUseCompactHeader()]) x-bind:class="{ 'is-dragging': isDragging, 'is-pressed': outlineActive, 'is-full': !canAddFiles() }"
        x-on:click.capture="pulseOutline()" role="group" aria-label="{{ $field->getDropzoneLabel() ?? __('filament-flex-fields::default.file_upload.visual.heading') }}"
        aria-disabled="{{ ($isDisabled || $field->isUploadAtCapacity()) ? 'true' : 'false' }}" x-bind:aria-disabled="!canAddFiles()"
        tabindex="{{ ($isDisabled || $field->isUploadAtCapacity()) ? -1 : 0 }}" x-bind:tabindex="canAddFiles() ? 0 : -1"
        x-on:click="if (canAddFiles() && !$event.target.closest('button, a, input')) { $event.currentTarget.focus({ preventScroll: true }); browse() }"
        x-on:keydown.enter.self.prevent="browse()" x-on:keydown.space.self.prevent="browse()"
        x-on:dragenter="dragEnter($event)" x-on:dragleave="dragLeave($event)" x-on:dragover="dragOver($event)" x-on:drop="drop($event)">
        @include('filament-flex-fields::forms.components.file-upload.frame')
        @include('filament-flex-fields::forms.components.file-upload.source-header', [
            'source' => 'file',
            'heading' => $field->getDropzoneLabel() ?? __('filament-flex-fields::default.file_upload.visual.heading'),
            'description' => $field->getEmptyStateHint() ?? __('filament-flex-fields::default.file_upload.visual.hint'),
        ])
        <div class="fff-flex-file-upload__source-controls">
            <button type="button" class="fff-flex-file-upload__select" wire:ignore.self disabled x-on:click="browse()" x-bind:disabled="!canAddFiles()">
                {{ __('filament-flex-fields::default.file_upload.visual.select') }}
            </button>
        </div>
    </div>
@endif
