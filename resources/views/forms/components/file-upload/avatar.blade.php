{{-- FLEX presentation derives its states from the sole native transport instance. --}}
<div class="fff-flex-file-upload__avatar-shell" role="group" aria-label="{{ $getLabel() }}" wire:ignore.self
    data-has-file="{{ count($initialFiles) > 0 ? 'true' : 'false' }}"
    x-bind:data-has-file="files.length > 0 || avatarExiting ? 'true' : 'false'"
    x-bind:class="{ 'is-dragging': isDragging, 'is-pressed': outlineActive, 'has-file': files.length > 0 || avatarExiting, 'is-complete': files[0]?.uploaded, 'is-failed': files[0]?.failed }"
    x-on:click.capture="pulseOutline()"
    x-on:dragenter="dragEnter($event)" x-on:dragleave="dragLeave($event)" x-on:dragover="dragOver($event)" x-on:drop="drop($event)">
    <button type="button" class="fff-flex-file-upload__avatar-fallback" wire:ignore.self disabled x-on:click="browse()" x-bind:disabled="!canAddFiles()"
        aria-label="{{ __('filament-flex-fields::default.file_upload.visual.select') }}">
        @include('filament-flex-fields::forms.components.file-upload.avatar-label')
    </button>
    <img class="fff-flex-file-upload__avatar-backdrop" wire:ignore @if ($initialFiles[0]['preview'] ?? null) src="{{ $initialFiles[0]['preview'] }}" @else style="display: none;" @endif
        x-bind:src="files[0]?.preview || (avatarExiting ? $el.getAttribute('src') : null)" x-bind:data-fff-viewer-file-key="files[0]?.viewerKey" x-show="files[0]?.preview" alt="" aria-hidden="true" x-transition.opacity.duration.220ms
        x-on:transitionend.self="if (!files[0]?.preview && $event.propertyName === 'opacity') { avatarExiting = false; $el.removeAttribute('src') }" />
    <div class="fff-flex-file-upload__avatar-tint" aria-hidden="true"></div>
    <svg class="fff-flex-file-upload__avatar-frame" viewBox="0 0 100 100" fill="none" aria-hidden="true" focusable="false"><circle cx="50" cy="50" r="49.3" fill="none" stroke="#d8d8e0" stroke-width="1.5" stroke-dasharray="8 6" stroke-linecap="round" vector-effect="non-scaling-stroke" /></svg>
    <span class="fff-flex-file-upload__avatar-progress" wire:ignore x-show="files[0]?.progressVisible" x-text="(files[0]?.progress || 0) + '%'" x-cloak></span>
    @if ($field->hasFileViewer())
        <button type="button" class="fff-flex-file-upload__avatar-viewer" x-cloak
            x-show="files[0]?.complete && !!files[0]?.preview && !files[0]?.failed"
            x-on:click.stop="fileAction(files[0], 'viewer')"
            x-bind:disabled="!displayReady || viewerPendingId !== null"
            x-bind:aria-busy="viewerPendingId === files[0]?.pondId"
            aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.open') }}"></button>
    @endif
    <div class="fff-flex-file-upload__avatar-actions" wire:ignore x-cloak>
        @php
            $avatarActions = [];
            if ($isDeletable()) {
                $avatarActions[] = ['left', 'remove', 'multiplication-sign', 'remove', "files.length > 0 && !files[0]?.uploaded && !files[0]?.uploading && !files[0]?.loading"];
                $avatarActions[] = ['right', 'remove', $flexConfig['instantUpload'] ? 'multiplication-sign' : 'undo', 'remove', "files[0]?.uploaded"];
            }
            if ($isImageEditorExplicitlyEnabled && ! $isDisabled) $avatarActions[] = ['center', 'edit', 'picasa', 'edit', "canEditImage(files[0])"];
            $avatarActions[] = ['right', 'retry', 'reload', 'retry', "files[0]?.failed"];
            $avatarActions[] = ['right', 'process', 'upload-01', 'upload', "files.length > 0 && !files[0]?.complete && !files[0]?.failed && !files[0]?.uploading && !files[0]?.loading && !files[0]?.paused"];
            if ($field->isPausable()) {
                $avatarActions[] = ['right', 'pause', 'pause', 'pause', "files[0]?.uploading"];
                $avatarActions[] = ['right', 'resume', 'play', 'resume', "files[0]?.paused"];
            } else $avatarActions[] = ['right', 'cancel', 'multiplication-sign', 'cancel', "files[0]?.uploading"];
        @endphp
        @foreach ($avatarActions as [$position, $action, $icon, $label, $visible])
            <button type="button" class="fff-flex-file-upload__avatar-action fff-flex-file-upload__avatar-action--{{ $position }}"
                data-visible="false" x-bind:data-visible="!!({{ $visible }})" x-bind:aria-hidden="!({{ $visible }})" x-bind:tabindex="({{ $visible }}) ? 0 : -1"
                x-on:click.stop="fileAction(files[0], @js($action))" x-bind:disabled="isDisabled || !displayReady || !({{ $visible }}){{ $action === 'edit' ? ' || files[0]?.uploading' : '' }}"
                aria-label="{{ __('filament-flex-fields::default.file_upload.visual.'.$label) }}">@include('filament-flex-fields::forms.components.file-upload.avatar-action-icon', ['icon' => $icon])</button>
        @endforeach
    </div>
</div>
