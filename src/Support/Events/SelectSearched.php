<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

final readonly class SelectSearched implements ComponentActivity
{
    public function __construct(
        public string $field,
        public string $queryHash,
        public int $queryLength,
        public string $source,
        public ?bool $hasCursor = null,
    ) {}

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'query_hash' => $this->queryHash,
            'query_len' => $this->queryLength,
            'source' => $this->source,
            'has_cursor' => $this->hasCursor,
        ];
    }
}
