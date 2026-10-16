<button type="submit" class="fff-upload-lightbox-save" wire:loading.attr="disabled" wire:target="callMountedAction" wire:loading.class="is-saving">
    <span wire:loading wire:target="callMountedAction" class="fff-upload-lightbox-save__spinner" aria-hidden="true"></span>
    <span wire:loading.remove wire:target="callMountedAction">{{ __('filament-flex-fields::default.file_upload.viewer.save') }}</span>
    <span wire:loading wire:target="callMountedAction" role="status" aria-live="polite">{{ __('filament-flex-fields::default.file_upload.viewer.saving') }}</span>
</button>
