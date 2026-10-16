<div class="fff-recording-host" wire:ignore wire:key="flex-recording-{{ $entry['id'] }}" x-fff-load x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('flex-catalog-recording') }}" x-data="flexCatalogRecordingComponent()">
    <x-filament-flex-fields::assets component="flex-catalog-recording" consumer="flex.detail.recording" :critical="true" />
<div class="fff-recording-selection" x-show="selecting" x-cloak>
    <span>{{ __('filament-flex-fields::catalog.recording.choose_hint') }}</span>
    <button type="button" class="fff-recording-launch" x-on:click="open()" x-bind:disabled="count === 0">{{ __('filament-flex-fields::catalog.recording.preview') }} <span x-text="count"></span></button>
    <button type="button" class="fff-recording-launch" x-on:click="exit()">{{ __('filament-flex-fields::catalog.recording.cancel') }}</button>
</div>
<div class="fff-recording-backdrop" x-show="recording" x-bind:class="{ 'fff-recording-controls-hidden': !controls }" x-cloak role="region" aria-label="{{ __('filament-flex-fields::catalog.recording.title') }}">
    <div class="fff-recording-canvas" x-ref="recordingCanvas" aria-hidden="true"></div>
    <button type="button" class="fff-recording-close" x-ref="closeRecording" x-on:click="exit()" aria-label="{{ __('filament-flex-fields::catalog.recording.close') }}"><x-filament::icon icon="hugeicons-cancel-01" /></button>
    <div class="fff-recording-bottom">
        <div class="fff-recording-zoom-row">
            <span>{{ __('filament-flex-fields::catalog.recording.zoom') }}</span>
            <input class="fff-recording-zoom" type="range" min="25" max="300" step="5" value="100" x-bind:value="zoom" x-on:input="zoom = $event.target.valueAsNumber; layout()" aria-label="{{ __('filament-flex-fields::catalog.recording.zoom') }}" />
            <span class="fff-recording-zoom-value" x-text="zoom + '%'">100%</span>
            <div class="fff-recording-widths" aria-label="{{ __('filament-flex-fields::catalog.recording.width') }}">
                @foreach ([320, 480, 720, 960] as $width)
                    <button type="button" class="fff-recording-width-button" x-bind:aria-pressed="width === {{ $width }}" x-on:click="width = {{ $width }}; layout()">{{ $width }}</button>
                @endforeach
            </div>
        </div>
        <div class="fff-recording-zoom-row">
            <span>{{ __('filament-flex-fields::catalog.recording.vertical') }}</span>
            <input class="fff-recording-zoom" type="range" min="-300" max="300" step="5" value="0" x-bind:value="verticalOffset" x-on:input="verticalOffset = $event.target.valueAsNumber; layout()" aria-label="{{ __('filament-flex-fields::catalog.recording.vertical') }}" />
            <span class="fff-recording-zoom-value" x-text="verticalOffset + 'px'">0px</span>
            <label class="fff-recording-helper-toggle"><input type="checkbox" checked x-model="showHelpers" x-on:change="$nextTick(() => layout())" />{{ __('filament-flex-fields::catalog.recording.helpers') }}</label>
        </div>
        <span class="fff-recording-hint">{{ __('filament-flex-fields::catalog.recording.shortcuts') }}</span>
    </div>
</div>

</div>
