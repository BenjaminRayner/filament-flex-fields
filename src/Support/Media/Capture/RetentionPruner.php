<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Capture;

use Bjanczak\FilamentFlexFields\Support\Media\Data\MediaFailure;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Explicit administrative retention, separate from abandoned-upload cleanup. */
final class RetentionPruner
{
    /** @param list<string> $directories
     * @return list<string>
     */
    public function disk(string $disk, array $directories, int $days, bool $dryRun = false, int $limit = 1000): array
    {
        $this->validate($days, $limit);
        $storage = Storage::disk($disk);
        if (! $storage instanceof FilesystemAdapter) {
            throw new MediaFailure('retention_list_failed', 'Retention requires a Laravel filesystem adapter.');
        }
        $cutoff = time() - $days * 86400;
        $removed = [];
        foreach (array_unique($directories) as $directory) {
            $directory = MediaAddress::path($directory);
            foreach ($storage->getDriver()->listContents($directory, true) as $entry) {
                if (! $entry->isFile() || ! str_starts_with($entry->path(), $directory.'/')) {
                    continue;
                }
                $modified = $entry->lastModified() ?? $storage->lastModified($entry->path());
                if ($modified <= 0 || $modified >= $cutoff || in_array($entry->path(), $removed, true)) {
                    continue;
                }
                if (! $dryRun && ! $storage->delete($entry->path())) {
                    throw new MediaFailure('cleanup_failed', 'Retention could not delete a media object.');
                }
                $removed[] = $entry->path();
                if (count($removed) >= $limit) {
                    return $removed;
                }
            }
        }

        return $removed;
    }

    /** @return list<string> */
    public function spatie(int $days, ?string $collection = null, bool $dryRun = false, ?string $kind = null, int $limit = 1000): array
    {
        $this->validate($days, $limit);
        if (! class_exists(Media::class)) {
            return [];
        }
        $model = config('media-library.media_model', Media::class);
        if (! is_string($model) || ! is_a($model, Media::class, true)) {
            throw new MediaFailure('spatie_configuration_invalid', 'Invalid native Media model.');
        }
        $instance = app($model);
        if (! $instance instanceof Media) {
            throw new MediaFailure('spatie_configuration_invalid', 'Native Media binding must return a Media model.');
        }
        $query = $instance->newQuery()->where('created_at', '<', now()->subDays($days))
            ->whereNotNull('custom_properties->flex_capture');
        if ($collection !== null && $collection !== '') {
            $query->where('collection_name', $collection);
        }
        if ($kind !== null && $kind !== '') {
            $query->where('custom_properties->flex_capture->kind', $kind);
        }
        $removed = [];
        foreach ($query->lazyById(100) as $media) {
            if (! $media instanceof Media) {
                throw new MediaFailure('spatie_configuration_invalid', 'Native media query returned an invalid model.');
            }
            if (! $this->hasStamp($media)) {
                continue;
            }
            $uuid = (string) $media->uuid;
            if (! $dryRun && $media->delete() === false) {
                throw new MediaFailure('cleanup_failed', 'Retention could not delete native media.');
            }
            $removed[] = $uuid;
            if (count($removed) >= $limit) {
                break;
            }
        }

        return $removed;
    }

    public function hasStamp(object $media): bool
    {
        if (! $media instanceof Media) {
            return false;
        }
        $stamp = $media->getCustomProperty('flex_capture');

        return is_array($stamp) && (filled($stamp['field'] ?? null)
            || (is_string($stamp['correlation_id'] ?? null) && is_string($stamp['kind'] ?? null)));
    }

    private function validate(int $days, int $limit): void
    {
        if ($days < 1 || $days > 36500 || $limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('Retention needs positive days and a bounded deletion batch.');
        }
    }
}
