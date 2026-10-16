<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Country;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\StateCasts\CountryFieldStateCast;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** @mixin CountryField */
trait NormalizesCountryState
{
    /**
     * @return array<StateCast>
     */
    public function getDefaultStateCasts(): array
    {
        return [
            ...parent::getDefaultStateCasts(),
            app(CountryFieldStateCast::class, ['field' => $this]),
        ];
    }

    public function normalizeState(mixed $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        $country = strtoupper(trim((string) $state));
        $allowed = $this->getResolvedCountryCodes();

        if (in_array($country, $allowed, true)) {
            return $country;
        }

        $default = $this->getDefaultCountryCode();

        if ($default !== null && in_array($default, $allowed, true)) {
            return $default;
        }

        return null;
    }
}
