<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Currency;

use Bjanczak\FilamentFlexFields\Support\CurrencyCountries;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Configuration errors fail before rendering; limits use the selected precision. */
final readonly class CurrencyBounds
{
    private function __construct(public float|int|string|null $minimum, public float|int|string|null $maximum) {}

    public static function resolve(mixed $minimum, mixed $maximum): self
    {
        $min = self::number($minimum, 'min');
        $max = self::number($maximum, 'max');
        if ($min !== null && $max !== null && (float) $min > (float) $max) {
            throw new InvalidArgumentException('Currency min must not exceed max.');
        }

        return new self($min, $max);
    }

    private static function number(mixed $value, string $name): float|int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        PickerConfiguration::number($value, $name);
        if (is_string($value) && ! preg_match('/^[+-]?\d+(?:\.\d+)?$/D', $value)) {
            throw new InvalidArgumentException('Currency bounds must use plain decimal notation.');
        }

        return $value;
    }

    public function minimumMinor(string $currency): ?int
    {
        return CurrencyCountries::toMinorUnits($this->minimum, $currency);
    }

    public function maximumMinor(string $currency): ?int
    {
        return CurrencyCountries::toMinorUnits($this->maximum, $currency);
    }
}
