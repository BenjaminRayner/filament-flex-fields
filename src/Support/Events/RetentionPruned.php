<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

final readonly class RetentionPruned implements ComponentActivity
{
    /** @param list<string> $items */
    public function __construct(
        public string $category,
        public int $count,
        public array $items,
        public bool $dryRun = false,
    ) {}

    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'count' => $this->count,
            'items' => $this->items,
            'dry_run' => $this->dryRun,
        ];
    }
}
