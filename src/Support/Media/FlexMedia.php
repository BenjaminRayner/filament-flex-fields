<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaWrite;
use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\QueuedImageConversions;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaContext;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaIngress;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\MediaPayload;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\MediaRef;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\MediaStaging;
use Closure;

/**
 * Container entry points for the new Media OS and temporary lease lifecycle.
 *
 * {@see MediaCaptureOs} remains as a deprecated facade for host-registered hooks (virus scan, signed URLs, etc.).
 */
final class FlexMedia
{
    public static function os(): MediaOs
    {
        return app(MediaOs::class);
    }

    /** @param (Closure(MediaReceipt, MediaWrite): void)|null $finalize */
    public static function store(MediaSource $source, MediaWrite $intent, ?Closure $finalize = null): MediaReceipt
    {
        return self::os()->store($source, $intent, $finalize);
    }

    public static function staging(): MediaStaging
    {
        return app(MediaStaging::class);
    }

    public static function images(): ImageMedia
    {
        return app(ImageMedia::class);
    }

    public static function queuedImages(): QueuedImageConversions
    {
        return app(QueuedImageConversions::class);
    }

    public static function ingress(): MediaIngress
    {
        return app(MediaIngress::class);
    }

    /**
     * @param  (Closure(MediaRef, MediaContext): MediaRef|null)|null  $afterPersist
     */
    public static function persist(
        MediaPayload $payload,
        MediaContext $context,
        ?Closure $afterPersist = null,
    ): ?MediaRef {
        return self::ingress()->persist($payload, $context, $afterPersist);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function signedUrl(string $disk, string $path, array $context = []): ?string
    {
        return self::ingress()->resolveSignedUrl($disk, $path, $context);
    }

    /** @param (Closure(): VoiceNoteTranscriptionInterface)|null $factory */
    public static function registerTranscriptionFactory(?Closure $factory): void
    {
        MediaCaptureOs::registerTranscriptionFactory($factory);
    }

    /**
     * @deprecated Use {@see MediaIngress} / {@see FlexMedia::persist()} capsules. Kept as alias into the container.
     *
     * @param  (callable(string $path): bool|null)|null  $callback
     */
    public static function registerVirusScanCallback(?callable $callback): void
    {
        MediaCaptureOs::registerVirusScanCallback($callback);
    }

    /**
     * @deprecated Use {@see FlexMedia::signedUrl()} / MediaIngress signed URL capsule.
     *
     * @param  (callable(string $disk, string $path, array<string, mixed> $context): string|null)|null  $resolver
     */
    public static function registerSignedUploadUrlResolver(?callable $resolver): void
    {
        MediaCaptureOs::registerSignedUploadUrlResolver($resolver);
    }
}
