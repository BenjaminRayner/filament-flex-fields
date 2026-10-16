<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TrackSlider;

use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use InvalidArgumentException;

/** Numeric presentation is separate from validation of submitted form state. */
final class TrackSliderValue
{
    public static function assertConfiguration(int|float $min, int|float $max, int|float $step, ?int $precision): void
    {
        if (NumericValue::parse($min) === null || NumericValue::parse($max) === null
            || NumericValue::parse($step) === null || $max < $min || ! is_finite((float) ($max - $min))
            || $step <= 0 || ! is_finite(($max - $min) / $step)
            || ($precision !== null && ($precision < 0 || $precision > 20))) {
            throw new InvalidArgumentException('TrackSlider requires finite ordered bounds, a positive step and precision between 0 and 20.');
        }
    }

    public static function normalize(mixed $value, int|float $min, int|float $max, bool $integer, ?int $precision): int|float
    {
        $number = min($max, max($min, NumericValue::parse($value) ?? $min));
        $number = $integer ? (int) NumericValue::round((float) $number) : ($precision === null ? $number : round($number, $precision));

        return min($max, max($min, $number));
    }

    public static function percentage(int|float $value, int|float $min, int|float $max): float
    {
        return $max === $min ? 0.0 : (($value - $min) / ($max - $min)) * 100;
    }

    public static function format(int|float $value, bool $integer, ?int $precision, ?string $suffix): string
    {
        return ($integer || $precision === null ? (string) $value : number_format($value, $precision, '.', '')).($suffix ?? '');
    }
}
