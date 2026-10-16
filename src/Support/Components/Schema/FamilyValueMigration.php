<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

/** Optional provider-authored value conversion. Never selected by imported data. */
interface FamilyValueMigration
{
    public function supportsValueMigration(int $fromVersion, int $toVersion): bool;

    public function migrateStoredValue(mixed $value, FamilySchemaDefinition $from, FamilySchemaDefinition $to, FamilyRenderContext $context): mixed;
}
