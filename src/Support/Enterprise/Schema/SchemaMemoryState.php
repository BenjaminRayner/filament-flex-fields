<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise\Schema;

final class SchemaMemoryState
{
    /** @var array<string, list<array{version: int, schema: array<string, mixed>, checksum: string, actor: ?string, state: string, published_at: string}>> */
    public array $versions = [];

    /** @var array<string, self> */
    public array $connections = [];

    /** @var array<string, int> */
    public array $counters = [];

    /** @var array<string, bool> Positive availability only, within one request. */
    public array $availableTables = [];
}
