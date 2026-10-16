<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Pipeline;

use Bjanczak\FilamentFlexFields\Support\Media\Compatibility\MediaTranslation;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaReceipt;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\MediaOs;
use Bjanczak\FilamentFlexFields\Support\Media\Pipeline\Refs\MediaRef;
use Closure;

/** @deprecated DTO translation only. New integrations inject MediaOs directly. */
final class MediaIngress
{
    public function __construct(private readonly MediaOs $media) {}

    /** @param (Closure(MediaRef, MediaContext): MediaRef|null)|null $afterPersist */
    public function persist(MediaPayload $payload, MediaContext $context, ?Closure $afterPersist = null): ?MediaRef
    {
        $intent = MediaTranslation::intent($context);
        try {
            $receipt = $this->media->store(MediaTranslation::source($payload), $intent,
                $afterPersist === null ? null : static function (MediaReceipt $receipt) use ($afterPersist, $context): void {
                    $reference = MediaTranslation::reference($receipt);
                    $replacement = $afterPersist($reference, $context);
                    if ($replacement !== null && ($replacement->identifier() !== $reference->identifier()
                        || $replacement->diskName() !== $reference->diskName()
                        || $replacement->driver() !== $reference->driver())) {
                        throw new MediaFailure('invalid_receipt', 'Use a Media OS processor to transform media; finalizers cannot replace ownership.');
                    }
                });

            return MediaTranslation::reference($receipt);
        } catch (MediaFailure $failure) {
            if (in_array($failure->reason, ['scan_rejected', 'scanner_failed', 'scanner_unavailable', 'size_rejected', 'type_rejected'], true)) {
                return null;
            }
            throw $failure;
        }
    }

    public function passesPrePersistScan(MediaPayload $payload, MediaContext $context): bool
    {
        try {
            $prepared = $this->media->inspect(MediaTranslation::source($payload), MediaTranslation::intent($context));
            $prepared->close();

            return true;
        } catch (MediaFailure) {
            return false;
        }
    }

    public function assertCleanStored(MediaRef $reference, MediaContext $context): bool
    {
        $intent = MediaTranslation::intent($context);
        $receipt = MediaTranslation::receipt($reference, $intent);
        try {
            $this->media->verify($receipt, $intent);

            return true;
        } catch (MediaFailure) {
            $this->media->compensate($receipt, $intent);

            return false;
        }
    }

    /** @param array<string, mixed> $context */
    public function resolveSignedUrl(string $disk, string $path, array $context = []): ?string
    {
        $resolver = MediaCaptureOs::signedUploadUrlResolver();
        $url = $resolver === null ? null : $resolver($disk, $path, $context);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function reject(MediaRef $reference, MediaContext $context): void
    {
        $intent = MediaTranslation::intent($context);
        $this->media->compensate(MediaTranslation::receipt($reference, $intent), $intent);
    }
}
