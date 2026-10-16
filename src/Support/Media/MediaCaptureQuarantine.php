<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaDescriptor;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaRegistry;
use Bjanczak\FilamentFlexFields\Support\Media\Security\Quarantine;
use Bjanczak\FilamentFlexFields\Support\Media\Security\RejectedMedia;
use Bjanczak\FilamentFlexFields\Support\Media\Sources\MediaSource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/** @deprecated Rejection aliases into the shared Media OS compensation service. */
final class MediaCaptureQuarantine
{
    public static function quarantineFromDisk(string $sourceDisk, string $path): ?string
    {
        return self::reject(new MediaReceipt('disk', $path, $sourceDisk, $path,
            new MediaDescriptor(0, 'application/octet-stream', '', basename($path)), 'legacy-rejection'));
    }

    public static function quarantineSpatieMedia(?object $media): ?string
    {
        if (! $media instanceof Media) {
            throw new MediaFailure('invalid_receipt', 'Spatie rejection requires native Media, not an arbitrary object.');
        }

        return self::reject(new MediaReceipt('spatie', (string) $media->uuid, (string) $media->disk,
            $media->getPathRelativeToRoot(), new MediaDescriptor((int) $media->size, (string) $media->mime_type, '', (string) $media->file_name),
            'legacy-rejection', $media));
    }

    public static function quarantineLocalPath(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }
        $disk = self::diskName();
        $result = null;
        $failure = null;
        try {
            $result = $disk === null ? null : app(Quarantine::class)->copySource(MediaSource::path($absolutePath), $disk);
        } catch (Throwable $error) {
            $failure = $error instanceof MediaFailure ? $error : new MediaFailure('quarantine_failed', 'Unable to quarantine local capture.', $error);
        }
        try {
            if (! unlink($absolutePath)) {
                throw new MediaFailure('cleanup_failed', 'Unable to remove rejected local capture.');
            }
        } catch (Throwable $error) {
            $failure ??= new MediaFailure('cleanup_failed', 'Unable to remove rejected local capture.', $error);
            $failure->cleanupFailed = true;
            try {
                report($error);
            } catch (Throwable) {
                // A logging outage cannot replace the original quarantine failure.
            }
        }
        if ($failure !== null) {
            throw $failure;
        }

        return $result;
    }

    public static function diskName(): ?string
    {
        $disk = config('filament-flex-fields.media_capture.quarantine_disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    public static function isEnabled(): bool
    {
        return self::diskName() !== null;
    }

    private static function reject(MediaReceipt $receipt): ?string
    {
        $result = app(RejectedMedia::class)->remove(app(MediaRegistry::class)->adapter($receipt->adapter), $receipt, self::diskName());
        if ($result->cleanupFailed) {
            throw new MediaFailure('cleanup_failed', 'Media rejection could not complete all cleanup operations.');
        }

        return $result->quarantinePath;
    }
}
