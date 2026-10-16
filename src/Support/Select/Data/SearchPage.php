<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

final readonly class SearchPage
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(public array $items, public ?string $cursor, public bool $hasMore) {}

    /** @return array{items: list<array<string, mixed>>, cursor: ?string, hasMore: bool} */
    public function toArray(): array
    {
        return ['items' => $this->items, 'cursor' => $this->cursor, 'hasMore' => $this->hasMore];
    }
}
