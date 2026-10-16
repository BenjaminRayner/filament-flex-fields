<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise\Schema;

use RuntimeException;

/** Safe conflict metadata: no schema, field values or actor details. */
final class SchemaVersionConflict extends RuntimeException
{
    public function __construct(public readonly int $expected, public readonly int $actual)
    {
        parent::__construct('Schema version changed; reload the schema before publishing.');
    }
}
