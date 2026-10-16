<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\PriceRange;

use InvalidArgumentException;

final readonly class RangeConstraints
{
    public function __construct(
        public int|float $min,
        public int|float $max,
        public int|float $step,
        public bool $integer,
        public ?int $decimalPlaces,
    ) {
        if (RangeValues::number($min) === null || RangeValues::number($max) === null || $max <= $min
            || ! is_finite((float) ($max - $min)) || $max - $min > 9007199254740991
            || RangeValues::number($step) === null || $step <= 0
            || ($decimalPlaces !== null && ($decimalPlaces < 0 || $decimalPlaces > 10))) {
            throw new InvalidArgumentException('Price range requires finite increasing bounds, a positive step and 0–10 decimal places.');
        }
        if ($integer && (floor((float) $min) !== (float) $min || floor((float) $max) !== (float) $max || floor((float) $step) !== (float) $step)) {
            throw new InvalidArgumentException('Integer price ranges require integer bounds and step.');
        }
        if (! $integer) {
            foreach ([$min, $max, $step] as $value) {
                if (abs(round((float) $value, $decimalPlaces ?? 10) - $value) > max(1.0, abs((float) $value)) * PHP_FLOAT_EPSILON * 8) {
                    throw new InvalidArgumentException('Price range decimalPlaces must represent its bounds and step exactly.');
                }
            }
        }
        if ((float) $min + (float) $step === (float) $min || (float) $max - (float) $step === (float) $max) {
            throw new InvalidArgumentException('Price range step is below numeric precision at these bounds.');
        }
    }

    public function snap(mixed $value): int|float
    {
        $number = (float) max($this->min, min($this->max, RangeValues::number($value) ?? $this->min));
        $number = $number === (float) $this->max ? $number
            : $this->min + round(($number - $this->min) / $this->step) * $this->step;
        $number = max($this->min, min($this->max, $number));
        $number = round((float) $number, $this->integer ? 0 : ($this->decimalPlaces ?? 10));

        return RangeValues::canonical($number);
    }

    public function accepts(int|float $value): bool
    {
        if ($value < $this->min || $value > $this->max || ($this->integer && floor((float) $value) !== (float) $value)) {
            return false;
        }
        $tolerance = max(1.0, abs((float) $value)) * PHP_FLOAT_EPSILON * 8;

        return abs($this->snap($value) - $value) <= $tolerance;
    }

    /** @param array<string, mixed> $state
     * @return array{min: int|float, max: int|float}
     */
    public function display(array $state): array
    {
        $min = $this->snap($state['min'] ?? $this->min);
        $max = $this->snap($state['max'] ?? $this->max);

        return ['min' => $min, 'max' => max($min, $max)];
    }
}
