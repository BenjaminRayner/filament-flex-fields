<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Icons;

use Closure;

/** Request/job-owned immutable metadata only; never retains fields or callbacks. */
final class AdmittedIconIndexes
{
    private const MAX_ENTRIES = 32;

    private const MAX_BYTES = 8_388_608;

    /** @var array<string, IconCatalogIndex> */
    private array $indexes = [];

    private int $bytes = 0;

    /** @param Closure(): IconCatalogIndex $produce */
    public function remember(string $key, Closure $produce): IconCatalogIndex
    {
        if (isset($this->indexes[$key])) {
            return $this->indexes[$key];
        }
        $index = $produce();
        if (count($this->indexes) >= self::MAX_ENTRIES) {
            return $index;
        }
        $bytes = strlen(json_encode($index->toArray(), JSON_THROW_ON_ERROR));
        if ($this->bytes + $bytes <= self::MAX_BYTES) {
            $this->indexes[$key] = $index;
            $this->bytes += $bytes;
        }

        return $index;
    }
}
