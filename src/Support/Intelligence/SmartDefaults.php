<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Defaults\SmartDefaultsState;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;

/** Request-local defaults. Persisted preferences belong in an application-owned store. */
final class SmartDefaults
{
    public static function remember(string $userKey, string $fieldKey, mixed $value, string $tenant = ''): void
    {
        self::state()->users[$tenant][$userKey][$fieldKey] = $value;
    }

    public static function recall(string $userKey, string $fieldKey, string $tenant = ''): mixed
    {
        return self::state()->users[$tenant][$userKey][$fieldKey] ?? null;
    }

    /** @param array<string, mixed> $map */
    public static function tenantDefaults(string|int $tenantId, array $map): void
    {
        self::state()->tenants[(string) $tenantId] = $map;
    }

    /** @return array<string, mixed> */
    public static function getTenantDefaults(string|int $tenantId): array
    {
        return self::state()->tenants[(string) $tenantId] ?? [];
    }

    public static function clearUser(string $userKey, ?string $tenant = null): void
    {
        foreach ($tenant === null ? array_keys(self::state()->users) : [$tenant] as $scope) {
            unset(self::state()->users[$scope][$userKey]);
        }
    }

    public static function reset(): void
    {
        self::state()->users = [];
        self::state()->tenants = [];
    }

    private static function state(): SmartDefaultsState
    {
        return RequestState::resolve(SmartDefaultsState::class);
    }
}
