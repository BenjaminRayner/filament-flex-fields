<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media;

use Closure;

final class MediaCaptureTenantState
{
    /** @var (Closure(array<string, mixed>): string|null)|null */
    public ?Closure $diskResolver = null;

    /** @var (Closure(array<string, mixed>): string|null)|null */
    public ?Closure $directoryPrefixResolver = null;
}
