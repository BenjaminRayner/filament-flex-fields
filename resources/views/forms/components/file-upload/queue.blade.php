@if ($field->isPausable())
<div class="fff-flex-file-upload__queue" x-show="queuePaused || files.some(file => file.paused || (!file.leaving && !file.complete && !file.failed))" x-cloak>
    <button type="button" class="fff-flex-file-upload__queue-action" x-show="!queuePaused" x-on:click="queueAction('pause')" x-bind:disabled="isDisabled || queueBusy"><x-filament::icon :icon="\Bjanczak\FilamentFlexFields\Support\FlexIcon::PauseFill" />{{ __('filament-flex-fields::default.file_upload.visual.pause_all') }}</button>
    <button type="button" class="fff-flex-file-upload__queue-action" x-show="queuePaused" x-on:click="queueAction('resume')" x-bind:disabled="isDisabled || queueBusy"><x-filament::icon icon="hugeicons-play" />{{ __('filament-flex-fields::default.file_upload.visual.resume_all') }}</button>
</div>
@endif
