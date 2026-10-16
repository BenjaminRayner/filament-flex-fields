<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Runtime;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Shared upstream health, isolated by provider/policy; network calls never hold the state mutex. */
final class CircuitBreaker
{
    private readonly string $key;

    public function __construct(
        string $identity,
        private readonly int $failureThreshold,
        private readonly int $openSeconds,
        private readonly int $probeSeconds = 60,
    ) {
        if (trim($identity) === '' || $failureThreshold < 1 || $openSeconds < 1 || $probeSeconds < 1) {
            throw new InvalidArgumentException('Circuit identity and positive failure/recovery budgets are required.');
        }

        $this->key = 'fff:circuit:v2:'.hash('sha256', serialize([$identity, $failureThreshold, $openSeconds, $probeSeconds]));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @param  (callable(T): bool)|null  $failed  Classify structured upstream failures without throwing.
     * @return T
     */
    public function run(callable $operation, ?callable $failed = null): mixed
    {
        [$generation, $probe] = $this->admit();

        try {
            try {
                $result = $operation();
                $success = $failed === null || ! $failed($result);
            } catch (Throwable $exception) {
                $this->complete($generation, false, $probe !== null);

                throw $exception;
            }

            $this->complete($generation, $success, $probe !== null);

            return $result;
        } finally {
            $probe?->release();
        }
    }

    /** @return array{string, Lock|null} */
    private function admit(): array
    {
        return $this->synchronized(function (): array {
            $state = $this->state();
            $probe = null;

            if ($state['until'] !== null) {
                if ($state['until'] > now()->getTimestamp()) {
                    throw new CircuitOpenException('Upstream circuit is open.');
                }

                $probe = Cache::lock($this->key.':probe', $this->probeSeconds);
                if (! $probe->get()) {
                    throw new CircuitOpenException('Upstream recovery probe is already running.');
                }

                $state['generation'] = $this->generation();
            }

            try {
                $this->store($state);
            } catch (Throwable $exception) {
                $probe?->release();

                throw $exception;
            }

            return [$state['generation'], $probe];
        });
    }

    private function complete(string $generation, bool $success, bool $probe): void
    {
        $this->synchronized(function () use ($generation, $success, $probe): void {
            $state = $this->state();
            if ($state['generation'] !== $generation) {
                return;
            }

            if ($success) {
                $state['failures'] = 0;
                if ($probe) {
                    $state['until'] = null;
                    $state['generation'] = $this->generation();
                }
            } else {
                $state['failures']++;
                if ($probe || $state['failures'] >= $this->failureThreshold) {
                    $state['until'] = now()->getTimestamp() + $this->openSeconds;
                    $state['failures'] = 0;
                    $state['generation'] = $this->generation();
                }
            }

            $this->store($state);
        });
    }

    /** @return array{failures: int, until: int|null, generation: string} */
    private function state(): array
    {
        $state = Cache::get($this->key);
        if (! is_array($state) || ! is_int($state['failures'] ?? null)
            || ! is_string($state['generation'] ?? null) || ! array_key_exists('until', $state)
            || ($state['until'] !== null && ! is_int($state['until']))) {
            return ['failures' => 0, 'until' => null, 'generation' => $this->generation()];
        }

        return ['failures' => $state['failures'], 'until' => $state['until'], 'generation' => $state['generation']];
    }

    /** @param array{failures: int, until: int|null, generation: string} $state */
    private function store(array $state): void
    {
        if (! Cache::put($this->key, $state, max(300, $this->openSeconds + $this->probeSeconds))) {
            throw new RuntimeException('Cannot persist upstream circuit state.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function synchronized(callable $operation): mixed
    {
        $lock = Cache::lock($this->key.':state', 10);
        if (! $lock->get()) {
            throw new CircuitOpenException('Upstream circuit state is busy.');
        }

        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    private function generation(): string
    {
        return bin2hex(random_bytes(16));
    }
}
