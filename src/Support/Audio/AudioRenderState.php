<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Audio;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\AudioField;

/** Resolve the field projection once; no remote audio is fetched during rendering. */
final readonly class AudioRenderState
{
    /** @param array<string, mixed> $configuration */
    private function __construct(public array $configuration, public ?string $source, public string $key) {}

    public static function from(AudioField $field): self
    {
        $labels = ['play' => __('filament-flex-fields::default.audio.play'), 'pause' => __('filament-flex-fields::default.audio.pause')];
        $labels['transcription'] = [];
        foreach ([
            'settings_title' => 'transcription_settings', 'settings_close' => 'transcription_settings_close',
            'model_hint' => 'transcription_model_hint', 'language_hint' => 'transcription_language_hint', 'task_hint' => 'transcription_task_hint',
            'multilingual' => 'transcription_multilingual', 'quantized' => 'transcription_quantized',
            'transcribe' => 'transcribe', 'transcribing' => 'transcribing', 'empty' => 'transcription_empty',
            'loading_audio' => 'transcription_loading_audio', 'loading_model' => 'transcription_loading_model',
            'transcribing_phase' => 'transcription_running', 'task_transcribe' => 'transcription_task_transcribe',
            'task_translate' => 'transcription_task_translate', 'language_auto' => 'language_auto',
        ] as $key => $translation) {
            $labels['transcription'][$key] = __('filament-flex-fields::default.audio.'.$translation);
        }
        foreach (['audio_fetch_failed' => 'fetch', 'audio_context_unavailable' => 'context', 'transcription_failed' => 'failed', 'transcription_model_timeout' => 'model_timeout', 'transcription_runtime_unconfigured' => 'runtime_unconfigured'] as $key => $translation) {
            $labels['transcription']['errors'][$key] = __('filament-flex-fields::default.audio.transcription_error_'.$translation);
        }
        $configuration = [
            'staticSrc' => $field->getSrc(), 'waveform' => $field->resolveWaveform($field->getState()),
            'waveformIsCustom' => $field->hasCustomWaveform(), 'loop' => $field->shouldLoop(),
            'readOnly' => $field->isDisabled() || $field->isReadOnly(), 'labels' => $labels,
            'transcription' => $field->wantsTranscription() ? $field->getTranscriptionAlpineConfig() : null,
        ];
        $source = $field->resolveAudioSrc($field->getState());

        return new self($configuration, $source, $field->getLivewireKey().'.'.hash('sha256', serialize([$configuration, $source, $field->getSize(), $field->isFullWidth()])));
    }
}
