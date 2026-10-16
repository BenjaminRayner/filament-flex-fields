<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\Rating\RatingValue;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

final readonly class RatingStateCast implements StateCast
{
    public function __construct(private bool $fractional) {}

    public function get(mixed $state): mixed
    {
        return $this->normalize($state);
    }

    public function set(mixed $state): mixed
    {
        return $this->normalize($state);
    }

    private function normalize(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return null;
        }
        $number = RatingValue::numeric($state);
        if ($number === null || $number < 0 || $number > RatingValue::MAX_ITEMS) {
            return $state;
        }

        return $this->fractional ? $number : (floor($number) === $number ? (int) $number : $state);
    }
}
