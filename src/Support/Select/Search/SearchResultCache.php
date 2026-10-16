<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Search;

use Bjanczak\FilamentFlexFields\Support\Select\Transport\BoundedJsonPayload;
use InvalidArgumentException;

/**
 * Per-field, per-request LRU. Budgets describe encoded payload, not PHP heap bytes.
 * Cache misses never change provider output and oversized results are not retained.
 */
final class SearchResultCache
{
    /** @var array<string, array{value: array<array-key, mixed>, bytes: int}> */
    private array $entries = [];

    private int $bytes = 0;

    public function __construct(
        private readonly int $maxEntries = 64,
        private readonly int $maxBytes = 4194304,
    ) {}

    /** @return ?array<array-key, mixed> */
    public function get(string $key): ?array
    {
        if (! isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        unset($this->entries[$key]);
        $this->entries[$key] = $entry;

        return $entry['value'];
    }

    /**
     * @template TStored of array<array-key, mixed>
     *
     * @param  TStored  $value
     * @return TStored
     */
    public function put(string $key, array $value): array
    {
        $this->forget($key);
        if ($this->maxEntries <= 0 || $this->maxBytes <= 0) {
            return $value;
        }

        try {
            $size = (new BoundedJsonPayload)->measure($value, $this->maxBytes);
        } catch (InvalidArgumentException) {
            // Non-JSON/oversized legacy options still render; they simply are not cached.
            return $value;
        }

        if ($size > $this->maxBytes) {
            return $value;
        }

        while ($this->entries !== [] && (count($this->entries) >= $this->maxEntries || $this->bytes + $size > $this->maxBytes)) {
            $this->forget((string) array_key_first($this->entries));
        }

        $this->entries[$key] = ['value' => $value, 'bytes' => $size];
        $this->bytes += $size;

        return $value;
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->bytes = 0;
    }

    private function forget(string $key): void
    {
        if (! isset($this->entries[$key])) {
            return;
        }
        $this->bytes -= $this->entries[$key]['bytes'];
        unset($this->entries[$key]);
    }
}
