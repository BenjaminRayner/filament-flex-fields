<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Pipeline;

use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaDestinations;

/** @deprecated Compatibility names; the storage module owns destination resolution. */
final class DiskPrecedenceResolver
{
    /** @param array<string, mixed> $tenantContext */
    public static function resolve(?string $explicitDisk = null, ?string $collectionDisk = null, array $tenantContext = [], bool $allowTenantOverride = true): string
    {
        return app(MediaDestinations::class)->disk($explicitDisk, $collectionDisk, $tenantContext, $allowTenantOverride);
    }

    /** @param array<string, mixed> $tenantContext */
    public static function resolveForSpatie(?string $explicitDisk = null, ?string $collectionDisk = null, array $tenantContext = []): string
    {
        return app(MediaDestinations::class)->spatieDisk($explicitDisk, $collectionDisk, $tenantContext);
    }
}
