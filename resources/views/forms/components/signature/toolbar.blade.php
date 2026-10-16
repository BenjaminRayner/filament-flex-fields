@php
    $canEdit = !$isDisabled && !$isReadOnly;
    $hasUtilities = $field->isPdfPreviewEnabled() || filled($field->getDownloadFormat()) || ($field->isFullscreenEnabled() && !($inFullscreen ?? false) && $canEdit);
@endphp
@if ($canEdit || $hasUtilities)
<div class="fff-signature__toolbar">
    @if ($canEdit)
        <div class="fff-signature__pens">
        <div class="fff-signature__choices" role="group" aria-label="{{ __('filament-flex-fields::default.signature.color') }}">
            @foreach ($field->getPenColors() as $color)
                <button type="button" class="fff-signature__choice fff-signature__choice--color" style="--fff-signature-choice-color: {{ $color }}"
                    x-on:click="selectPenColor(@js($color))" x-bind:aria-pressed="penColor === @js($color)" x-bind:disabled="isDrawing" aria-label="{{ __('filament-flex-fields::default.signature.color') }} {{ $color }}"><span></span></button>
            @endforeach
        </div>
        <span class="fff-signature__divider" aria-hidden="true"></span>
        <div class="fff-signature__choices" role="group" aria-label="{{ __('filament-flex-fields::default.signature.width') }}">
            @foreach ($field->getPenWidths() as $width)
                <button type="button" class="fff-signature__choice fff-signature__choice--width" style="--fff-signature-choice-width: {{ $width * 2 }}px"
                    x-on:click="selectPenWidth(@js($width))" x-bind:aria-pressed="penWidth === @js($width)" x-bind:disabled="isDrawing" aria-label="{{ __('filament-flex-fields::default.signature.width') }} {{ $width }}"><span></span></button>
            @endforeach
        </div>
        </div>
    @endif
    <div class="fff-signature__button-group" role="group" aria-label="{{ __('filament-flex-fields::default.signature.actions') }}">
        @if ($canEdit)
        <div class="fff-signature__actions">
            @if ($field->isUndoable())
                <button type="button" class="fff-signature__action" disabled x-bind:disabled="!canUndo" x-on:click="undo()" aria-label="{{ __('filament-flex-fields::default.signature.undo') }}"><x-filament::icon :icon="$field->getUndoIcon()" /></button>
                <button type="button" class="fff-signature__action" disabled x-bind:disabled="!canRedo" x-on:click="redo()" aria-label="{{ __('filament-flex-fields::default.signature.redo') }}"><x-filament::icon icon="hugeicons-redo" /></button>
            @endif
            @if ($field->isReplayEnabled())
                <button type="button" class="fff-signature__action" disabled x-bind:disabled="!canReplay" x-bind:aria-pressed="isReplaying"
                    x-bind:title="hasSignature && !isDrawing && !canReplay ? labels.replay_unavailable : labels.replay"
                    x-on:click="replay()" aria-label="{{ __('filament-flex-fields::default.signature.replay') }}"><x-filament::icon icon="hugeicons-play" /></button>
            @endif
            <button type="button" class="fff-signature__action" disabled x-bind:disabled="!canClear" x-on:click="clear()" aria-label="{{ __('filament-flex-fields::default.signature.clear') }}"><x-filament::icon :icon="$field->getClearIcon()" /></button>
        </div>
    @endif
    @if ($hasUtilities)
    <div class="fff-signature__utilities">
        @if ($field->isPdfPreviewEnabled())<button type="button" class="fff-signature__action" x-on:click="openPdfPreview()" aria-label="{{ __('filament-flex-fields::default.signature.pdf_preview') }}"><x-filament::icon :icon="$field->getPdfPreviewIcon()" /></button>@endif
        @if (filled($field->getDownloadFormat()))<button type="button" class="fff-signature__action" disabled x-bind:disabled="!canDownload" x-on:click="downloadSignature()" aria-label="{{ __('filament-flex-fields::default.signature.download') }}"><x-filament::icon :icon="$field->getDownloadIcon()" /></button>@endif
        @if ($field->isFullscreenEnabled() && !($inFullscreen ?? false) && !$isDisabled && !$isReadOnly)<button type="button" class="fff-signature__action" x-on:click="openFullscreen()" aria-label="{{ __('filament-flex-fields::default.signature.fullscreen') }}"><x-filament::icon :icon="$field->getFullscreenIcon()" /></button>@endif
    </div>
    @endif
    </div>
</div>
@endif
