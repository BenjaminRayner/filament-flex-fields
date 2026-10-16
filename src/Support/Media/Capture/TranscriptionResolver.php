<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Capture;

use Bjanczak\FilamentFlexFields\Support\Media\CircuitBreakerTranscriptionProvider;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureState;
use Bjanczak\FilamentFlexFields\Support\Media\NullVoiceNoteTranscription;
use Bjanczak\FilamentFlexFields\Support\Media\VoiceNoteTranscriptionInterface;
use InvalidArgumentException;

final class TranscriptionResolver
{
    public function __construct(private readonly MediaCaptureState $state) {}

    /** @param array<string, mixed>|null $configuration */
    public function resolve(?array $configuration = null): ?VoiceNoteTranscriptionInterface
    {
        if ($this->state->transcriptionConfigured) {
            return $this->state->transcriptionInterface;
        }
        $configuration ??= config('filament-flex-fields.media_capture', []);
        if (! is_array($configuration)) {
            throw new InvalidArgumentException('Media capture configuration must be an array.');
        }
        $factory = $this->state->transcriptionFactory;
        if ($factory !== null) {
            $provider = $factory();
            if (! $provider instanceof VoiceNoteTranscriptionInterface) {
                throw new InvalidArgumentException('Transcription factory must return VoiceNoteTranscriptionInterface.');
            }
        } else {
            $class = $configuration['transcription'] ?? null;
            if ($class !== null && $class !== '') {
                if (! is_string($class) || ! is_subclass_of($class, VoiceNoteTranscriptionInterface::class)) {
                    throw new InvalidArgumentException('Configured transcription provider must implement VoiceNoteTranscriptionInterface.');
                }
                $provider = app($class);
            } else {
                $provider = new NullVoiceNoteTranscription;
            }
            $breaker = $configuration['transcription_circuit_breaker'] ?? [];
            if (! is_array($breaker)) {
                throw new InvalidArgumentException('Transcription circuit breaker configuration must be an array.');
            }
            if (($breaker['enabled'] ?? true) === true) {
                $provider = new CircuitBreakerTranscriptionProvider($provider,
                    $breaker['failure_threshold'] ?? 3, $breaker['open_seconds'] ?? 30,
                    $breaker['cache_scope'] ?? null, $breaker['probe_seconds'] ?? 60);
            }
        }
        // A failed factory/configuration is not cached; a later retry may succeed.
        $this->state->transcriptionInterface = $provider;
        $this->state->transcriptionConfigured = true;

        return $provider;
    }
}
