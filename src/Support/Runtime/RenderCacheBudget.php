<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use InvalidArgumentException;

/** Accounted bytes include payloads, identity strings and a fixed entry overhead. */
final readonly class RenderCacheBudget
{
    public function __construct(
        public int $owners = 64,
        public int $entries = 2048,
        public int $entriesPerOwner = 512,
        public int $bytes = 4_194_304,
        public int $entryBytes = 262_144,
    ) {
        if ($owners < 1 || $owners > 512 || $entries < 1 || $entries > 32768
            || $entriesPerOwner < 1 || $entriesPerOwner > 4096 || $bytes < 1 || $bytes > 67_108_864
            || $entryBytes < 1 || $entryBytes > 4_194_304 || $entryBytes > $bytes) {
            throw new InvalidArgumentException('Invalid FLEX render cache budget.');
        }
    }
}
