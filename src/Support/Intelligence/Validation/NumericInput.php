<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Validation;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Values\NumericValue;
use InvalidArgumentException;

/** Server-side input contract, independent of a browser control's constraints. */
final readonly class NumericInput
{
    public function __construct(
        public ?float $min = null,
        public ?float $max = null,
        public bool $integer = false,
        public bool $required = true,
        public float $default = 0.0,
    ) {
        foreach ([$min, $max, $default] as $bound) {
            if ($bound !== null) {
                NumericValue::finite($bound);
            }
        }
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Input minimum must not exceed its maximum.');
        }
        if (! $required) {
            $this->validate($default);
        }
    }

    public function validate(mixed $value): float
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new InvalidArgumentException('Enter a value.');
            }
            $value = $this->default;
        }
        if (is_bool($value) || is_array($value) || ! is_numeric($value) || (is_string($value) && strlen($value) > 256)) {
            throw new InvalidArgumentException('Enter a valid number.');
        }
        if ($this->integer && is_string($value) && $this->hasFraction($value)) {
            throw new InvalidArgumentException('Enter a whole number.');
        }
        $number = NumericValue::read($value);
        if ($this->integer && floor($number) !== $number) {
            throw new InvalidArgumentException('Enter a whole number.');
        }
        if ($this->integer && abs($number) > NumericValue::MAX_EXACT_INTEGER) {
            throw new InvalidArgumentException('Whole number exceeds the exact calculation range.');
        }
        if ($this->min !== null && $number < $this->min) {
            throw new InvalidArgumentException("Minimum value: {$this->min}.");
        }
        if ($this->max !== null && $number > $this->max) {
            throw new InvalidArgumentException("Maximum value: {$this->max}.");
        }

        return $number;
    }

    /** Inspect decimal digits before conversion can round a fraction into an integer. */
    private function hasFraction(string $value): bool
    {
        if (! preg_match('/^[+-]?(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', trim($value), $parts)) {
            return true;
        }
        $fraction = $parts[2] ?? '';
        $exponent = (int) max(-512, min(512, (float) ($parts[3] ?? 0)));
        $places = strlen($fraction) - $exponent;

        return $places > 0 && rtrim(substr($parts[1].$fraction, -$places), '0') !== '';
    }
}
