<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Formula;

use InvalidArgumentException;

/** Structural checks apply even to branches skipped at evaluation time. */
final class FunctionSignature
{
    public static function validate(string $name, int $count): void
    {
        [$min, $max] = match ($name) {
            'abs', 'floor', 'ceil', 'trunc', 'pct', 'sign', 'not', 'sqrt' => [1, 1],
            'round' => [1, 2],
            'pow' => [2, 2],
            'if', 'clamp', 'between' => [3, 3],
            'min', 'max', 'sum', 'avg', 'and', 'or' => [2, 2048],
            'coalesce', 'nz' => [1, 2048],
            default => throw new InvalidArgumentException("Unknown formula function [{$name}]."),
        };
        if ($count < $min || $count > $max) {
            throw new InvalidArgumentException("Function [{$name}] received an invalid number of arguments.");
        }
    }
}
