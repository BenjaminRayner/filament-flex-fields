<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Manifest;

use InvalidArgumentException;

/** Bounded identities are portable metadata, never paths, callbacks or URLs. */
final class ManifestIdentity
{
    public static function assert(mixed $id): void
    {
        if (! is_string($id) || strlen($id) > 96 || preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid artifact identity.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $ids
     *
     * @phpstan-assert list<string> $ids
     */
    public static function assertList(array $ids): void
    {
        if (! array_is_list($ids) || count($ids) > 1024) {
            throw new InvalidArgumentException('Artifact edges must be a bounded list of unique identities.');
        }
        foreach ($ids as $id) {
            self::assert($id);
        }
        if (count(array_unique($ids, SORT_STRING)) !== count($ids)) {
            throw new InvalidArgumentException('Duplicate artifact dependency identity.');
        }
    }
}
