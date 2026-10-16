<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Data;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

/** Immutable canonical membership index; never casts arbitrary structures to keys. */
final class OptionKeySet
{
    /** @var array<int|string, true> */
    private readonly array $keys;

    public function __construct(mixed $values)
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $keys = [];
        foreach (is_array($values) ? $values : ($values === null ? [] : [$values]) as $value) {
            $key = OptionValue::normalize($value);
            if ($key === null) {
                throw new InvalidArgumentException('Select option keys must be strings, integers, booleans or backed enums.');
            }
            $keys[(string) $key] = true;
        }
        $this->keys = $keys;
    }

    public function contains(mixed $value): bool
    {
        $key = OptionValue::normalize($value);

        return $key !== null && isset($this->keys[(string) $key]);
    }
}
