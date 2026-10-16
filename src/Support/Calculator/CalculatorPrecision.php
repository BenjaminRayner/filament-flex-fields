<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Calculator;

use InvalidArgumentException;

/** Server equivalent of the calculator result precision policy. */
final class CalculatorPrecision
{
    public static function apply(float|int $value, ?int $places, bool $integer, string $mode): float|int
    {
        $places = $integer ? 0 : $places;
        if ($places === null) {
            return $value;
        }
        if ($places < 0 || $places > 12) {
            throw new InvalidArgumentException('Invalid calculator precision.');
        }
        $scale = 10 ** $places;
        $scaled = $value * $scale;
        $nearest = round($scaled);
        if (abs($scaled - $nearest) <= PHP_FLOAT_EPSILON * max(1, abs($scaled)) * 4) {
            $scaled = $nearest;
        }
        $normalized = match ($mode) {
            'round' => round($scaled), 'floor' => floor($scaled), 'ceil' => ceil($scaled),
            'truncate' => $scaled < 0 ? ceil($scaled) : floor($scaled),
            default => throw new InvalidArgumentException('Invalid calculator rounding mode.'),
        } / $scale;
        if (! is_finite($normalized)) {
            throw new InvalidArgumentException('Non-finite calculator value.');
        }

        if ($integer && abs($normalized) > 9007199254740991) {
            throw new InvalidArgumentException('Calculator integer exceeds the exact browser range.');
        }

        return $integer ? (int) $normalized : $normalized;
    }
}
