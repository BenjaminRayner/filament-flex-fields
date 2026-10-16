@php
    use Bjanczak\FilamentFlexFields\Support\FlexIcon;
@endphp
<div class="fff-flex-file-upload__source-card fff-flex-file-upload__source-card--url">
    @include('filament-flex-fields::forms.components.file-upload.frame')
    @include('filament-flex-fields::forms.components.file-upload.source-header', [
        'source' => 'url',
        'heading' => __('filament-flex-fields::default.file_upload.visual.url_heading'),
        'description' => __('filament-flex-fields::default.file_upload.visual.url_hint'),
    ])
    <div class="fff-flex-file-upload__source-controls fff-flex-file-upload__url-controls">
        <label class="sr-only" for="{{ $id }}-import-url">{{ __('filament-flex-fields::default.file_upload.visual.url_heading') }}</label>
        <div class="fff-flex-file-upload__url-field fff-flex-text-input fff-flex-text-input--{{ $getSize() }} fff-flex-text-input--primary fff-rounding-xl has-focus-outline" x-bind:class="{ 'is-disabled': urlImporting || isDisabled }">
            <x-filament-flex-fields::text-input.shell>
                <x-filament::input.wrapper class="fff-flex-text-input__wrapper" x-bind:class="{ 'fi-disabled': urlImporting || isDisabled }">
                    <x-filament-flex-fields::text-input.control id="{{ $id }}-import-url" type="url" inputmode="url" autocomplete="url" x-model="urlValue"
                        x-bind:disabled="urlImporting || isDisabled" :placeholder="__('filament-flex-fields::default.file_upload.sources.url_placeholder')"
                        maxlength="2048" autocapitalize="off" spellcheck="false" x-on:keydown.enter.prevent="importFromUrl()" />
                </x-filament::input.wrapper>
            </x-filament-flex-fields::text-input.shell>
        </div>
        <button type="button" class="fff-flex-file-upload__select" wire:ignore.self disabled x-on:click="importFromUrl()" x-bind:disabled="urlImporting || isDisabled">
            <x-filament::icon :icon="FlexIcon::ArrowDownToSquare" />
            <span x-text="urlImporting ? labels.urlImporting : labels.urlImport">{{ __('filament-flex-fields::default.file_upload.sources.url_import') }}</span>
        </button>
    </div>
    <div class="fff-flex-file-upload__source-footer" x-show="urlError" x-cloak>
        <p class="fff-flex-file-upload__error" x-show="urlError" x-text="urlError" x-cloak role="alert"></p>
    </div>
</div>
