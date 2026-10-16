<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Storage;

use Illuminate\Support\Facades\Storage;
use Throwable;

/** Optional filesystem URL signing, without unsigned fallback. */
final class SignedMediaUrls
{
    public function forPath(string $disk, string $path, mixed $minutes = null): ?string
    {
        try {
            MediaAddress::path($path);
            $ttl = filter_var($minutes ?? config('filament-flex-fields.media_capture.signed_url_minutes', 15), FILTER_VALIDATE_INT);
            if ($ttl === false || $ttl < 1 || $ttl > 10080) {
                return null;
            }
            $storage = Storage::disk($disk);

            return method_exists($storage, 'temporaryUrl') ? $storage->temporaryUrl($path, now()->addMinutes($ttl)) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
