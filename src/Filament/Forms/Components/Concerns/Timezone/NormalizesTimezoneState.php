<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Timezone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;

/** @mixin TimezoneField */
trait NormalizesTimezoneState
{
    public function normalizeState(mixed $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        $timezone = trim((string) $state);
        $allowed = $this->getResolvedTimezoneIdentifiers();

        if (in_array($timezone, $allowed, true)) {
            return $timezone;
        }

        $default = $this->getDefaultTimezoneIdentifier();

        if ($default !== null && in_array($default, $allowed, true)) {
            return $default;
        }

        return null;
    }
}
