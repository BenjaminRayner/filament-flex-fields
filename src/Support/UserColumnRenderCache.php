<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;
use Closure;

/** Compatibility API over the bounded request-owned render cache. */
final class UserColumnRenderCache
{
    /** @param Closure(): string $callback */
    public static function remember(string $key, Closure $callback): string
    {
        return RequestState::resolve(ScopedRenderCache::class)->remember(self::class, $key, $callback);
    }

    public static function flush(): void
    {
        RequestState::resolve(ScopedRenderCache::class)->flush(self::class);
    }

    /** @return array<string, string> */
    public static function entries(): array
    {
        /** @var array<string, string> $entries */
        $entries = RequestState::resolve(ScopedRenderCache::class)->entries(self::class);

        return $entries;
    }
}
