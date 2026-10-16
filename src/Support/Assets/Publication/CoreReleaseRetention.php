<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Publication;

use Illuminate\Filesystem\Filesystem;

/** Explicit deployment operation. Publication never deletes an old final release. */
final class CoreReleaseRetention
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /** @param list<string> $protected
     * @return list<array{release: string, publishedAt: int, protected: bool, eligible: bool, removed: bool}>
     */
    public function run(string $publicRoot, string $prefix, string $current, int $keep = 3, int $minimumAge = 2592000, array $protected = [], bool $prune = false): array
    {
        return (new ReleaseHistoryRetention($this->filesystem))->run(
            $publicRoot,
            ReleaseInventory::prefix($prefix).'filament-flex-fields-assets/core',
            $current,
            BuiltInReleaseMarker::inventory(...),
            $keep,
            $minimumAge,
            $protected,
            $prune,
        );
    }
}
