@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
@endphp
<div class="fff-flex-file-upload__source-card fff-flex-file-upload__source-card--camera">
    @include('filament-flex-fields::forms.components.file-upload.frame')
    @include('filament-flex-fields::forms.components.file-upload.source-header', [
        'source' => 'camera',
        'heading' => __('filament-flex-fields::default.file_upload.sources.webcam_modal_heading'),
        'description' => __('filament-flex-fields::default.file_upload.visual.camera_hint'),
    ])
    <div class="fff-flex-file-upload__source-controls">
        <button type="button" class="fff-flex-file-upload__select" wire:ignore.self disabled x-on:click="openWebcamModal()" x-bind:disabled="isDisabled">
            <x-filament::icon :icon="FlexIcon::Camera" />
            {{ __('filament-flex-fields::default.file_upload.sources.webcam_open_action') }}
        </button>
    </div>
    <div class="fff-flex-file-upload__source-footer">
        <span class="fff-flex-file-upload__source-note">{{ __('filament-flex-fields::default.file_upload.visual.camera_privacy') }}</span>
    </div>
</div>
