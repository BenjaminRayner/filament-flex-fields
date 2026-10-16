<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values;

use UnexpectedValueException;

/** A bounded walk before serialization; never invokes user-defined serialization. */
final readonly class JsonDataBudget
{
    public function __construct(public int $bytes, public int $nodes, public int $depth)
    {
        if ($bytes < 1 || $nodes < 1 || $depth < 0 || $depth > 60) {
            throw new UnexpectedValueException('Invalid JSON data budget.');
        }
    }

    public function assert(mixed $value): void
    {
        $nodes = 0;
        $bytes = 0;
        $this->visit($value, 0, $nodes, $bytes);
    }

    public function assertEncoded(string $payload): void
    {
        if (strlen($payload) > $this->bytes) {
            throw new UnexpectedValueException('JSON data exceeds its byte budget.');
        }
    }

    /** Break PHP array reference aliases without changing JSON-compatible value types. */
    public function copy(mixed $value): mixed
    {
        $this->assert($value);

        return $this->copyValue($value);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $value
     * @return array<TKey, mixed>
     */
    public function copyArray(array $value): array
    {
        $this->assert($value);

        return $this->copyArrayValue($value);
    }

    private function copyValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return $this->copyArrayValue($value);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $value
     * @return array<TKey, mixed>
     */
    private function copyArrayValue(array $value): array
    {
        $copy = [];
        foreach ($value as $key => $item) {
            $copy[$key] = $this->copyValue($item);
        }

        return $copy;
    }

    private function visit(mixed $value, int $depth, int &$nodes, int &$bytes): void
    {
        if (++$nodes > $this->nodes || $depth > $this->depth) {
            throw new UnexpectedValueException('JSON data exceeds its node or depth budget.');
        }
        if (is_array($value)) {
            $bytes += 2 + max(0, count($value) - 1);
            $list = array_is_list($value);
            foreach ($value as $key => $item) {
                if (is_string($key) && ! mb_check_encoding($key, 'UTF-8')) {
                    throw new UnexpectedValueException('JSON map keys require valid UTF-8.');
                }
                if (! $list) {
                    $bytes += $this->textBytes((string) $key) + 1;
                }
                $this->visit($item, $depth + 1, $nodes, $bytes);
            }
        } elseif (is_string($value)) {
            if (! mb_check_encoding($value, 'UTF-8')) {
                throw new UnexpectedValueException('JSON text requires valid UTF-8.');
            }
            $bytes += $this->textBytes($value);
        } elseif (is_null($value) || is_bool($value) || is_int($value) || (is_float($value) && is_finite($value))) {
            $bytes += strlen(json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        } else {
            throw new UnexpectedValueException('JSON data requires finite scalars, null or arrays.');
        }
        if ($bytes > $this->bytes) {
            throw new UnexpectedValueException('JSON data exceeds its byte budget.');
        }
    }

    private function textBytes(string $value): int
    {
        if (strlen($value) > $this->bytes) {
            throw new UnexpectedValueException('JSON text exceeds its byte budget.');
        }

        return strlen(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
