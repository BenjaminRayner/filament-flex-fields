<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Spatie;

use Bjanczak\FilamentFlexFields\Support\Media\FlexMedia;
use DateTimeInterface;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/** URL presentation for an already owner/filter-authorized native Media object. */
final class SpatieMediaUrls
{
    /** @param array<string, mixed> $context */
    public function forMedia(Media $media, string $visibility, DateTimeInterface $expiresAt, string $conversion = '', array $context = []): ?string
    {
        $seconds = $expiresAt->getTimestamp() - time();
        if (! in_array($visibility, ['public', 'private'], true) || $seconds < 1 || $seconds > 604800) {
            return null;
        }
        if ($conversion !== '' && ! $media->hasGeneratedConversion($conversion)) {
            $conversion = '';
        }
        try {
            $disk = $conversion === '' ? (string) $media->disk : (string) ($media->conversions_disk ?: $media->disk);
            $signed = FlexMedia::signedUrl($disk, $media->getPathRelativeToRoot($conversion), [...$context,
                'visibility' => $visibility, 'conversion' => $conversion,
                'minutes' => max(1, (int) ceil($seconds / 60)),
            ]);
            if ($signed !== null) {
                return $signed;
            }

            return $visibility === 'private' ? $media->getTemporaryUrl($expiresAt, $conversion) : $media->getUrl($conversion);
        } catch (Throwable) {
            return null;
        }
    }
}
