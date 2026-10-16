@php($viewerLabels = \Illuminate\Support\Arr::only(__('filament-flex-fields::default.file_upload.viewer'), ['failed', 'saveFailed']))
<div class="fff-upload-viewer" data-fff-viewer-field="{{ $viewerField->getKey() }}" wire:ignore x-fff-load
    x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('file-upload-viewer', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
    x-data="fileUploadViewer({ items: @js($viewerItems), selected: @js($viewerKey), schemaComponentKey: @js($viewerField->getKey()), cancelParentActions: @js($viewerCancelParentActions), labels: @js($viewerLabels) })">
    <x-filament-flex-fields::assets component="file-upload-viewer" :consumer="$viewerField->getLivewireKey().'.viewer'" />
    @include('filament-flex-fields::forms.components.file-upload.viewer-chrome', ['pending' => false])
    <div class="fff-upload-viewer__stage" x-ref="stage" tabindex="0" role="group" aria-label="{{ __('filament-flex-fields::default.file_upload.viewer.open') }}">
        <img x-ref="image" x-bind:src="imageUrl" x-bind:alt="current()?.name ?? ''" decoding="async" draggable="false"
            x-on:load="imageReady()" x-on:error="imageFailed()" />
        <div class="fff-upload-viewer__loading" x-show="loading && !previewVisible" role="status"><span></span></div>
        @include('filament-flex-fields::forms.components.file-upload.viewer-navigation', ['pending' => false])
    </div>
    <div class="fff-upload-viewer__error" x-show="error" role="alert" x-text="error"></div>
</div>
