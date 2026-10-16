<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\StateCasts;

use Bjanczak\FilamentFlexFields\Support\NumberStepper\NumericValue;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** Invalid payloads stay intact so form validation can reject them before save. */
final class NumberStepperStateCast implements StateCast
{
    public function __construct(private readonly bool $nullable, private readonly bool $integer) {}

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
            return $this->nullable ? null : 0;
        }

        $number = NumericValue::parse($state);
        if ($number === null) {
            return $state;
        }

        return $this->integer && floor((float) $number) === (float) $number ? (int) $number : $number;
    }
}
