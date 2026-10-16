<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use RuntimeException;
use WeakReference;

/** Only bounded scalar metrics and weak references survive a tested scope. */
final class WorkerReport
{
    public int $completed = 0;

    public int $expectedFailures = 0;

    public int $factoryCalls = 0;

    public ?int $warmMemory = null;

    public int $lastMemory = 0;

    /** @var list<WeakReference<object>> */
    private array $previous = [];

    public static function iterations(): int
    {
        $value = getenv('FLEX_CORE_SOAK_ITERATIONS');
        $iterations = $value === false ? 1000 : filter_var($value, FILTER_VALIDATE_INT);
        if (! is_int($iterations) || $iterations < 200 || $iterations > 100000) {
            throw new RuntimeException('FLEX_CORE_SOAK_ITERATIONS must be between 200 and 100000.');
        }

        return $iterations;
    }

    /** @param list<object> $objects */
    public function observe(array $objects): void
    {
        gc_collect_cycles();
        foreach ($this->previous as $reference) {
            if ($reference->get() !== null) {
                throw new RuntimeException('A previous worker scope is still retained.');
            }
        }
        $this->previous = array_map(WeakReference::create(...), $objects);
        $this->completed++;
        $this->lastMemory = memory_get_usage();
        if ($this->completed === 200) {
            $this->warmMemory = $this->lastMemory;
        }
    }

    public function assertStable(): void
    {
        if ($this->warmMemory === null || $this->lastMemory - $this->warmMemory > 8 * 1024 * 1024) {
            throw new RuntimeException('Worker memory grew by more than 8 MiB after warm-up.');
        }
    }

    public function describe(string $host): string
    {
        return json_encode([
            'host' => $host,
            'completed' => $this->completed,
            'expected_failures' => $this->expectedFailures,
            'factory_calls' => $this->factoryCalls,
            'memory_growth_bytes' => $this->lastMemory - ($this->warmMemory ?? 0),
            'peak_bytes' => memory_get_peak_usage(true),
        ], JSON_THROW_ON_ERROR);
    }
}
