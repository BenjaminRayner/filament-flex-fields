<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

/** The application atomically persists after and retains before for rollback/concurrency. */
final readonly class FamilySchemaMigrationResult
{
    public function __construct(public FamilyValueSnapshot $before, public FamilyValueSnapshot $after) {}

    public function changed(): bool
    {
        return FamilyValueSnapshotCodec::encode($this->before) !== FamilyValueSnapshotCodec::encode($this->after);
    }
}
