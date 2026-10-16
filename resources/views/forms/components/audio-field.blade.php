@php
    $render = \Bjanczak\FilamentFlexFields\Support\Audio\AudioRenderState::from($field);
    $statePath = $getStatePath();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field"
    :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class($getWrapperClasses())">
    @include('filament-flex-fields::partials.load-stylesheet', [
        'component'=>'audio-field', 'livewireKey'=>$getLivewireKey(), 'assetSchemaComponent'=>$field,
        'capabilities'=>$field->isTranscriptionEnabled() && $field->isTranscriptionSettingsVisible() ? ['transcription'] : [],
    ])
    <div wire:ignore wire:key="{{ $render->key }}"
        @class(['fff-audio-field','fff-audio-field--'.$getSize(),'is-full-width'=>$isFullWidth(),
            'is-disabled'=>$isDisabled(),'is-read-only'=>$isReadOnly(),'is-empty'=>$render->source===null,
            'has-transcription'=>$field->isTranscriptionEnabled(),'has-transcription-cta'=>$field->isTranscriptionRuntimeMissing()])
        role="group" aria-label="{{ $getLabel() }}">
        <div class="fff-audio-field__shell" x-fff-load
            x-fff-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('audio-field', \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME) }}"
            x-data="audioFieldFormComponent({ ...@js($render->configuration), state:$wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} })">
            @include('filament-flex-fields::forms.components.partials.audio.player', ['loop'=>$shouldLoop()])
            @if ($field->isTranscriptionEnabled())
                @include('filament-flex-fields::forms.components.partials.audio-field-transcription-panel', ['settingsVisible'=>$field->isTranscriptionSettingsVisible()])
            @elseif ($field->isTranscriptionRuntimeMissing())
                <div class="fff-audio-field__transcription-cta" role="status">
                    <p class="fff-audio-field__transcription-cta-text">{{ __('filament-flex-fields::default.audio.transcription_runtime_missing') }}</p>
                    <code class="fff-audio-field__transcription-cta-command">{{ \Bjanczak\FilamentFlexFields\Support\WhisperRuntimeManifest::INSTALL_COMMAND }}</code>
                </div>
            @endif
        </div>
    </div>
</x-dynamic-component>
