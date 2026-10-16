<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use Closure;
use InvalidArgumentException;

/** Request/job-owned LRU bounded across all owners and accounted payload bytes. */
final class ScopedRenderCache
{
    /** @var array<string, array<string, mixed>> */
    private array $entries = [];

    /** @var array<string, array{owner: string, key: string, bytes: int}> */
    private array $order = [];

    /** @var array<string, true> */
    private array $owners = [];

    private int $bytes = 0;

    private int $hits = 0;

    private int $misses = 0;

    private int $bypasses = 0;

    private int $evictions = 0;

    private readonly RenderCacheBudget $budget;

    public function __construct(?RenderCacheBudget $budget = null)
    {
        $this->budget = $budget ?? new RenderCacheBudget;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $render
     * @return T
     */
    public function remember(string $owner, string $key, Closure $render): mixed
    {
        if ($owner === '' || strlen($owner) > 512 || strlen($key) > 1024
            || str_contains($owner, "\0") || str_contains($key, "\0")) {
            throw new InvalidArgumentException('Invalid FLEX render cache identity.');
        }
        $identity = $owner."\0".$key;
        if (array_key_exists($key, $this->entries[$owner] ?? [])) {
            $this->hits++;
            /** @var T $cached */
            $cached = $this->entries[$owner][$key];
            $record = $this->order[$identity];
            unset($this->order[$identity], $this->entries[$owner][$key], $this->owners[$owner]);
            $this->order[$identity] = $record;
            $this->entries[$owner][$key] = $cached;
            $this->owners[$owner] = true;

            return $cached;
        }
        $this->misses++;
        $value = $render();
        $overhead = 128 + strlen($owner) + strlen($key);
        $weight = RenderCacheWeight::estimate($value, $this->budget->entryBytes - $overhead);
        if ($weight === null) {
            $this->bypasses++;

            return $value;
        }
        $value = is_array($value) ? ArraySnapshot::copy($value) : $value;
        $bytes = $weight + $overhead;
        // A render callback may re-enter this cache; reconcile its entry before admission.
        $this->remove($identity);
        if (! isset($this->owners[$owner]) && count($this->owners) >= $this->budget->owners) {
            $this->flush((string) array_key_first($this->owners), true);
        }
        if (count($this->entries[$owner] ?? []) >= $this->budget->entriesPerOwner) {
            $this->remove($owner."\0".array_key_first($this->entries[$owner]), true);
        }
        while ($this->order !== [] && (count($this->order) >= $this->budget->entries
            || $this->bytes + $bytes > $this->budget->bytes)) {
            $this->remove((string) array_key_first($this->order), true);
        }
        $this->entries[$owner][$key] = $value;
        $this->order[$identity] = ['owner' => $owner, 'key' => $key, 'bytes' => $bytes];
        unset($this->owners[$owner]);
        $this->owners[$owner] = true;
        $this->bytes += $bytes;

        return $value;
    }

    /** @return array<string, mixed> */
    public function entries(string $owner): array
    {
        return $this->entries[$owner] ?? [];
    }

    public function flush(string $owner, bool $evicted = false): void
    {
        foreach (array_keys($this->entries[$owner] ?? []) as $key) {
            $this->remove($owner."\0".$key, $evicted);
        }
    }

    public function flushAll(): void
    {
        $this->entries = $this->order = $this->owners = [];
        $this->bytes = 0;
    }

    /**
     * No keys, rendered content, tenant IDs or callback values are exposed.
     *
     * @return array{owners: int, entries: int, bytes: int, hits: int, misses: int, bypasses: int, evictions: int}
     */
    public function snapshot(): array
    {
        return ['owners' => count($this->owners), 'entries' => count($this->order),
            'bytes' => $this->bytes, 'hits' => $this->hits, 'misses' => $this->misses,
            'bypasses' => $this->bypasses, 'evictions' => $this->evictions];
    }

    private function remove(string $identity, bool $evicted = false): void
    {
        $entry = $this->order[$identity] ?? null;
        if ($entry === null) {
            return;
        }
        unset($this->order[$identity], $this->entries[$entry['owner']][$entry['key']]);
        $this->bytes -= $entry['bytes'];
        if ($this->entries[$entry['owner']] === []) {
            unset($this->entries[$entry['owner']], $this->owners[$entry['owner']]);
        }
        if ($evicted) {
            $this->evictions++;
        }
    }
}
