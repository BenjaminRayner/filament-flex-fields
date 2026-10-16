<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Closure;

/** Media hooks and configured providers are owned by one request or queue job. */
final class MediaCaptureState
{
    /** @var (Closure(string): (bool|null))|null */
    public ?Closure $virusScanCallback = null;

    /** @var (Closure(): (string|null))|null */
    public ?Closure $legalSignerIdResolver = null;

    /** @var (Closure(): (string|null))|null */
    public ?Closure $documentHashResolver = null;

    /** @var (Closure(string, string, array<string, mixed>): (string|null))|null */
    public ?Closure $signedUploadUrlResolver = null;

    /** @var (Closure(string): (string|null))|null */
    public ?Closure $tokenizeCreditCardCallback = null;

    public ?VoiceNoteTranscriptionInterface $transcriptionInterface = null;

    /** @var (Closure(): VoiceNoteTranscriptionInterface)|null */
    public ?Closure $transcriptionFactory = null;

    /**
     * @var array{
     *     uploads: array{enabled: bool, days: int|null},
     *     voice_notes: array{enabled: bool, days: int|null},
     *     signatures: array{enabled: bool, days: int|null},
     *     temp_captures: array{enabled: bool, days: int|null},
     * }|null
     */
    public ?array $retentionPolicies = null;

    public bool $signedUrlConfigured = false;

    public bool $transcriptionConfigured = false;
}
