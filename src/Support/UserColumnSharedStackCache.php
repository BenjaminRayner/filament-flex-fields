<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Runtime\ScopedRenderCache;
use Closure;

/** Compatibility API over the bounded request-owned render cache. */
final class UserColumnSharedStackCache
{
    /** @param Closure(): mixed $callback */
    public static function remember(string $key, Closure $callback): mixed
    {
        return RequestState::resolve(ScopedRenderCache::class)->remember(self::class, $key, $callback);
    }

    public static function flush(): void
    {
        RequestState::resolve(ScopedRenderCache::class)->flush(self::class);
    }

    /** @return array<string, mixed> */
    public static function entries(): array
    {
        /** @var array<string, mixed> $entries */
        $entries = RequestState::resolve(ScopedRenderCache::class)->entries(self::class);

        return $entries;
    }
}
