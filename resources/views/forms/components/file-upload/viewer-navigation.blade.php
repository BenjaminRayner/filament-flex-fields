@php($pending = $pending ?? false)
@foreach (['previous' => -1, 'next' => 1] as $directionName => $directionStep)
    <button type="button" class="fff-upload-viewer__arrow fff-upload-viewer__arrow--{{ $directionName }} fff-upload-viewer__chrome"
        @if ($pending) disabled data-fff-viewer-entry-arrow
        @else wire:loading.attr="disabled" wire:target="callMountedAction" x-show="items.length > 1" x-on:click="navigate({{ $directionStep }})" x-bind:disabled="busy || loading" @endif
        aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.'.$directionName) }}"><x-filament::icon :icon="$directionStep < 0 ? 'hugeicons-arrow-left-01' : 'hugeicons-arrow-right-01'" /></button>
@endforeach
