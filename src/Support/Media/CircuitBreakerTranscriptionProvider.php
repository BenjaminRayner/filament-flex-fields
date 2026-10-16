<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Runtime\CircuitBreaker;
use Throwable;

/** Transcription adapter; null is a valid no-transcription result, exceptions are failures. */
final class CircuitBreakerTranscriptionProvider implements VoiceNoteTranscriptionInterface
{
    private readonly CircuitBreaker $circuit;

    public function __construct(
        private readonly VoiceNoteTranscriptionInterface $provider,
        int $failureThreshold = 3,
        int $openSeconds = 30,
        ?string $cacheScope = null,
        int $probeSeconds = 60,
    ) {
        $this->circuit = new CircuitBreaker(
            'transcription:'.$provider::class.':'.($cacheScope ?? 'default'),
            $failureThreshold, $openSeconds, $probeSeconds,
        );
    }

    public function transcribe(string $disk, string $path, array $context = []): ?string
    {
        try {
            return $this->circuit->run(fn (): ?string => $this->provider->transcribe($disk, $path, $context));
        } catch (Throwable) {
            return null;
        }
    }
}
