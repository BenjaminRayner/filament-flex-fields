<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\RichEditor;

/** Scoped to a request; never memoizes authorization decisions. */
class RichEditorSpatieMediaRepository
{
    /** @var array<string, object|null> */
    private array $cache = [];

    public function findByUuid(string $uuid): ?object
    {
        $mediaClass = 'Spatie\\MediaLibrary\\MediaCollections\\Models\\Media';

        if (! class_exists($mediaClass)) {
            return null;
        }

        $query = $mediaClass::query()->where('uuid', $uuid)->applyScopes();
        $connection = $query->getModel()->getConnection();
        $key = hash('sha256', serialize([$connection->getName(), $connection->getDatabaseName(), $query->getQuery()->toSql(), $query->getQuery()->getBindings()]));

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if (count($this->cache) >= 256) {
            array_shift($this->cache);
        }

        return $this->cache[$key] = $query->first();
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}
