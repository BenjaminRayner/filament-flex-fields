@php($pending = $pending ?? false)
<button type="button" class="fff-upload-viewer__close fff-upload-viewer__chrome"
    @unless ($pending) wire:loading.attr="disabled" wire:target="callMountedAction" x-on:click="close()" @endunless
    aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.close') }}"><x-filament::icon icon="hugeicons-cancel-01" /></button>
<div class="fff-upload-viewer__identity fff-upload-viewer__chrome">
    <span @if ($pending) data-fff-viewer-entry-name @else x-text="current()?.name" @endif></span>
    <span @if ($pending) data-fff-viewer-entry-position @else x-text="position() + ' / ' + items.length" @endif></span>
</div>
<div class="fff-upload-viewer__caption fff-upload-viewer__chrome">
    <button type="button" class="fff-upload-viewer__zoom"
        @if ($pending) disabled @else wire:loading.attr="disabled" wire:target="callMountedAction" x-on:click="setScale(scale / 1.25)" x-bind:disabled="busy || loading || scale <= 1" @endif
        aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.zoomOut') }}"><x-filament::icon icon="hugeicons-search-minus" /></button>
    <button type="button" class="fff-upload-viewer__percentage"
        @if ($pending) disabled @else wire:loading.attr="disabled" wire:target="callMountedAction" x-on:click="resetZoom()" x-bind:disabled="busy || loading" x-text="Math.round(scale * 100) + '%'" @endif
        aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.resetZoom') }}">100%</button>
    <button type="button" class="fff-upload-viewer__zoom"
        @if ($pending) disabled @else wire:loading.attr="disabled" wire:target="callMountedAction" x-on:click="setScale(scale * 1.25)" x-bind:disabled="busy || loading || scale >= 6" @endif
        aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.zoomIn') }}"><x-filament::icon icon="hugeicons-search-add" /></button>
</div>
