<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Storage\SignedMediaUrls;

/** Compatibility callback; signing belongs to the shared storage service. */
final class LaravelStorageSignedUrlResolver
{
    /** @param array<string, mixed> $context */
    public function __invoke(string $disk, string $path, array $context = []): ?string
    {
        return app(SignedMediaUrls::class)->forPath($disk, $path, $context['minutes'] ?? null);
    }
}
