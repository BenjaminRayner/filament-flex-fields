<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaDestinations;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Closure;
use Illuminate\Database\Eloquent\Model;

/** @deprecated Host registration aliases; MediaDestinations owns path and disk policy. */
final class MediaCaptureTenantDiskResolver
{
    /** @param (callable(array<string, mixed>): string|null)|null $resolver */
    public static function registerDiskResolver(?callable $resolver): void
    {
        RequestState::configure(MediaCaptureTenantState::class, static function (MediaCaptureTenantState $state) use ($resolver): void {
            $state->diskResolver = $resolver === null ? null : Closure::fromCallable($resolver);
        });
    }

    /** @param (callable(array<string, mixed>): string|null)|null $resolver */
    public static function registerDirectoryPrefixResolver(?callable $resolver): void
    {
        RequestState::configure(MediaCaptureTenantState::class, static function (MediaCaptureTenantState $state) use ($resolver): void {
            $state->directoryPrefixResolver = $resolver === null ? null : Closure::fromCallable($resolver);
        });
    }

    /** @param array<string, mixed> $context */
    public static function resolveDisk(?string $fallback = null, array $context = []): string
    {
        return app(MediaDestinations::class)->tenantDisk($fallback, $context);
    }

    /** @param array<string, mixed> $context */
    public static function resolveDirectory(string $prefix = 'uploads', ?Model $record = null, int|string|null $userId = null, array $context = []): string
    {
        return app(MediaDestinations::class)->directory($prefix, $record, $userId, $context);
    }

    public static function shouldApplyDirectoryPrefix(): bool
    {
        return app(MediaDestinations::class)->hasDirectoryPrefix();
    }

    public static function reset(): void
    {
        $state = RequestState::resolve(MediaCaptureTenantState::class);
        $state->diskResolver = null;
        $state->directoryPrefixResolver = null;
    }
}
