<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Runtime;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaPolicy;
use InvalidArgumentException;

final class MediaDefaults
{
    public function policy(): MediaPolicy
    {
        $config = config('filament-flex-fields.media_capture', []);
        if (! is_array($config)) {
            throw new InvalidArgumentException('Media OS configuration must be an array.');
        }

        return new MediaPolicy(
            maxBytes: $config['max_bytes'] ?? 268435456,
            minBytes: $config['min_bytes'] ?? 0,
            mimeTypes: $config['mime_types'] ?? [],
            requireScanner: $config['require_virus_scan'] ?? false,
            scanBeforeWrite: $config['scan_before_persist'] ?? true,
            scanAfterWrite: $config['scan_after_persist'] ?? true,
            verifyIntegrity: $config['verify_integrity'] ?? true,
            visibility: $config['visibility'] ?? 'private',
            quarantineDisk: $config['quarantine_disk'] ?? null,
        );
    }
}
