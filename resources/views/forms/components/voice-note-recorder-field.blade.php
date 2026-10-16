@php
    $statePath = $getStatePath();
    $disabled = $isDisabled();
    $initialUrl = $field->getInitialAudioUrl();
    $settings = [
        'statePath' => $statePath, 'schemaComponentKey' => $getKey(),
        'initialAudioUrl' => $initialUrl, 'initialWaveform' => $field->getInitialAudioWaveform(),
        'maxBytes' => ($field->getMaxSize() ?? 25600) * 1024,
        'maxDuration' => $field->getMaxDuration(), 'audioBitsPerSecond' => $field->getAudioBitsPerSecond(),
        'confirmDeletion' => $field->shouldConfirmDeletion(), 'uploadImmediately' => $field->shouldUploadImmediately(),
        'readOnly' => $disabled, 'waveformMetadataPath' => filled($waveformPath = $field->getStoreWaveformInPath()) ? $field->resolveRelativeStatePath($waveformPath) : null,
    ];
    $identity = $settings;
    unset($identity['initialAudioUrl'], $identity['initialWaveform']);
    $identity['record'] = $field->getRecord()?->getKey();
    $labels = [];
    foreach (['play','pause','capture_unavailable','capture_failed','too_large','uploading_on_submit','delete_confirm','delete_failed','upload_failed'] as $key) {
        $labels[\Illuminate\Support\Str::camel($key)] = __('filament-flex-fields::default.audio.'.$key);
    }
    $labels['permission_denied'] = __('filament-flex-fields::recording.permission_denied');
    $labels['device_missing'] = __('filament-flex-fields::recording.device_missing');
    $labels['device_busy'] = __('filament-flex-fields::recording.device_busy');
    $radius = ['none'=>0,'sm'=>12,'md'=>24,'lg'=>28,'xl'=>32,'full'=>24][$getRounding()] ?? 24;
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component'=>'voice-note-recorder-field', 'livewireKey'=>$getLivewireKey(), 'assetSchemaComponent'=>$field,
    ])
    <div wire:ignore wire:key="{{ $getLivewireKey() }}.{{ hash('sha256',serialize($identity)) }}"
        class="fff-voice-recorder fff-voice-recorder--{{ $getSize() }}"
        style="--fff-voice-radius:{{ $radius }}px" role="group" aria-label="{{ $getLabel() }}"
        x-fff-load x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('voice-note-recorder-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
        x-data="voiceNoteRecorderFieldFormComponent({ ...@js($settings), labels:@js($labels), state:$wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })"
        x-bind:aria-busy="initializing || isBackgroundUploading || isProtectingRecording || isDeleting">
        <div class="fff-voice-recorder__header">
            <span class="fff-voice-recorder__eyebrow">{{ __('filament-flex-fields::recording.voice_note') }}</span>
            <span class="fff-voice-recorder__limit">{{ __('filament-flex-fields::recording.limit', ['seconds'=>$getMaxDuration()]) }}</span>
        </div>
        <div class="fff-voice-recorder__idle" x-show="mode==='idle'" @if ($initialUrl) style="display:none" @endif>
            <button type="button" class="fff-voice-recorder__record" x-on:click="startRecording()"
                x-bind:disabled="readOnly || initializing" @disabled($disabled)
                aria-label="{{ __('filament-flex-fields::default.audio.record_start') }}">
                <span x-show="!initializing">{{ \Filament\Support\generate_icon_html($field->getMicrophoneIcon()) }}</span>
                <x-filament::loading-indicator x-show="initializing" x-cloak />
            </button>
            <div class="fff-voice-recorder__intro">
                <span class="fff-voice-recorder__title">{{ __('filament-flex-fields::recording.start') }}</span>
                <span class="fff-voice-recorder__hint">{{ __('filament-flex-fields::recording.review_hint') }}</span>
            </div>
        </div>
        <div class="fff-voice-recorder__capture" x-show="mode==='recording' || mode==='paused'" x-cloak>
            <div class="fff-voice-recorder__status"><span class="fff-voice-recorder__dot" x-bind:class="{ 'is-paused':mode==='paused' }"></span>
                <span x-text="formattedDuration" class="fff-voice-recorder__time">0:00</span></div>
            <canvas x-ref="canvas" class="fff-voice-recorder__canvas" aria-hidden="true"></canvas>
            <div class="fff-voice-recorder__actions">
                <button type="button" class="fff-voice-recorder__button" x-on:click="toggleRecordingPause()" aria-label="{{ __('filament-flex-fields::recording.pause_resume') }}">
                    <span x-show="mode==='recording'">{{ \Filament\Support\generate_icon_html($field->getPauseIcon()) }}</span>
                    <span x-show="mode==='paused'" x-cloak>{{ \Filament\Support\generate_icon_html($field->getPlayIcon()) }}</span>
                </button>
                <button type="button" class="fff-voice-recorder__button" x-on:click="cancelRecording()" aria-label="{{ __('filament-flex-fields::default.audio.cancel') }}">{{ \Filament\Support\generate_icon_html($field->getTrashIcon()) }}</button>
                <button type="button" class="fff-voice-recorder__button fff-voice-recorder__button--primary" x-on:click="stopRecording()" aria-label="{{ __('filament-flex-fields::default.audio.save') }}">{{ \Filament\Support\generate_icon_html($field->getCheckmarkIcon()) }}</button>
            </div>
        </div>
        <div class="fff-voice-recorder__playback fff-audio-field" x-show="mode==='playback'" @if (!$initialUrl) style="display:none" @endif>
            @include('filament-flex-fields::forms.components.partials.audio.player')
            <button type="button" class="fff-voice-recorder__button" x-on:click="deleteRecording()"
                x-bind:disabled="readOnly || isBackgroundUploading || isProtectingRecording || isDeleting" aria-label="{{ __('filament-flex-fields::default.audio.delete') }}">
                <span x-show="!isDeleting">{{ \Filament\Support\generate_icon_html($field->getTrashIcon()) }}</span>
                <x-filament::loading-indicator x-show="isDeleting" x-cloak />
            </button>
        </div>
        <div class="fff-voice-recorder__processing" x-show="mode==='processing' || mode==='uploading'" x-cloak>
            <x-filament::loading-indicator /> <span>{{ __('filament-flex-fields::recording.preparing') }}</span>
        </div>
        <div class="fff-voice-recorder__footer" x-show="mode==='playback'" x-cloak>
            <span class="fff-voice-recorder__hint" x-show="!pendingUpload && !isBackgroundUploading">{{ __('filament-flex-fields::recording.uploaded') }}</span>
            <span class="fff-voice-recorder__hint" x-show="pendingUpload && !isBackgroundUploading">{{ __('filament-flex-fields::recording.pending') }}</span>
            <span class="fff-voice-recorder__progress" x-show="isBackgroundUploading" x-text="uploadProgress+'%'" role="status"></span>
            <button type="button" class="fff-voice-recorder__save" x-show="pendingUpload && !isBackgroundUploading" x-bind:disabled="isProtectingRecording" x-on:click="uploadRecording().catch(()=>{})">{{ __('filament-flex-fields::recording.upload') }}</button>
        </div>
        <p class="fff-voice-recorder__error" x-show="error" x-cloak x-text="error" role="alert"></p>
    </div>
</x-dynamic-component>
