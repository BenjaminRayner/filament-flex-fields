<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Tables\Columns\Column;

/** Trusted provider code; importing configuration cannot select or invoke arbitrary callbacks. */
interface FamilySchemaAdapter
{
    public function authorize(FamilySchemaDefinition $definition, FamilyRenderContext $context): bool;

    /** @param array<string, mixed> $configuration */
    public function configure(Field|Column|Entry $surface, FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): void;

    /**
     * @param  array<string, mixed>  $configuration
     * @return list<string|object>
     */
    public function rules(FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): array;

    /**
     * Only persisted configuration is migrated. New values have no permissive migration path.
     *
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public function migrateConfiguration(array $configuration, int $fromVersion, int $toVersion): array;
}
