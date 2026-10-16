<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use ReflectionClass;

/** Immutable framework capabilities; no rendered schemas or request state are retained. */
final class FilamentSchemaCompatibility
{
    private static ?bool $deferredLoading = null;

    private static ?bool $childSchemaCacheReset = null;

    public static function supportsDeferredLoading(): bool
    {
        return self::$deferredLoading ??= (new ReflectionClass(Schema::class))->hasMethod('deferLoading');
    }

    public static function isDeferred(Schema $schema): bool
    {
        return self::supportsDeferredLoading() && $schema->isLoadingDeferred();
    }

    public static function supportsChildSchemaCacheReset(): bool
    {
        return self::$childSchemaCacheReset ??= (new ReflectionClass(Component::class))->hasMethod('clearCachedChildSchemas');
    }
}
