<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/** Request/job-scoped failure isolation for disposable, reproducible public data. */
final class OptionalSharedCache
{
    public const MAX_BATCH_BYTES = 16_777_216;

    private bool $unavailable = false;

    private int $hits = 0;

    private int $misses = 0;

    private int $failures = 0;

    private int $rejections = 0;

    private int $writes = 0;

    /**
     * @template T
     *
     * @param  Closure(): T  $produce
     * @param  Closure(mixed): bool  $accepts
     * @return T
     */
    public function remember(string $key, int $seconds, Closure $produce, Closure $accepts): mixed
    {
        $this->assertRetention($seconds);
        $cached = $this->readMany([$key], $accepts);
        if (array_key_exists($key, $cached)) {
            /** @var T $value */
            $value = $cached[$key];

            return $value;
        }
        // Producer and contract errors are not storage failures and must propagate.
        $value = $produce();
        if (! $accepts($value)) {
            $this->rejections++;

            return $value;
        }
        $this->writeMany([$key => $value], $seconds);

        return $value;
    }

    /**
     * @param  list<string>  $keys
     * @param  Closure(mixed): bool  $accepts
     * @return array<string, mixed>
     */
    public function readMany(array $keys, Closure $accepts): array
    {
        if (count($keys) > 128) {
            throw new InvalidArgumentException('FLEX optional cache batch exceeds 128 entries.');
        }
        foreach ($keys as $key) {
            $this->assertKey($key);
        }
        if ($keys === []) {
            return [];
        }
        $records = [];
        if (! $this->unavailable) {
            try {
                $records = Cache::many($keys);
            } catch (Throwable) {
                $this->failed();
            }
        }
        $result = [];
        $bytes = 0;
        foreach ($keys as $key) {
            $raw = $records[$key] ?? null;
            if (is_string($raw)) {
                $bytes += strlen($raw);
            }
            if ($bytes > self::MAX_BATCH_BYTES) {
                $this->misses++;
                $this->rejections++;

                continue;
            }
            $decoded = OptionalCachePayload::decode($raw);
            if ($decoded !== null && $accepts($decoded['value'])) {
                $this->hits++;
                $result[$key] = $decoded['value'];
            } else {
                $this->misses++;
                if ($raw !== null) {
                    $this->rejections++;
                }
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $values */
    public function writeMany(array $values, int $seconds): void
    {
        $this->assertRetention($seconds);
        if (count($values) > 128) {
            throw new InvalidArgumentException('FLEX optional cache batch exceeds 128 entries.');
        }
        foreach ($values as $key => $value) {
            $this->assertKey($key);
        }
        if ($this->unavailable || $values === []) {
            return;
        }
        $encoded = [];
        $bytes = 0;
        foreach ($values as $key => $value) {
            $payload = OptionalCachePayload::encode($value);
            if ($payload === null || $bytes + strlen($payload) > self::MAX_BATCH_BYTES) {
                $this->rejections++;
            } else {
                $bytes += strlen($payload);
                $encoded[$key] = $payload;
            }
        }
        if ($encoded !== []) {
            try {
                if (Cache::putMany($encoded, $seconds)) {
                    $this->writes += count($encoded);
                } else {
                    $this->failed();
                }
            } catch (Throwable) {
                $this->failed();
            }
        }
    }

    /** @return array{hits: int, misses: int, failures: int, rejections: int, writes: int, unavailable: bool} */
    public function snapshot(): array
    {
        return ['hits' => $this->hits, 'misses' => $this->misses, 'failures' => $this->failures,
            'rejections' => $this->rejections, 'writes' => $this->writes, 'unavailable' => $this->unavailable];
    }

    private function failed(): void
    {
        $this->failures++;
        $this->unavailable = true;
    }

    private function assertKey(string $key): void
    {
        if (preg_match('/^[a-zA-Z0-9._-]{1,200}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Invalid FLEX optional cache identity.');
        }
    }

    private function assertRetention(int $seconds): void
    {
        if ($seconds < 1 || $seconds > 7_776_000) {
            throw new InvalidArgumentException('FLEX optional cache retention must be between one second and 90 days.');
        }
    }
}
