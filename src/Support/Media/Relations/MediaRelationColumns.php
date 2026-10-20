<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Relations;

use InvalidArgumentException;

/** Explicit SQL columns; neither model ownership nor table names come from form state. */
final readonly class MediaRelationColumns
{
    public function __construct(
        public string $disk = 'disk',
        public string $path = 'path',
        public string $collection = 'collection',
        public string $originalName = 'original_name',
        public string $order = 'sort_order',
        public ?string $metadata = null,
    ) {
        $columns = $this->all();
        foreach ($columns as $column) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/D', $column)) {
                throw new InvalidArgumentException('Media relation columns must be simple SQL identifiers.');
            }
        }
        if (count(array_unique(array_map(strtolower(...), $columns))) !== count($columns)) {
            throw new InvalidArgumentException('Media relation columns must be distinct.');
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_values(array_filter([$this->disk, $this->path, $this->collection, $this->originalName, $this->order, $this->metadata], static fn (?string $column): bool => $column !== null));
    }
}
