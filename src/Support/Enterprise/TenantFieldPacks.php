<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Enterprise;

use Bjanczak\FilamentFlexFields\Support\Enterprise\Access\TenantFieldPackState;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use InvalidArgumentException;

/** A missing pack is unrestricted; an explicitly registered empty pack permits no types. */
final class TenantFieldPacks
{
    /** @param list<string> $fieldTypeKeys */
    public static function registerPack(string $tenantId, array $fieldTypeKeys): void
    {
        if ($tenantId === '' || in_array('', $fieldTypeKeys, true)) {
            throw new InvalidArgumentException('Tenant pack requires a tenant id and nonempty field type keys.');
        }

        self::state()->packs[$tenantId] = array_values(array_unique($fieldTypeKeys));
    }

    public static function hasPack(string $tenantId): bool
    {
        return array_key_exists($tenantId, self::state()->packs);
    }

    /** @return list<string> */
    public static function packFor(string $tenantId): array
    {
        return self::state()->packs[$tenantId] ?? [];
    }

    public static function clear(): void
    {
        self::state()->packs = [];
    }

    private static function state(): TenantFieldPackState
    {
        return RequestState::resolve(TenantFieldPackState::class);
    }
}
