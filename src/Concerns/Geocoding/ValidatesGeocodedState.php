<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Geocoding;

use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use InvalidArgumentException;

/**
 * Address lifecycle hooks and localized validation.
 */
trait ValidatesGeocodedState
{
    protected function setUpGeocodedAddress(): void
    {
        $this->afterStateHydrated(function (self $component, mixed $state): void {
            $component->state($component->hydrateToCanonical($state));
        });

        $this->dehydrateStateUsing(fn (self $component, mixed $state): mixed => $component->dehydrateFromCanonical(
            $component->hydrateToCanonical($state),
        ));

        $this->rule(function (self $component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                try {
                    $canonical = $component->hydrateToCanonical($value);
                } catch (InvalidArgumentException) {
                    $fail(__('validation.in', ['attribute' => $component->getLabel()]));

                    return;
                }

                $hasValue = $component->hasAnyStoredValue($canonical);
                if ($component->isRequired() && ! $hasValue) {
                    $fail(__('validation.required', ['attribute' => $component->getLabel()]));

                    return;
                }

                if (! $hasValue) {
                    return;
                }

                $message = $component->getValidationMessage($canonical);

                if ($message !== null) {
                    $fail($message);
                }
            };
        });
    }

    public function getRequiredValidationRule(): string|Closure
    {
        return 'nullable';
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function getValidationMessage(array $state): ?string
    {
        $canonical = $this->normalizeCanonical($state);
        $translationKey = $this->geocodedAddressTranslationKey();
        $fields = $this->getFields();

        foreach ($this->getRequiredFields() as $field) {
            if (blank($canonical[$field] ?? null)) {
                return Translations::get("filament-flex-fields::default.validation.{$translationKey}.required_field", [
                    'field' => Translations::get("filament-flex-fields::default.{$translationKey}.fields.{$field}"),
                ]);
            }
        }

        if (
            in_array('lat', $fields, true)
            && filled($canonical['lat'] ?? null)
            && ($canonical['lat'] < -90 || $canonical['lat'] > 90)
        ) {
            return Translations::get("filament-flex-fields::default.validation.{$translationKey}.invalid_latitude");
        }

        if (
            in_array('lng', $fields, true)
            && filled($canonical['lng'] ?? null)
            && ($canonical['lng'] < -180 || $canonical['lng'] > 180)
        ) {
            return Translations::get("filament-flex-fields::default.validation.{$translationKey}.invalid_longitude");
        }

        if (in_array('lat', $fields, true) && in_array('lng', $fields, true) && (($canonical['lat'] ?? null) === null) !== (($canonical['lng'] ?? null) === null)) {
            return Translations::get('filament-flex-fields::location.validation.coordinate_pair');
        }
        if (filled($canonical['country'] ?? null) && ! preg_match('/^[A-Z]{2}$/D', (string) $canonical['country'])) {
            return Translations::get("filament-flex-fields::default.validation.{$translationKey}.country_not_allowed");
        }
        $countries = $this->getCountries();

        if (
            $countries !== null
            && in_array('country', $fields, true)
            && $this->hasAnyStoredValue($canonical)
            && ! in_array(strtoupper((string) $canonical['country']), $countries, true)
        ) {
            return Translations::get("filament-flex-fields::default.validation.{$translationKey}.country_not_allowed");
        }

        if ($this->isStreetAddressesOnly() && $this->hasAnyStoredValue($canonical) && ! $this->hasStreetAddress($canonical)) {
            return Translations::get("filament-flex-fields::default.validation.{$translationKey}.street_address_required");
        }

        if ($this->isStreetNumberRequired() && $this->hasAnyStoredValue($canonical)
            && ! preg_match('/^\d+[\p{L}\d\/\-]*\s+\S/u', (string) ($canonical['street'] ?? ''))) {
            return Translations::get('filament-flex-fields::location.validation.street_number_required');
        }

        return null;
    }
}
